<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesReservedRentals;
use Tests\TestCase;

class HandoverFlowTest extends TestCase
{
    use CreatesReservedRentals;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkOutPayload(Rental $rental, array $overrides = []): array
    {
        return [
            'inspections' => $this->inspectionsFor($rental),
            'signature' => $this->signatureDataUrl(),
            'deposit_amount' => '20.00',
            'deposit_method' => 'cash',
            ...$overrides,
        ];
    }

    public function test_staff_can_run_a_rental_from_check_out_to_settlement(): void
    {
        $staff = $this->userWithRole('staff');
        $rental = $this->reservedRental();
        $itemId = $rental->items()->first()->id;
        $payload = $this->checkOutPayload($rental);
        $payload['inspections'][$itemId]['photos'] = [UploadedFile::fake()->image('front.jpg')];

        $this->actingAs($staff)->get(route('rentals.check-out', $rental))->assertOk()->assertSee('Complete check-out');
        $this->actingAs($staff)->post(route('rentals.check-out.store', $rental), $payload)
            ->assertRedirect(route('rentals.show', $rental))
            ->assertSessionHas('status');
        $this->assertSame(RentalStatus::Out, $rental->refresh()->status);

        $this->actingAs($staff)->get(route('rentals.check-in', $rental))->assertOk();

        $checkIn = $this->inspectionsFor($rental, ['meter_reading' => 140]);
        $checkIn[$itemId]['damage'] = ['description' => 'Dented case', 'cost' => '60', 'charged' => '45'];
        $this->actingAs($staff)->post(route('rentals.check-in.store', $rental), ['inspections' => $checkIn])
            ->assertRedirect(route('rentals.show', $rental));
        $this->assertSame(RentalStatus::Returned, $rental->refresh()->status);

        $this->actingAs($staff)->get(route('rentals.show', $rental))
            ->assertOk()
            ->assertSee('Dented case')
            ->assertSee('Settle &amp; close rental', false)
            ->assertSee('value="'.'0.00'.'"', false);

        $this->actingAs($staff)->post(route('rentals.settle', $rental), ['refund_amount' => '0'])
            ->assertRedirect(route('rentals.show', $rental));

        $this->assertSame(RentalStatus::Closed, $rental->refresh()->status);
        $this->assertSame(DepositStatus::Forfeited, $rental->deposit->status);
    }

