<?php

namespace Database\Factories;

use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30))->startOfHour();

        return [
            'number' => 'RN-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'location_id' => Location::factory(),
            'status' => RentalStatus::Reserved,
            'start_at' => $start,
            'end_at' => $start->copy()->addDays(2),
        ];
    }

    public function status(RentalStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
