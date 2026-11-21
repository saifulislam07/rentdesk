<?php

namespace Database\Factories;

use App\Enums\ProductTracking;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'tracking' => ProductTracking::Serial,
            'description' => fake()->sentence(),
            'replacement_value' => fake()->randomFloat(2, 200, 5000),
            'deposit_amount' => fake()->randomFloat(2, 20, 500),
            'buffer_minutes' => 60,
        ];
    }

    public function bulk(): static
    {
        return $this->state(['tracking' => ProductTracking::Bulk]);
    }
}
