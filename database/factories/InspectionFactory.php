<?php

namespace Database\Factories;

use App\Enums\InspectionType;
use App\Enums\UnitCondition;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'unit_id' => Unit::factory(),
            'type' => InspectionType::Out,
            'condition' => UnitCondition::Good,
            'meter_reading' => null,
            'checklist' => ['clean', 'working'],
            'notes' => null,
            'signature_path' => null,
            'inspected_by' => null,
        ];
    }
}
