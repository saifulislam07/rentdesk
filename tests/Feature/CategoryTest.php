<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_but_not_create_categories(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('categories.index'))->assertOk();
        $this->actingAs($staff)
            ->post(route('categories.store'), ['name' => 'Tools'])
            ->assertForbidden();
    }

    public function test_manager_can_create_a_category_with_generated_slug(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->post(route('categories.store'), ['name' => 'Power Tools'])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Power Tools', 'slug' => 'power-tools']);
    }

    public function test_generated_slugs_are_unique(): void
    {
        Category::factory()->create(['name' => 'Tools', 'slug' => 'tools']);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('categories.store'), ['name' => 'Tools']);

        $this->assertDatabaseHas('categories', ['name' => 'Tools', 'slug' => 'tools-2']);
    }

    public function test_manager_can_update_a_category(): void
    {
        $category = Category::factory()->create();
        $parent = Category::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('categories.update', $category), [
                'name' => 'Renamed',
                'slug' => 'renamed',
                'parent_id' => $parent->id,
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'renamed',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('categories.update', $category), [
                'name' => $category->name,
                'parent_id' => $category->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_admin_can_delete_an_empty_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertModelMissing($category);
    }

    public function test_manager_cannot_delete_a_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->delete(route('categories.destroy', $category))
            ->assertForbidden();
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('categories.destroy', $product->category))
            ->assertSessionHas('error');

        $this->assertModelExists($product->category);
    }
}
