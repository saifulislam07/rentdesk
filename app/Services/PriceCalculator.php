<?php

namespace App\Services;

use App\Enums\RatePeriod;
use App\Models\Product;
use App\Models\ProductRate;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;

/**
 * Prices a rental period from a product's rate plan.
 *
 * Every rate period is considered on its own: the duration is rounded up to whole periods
 * (never fewer than the rate's minimum) and the cheapest result wins. A day is 24 hours, a
 * week 7 days and a month 30 days. Weekend prices apply to daily blocks starting on a
 * Saturday or Sunday. All dates are UTC. Money is handled in cents to avoid float drift.
 */
class PriceCalculator
{
    private const MINUTES_PER_PERIOD = [
        'hour' => 60,
        'day' => 1440,
        'week' => 10080,
        'month' => 43200,
    ];

    /**
     * @throws InvalidArgumentException When the period is empty or the quantity is below one.
     * @throws DomainException When the product has no rates.
     */
    public function calculate(Product $product, CarbonInterface $start, CarbonInterface $end, int $quantity = 1): PriceQuote
    {
        if ($end->lessThanOrEqualTo($start) || $quantity < 1) {
            throw new InvalidArgumentException('The rental must end after it starts and cover at least one item.');
        }

        if ($product->rates->isEmpty()) {
            throw new DomainException(__('This product has no rates.'));
        }

        $minutes = (int) ceil($start->diffInSeconds($end) / 60);

        $best = $product->rates
            ->map(fn (ProductRate $rate): array => $this->price($rate, $start, $minutes))
            ->sort(fn (array $a, array $b): int => [$a['cents'], -$a['length']] <=> [$b['cents'], -$b['length']])
            ->first();

        return new PriceQuote(
            $best['rate']->period,
            $best['periods'],
            Money::format(Money::toCents($best['rate']->price)),
            Money::format($best['cents'] * $quantity),
        );
    }

    /**
     * @return array{rate: ProductRate, periods: int, cents: int, length: int}
     */
    private function price(ProductRate $rate, CarbonInterface $start, int $minutes): array
    {
        $length = self::MINUTES_PER_PERIOD[$rate->period->value];
        $periods = max((int) ceil($minutes / $length), $rate->min_qty_periods);

        $cents = 0;

        for ($index = 0; $index < $periods; $index++) {
            $cents += $this->priceOfPeriod($rate, $start->copy()->addMinutes($index * $length));
        }

        return ['rate' => $rate, 'periods' => $periods, 'cents' => $cents, 'length' => $length];
    }

    private function priceOfPeriod(ProductRate $rate, CarbonInterface $periodStart): int
    {
        $useWeekendPrice = $rate->period === RatePeriod::Day
            && $rate->weekend_price !== null
            && $periodStart->isWeekend();

        return Money::toCents($useWeekendPrice ? $rate->weekend_price : $rate->price);
    }
}
