<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerDocument>
 */
class CustomerDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => DocumentType::NationalId,
            'file_path' => 'customer-documents/'.fake()->uuid().'.pdf',
            'original_name' => 'id-card.pdf',
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(['verified_at' => now()]);
    }
}
