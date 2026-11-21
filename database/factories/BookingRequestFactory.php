<?php

namespace Database\Factories;

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(3, 20))->startOfHour();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1-555-###-####'),
            'location_id' => Location::factory(),
            'product_id' => Product::factory(),
            'qty' => 1,
            'start_at' => $start,
            'end_at' => $start->copy()->addDays(2),
            'message' => null,
            'status' => BookingRequestStatus::New,
        ];
    }

    public function status(BookingRequestStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
