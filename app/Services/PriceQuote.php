<?php

namespace App\Services;

use App\Enums\RatePeriod;

/**
 * The outcome of pricing one product for a period.
 */
final readonly class PriceQuote
{
    /**
     * @param  string  $rate  Base price of one billed period, e.g. "25.00".
     * @param  string  $amount  Total for the whole quantity, e.g. "150.00".
     */
    public function __construct(
        public RatePeriod $period,
        public int $periods,
        public string $rate,
        public string $amount,
    ) {}
}
