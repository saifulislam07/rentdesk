<?php

namespace App\Services;

use App\Enums\ProductTracking;
use App\Exceptions\UnitUnavailableException;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Adds items to a rental without ever double-booking a unit.
 *
 * The availability check and the insert run in one transaction that first locks the unit
 * (or product) row, so two concurrent bookings of the same unit are serialised and the
 * second one sees the first.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly PriceCalculator $prices,
    ) {}

    /**
     * @throws UnitUnavailableException
     */
    public function bookUnit(Rental $rental, Unit $unit): RentalItem
    {
        return DB::transaction(function () use ($rental, $unit): RentalItem {
            $unit = Unit::query()->with('product.rates')->lockForUpdate()->findOrFail($unit->id);

            if (! $this->availability->isUnitAvailable($unit, $rental->start_at, $rental->end_at)) {
                throw UnitUnavailableException::forUnit($unit);
            }

            return $this->createItem($rental, $unit->product, $unit, 1);
        });
    }

    /**
     * @throws UnitUnavailableException
     */
    public function bookQuantity(Rental $rental, Product $product, int $quantity): RentalItem
    {
        return DB::transaction(function () use ($rental, $product, $quantity): RentalItem {
            $product = Product::query()->with('rates')->lockForUpdate()->findOrFail($product->id);

            $available = $this->availability->availableQuantity(
                $product,
                $rental->start_at,
                $rental->end_at,
                $rental->location_id,
            );

            if ($product->tracking !== ProductTracking::Bulk || $quantity > $available) {
                throw new UnitUnavailableException(
                    __('Only :count of :product available for the selected period.', [
                        'count' => $available,
                        'product' => $product->name,
                    ])
                );
            }

            return $this->createItem($rental, $product, null, $quantity);
        });
    }

    private function createItem(Rental $rental, Product $product, ?Unit $unit, int $quantity): RentalItem
    {
        $quote = $this->prices->calculate($product, $rental->start_at, $rental->end_at, $quantity);

        return $rental->items()->create([
            'product_id' => $product->id,
            'unit_id' => $unit?->id,
            'qty' => $quantity,
            'start_at' => $rental->start_at,
            'end_at' => $rental->end_at,
            'rate' => $quote->rate,
            'amount' => $quote->amount,
        ]);
    }
}
