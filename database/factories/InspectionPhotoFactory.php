<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionPhoto>
 */
class InspectionPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'path' => 'inspections/photo-'.fake()->uuid().'.jpg',
        ];
    }
}
