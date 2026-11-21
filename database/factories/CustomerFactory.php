<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CustomerType::Person,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1-555-###-####'),
            'address' => fake()->streetAddress(),
            'tax_no' => null,
            'is_blacklisted' => false,
            'notes' => null,
        ];
    }

    public function company(): static
    {
        return $this->state([
            'type' => CustomerType::Company,
            'name' => fake()->company(),
            'tax_no' => fake()->numerify('TAX-#######'),
        ]);
    }

    public function blacklisted(): static
    {
        return $this->state(['is_blacklisted' => true]);
    }
}
