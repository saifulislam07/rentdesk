<?php

namespace Tests\Feature;

use App\Enums\UnitStatus;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_add_units_but_can_view_them(): void
    {
        $staff = $this->userWithRole('staff');
        $unit = Unit::factory()->create();

        $this->actingAs($staff)->get(route('units.show', $unit))->assertOk();
        $this->actingAs($staff)->get(route('products.units.create', $unit->product))->assertForbidden();
    }

    public function test_manager_can_add_many_units_from_a_serial_list(): void
    {
        $product = Product::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('products.units.store', $product), [
                'serials' => "SN-1\nSN-2, SN-3\n\nSN-2",
                'location_id' => $location->id,
                'condition' => 'good',
            ])
            ->assertRedirect(route('products.show', $product));

        $this->assertSame(3, $product->units()->count());
        $this->assertDatabaseHas('units', ['product_id' => $product->id, 'serial_no' => 'SN-3', 'condition' => 'good']);
        $this->assertSame(3, $product->units()->distinct()->count('qr_code'));
    }

    public function test_duplicate_serials_are_rejected_and_nothing_is_created(): void
    {
        $existing = Unit::factory()->create(['serial_no' => 'SN-1']);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('products.units.store', $existing->product), [
                'serials' => "SN-1\nSN-2",
                'location_id' => $existing->location_id,
                'condition' => 'new',
            ])
            ->assertSessionHasErrors('serials');

        $this->assertSame(1, $existing->product->units()->count());
    }

    public function test_serial_tracked_products_always_get_a_quantity_of_one(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('products.units.store', $product), [
                'serials' => 'SN-1',
                'location_id' => Location::factory()->create()->id,
                'condition' => 'new',
                'quantity' => 50,
            ]);

        $this->assertDatabaseHas('units', ['serial_no' => 'SN-1', 'quantity' => 1]);
    }

    public function test_bulk_products_keep_the_submitted_quantity(): void
    {
        $product = Product::factory()->bulk()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('products.units.store', $product), [
                'serials' => 'LOT-1',
                'location_id' => Location::factory()->create()->id,
                'condition' => 'new',
                'quantity' => 50,
            ]);

        $this->assertDatabaseHas('units', ['serial_no' => 'LOT-1', 'quantity' => 50]);
    }

    public function test_manager_can_update_a_unit(): void
    {
        $unit = Unit::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('units.update', $unit), [
                'location_id' => $location->id,
                'serial_no' => $unit->serial_no,
                'condition' => 'poor',
                'status' => 'maintenance',
            ])
            ->assertRedirect(route('products.show', $unit->product_id));

        $unit->refresh();

        $this->assertSame(UnitStatus::Maintenance, $unit->status);
        $this->assertSame($location->id, $unit->location_id);
    }

    public function test_unit_serial_must_be_unique_within_its_product(): void
    {
        $unit = Unit::factory()->create();
        $other = Unit::factory()->for($unit->product)->create();

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('units.update', $unit), [
                'location_id' => $unit->location_id,
                'serial_no' => $other->serial_no,
                'condition' => 'good',
                'status' => 'available',
            ])
            ->assertSessionHasErrors('serial_no');
    }

    public function test_admin_can_delete_a_unit_but_manager_cannot(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->delete(route('units.destroy', $unit))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('units.destroy', $unit))
            ->assertRedirect(route('products.show', $unit->product_id));

        $this->assertModelMissing($unit);
    }

    public function test_scanning_a_qr_token_redirects_to_the_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('units.scan', $unit->qr_code))
            ->assertRedirect(route('units.show', $unit));
    }

    public function test_label_page_lists_only_selected_units(): void
    {
        $product = Product::factory()->create();
        $units = Unit::factory()->count(2)->for($product)->create();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('products.labels', ['product' => $product, 'units' => [$units[0]->id]]))
            ->assertOk()
            ->assertSee($units[0]->serial_no)
            ->assertDontSee($units[1]->serial_no);
    }
}
