<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_every_inventory_page(): void
    {
        $admin = $this->userWithRole('admin');
        $unit = Unit::factory()->create();
        $product = $unit->product;
        ProductRate::factory()->for($product)->create();

        $pages = [
            route('dashboard'),
            route('locations.index'),
            route('locations.create'),
            route('locations.edit', $unit->location),
            route('categories.index'),
            route('categories.create'),
            route('categories.edit', $product->category),
            route('brands.index'),
            route('brands.create'),
            route('brands.edit', Brand::factory()->create()),
            route('products.index'),
            route('products.create'),
            route('products.edit', $product),
            route('products.show', $product),
            route('products.units.create', $product),
            route('units.show', $unit),
            route('units.edit', $unit),
            route('products.labels', $product),
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_sidebar_only_lists_pages_the_user_may_view(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create()->givePermissionTo('products.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee(route('products.index'))
            ->assertDontSee(route('brands.index'));
    }

    public function test_seeded_demo_data_is_consistent(): void
    {
        Storage::fake('local');
        $this->seed();

        $this->assertGreaterThanOrEqual(9, Product::count());
        $this->assertGreaterThanOrEqual(40, Unit::count());
        $this->assertSame(Unit::count(), Unit::distinct()->count('qr_code'));
        $this->assertGreaterThan(0, Category::count() + Location::count());
    }
}
