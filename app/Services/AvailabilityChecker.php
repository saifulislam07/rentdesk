<?php

namespace App\Services;

use App\Enums\ProductTracking;
use App\Models\Product;
use App\Models\RentalItem;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Answers whether units and stock are free for a period.
 *
 * A unit is busy when a reserved or out rental item overlaps the period once the product's
 * buffer time is added on both sides. Quotes, returned and cancelled rentals never block.
 */
class AvailabilityChecker
{
    public function isUnitAvailable(
        Unit $unit,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreRentalId = null,
    ): bool {
        if (! $unit->status->isRentable()) {
            return false;
        }

        return ! RentalItem::query()
            ->where('unit_id', $unit->id)
            ->overlapping($start, $end, $unit->product->buffer_minutes)
            ->excludingRental($ignoreRentalId)
            ->exists();
    }

    /**
     * Units of a serial-tracked product that are free for the period.
     *
     * @return Collection<int, Unit>
     */
    public function availableUnits(
        Product $product,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $locationId = null,
        ?int $ignoreRentalId = null,
    ): Collection {
        return $this->availableUnitsQuery($product, $start, $end, $locationId, $ignoreRentalId)
            ->with('location')
            ->orderBy('serial_no')
            ->get();
    }

    /**
     * How many items of the product can still be rented for the period.
     */
    public function availableQuantity(
        Product $product,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $locationId = null,
        ?int $ignoreRentalId = null,
    ): int {
        if ($product->tracking === ProductTracking::Serial) {
            return $this->availableUnitsQuery($product, $start, $end, $locationId, $ignoreRentalId)->count();
        }

        $stock = (int) $product->units()
            ->rentable()
            ->when($locationId, fn (Builder $query) => $query->where('location_id', $locationId))
            ->sum('quantity');

        $booked = (int) RentalItem::query()
            ->where('product_id', $product->id)
            ->overlapping($start, $end, $product->buffer_minutes)
            ->excludingRental($ignoreRentalId)
            ->when($locationId, fn (Builder $query) => $query->whereHas(
                'rental',
                fn (Builder $rental) => $rental->where('location_id', $locationId)
            ))
            ->sum('qty');

        return max($stock - $booked, 0);
    }

    /**
     * @return Builder<Unit>
     */
    private function availableUnitsQuery(
        Product $product,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $locationId,
        ?int $ignoreRentalId,
    ): Builder {
        return $product->units()
            ->getQuery()
            ->rentable()
            ->when($locationId, fn (Builder $query) => $query->where('location_id', $locationId))
            ->whereDoesntHave(
                'rentalItems',
                fn (Builder $items) => $items
                    ->overlapping($start, $end, $product->buffer_minutes)
                    ->excludingRental($ignoreRentalId)
            );
    }
}
