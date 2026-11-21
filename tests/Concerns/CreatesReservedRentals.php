<?php

namespace Tests\Concerns;

use App\Enums\RatePeriod;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Rental;
use App\Models\Unit;
use App\Services\RentalService;

trait CreatesReservedRentals
{
    /**
     * A reserved rental of two days with a unit assigned to each of its items, priced at 25 a day with a deposit per item.
     */
    protected function reservedRental(int $units = 2, string $depositPerItem = '10.00'): Rental
    {
        $location = Location::factory()->create();
        $product = Product::factory()->create(['buffer_minutes' => 0, 'deposit_amount' => $depositPerItem]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => 25]);
        Unit::factory()->count($units)->for($product)->for($location)->create(['meter_reading' => 100]);

        $service = app(RentalService::class);
        $rental = $service->createQuote([
            'customer_id' => Customer::factory()->create()->id,
            'location_id' => $location->id,
            'start_at' => '2026-12-01 10:00',
            'end_at' => '2026-12-03 10:00',
        ]);
        $service->addItem($rental, $product, $units);

        return $service->confirm($rental);
    }

    /**
     * Valid inspection input for every unit of the rental, keyed by rental item id.
     *
     * @param  array<string, mixed>  $overrides  Merged into every inspection.
     * @return array<int, array<string, mixed>>
     */
    protected function inspectionsFor(Rental $rental, array $overrides = []): array
    {
        return $rental->items()->whereNotNull('unit_id')->pluck('id')->mapWithKeys(fn (int $id): array => [
            $id => [
                'condition' => 'good',
                'meter_reading' => 110,
                'checklist' => ['clean', 'working'],
                'notes' => 'Looks fine',
                ...$overrides,
            ],
        ])->all();
    }

    /**
     * A valid 1x1 PNG as the signature pad would submit it.
     */
    protected function signatureDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    protected function signatureBytes(): string
    {
        return base64_decode(substr($this->signatureDataUrl(), strlen('data:image/png;base64,')));
    }
}
