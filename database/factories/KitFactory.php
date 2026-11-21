<?php

namespace Database\Factories;

use App\Models\Kit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kit>
 */
class KitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' kit',
            'price' => null,
        ];
    }
}
