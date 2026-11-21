<?php

namespace Tests\Feature;

use App\Enums\RatePeriod;
use App\Exceptions\UnitUnavailableException;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Rental;
use App\Models\Unit;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->booking = app(BookingService::class);
    }

    private function rental(string $from, string $to): Rental
    {
        return Rental::factory()->create([
            'start_at' => CarbonImmutable::parse($from, 'UTC'),
            'end_at' => CarbonImmutable::parse($to, 'UTC'),
        ]);
    }

    private function pricedUnit(int $bufferMinutes = 0): Unit
    {
        $unit = Unit::factory()->for(Product::factory()->state(['buffer_minutes' => $bufferMinutes]))->create();
        ProductRate::factory()->for($unit->product)->create(['period' => RatePeriod::Day, 'price' => 25]);

        return $unit;
    }

    public function test_booking_a_unit_stores_a_priced_item(): void
    {
        $unit = $this->pricedUnit();
        $rental = $this->rental('2026-12-01 10:00', '2026-12-03 10:00');

        $item = $this->booking->bookUnit($rental, $unit);

        $this->assertSame($unit->id, $item->unit_id);
        $this->assertSame(1, $item->qty);
        $this->assertSame('25.00', $item->rate);
        $this->assertSame('50.00', $item->amount);
        $this->assertTrue($item->start_at->equalTo($rental->start_at));
    }

    public function test_the_same_unit_cannot_be_booked_twice_for_overlapping_periods(): void
    {
        $unit = $this->pricedUnit();
        $this->booking->bookUnit($this->rental('2026-12-01 10:00', '2026-12-03 10:00'), $unit);

        $this->expectException(UnitUnavailableException::class);

        try {
            $this->booking->bookUnit($this->rental('2026-12-02 10:00', '2026-12-04 10:00'), $unit);
        } finally {
            $this->assertDatabaseCount('rental_items', 1);
        }
    }

    public function test_back_to_back_bookings_are_allowed(): void
    {
        $unit = $this->pricedUnit();
        $this->booking->bookUnit($this->rental('2026-12-01 10:00', '2026-12-03 10:00'), $unit);
        $this->booking->bookUnit($this->rental('2026-12-03 10:00', '2026-12-05 10:00'), $unit);

        $this->assertDatabaseCount('rental_items', 2);
    }

    public function test_buffer_time_prevents_back_to_back_bookings(): void
    {
        $unit = $this->pricedUnit(bufferMinutes: 120);
        $this->booking->bookUnit($this->rental('2026-12-01 10:00', '2026-12-03 10:00'), $unit);

        $this->expectException(UnitUnavailableException::class);

        $this->booking->bookUnit($this->rental('2026-12-03 10:00', '2026-12-05 10:00'), $unit);
    }

    public function test_bulk_booking_is_limited_to_the_remaining_stock(): void
    {
        $product = Product::factory()->bulk()->create(['buffer_minutes' => 0]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => 2]);
        $unit = Unit::factory()->for($product)->create(['quantity' => 10]);
        $rental = $this->rental('2026-12-01 10:00', '2026-12-02 10:00');
        $rental->update(['location_id' => $unit->location_id]);

        $item = $this->booking->bookQuantity($rental, $product, 7);

        $this->assertSame('14.00', $item->amount);

        $this->expectException(UnitUnavailableException::class);

        $this->booking->bookQuantity($this->rental('2026-12-01 12:00', '2026-12-01 18:00'), $product, 4);
    }

    public function test_serial_products_cannot_be_booked_by_quantity(): void
    {
        $unit = $this->pricedUnit();

        $this->expectException(UnitUnavailableException::class);

        $this->booking->bookQuantity($this->rental('2026-12-01 10:00', '2026-12-02 10:00'), $unit->product, 1);
    }
}
