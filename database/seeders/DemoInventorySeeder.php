<?php

namespace Database\Seeders;

use App\Enums\RatePeriod;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Sample catalogue: two locations, four categories, eleven products with rates and four units each.
 */
class DemoInventorySeeder extends Seeder
{
    /**
     * Demo catalogue: category => [product name => daily rate].
     *
     * @var array<string, array<string, int>>
     */
    private const CATALOGUE = [
        'Power Tools' => ['Hammer Drill' => 25, 'Angle Grinder' => 18, 'Concrete Mixer' => 45, 'LED Work Light' => 12],
        'Construction' => ['Mini Excavator' => 220, 'Scaffold Set' => 60],
        'Camera & Audio' => ['Mirrorless Camera Kit' => 95, 'PA Speaker System' => 70, 'Wireless Microphone' => 22],
        'Party & Events' => ['Marquee Tent 6x6' => 120, 'Folding Table Set' => 30],
    ];

    public function run(): void
    {
        $locations = collect([['Main Branch', 'MAIN'], ['Downtown Depot', 'DTN']])
            ->map(fn (array $row): Location => Location::factory()->create([
                'name' => $row[0],
                'code' => $row[1],
            ]));

        $brands = Brand::factory()->count(4)->create();

        foreach (self::CATALOGUE as $categoryName => $products) {
            $category = Category::factory()->create(['name' => $categoryName]);

            foreach ($products as $productName => $dailyRate) {
                $product = Product::factory()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brands->random()->id,
                    'name' => $productName,
                    'deposit_amount' => $dailyRate * 2,
                ]);

                ProductRate::factory()->create([
                    'product_id' => $product->id,
                    'period' => RatePeriod::Day,
                    'price' => $dailyRate,
                    'weekend_price' => $dailyRate * 1.2,
                ]);
                ProductRate::factory()->create([
                    'product_id' => $product->id,
                    'period' => RatePeriod::Week,
                    'price' => $dailyRate * 5,
                ]);

                // Two units at each location, bought recently enough that no service is due yet.
                Unit::factory()
                    ->count(4)
                    ->for($product)
                    ->sequence(fn ($sequence): array => [
                        'location_id' => $locations[$sequence->index % 2]->id,
                        'purchased_at' => now()->subDays(30 + $sequence->index * 20),
                    ])
                    ->create();
            }
        }
    }
}
