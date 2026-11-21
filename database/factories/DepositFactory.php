<?php

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use App\Models\Deposit;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'amount' => 100,
            'method' => PaymentMethod::Cash,
            'status' => DepositStatus::Held,
            'refunded_amount' => 0,
            'settled_at' => null,
        ];
    }
}
