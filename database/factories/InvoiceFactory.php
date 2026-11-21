<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'rental_id' => Rental::factory(),
            'status' => InvoiceStatus::Draft,
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'total' => 100,
            'currency' => 'USD',
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(7)->toDateString(),
        ];
    }
}
