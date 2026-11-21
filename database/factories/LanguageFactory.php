<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??'),
            'name' => fake()->unique()->word(),
            'dir' => 'ltr',
            'is_active' => true,
        ];
    }

    public function rtl(): static
    {
        return $this->state(['dir' => 'rtl']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
