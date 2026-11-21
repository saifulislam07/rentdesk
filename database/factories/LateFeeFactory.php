<?php

namespace Database\Factories;

use App\Models\LateFee;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LateFee>
 */
class LateFeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'days_late' => 1,
            'amount' => 25,
            'calculated_at' => now(),
        ];
    }
}
