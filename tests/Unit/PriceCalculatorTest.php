<?php

namespace Tests\Unit;

use App\Enums\RatePeriod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Services\PriceCalculator;
use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    /** A Friday. */
    private const FRIDAY = '2026-11-20 10:00:00';

    /**
     * @param  array<string, array{price: string, min?: int, weekend?: string|null}>  $rates  Keyed by period.
     */
    private function productWithRates(array $rates): Product
    {
        $product = new Product;
        $product->setRelation('rates', collect($rates)->map(fn (array $rate, string $period): ProductRate => new ProductRate([
            'period' => RatePeriod::from($period),
            'price' => $rate['price'],
            'min_qty_periods' => $rate['min'] ?? 1,
            'weekend_price' => $rate['weekend'] ?? null,
        ]))->values());

        return $product;
    }

    private function at(string $modifier = ''): CarbonImmutable
    {
        return CarbonImmutable::parse(self::FRIDAY, 'UTC')->modify($modifier ?: 'now');
    }

    public function test_duration_is_rounded_up_to_whole_periods(): void
    {
        $product = $this->productWithRates(['hour' => ['price' => '10.00']]);

        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+61 minutes'));

        $this->assertSame(RatePeriod::Hour, $quote->period);
        $this->assertSame(2, $quote->periods);
        $this->assertSame('20.00', $quote->amount);
    }

    public function test_cheapest_rate_period_wins(): void
    {
        $product = $this->productWithRates([
            'day' => ['price' => '20.00'],
            'week' => ['price' => '100.00'],
        ]);

        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+6 days'));

        $this->assertSame(RatePeriod::Week, $quote->period);
        $this->assertSame('100.00', $quote->amount);
        $this->assertSame('100.00', $quote->rate);
    }

    public function test_daily_rate_wins_when_cheaper_than_the_weekly_rate(): void
    {
        $product = $this->productWithRates([
            'day' => ['price' => '20.00'],
            'week' => ['price' => '100.00'],
        ]);

        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+2 days'));

        $this->assertSame(RatePeriod::Day, $quote->period);
        $this->assertSame('40.00', $quote->amount);
    }

    public function test_equal_prices_prefer_the_longer_period(): void
    {
        $product = $this->productWithRates([
            'day' => ['price' => '10.00'],
            'week' => ['price' => '70.00'],
        ]);

        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+7 days'));

        $this->assertSame(RatePeriod::Week, $quote->period);
    }

    public function test_minimum_periods_are_charged(): void
    {
        $product = $this->productWithRates(['day' => ['price' => '30.00', 'min' => 3]]);

        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+4 hours'));

        $this->assertSame(3, $quote->periods);
        $this->assertSame('90.00', $quote->amount);
    }

    public function test_weekend_price_applies_to_saturday_and_sunday_blocks(): void
    {
        $product = $this->productWithRates(['day' => ['price' => '20.00', 'weekend' => '35.00']]);

        // Friday, Saturday and Sunday blocks: 20 + 35 + 35.
        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+3 days'));

        $this->assertSame('90.00', $quote->amount);
    }

    public function test_quantity_multiplies_the_amount(): void
    {
        $product = $this->productWithRates(['day' => ['price' => '12.50']]);

        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+1 day'), 4);

        $this->assertSame('12.50', $quote->rate);
        $this->assertSame('50.00', $quote->amount);
    }

    public function test_amounts_do_not_drift_with_fractional_prices(): void
    {
        $product = $this->productWithRates(['hour' => ['price' => '0.10']]);

        $quote = (new PriceCalculator)->calculate($product, $this->at(), $this->at('+3 hours'));

        $this->assertSame('0.30', $quote->amount);
    }

    public function test_period_must_end_after_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PriceCalculator)->calculate($this->productWithRates(['day' => ['price' => '1.00']]), $this->at(), $this->at());
    }

    public function test_quantity_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PriceCalculator)->calculate($this->productWithRates(['day' => ['price' => '1.00']]), $this->at(), $this->at('+1 day'), 0);
    }

    public function test_product_without_rates_cannot_be_priced(): void
    {
        $this->expectException(DomainException::class);

        (new PriceCalculator)->calculate($this->productWithRates([]), $this->at(), $this->at('+1 day'));
    }
}
