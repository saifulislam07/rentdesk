<?php

namespace Tests\Feature;

use App\Enums\DamageStatus;
use App\Enums\DepositStatus;
use App\Enums\RentalStatus;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\RentalException;
use App\Models\Rental;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesReservedRentals;
use Tests\TestCase;

class HandoverServiceTest extends TestCase
{
    use CreatesReservedRentals;
    use RefreshDatabase;

    private HandoverService $handover;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->handover = app(HandoverService::class);
        $this->staff = User::factory()->create();
    }

    /**
     * @return array{amount: string, method: string}
     */
    private function deposit(string $amount = '20.00'): array
    {
        return ['amount' => $amount, 'method' => 'cash'];
    }

    private function checkedOutRental(int $units = 2): Rental
    {
        $rental = $this->reservedRental($units);
        $this->handover->checkOut($rental, $this->staff, $this->inspectionsFor($rental), $this->signatureBytes(), $this->deposit((string) ($units * 10)));

        return $rental->refresh();
    }

    public function test_check_out_hands_over_the_units_and_records_everything(): void
    {
        $rental = $this->reservedRental();
        $inspections = $this->inspectionsFor($rental, ['condition' => 'fair', 'meter_reading' => 120]);
        $firstItemId = array_key_first($inspections);
        $inspections[$firstItemId]['photos'] = [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')];

        $this->handover->checkOut($rental, $this->staff, $inspections, $this->signatureBytes(), $this->deposit());

        $rental->refresh();
        $this->assertSame(RentalStatus::Out, $rental->status);
        $this->assertSame(2, $rental->inspections()->where('type', 'out')->count());
        $this->assertSame(2, $rental->items()->with('unit')->get()->where('unit.status', UnitStatus::Rented)->count());

        $unit = $rental->items()->first()->unit;
        $this->assertSame(UnitCondition::Fair, $unit->condition);
        $this->assertSame(120, $unit->meter_reading);

        $inspection = $rental->inspections()->where('unit_id', $rental->items()->find($firstItemId)->unit_id)->first();
        $this->assertSame($this->staff->id, $inspection->inspected_by);
        $this->assertSame(['clean', 'working'], $inspection->checklist);
        $this->assertCount(2, $inspection->photos);
        Storage::disk('local')->assertExists($inspection->photos->first()->path);
        Storage::disk('local')->assertExists($inspection->signature_path);
        $this->assertSame($this->signatureBytes(), Storage::disk('local')->get($inspection->signature_path));

        $this->assertSame(DepositStatus::Held, $rental->deposit->status);
        $this->assertSame('20.00', $rental->deposit->amount);
    }

    public function test_check_out_requires_the_full_deposit_and_changes_nothing_otherwise(): void
    {
        $rental = $this->reservedRental();

        try {
            $this->handover->checkOut($rental, $this->staff, $this->inspectionsFor($rental), $this->signatureBytes(), $this->deposit('5.00'));
            $this->fail('Expected the check-out to be refused.');
        } catch (RentalException) {
            $this->assertSame(RentalStatus::Reserved, $rental->refresh()->status);
            $this->assertDatabaseCount('deposits', 0);
            $this->assertDatabaseCount('inspections', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_a_rental_without_a_required_deposit_needs_none(): void
    {
        $rental = $this->reservedRental(1, '0.00');

        $this->handover->checkOut($rental, $this->staff, $this->inspectionsFor($rental), $this->signatureBytes());

        $this->assertSame(RentalStatus::Out, $rental->refresh()->status);
        $this->assertDatabaseCount('deposits', 0);
    }

    public function test_every_unit_must_be_inspected(): void
    {
        $rental = $this->reservedRental();
        $inspections = $this->inspectionsFor($rental);
        array_shift($inspections);

        $this->expectException(RentalException::class);

        $this->handover->checkOut($rental, $this->staff, $inspections, $this->signatureBytes(), $this->deposit());
    }

    public function test_a_failed_check_out_rolls_back_and_deletes_uploaded_files(): void
    {
        $rental = $this->reservedRental();
        $inspections = $this->inspectionsFor($rental);
        $inspections[array_key_first($inspections)]['photos'] = [UploadedFile::fake()->image('a.jpg')];
        $rental->items()->orderByDesc('id')->first()->unit->update(['status' => UnitStatus::Maintenance]);

        try {
            $this->handover->checkOut($rental, $this->staff, $inspections, $this->signatureBytes(), $this->deposit());
            $this->fail('Expected the check-out to fail.');
        } catch (RentalException) {
            $this->assertSame(RentalStatus::Reserved, $rental->refresh()->status);
            $this->assertDatabaseCount('inspections', 0);
            $this->assertDatabaseCount('deposits', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_only_reserved_rentals_can_be_checked_out(): void
    {
        $rental = $this->checkedOutRental();

        $this->expectException(RentalException::class);

        $this->handover->checkOut($rental, $this->staff, $this->inspectionsFor($rental), $this->signatureBytes(), $this->deposit());
    }

    public function test_check_in_returns_units_to_stock_and_the_rental_is_returned(): void
    {
        $rental = $this->checkedOutRental();

        $this->handover->checkIn($rental, $this->staff, $this->inspectionsFor($rental, ['condition' => 'good', 'meter_reading' => 150]));

        $rental->refresh();
        $this->assertSame(RentalStatus::Returned, $rental->status);
        $this->assertNotNull($rental->returned_at);
        $this->assertSame(2, $rental->inspections()->where('type', 'in')->count());

        foreach ($rental->items()->with('unit')->get() as $item) {
            $this->assertSame(UnitStatus::Available, $item->unit->status);
            $this->assertSame(150, $item->unit->meter_reading);
        }
    }

    public function test_check_in_records_damage_and_can_send_a_unit_to_maintenance(): void
    {
        $rental = $this->checkedOutRental(1);
        $inspections = $this->inspectionsFor($rental, [
            'condition' => 'poor',
            'needs_maintenance' => '1',
            'damage' => ['description' => 'Cracked housing', 'cost' => '80.00', 'charged' => '50.00'],
        ]);

        $this->handover->checkIn($rental, $this->staff, $inspections);

        $damage = $rental->damages()->firstOrFail();
        $this->assertSame('Cracked housing', $damage->description);
        $this->assertSame('80.00', $damage->cost);
        $this->assertSame('50.00', $damage->charged_amount);
        $this->assertSame(DamageStatus::Charged, $damage->status);
        $this->assertNotNull($damage->inspection_id);
        $this->assertSame(UnitStatus::Maintenance, $rental->items()->first()->unit->status);
        $this->assertSame('50.00', $rental->load('damages')->chargedDamageTotal());
    }

    public function test_damage_without_a_charge_stays_pending(): void
    {
        $rental = $this->checkedOutRental(1);

        $this->handover->checkIn($rental, $this->staff, $this->inspectionsFor($rental, ['damage' => ['description' => 'Scratch']]));

        $this->assertSame(DamageStatus::Pending, $rental->damages()->first()->status);
    }

    public function test_check_in_can_store_an_optional_signature(): void
    {
        $rental = $this->checkedOutRental(1);

        $this->handover->checkIn($rental, $this->staff, $this->inspectionsFor($rental), $this->signatureBytes());

        $inspection = $rental->inspections()->where('type', 'in')->first();
        Storage::disk('local')->assertExists($inspection->signature_path);
    }

    public function test_only_rentals_that_are_out_can_be_checked_in(): void
    {
        $rental = $this->reservedRental();

        $this->expectException(RentalException::class);

        $this->handover->checkIn($rental, $this->staff, $this->inspectionsFor($rental));
    }

    public function test_a_checked_out_unit_cannot_be_given_to_another_rental(): void
    {
        $rental = $this->checkedOutRental(1);
        $unit = $rental->items()->first()->unit;

        $other = Rental::factory()->create(['status' => RentalStatus::Reserved]);
        $other->items()->create([
            'product_id' => $unit->product_id,
            'unit_id' => $unit->id,
            'start_at' => '2026-12-10 10:00',
            'end_at' => '2026-12-11 10:00',
        ]);

        $this->expectException(RentalException::class);

        $this->handover->checkOut($other, $this->staff, $this->inspectionsFor($other), $this->signatureBytes());
    }
}
