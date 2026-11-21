<?php

namespace Database\Factories;

use App\Enums\DamageStatus;
use App\Models\Damage;
use App\Models\Rental;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Damage>
 */
class DamageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'unit_id' => Unit::factory(),
            'inspection_id' => null,
            'description' => fake()->sentence(),
            'cost' => 80,
            'charged_amount' => 50,
            'status' => DamageStatus::Charged,
        ];
    }
}
