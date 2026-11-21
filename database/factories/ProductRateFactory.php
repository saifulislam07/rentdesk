<?php

namespace Database\Factories;

use App\Enums\RatePeriod;
use App\Models\Product;
use App\Models\ProductRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRate>
 */
class ProductRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'period' => RatePeriod::Day,
            'price' => fake()->randomFloat(2, 10, 300),
            'min_qty_periods' => 1,
            'weekend_price' => null,
        ];
    }
}
