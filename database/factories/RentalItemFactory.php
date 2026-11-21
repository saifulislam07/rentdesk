<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalItem>
 */
class RentalItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rental = Rental::factory();

        return [
            'rental_id' => $rental,
            'product_id' => Product::factory(),
            'unit_id' => null,
            'qty' => 1,
            'start_at' => now()->addDay()->startOfHour(),
            'end_at' => now()->addDays(3)->startOfHour(),
            'rate' => 0,
            'amount' => 0,
        ];
    }

    /**
     * Book the given unit (and its product) for the period, on a rental with the same period.
     */
    public function forUnit(Unit $unit, \DateTimeInterface $start, \DateTimeInterface $end): static
    {
        return $this->state(fn (): array => [
            'rental_id' => Rental::factory()->state(['start_at' => $start, 'end_at' => $end]),
            'product_id' => $unit->product_id,
            'unit_id' => $unit->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);
    }
}
