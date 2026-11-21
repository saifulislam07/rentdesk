<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('locations.index'))->assertRedirect(route('login'));
    }

    public function test_staff_can_view_but_not_create_locations(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('locations.index'))->assertOk();
        $this->actingAs($staff)->get(route('locations.create'))->assertForbidden();
        $this->actingAs($staff)
            ->post(route('locations.store'), ['name' => 'Depot', 'code' => 'DEP'])
            ->assertForbidden();
    }

    public function test_manager_can_create_a_location(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->post(route('locations.store'), [
                'name' => 'Depot',
                'code' => 'dep',
                'is_active' => '1',
            ])
            ->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('locations', ['name' => 'Depot', 'code' => 'DEP', 'is_active' => true]);
    }

    public function test_location_code_must_be_unique(): void
    {
        Location::factory()->create(['code' => 'MAIN']);

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('locations.store'), ['name' => 'Other', 'code' => 'MAIN'])
            ->assertSessionHasErrors('code');
    }

    public function test_manager_can_update_a_location(): void
    {
        $location = Location::factory()->create(['code' => 'OLD']);

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('locations.update', $location), ['name' => 'Renamed', 'code' => 'OLD'])
            ->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Renamed', 'is_active' => false]);
    }

    public function test_admin_can_delete_an_empty_location(): void
    {
        $location = Location::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('locations.destroy', $location))
            ->assertRedirect(route('locations.index'));

        $this->assertModelMissing($location);
    }

    public function test_manager_cannot_delete_a_location(): void
    {
        $location = Location::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->delete(route('locations.destroy', $location))
            ->assertForbidden();

        $this->assertModelExists($location);
    }

    public function test_location_with_units_cannot_be_deleted(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('locations.destroy', $unit->location))
            ->assertSessionHas('error');

        $this->assertModelExists($unit->location);
    }
}
