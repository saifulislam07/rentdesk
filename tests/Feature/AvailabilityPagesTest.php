<?php

namespace Tests\Feature;

use App\Enums\RatePeriod;
use App\Enums\RentalStatus;
use App\Models\ProductRate;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('availability'))->assertRedirect(route('login'));
        $this->get(route('calendar'))->assertRedirect(route('login'));
        $this->getJson(route('calendar.events'))->assertUnauthorized();
    }

    public function test_availability_form_renders_without_a_search(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('availability'))->assertOk();
    }

    public function test_availability_search_lists_free_units_and_a_price(): void
    {
        $free = Unit::factory()->create();
        $busy = Unit::factory()->for($free->product)->create();
        ProductRate::factory()->for($free->product)->create(['period' => RatePeriod::Day, 'price' => 40]);

        $start = CarbonImmutable::parse('2026-12-01 10:00', 'UTC');
        $end = $start->addDay();
        RentalItem::factory()->forUnit($busy, $start, $end)->create();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('availability', [
                'product_id' => $free->product_id,
                'start_at' => '2026-12-01T10:00',
                'end_at' => '2026-12-02T10:00',
            ]))
            ->assertOk()
            ->assertSee($free->serial_no)
            ->assertDontSee($busy->serial_no)
            ->assertSee('40.00');
    }

    public function test_availability_search_validates_the_period(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('availability', [
                'product_id' => $unit->product_id,
                'start_at' => '2026-12-02T10:00',
                'end_at' => '2026-12-01T10:00',
            ]))
            ->assertSessionHasErrors('end_at');
    }

    public function test_user_without_rental_permission_is_forbidden(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create()->givePermissionTo('products.view');

        $this->actingAs($user)->get(route('availability'))->assertForbidden();
        $this->actingAs($user)->get(route('calendar'))->assertForbidden();
    }

    public function test_calendar_feed_returns_only_blocking_items_in_the_window(): void
    {
        $unit = Unit::factory()->create();
        $start = CarbonImmutable::parse('2026-12-10 09:00', 'UTC');

        $visible = RentalItem::factory()->forUnit($unit, $start, $start->addDays(2))->create();
        RentalItem::factory()->forUnit($unit, $start->addDays(10), $start->addDays(11))->create();
        RentalItem::factory()->forUnit($unit, $start, $start->addDay())->create([
            'rental_id' => Rental::factory()->status(RentalStatus::Cancelled),
        ]);

        $this->actingAs($this->userWithRole('staff'))
            ->getJson(route('calendar.events', ['start' => '2026-12-01T00:00:00Z', 'end' => '2026-12-31T00:00:00Z']))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $visible->id]);

        $this->actingAs($this->userWithRole('staff'))
            ->getJson(route('calendar.events', ['start' => '2026-12-20T00:00:00Z', 'end' => '2026-12-31T00:00:00Z']))
            ->assertJsonCount(1);

        $this->actingAs($this->userWithRole('staff'))->get(route('calendar'))->assertOk();
    }
}
