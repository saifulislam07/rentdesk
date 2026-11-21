<?php

namespace Database\Factories;

use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'serial_no' => strtoupper(fake()->unique()->bothify('SN-########')),
            'condition' => UnitCondition::Good,
            'status' => UnitStatus::Available,
            'quantity' => 1,
            'purchased_at' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'purchase_cost' => fake()->randomFloat(2, 100, 4000),
        ];
    }

    public function status(UnitStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
