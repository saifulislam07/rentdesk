<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ProductTracking;
use App\Enums\RentalStatus;
use App\Exceptions\RentalException;
use App\Exceptions\UnitUnavailableException;
use App\Models\Customer;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The quote → reserved → cancelled part of a rental's life.
 *
 * Quotes hold priced items without units and block nothing. Confirming a quote reserves it
 * and assigns a free unit to every serial-tracked item, all in one transaction, so a quote
 * that cannot be fulfilled stays a quote.
 */
class RentalService
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly PriceCalculator $prices,
    ) {}

    /**
     * @param  array{customer_id: int, location_id: int, start_at: string|\DateTimeInterface, end_at: string|\DateTimeInterface, discount?: string|float|null, notes?: string|null}  $data
     *
     * @throws RentalException
     */
    public function createQuote(array $data): Rental
    {
        $this->assertCustomerMayRent(Customer::findOrFail($data['customer_id']));

        return DB::transaction(function () use ($data): Rental {
            $rental = Rental::create([
                ...$data,
                'discount' => $data['discount'] ?? 0,
                'number' => (string) Str::ulid(),
                'status' => RentalStatus::Quote,
                'currency' => config('rentdesk.currency'),
            ]);

            $rental->update(['number' => sprintf('RN-%06d', $rental->id)]);

            return $this->recalculate($rental);
        });
    }

    /**
     * @param  array{customer_id: int, location_id: int, start_at: string|\DateTimeInterface, end_at: string|\DateTimeInterface, discount?: string|float|null, notes?: string|null}  $data
     *
     * @throws RentalException
     */
    public function updateQuote(Rental $rental, array $data): Rental
    {
        $this->assertEditable($rental);
        $this->assertCustomerMayRent(Customer::findOrFail($data['customer_id']));

        return DB::transaction(function () use ($rental, $data): Rental {
            $rental->update([...$data, 'discount' => $data['discount'] ?? 0]);

            foreach ($rental->items()->with('product.rates')->get() as $item) {
                $this->priceItem($item, $rental);
            }

            return $this->recalculate($rental);
        });
    }

    /**
     * Add a product to a quote. Serial products become one item per unit wanted.
     *
     * @return Collection<int, RentalItem>
     *
     * @throws RentalException
     */
    public function addItem(Rental $rental, Product $product, int $quantity): Collection
    {
        $this->assertEditable($rental);

        return DB::transaction(function () use ($rental, $product, $quantity): Collection {
            $items = $this->createItems($rental, $product, $quantity);

            $this->recalculate($rental);

            return $items;
        });
    }

    /**
     * Add a kit to a quote: each of its products becomes items, priced on their own or, when the
     * kit has a flat daily price, scaled so the kit's lines add up to exactly that price.
     *
     * @return Collection<int, RentalItem>
     *
     * @throws RentalException
     */
    public function addKit(Rental $rental, Kit $kit, int $quantity): Collection
    {
        $this->assertEditable($rental);

        $kit->loadMissing('items.product.rates');

        if ($kit->items->isEmpty()) {
            throw new RentalException(__('This kit has no products.'));
        }

        return DB::transaction(function () use ($rental, $kit, $quantity): Collection {
            $items = $kit->items
                ->flatMap(fn (KitItem $line): Collection => $this->createItems($rental, $line->product, $line->qty * $quantity))
                ->each(fn (RentalItem $item) => $item->update(['kit_id' => $kit->id]));

            if ($kit->price !== null) {
                $this->applyKitPrice($rental, $kit, $quantity, $items);
            }

            $this->recalculate($rental);

            return $items;
        });
    }

    /**
     * Remove every line that came from the kit.
     *
     * @throws RentalException
     */
    public function removeKit(Rental $rental, Kit $kit): Rental
    {
        $this->assertEditable($rental);

        $rental->items()->where('kit_id', $kit->id)->delete();

        return $this->recalculate($rental);
    }

    /**
     * @throws RentalException
     */
    public function removeItem(Rental $rental, RentalItem $item): Rental
    {
        $this->assertEditable($rental);

        $item->delete();

        return $this->recalculate($rental);
    }

    /**
     * Reserve a quote and assign units to its serial-tracked items.
     *
     * @throws RentalException
     * @throws UnitUnavailableException
     */
    public function confirm(Rental $rental): Rental
    {
        return DB::transaction(function () use ($rental): Rental {
            $rental = Rental::query()->with('customer')->lockForUpdate()->findOrFail($rental->id);

            $this->assertEditable($rental, __('Only quotes can be confirmed.'));
            $this->assertCustomerMayRent($rental->customer);

            $items = $rental->items()->with('product')->get();

            if ($items->isEmpty()) {
                throw new RentalException(__('Add at least one item before confirming.'));
            }

            // Reserve first so items assigned below block the units that follow.
            $rental->update(['status' => RentalStatus::Reserved]);

            $items
                ->filter(fn (RentalItem $item): bool => $item->product->tracking === ProductTracking::Serial)
                ->each(fn (RentalItem $item) => $this->claimUnit($rental, $item));

            $items
                ->filter(fn (RentalItem $item): bool => $item->product->tracking === ProductTracking::Bulk)
                ->groupBy('product_id')
                ->each(fn (Collection $lines) => $this->assertStock($rental, $lines->first()->product, $lines->sum('qty')));

            return $rental->refresh();
        });
    }

    /**
     * @throws RentalException
     */
    public function cancel(Rental $rental): Rental
    {
        return DB::transaction(function () use ($rental): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! $rental->status->isCancellable()) {
                throw new RentalException(__('This rental can no longer be cancelled.'));
            }

            $rental->update(['status' => RentalStatus::Cancelled]);
            $rental->invoices()->where('status', '!=', InvoiceStatus::Paid)->update(['status' => InvoiceStatus::Void]);

            return $rental;
        });
    }

    /**
     * Recompute subtotal, tax, total and required deposit from the rental's items.
     */
    public function recalculate(Rental $rental): Rental
    {
        $items = $rental->items()->with('product')->get();

        $subtotal = $items->sum(fn (RentalItem $item): int => Money::toCents($item->amount));
        $discount = min(Money::toCents($rental->discount), $subtotal);
        $taxable = $subtotal - $discount;
        $tax = (int) round($taxable * (float) config('rentdesk.tax_rate') / 100);
        $deposit = $items->sum(fn (RentalItem $item): int => Money::toCents($item->product->deposit_amount) * $item->qty);

        // The stored discount is what staff entered; only the amount applied is capped by the subtotal.
        $rental->update([
            'subtotal' => Money::format($subtotal),
            'tax' => Money::format($tax),
            'total' => Money::format($taxable + $tax),
            'deposit_required' => Money::format($deposit),
        ]);

        return $rental;
    }

    /**
     * Create the priced items for a product; serial products become one item per unit wanted.
     *
     * @return Collection<int, RentalItem>
     */
    private function createItems(Rental $rental, Product $product, int $quantity): Collection
    {
        $product->loadMissing('rates');
        $serial = $product->tracking === ProductTracking::Serial;

        return collect(range(1, $serial ? $quantity : 1))->map(function () use ($rental, $product, $quantity, $serial): RentalItem {
            $item = $rental->items()->make([
                'product_id' => $product->id,
                'qty' => $serial ? 1 : $quantity,
            ]);
            $item->setRelation('product', $product);
            $this->priceItem($item, $rental);

            return $item;
        });
    }

    /**
     * @param  Collection<int, RentalItem>  $items
     */
    private function applyKitPrice(Rental $rental, Kit $kit, int $quantity, Collection $items): void
    {
        $days = max(1, (int) ceil($rental->start_at->diffInMinutes($rental->end_at) / 1440));
        $target = Money::toCents($kit->price) * $days * $quantity;

        $allocated = KitPricing::allocate(
            $items->map(fn (RentalItem $item): int => Money::toCents($item->amount))->all(),
            $target,
        );

        foreach ($items->values() as $index => $item) {
            $item->update([
                'amount' => Money::format($allocated[$index]),
                'rate' => Money::format((int) round($allocated[$index] / $item->qty)),
            ]);
        }
    }

    private function priceItem(RentalItem $item, Rental $rental): void
    {
        try {
            $quote = $this->prices->calculate(
                $item->product,
                CarbonImmutable::instance($rental->start_at),
                CarbonImmutable::instance($rental->end_at),
                $item->qty,
            );
        } catch (DomainException $exception) {
            throw new RentalException($item->product->name.': '.$exception->getMessage());
        }

        $item->fill([
            'start_at' => $rental->start_at,
            'end_at' => $rental->end_at,
            'rate' => $quote->rate,
            'amount' => $quote->amount,
        ])->save();
    }

    private function claimUnit(Rental $rental, RentalItem $item): void
    {
        if ($item->unit_id !== null) {
            return;
        }

        $candidates = $this->availability->availableUnits(
            $item->product,
            CarbonImmutable::instance($rental->start_at),
            CarbonImmutable::instance($rental->end_at),
            $rental->location_id,
        );

        foreach ($candidates as $candidate) {
            $unit = Unit::query()->with('product')->lockForUpdate()->find($candidate->id);

            if ($unit && $this->availability->isUnitAvailable($unit, CarbonImmutable::instance($rental->start_at), CarbonImmutable::instance($rental->end_at))) {
                $item->update(['unit_id' => $unit->id]);

                return;
            }
        }

        throw new UnitUnavailableException(
            __('No :product unit is available at this location for the selected period.', ['product' => $item->product->name])
        );
    }

    private function assertStock(Rental $rental, Product $product, int $needed): void
    {
        Product::query()->lockForUpdate()->find($product->id);

        $available = $this->availability->availableQuantity(
            $product,
            CarbonImmutable::instance($rental->start_at),
            CarbonImmutable::instance($rental->end_at),
            $rental->location_id,
            ignoreRentalId: $rental->id,
        );

        if ($needed > $available) {
            throw new UnitUnavailableException(
                __('Only :count of :product available for the selected period.', ['count' => $available, 'product' => $product->name])
            );
        }
    }

    private function assertEditable(Rental $rental, ?string $message = null): void
    {
        if (! $rental->status->isEditable()) {
            throw new RentalException($message ?? __('Only quotes can be changed.'));
        }
    }

    private function assertCustomerMayRent(Customer $customer): void
    {
        if ($customer->is_blacklisted) {
            throw new RentalException(__(':name is blacklisted and cannot rent.', ['name' => $customer->name]));
        }
    }
}
