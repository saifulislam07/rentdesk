<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\Location;
use App\Models\StockTransfer;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'from_location_id' => Location::factory(),
            'to_location_id' => Location::factory(),
            'status' => TransferStatus::InTransit,
            'sent_at' => now(),
            'received_at' => null,
        ];
    }
}
