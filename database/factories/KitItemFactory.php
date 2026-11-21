<?php

namespace Database\Factories;

use App\Models\Kit;
use App\Models\KitItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitItem>
 */
class KitItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kit_id' => Kit::factory(),
            'product_id' => Product::factory(),
            'qty' => 1,
        ];
    }
}