    public function test_settlement_form_suggests_the_deposit_minus_damage(): void
    {
        $rental = $this->reservedRental(1, '100.00');
        $itemId = $rental->items()->first()->id;
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['deposit_amount' => '100.00']));
        $this->actingAs($staff)->post(route('rentals.check-in.store', $rental), [
            'inspections' => [$itemId => [...$this->inspectionsFor($rental)[$itemId], 'damage' => ['description' => 'Dent', 'charged' => '30']]],
        ]);

        $this->actingAs($staff)->get(route('rentals.show', $rental))->assertSee('value="70.00"', false);
    }

    public function test_check_out_validation(): void
    {
        $rental = $this->reservedRental();
        $staff = $this->userWithRole('staff');
        $itemIds = array_keys($this->inspectionsFor($rental));

        $this->actingAs($staff)
            ->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['signature' => null]))
            ->assertSessionHasErrors('signature');

        $this->actingAs($staff)
            ->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['signature' => 'data:image/png;base64,'.base64_encode('not a png')]))
            ->assertSessionHasErrors('signature');

        $this->actingAs($staff)
            ->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['inspections' => [$itemIds[0] => ['condition' => 'good']]]))
            ->assertSessionHasErrors('inspections');

        $this->actingAs($staff)
            ->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['deposit_method' => null]))
            ->assertSessionHasErrors('deposit_method');

        $bad = $this->inspectionsFor($rental);
        $bad[$itemIds[0]]['condition'] = 'shiny';
        $bad[$itemIds[0]]['photos'] = [UploadedFile::fake()->create('virus.exe', 10)];
        $this->actingAs($staff)
            ->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['inspections' => $bad]))
            ->assertSessionHasErrors(["inspections.{$itemIds[0]}.condition", "inspections.{$itemIds[0]}.photos.0"]);

        $this->assertSame(RentalStatus::Reserved, $rental->refresh()->status);
    }

    public function test_a_short_deposit_returns_to_the_form_with_the_message(): void
    {
        $rental = $this->reservedRental();

        $this->actingAs($this->userWithRole('staff'))
            ->from(route('rentals.check-out', $rental))
            ->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['deposit_amount' => '1']))
            ->assertRedirect(route('rentals.check-out', $rental))
            ->assertSessionHas('error');
    }

    public function test_check_in_meter_reading_cannot_go_backwards(): void
    {
        $rental = $this->reservedRental(1);
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['deposit_amount' => '10']));
        $itemId = $rental->items()->first()->id;

        $this->actingAs($staff)
            ->post(route('rentals.check-in.store', $rental), ['inspections' => $this->inspectionsFor($rental, ['meter_reading' => 50])])
            ->assertSessionHasErrors("inspections.{$itemId}.meter_reading");
    }

    public function test_damage_needs_a_description_when_amounts_are_given(): void
    {
        $rental = $this->reservedRental(1);
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['deposit_amount' => '10']));
        $itemId = $rental->items()->first()->id;

        $this->actingAs($staff)
            ->post(route('rentals.check-in.store', $rental), ['inspections' => $this->inspectionsFor($rental, ['damage' => ['charged' => '20']])])
            ->assertSessionHasErrors("inspections.{$itemId}.damage.description");
    }

    public function test_wrong_status_pages_redirect_with_an_error(): void
    {
        $staff = $this->userWithRole('staff');
        $reserved = $this->reservedRental();

        $this->actingAs($staff)->from(route('rentals.show', $reserved))->get(route('rentals.check-in', $reserved))
            ->assertRedirect(route('rentals.show', $reserved))->assertSessionHas('error');

        $quote = Rental::factory()->status(RentalStatus::Quote)->create();
        $this->actingAs($staff)->from(route('rentals.show', $quote))->get(route('rentals.check-out', $quote))
            ->assertRedirect(route('rentals.show', $quote))->assertSessionHas('error');
    }

    public function test_viewers_cannot_hand_over_or_settle(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create()->givePermissionTo('rentals.view');
        $rental = $this->reservedRental();

        $this->actingAs($viewer)->get(route('rentals.check-out', $rental))->assertForbidden();
        $this->actingAs($viewer)->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental))->assertForbidden();
        $this->actingAs($viewer)->post(route('rentals.check-in.store', $rental), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('rentals.settle', $rental))->assertForbidden();
    }

    public function test_photos_and_signatures_are_served_only_to_authorised_staff(): void
    {
        $rental = $this->reservedRental(1);
        $staff = $this->userWithRole('staff');
        $itemId = $rental->items()->first()->id;
        $payload = $this->checkOutPayload($rental, ['deposit_amount' => '10']);
        $payload['inspections'][$itemId]['photos'] = [UploadedFile::fake()->image('front.jpg')];
        $this->actingAs($staff)->post(route('rentals.check-out.store', $rental), $payload);

        $inspection = $rental->inspections()->firstOrFail();
        $photo = InspectionPhoto::firstOrFail();

        auth()->logout();
        $this->get(route('inspection-photos.show', $photo))->assertRedirect(route('login'));
        $this->get(route('inspections.signature', $inspection))->assertRedirect(route('login'));

        $noAccess = User::factory()->create();
        $this->actingAs($noAccess)->get(route('inspection-photos.show', $photo))->assertForbidden();
        $this->actingAs($noAccess)->get(route('inspections.signature', $inspection))->assertForbidden();

        $this->actingAs($staff)->get(route('inspection-photos.show', $photo))->assertOk();
        $signature = $this->actingAs($staff)->get(route('inspections.signature', $inspection));
        $signature->assertOk();
        $this->assertSame($this->signatureBytes(), $signature->streamedContent());
    }

    public function test_the_signature_route_404s_when_there_is_none(): void
    {
        $inspection = Inspection::factory()->create(['signature_path' => null]);

        $this->actingAs($this->userWithRole('staff'))->get(route('inspections.signature', $inspection))->assertNotFound();
    }

    public function test_the_scan_page_renders_and_a_scanned_token_opens_the_unit_with_its_open_rental(): void
    {
        $rental = $this->reservedRental(1);
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('rentals.check-out.store', $rental), $this->checkOutPayload($rental, ['deposit_amount' => '10']));
        $unit = Unit::where('status', UnitStatus::Rented)->firstOrFail();

        $this->actingAs($staff)->get(route('scan'))->assertOk()->assertSee('id="scanner"', false);

        $this->actingAs($staff)->get(route('units.scan', $unit->qr_code))->assertRedirect(route('units.show', $unit));
        $this->actingAs($staff)->get(route('units.show', $unit))
            ->assertOk()
            ->assertSee('Currently out on')
            ->assertSee($rental->number);
    }

    public function test_the_scan_page_is_forbidden_without_unit_access(): void
    {
        $this->actingAs(User::factory()->create())->get(route('scan'))->assertForbidden();
    }
}
