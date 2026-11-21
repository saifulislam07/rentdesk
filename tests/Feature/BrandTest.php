<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_but_not_create_brands(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('brands.index'))->assertOk();
        $this->actingAs($staff)->post(route('brands.store'), ['name' => 'Makita'])->assertForbidden();
    }

    public function test_manager_can_create_a_brand_with_a_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('brands.store'), [
                'name' => 'Makita',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect(route('brands.index'));

        $brand = Brand::firstWhere('slug', 'makita');

        $this->assertNotNull($brand);
        Storage::disk('public')->assertExists($brand->logo);
    }

    public function test_logo_must_be_an_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('brands.store'), [
                'name' => 'Makita',
                'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_manager_can_update_a_brand_and_replace_its_logo(): void
    {
        Storage::fake('public');
        $brand = Brand::factory()->create(['logo' => 'brands/old.png']);
        Storage::disk('public')->put('brands/old.png', 'old');

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('brands.update', $brand), [
                'name' => 'Renamed',
                'slug' => 'renamed',
                'logo' => UploadedFile::fake()->image('new.png'),
            ])
            ->assertRedirect(route('brands.index'));

        $brand->refresh();

        $this->assertSame('Renamed', $brand->name);
        Storage::disk('public')->assertMissing('brands/old.png');
        Storage::disk('public')->assertExists($brand->logo);
    }

    public function test_admin_can_delete_a_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('brands.destroy', $brand))
            ->assertRedirect(route('brands.index'));

        $this->assertModelMissing($brand);
    }

    public function test_manager_cannot_delete_a_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->delete(route('brands.destroy', $brand))
            ->assertForbidden();
    }
}
