<?php

namespace Tests\Feature;

use App\Enums\RatePeriod;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'name' => 'Hammer Drill',
            'sku' => 'DRILL-001',
            'tracking' => 'serial',
            'replacement_value' => '350.00',
            'deposit_amount' => '50.00',
            'buffer_minutes' => 30,
            'rates' => [
                'day' => ['price' => '25.00', 'min_qty_periods' => '1', 'weekend_price' => '30.00'],
                'week' => ['price' => '120.00', 'min_qty_periods' => '1', 'weekend_price' => ''],
                'hour' => ['price' => '', 'min_qty_periods' => '1', 'weekend_price' => ''],
            ],
            ...$overrides,
        ];
    }

    public function test_staff_can_view_but_not_create_products(): void
    {
        $staff = $this->userWithRole('staff');
        $product = Product::factory()->create();

        $this->actingAs($staff)->get(route('products.index'))->assertOk();
        $this->actingAs($staff)->get(route('products.show', $product))->assertOk();
        $this->actingAs($staff)->get(route('products.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('products.store'), $this->validPayload())->assertForbidden();
    }

    public function test_manager_can_create_a_product_with_rates_on_the_same_form(): void
    {
        $response = $this->actingAs($this->userWithRole('manager'))
            ->post(route('products.store'), $this->validPayload());

        $product = Product::firstWhere('sku', 'DRILL-001');

        $response->assertRedirect(route('products.show', $product));
        $this->assertSame('hammer-drill', $product->slug);
        $this->assertEqualsCanonicalizing(['day', 'week'], $product->rates->pluck('period.value')->all());
        $this->assertDatabaseHas('product_rates', [
            'product_id' => $product->id,
            'period' => 'day',
            'price' => '25.00',
            'weekend_price' => '30.00',
        ]);
    }

    public function test_product_requires_valid_data(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->post(route('products.store'), $this->validPayload([
                'name' => '',
                'tracking' => 'unknown',
                'rates' => ['day' => ['price' => '-1']],
            ]))
            ->assertSessionHasErrors(['name', 'tracking', 'rates.day.price']);
    }

    public function test_sku_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'DRILL-001']);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('products.store'), $this->validPayload())
            ->assertSessionHasErrors('sku');
    }

    public function test_manager_can_update_a_product_and_its_rates(): void
    {
        $product = Product::factory()->create(['sku' => 'DRILL-001']);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => 10]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Month, 'price' => 300]);

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('products.update', $product), $this->validPayload([
                'name' => 'Renamed',
                'slug' => $product->slug,
                'rates' => ['day' => ['price' => '40.00', 'min_qty_periods' => '2']],
            ]))
            ->assertRedirect(route('products.show', $product));

        $this->assertSame('Renamed', $product->refresh()->name);
        $this->assertSame(['day'], $product->rates->pluck('period.value')->all());
        $this->assertDatabaseHas('product_rates', ['product_id' => $product->id, 'price' => '40.00', 'min_qty_periods' => 2]);
    }

    public function test_admin_can_delete_a_product_without_units(): void
    {
        $product = Product::factory()->create();
        ProductRate::factory()->for($product)->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertModelMissing($product);
        $this->assertDatabaseCount('product_rates', 0);
    }

    public function test_product_with_units_cannot_be_deleted(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('products.destroy', $unit->product))
            ->assertSessionHas('error');

        $this->assertModelExists($unit->product);
    }

    public function test_manager_cannot_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->delete(route('products.destroy', $product))
            ->assertForbidden();
    }
}
