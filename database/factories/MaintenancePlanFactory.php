<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'every_days' => 90,
            'every_rentals' => null,
        ];
    }
}
