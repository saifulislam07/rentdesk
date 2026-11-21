<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Models\Location;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use App\Services\AvailabilityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = new AvailabilityChecker;
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-12-01 {$time}", 'UTC');
    }

    private function book(Unit $unit, string $from, string $to, RentalStatus $status = RentalStatus::Reserved): RentalItem
    {
        return RentalItem::factory()->forUnit($unit, $this->at($from), $this->at($to))->create([
            'rental_id' => Rental::factory()->status($status)->create(['start_at' => $this->at($from), 'end_at' => $this->at($to)]),
        ]);
    }

    private function unit(int $bufferMinutes = 0): Unit
    {
        return Unit::factory()->for(Product::factory()->state(['buffer_minutes' => $bufferMinutes]))->create();
    }

    public function test_unit_without_bookings_is_available(): void
    {
        $this->assertTrue($this->checker->isUnitAvailable($this->unit(), $this->at('10:00'), $this->at('12:00')));
    }

    public function test_overlapping_booking_blocks_the_unit(): void
    {
        $unit = $this->unit();
        $this->book($unit, '10:00', '14:00');

        foreach ([['09:00', '11:00'], ['13:00', '15:00'], ['11:00', '12:00'], ['09:00', '15:00']] as [$from, $to]) {
            $this->assertFalse(
                $this->checker->isUnitAvailable($unit, $this->at($from), $this->at($to)),
                "{$from}-{$to} should conflict"
            );
        }
    }

    public function test_back_to_back_bookings_do_not_conflict_without_a_buffer(): void
    {
        $unit = $this->unit();
        $this->book($unit, '10:00', '14:00');

        $this->assertTrue($this->checker->isUnitAvailable($unit, $this->at('14:00'), $this->at('16:00')));
        $this->assertTrue($this->checker->isUnitAvailable($unit, $this->at('08:00'), $this->at('10:00')));
    }

    public function test_buffer_time_is_kept_free_on_both_sides(): void
    {
        $unit = $this->unit(bufferMinutes: 60);
        $this->book($unit, '10:00', '14:00');

        $this->assertFalse($this->checker->isUnitAvailable($unit, $this->at('14:00'), $this->at('16:00')));
        $this->assertFalse($this->checker->isUnitAvailable($unit, $this->at('08:30'), $this->at('10:00')));
        $this->assertTrue($this->checker->isUnitAvailable($unit, $this->at('15:00'), $this->at('17:00')));
        $this->assertTrue($this->checker->isUnitAvailable($unit, $this->at('07:00'), $this->at('09:00')));
    }

    public function test_only_reserved_and_out_rentals_block(): void
    {
        $unit = $this->unit();

        foreach ([RentalStatus::Quote, RentalStatus::Returned, RentalStatus::Closed, RentalStatus::Cancelled] as $status) {
            $this->book($unit, '10:00', '14:00', $status);
        }

        $this->assertTrue($this->checker->isUnitAvailable($unit, $this->at('10:00'), $this->at('14:00')));

        $this->book($unit, '10:00', '14:00', RentalStatus::Out);

        $this->assertFalse($this->checker->isUnitAvailable($unit, $this->at('10:00'), $this->at('14:00')));
    }

    public function test_maintenance_and_retired_units_are_never_available(): void
    {
        foreach ([UnitStatus::Maintenance, UnitStatus::Retired] as $status) {
            $unit = Unit::factory()->status($status)->create();

            $this->assertFalse($this->checker->isUnitAvailable($unit, $this->at('10:00'), $this->at('12:00')));
        }
    }

    public function test_currently_rented_unit_is_available_after_it_comes_back(): void
    {
        $unit = Unit::factory()->status(UnitStatus::Rented)->create();
        $this->book($unit, '08:00', '10:00', RentalStatus::Out);

        $this->assertTrue($this->checker->isUnitAvailable($unit, $this->at('12:00'), $this->at('14:00')));
    }

    public function test_a_rental_can_be_ignored_when_rechecking_its_own_items(): void
    {
        $unit = $this->unit();
        $item = $this->book($unit, '10:00', '14:00');

        $this->assertFalse($this->checker->isUnitAvailable($unit, $this->at('10:00'), $this->at('14:00')));
        $this->assertTrue($this->checker->isUnitAvailable($unit, $this->at('10:00'), $this->at('14:00'), $item->rental_id));
    }

    public function test_available_units_excludes_booked_ones_and_filters_by_location(): void
    {
        $product = Product::factory()->create(['buffer_minutes' => 0]);
        $main = Location::factory()->create();
        $other = Location::factory()->create();

        $free = Unit::factory()->for($product)->for($main)->create();
        $booked = Unit::factory()->for($product)->for($main)->create();
        $elsewhere = Unit::factory()->for($product)->for($other)->create();
        $this->book($booked, '10:00', '14:00');

        $anywhere = $this->checker->availableUnits($product, $this->at('11:00'), $this->at('12:00'));
        $atMain = $this->checker->availableUnits($product, $this->at('11:00'), $this->at('12:00'), $main->id);

        $this->assertEqualsCanonicalizing([$free->id, $elsewhere->id], $anywhere->modelKeys());
        $this->assertSame([$free->id], $atMain->modelKeys());
        $this->assertSame(2, $this->checker->availableQuantity($product, $this->at('11:00'), $this->at('12:00')));
    }

    public function test_bulk_quantity_is_stock_minus_overlapping_bookings(): void
    {
        $product = Product::factory()->bulk()->create(['buffer_minutes' => 0]);
        Unit::factory()->for($product)->create(['quantity' => 10]);
        Unit::factory()->for($product)->status(UnitStatus::Retired)->create(['quantity' => 5]);

        RentalItem::factory()->create([
            'rental_id' => Rental::factory()->create(),
            'product_id' => $product->id,
            'qty' => 4,
            'start_at' => $this->at('10:00'),
            'end_at' => $this->at('14:00'),
        ]);

        $this->assertSame(6, $this->checker->availableQuantity($product, $this->at('11:00'), $this->at('12:00')));
        $this->assertSame(10, $this->checker->availableQuantity($product, $this->at('14:00'), $this->at('16:00')));
    }
}
