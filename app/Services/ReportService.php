<?php

namespace App\Services;

use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Models\Damage;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Figures for the reports screen and the dashboard. All dates are UTC.
 *
 * Date grouping and clipping happen in PHP so the same code runs on MySQL and SQLite.
 */
class ReportService
{
    /**
     * Rentals that count as business done: everything past the quote stage that was not cancelled.
     *
     * @return list<RentalStatus>
     */
    public static function countedStatuses(): array
    {
        return [RentalStatus::Reserved, RentalStatus::Out, RentalStatus::Returned, RentalStatus::Closed];
    }

    /**
     * Share of the period each product was booked, by item-minutes over stock-minutes.
     *
     * Stock is the quantity of units that are not retired today, so units bought or retired
     * inside the period are treated as if they were there the whole time.
     *
     * @return Collection<int, array{product: Product, stock: int, booked_minutes: int, percent: float}>
     */
    public function utilization(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $periodMinutes = max(1, (int) ceil($from->diffInMinutes($to)));

        $booked = [];

        RentalItem::query()
            ->whereHas('rental', fn ($rental) => $rental->whereIn('status', self::countedStatuses()))
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->get(['product_id', 'qty', 'start_at', 'end_at'])
            ->each(function (RentalItem $item) use (&$booked, $from, $to): void {
                $overlapSeconds = min($item->end_at->timestamp, $to->timestamp) - max($item->start_at->timestamp, $from->timestamp);

                $booked[$item->product_id] = ($booked[$item->product_id] ?? 0) + (int) round(max(0, $overlapSeconds) / 60) * $item->qty;
            });

        return Product::query()
            ->withSum(['units as stock' => fn ($units) => $units->where('status', '!=', UnitStatus::Retired)], 'quantity')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($booked, $periodMinutes): array {
                $stock = (int) $product->stock;
                $minutes = $booked[$product->id] ?? 0;

                return [
                    'product' => $product,
                    'stock' => $stock,
                    'booked_minutes' => $minutes,
                    'percent' => $stock > 0 ? round(min(100, $minutes / ($stock * $periodMinutes) * 100), 1) : 0.0,
                ];
            })
            ->sortByDesc('percent')
            ->values();
    }

    /**
     * Revenue of rentals starting in the period, by start day: rental totals plus late fees and damage charged.
     *
     * @return array{days: Collection<string, array{date: string, rentals: int, rental_total: int, late_fees: int, damage: int, total: int}>, totals: array{rentals: int, rental_total: string, late_fees: string, damage: string, total: string}}
     */
    public function revenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];

        Rental::query()
            ->with(['lateFee', 'damages'])
            ->whereIn('status', self::countedStatuses())
            ->whereBetween('start_at', [$from, $to])
            ->orderBy('start_at')
            ->get()
            ->each(function (Rental $rental) use (&$days): void {
                $date = $rental->start_at->toDateString();
                $day = $days[$date] ?? ['date' => $date, 'rentals' => 0, 'rental_total' => 0, 'late_fees' => 0, 'damage' => 0, 'total' => 0];

                $day['rentals']++;
                $day['rental_total'] += Money::toCents($rental->total);
                $day['late_fees'] += Money::toCents($rental->lateFee?->amount ?? 0);
                $day['damage'] += Money::toCents($rental->chargedDamageTotal());
                $day['total'] = $day['rental_total'] + $day['late_fees'] + $day['damage'];

                $days[$date] = $day;
            });

        $days = collect($days);

        return [
            'days' => $days,
            'totals' => [
                'rentals' => $days->sum('rentals'),
                'rental_total' => Money::format($days->sum('rental_total')),
                'late_fees' => Money::format($days->sum('late_fees')),
                'damage' => Money::format($days->sum('damage')),
                'total' => Money::format($days->sum('total')),
            ],
        ];
    }

    /**
     * Rentals that are out past their end time, worst first.
     *
     * @return Collection<int, array{rental: Rental, days_late: int, fee: string}>
     */
    public function overdue(): Collection
    {
        return Rental::query()
            ->with(['customer:id,name,phone', 'lateFee'])
            ->where('status', RentalStatus::Out)
            ->where('end_at', '<', now())
            ->orderBy('end_at')
            ->get()
            ->map(fn (Rental $rental): array => [
                'rental' => $rental,
                'days_late' => max(1, (int) ceil($rental->end_at->diffInMinutes(now()) / 1440)),
                'fee' => $rental->lateFee?->amount ?? '0.00',
            ]);
    }

    /**
     * Damage recorded in the period with repair cost and what was charged to customers.
     *
     * @return array{rows: Collection<int, Damage>, cost: string, charged: string}
     */
    public function damages(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Damage::query()
            ->with(['unit.product', 'rental:id,number'])
            ->whereBetween('created_at', [$from, $to])
            ->latest('id')
            ->get();

        return [
            'rows' => $rows,
            'cost' => Money::format($rows->sum(fn (Damage $damage): int => Money::toCents($damage->cost))),
            'charged' => Money::format($rows->sum(fn (Damage $damage): int => Money::toCents($damage->charged_amount))),
        ];
    }
}
