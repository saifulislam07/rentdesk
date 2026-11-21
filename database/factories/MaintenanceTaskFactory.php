<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceTask;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceTask>
 */
class MaintenanceTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'plan_id' => null,
            'due_at' => now(),
            'status' => MaintenanceStatus::Open,
            'notes' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(['status' => MaintenanceStatus::Done, 'done_at' => now(), 'cost' => 20]);
    }
}
