<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_show_the_range_and_let_the_user_pick_the_page_size(): void
    {
        Brand::factory()->count(12)->create();

        $this->actingAs($this->userWithRole('staff'))->get(route('brands.index'))
            ->assertOk()
            ->assertSee('Showing 1 to 10 of 12 results')
            ->assertSee('name="per_page"', false);
    }

    public function test_the_page_size_is_honoured_only_for_the_allowed_values(): void
    {
        Brand::factory()->count(12)->create();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('brands.index', ['per_page' => 25]))->assertSee('Showing 1 to 12 of 12 results');
        $this->actingAs($staff)->get(route('brands.index', ['per_page' => 7]))->assertSee('Showing 1 to 10 of 12 results');
    }

    public function test_page_links_keep_the_filters_and_the_page_size(): void
    {
        Customer::factory()->count(12)->create(['name' => 'Findable Person']);

        $this->actingAs($this->userWithRole('staff'))->get(route('customers.index', ['q' => 'Findable', 'per_page' => 10]))
            ->assertSee('q=Findable', false)
            ->assertSee('per_page=10', false)
            ->assertSee('page=2', false);
    }

    public function test_flash_messages_are_handed_to_the_toaster(): void
    {
        $this->actingAs($this->userWithRole('manager'))->withSession(['status' => 'Saved it.', 'error' => 'Broke it.'])
            ->get(route('dashboard'))
            ->assertSee('data-toast="success" data-message="Saved it."', false)
            ->assertSee('data-toast="error" data-message="Broke it."', false);
    }

    public function test_destructive_forms_ask_for_confirmation_with_the_dialog(): void
    {
        Brand::factory()->create();

        $this->actingAs($this->userWithRole('admin'))->get(route('brands.index'))
            ->assertSee('data-confirm="Are you sure?"', false)
            ->assertDontSee('onsubmit', false);
    }

    public function test_pages_tell_the_scripts_the_date_format_and_language(): void
    {
        config(['rentdesk.date_format' => 'd/m/Y', 'rentdesk.time_format' => 'h:i A']);

        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard'))
            ->assertSee('window.rentdesk', false)
            ->assertSee('h:i K', false);
    }
}
