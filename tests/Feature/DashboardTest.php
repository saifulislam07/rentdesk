<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MaintenanceTask;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_without_rental_access_only_see_a_welcome(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create(['name' => 'Newcomer']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, Newcomer.')
            ->assertDontSee('Out on rent');
    }

    public function test_the_dashboard_summarises_today(): void
    {
        Carbon::setTestNow('2026-12-10 12:00');
        Unit::factory()->count(3)->create();
        Unit::factory()->status(UnitStatus::Maintenance)->create();
        Rental::factory()->status(RentalStatus::Out)->create(['number' => 'RN-LATE', 'start_at' => '2026-12-05 10:00', 'end_at' => '2026-12-08 10:00']);
        Rental::factory()->status(RentalStatus::Out)->create(['number' => 'RN-DUE', 'start_at' => '2026-12-08 10:00', 'end_at' => '2026-12-10 17:00']);
        Rental::factory()->status(RentalStatus::Reserved)->create(['number' => 'RN-PICKUP', 'start_at' => '2026-12-10 15:00', 'end_at' => '2026-12-12 15:00']);
        Rental::factory()->status(RentalStatus::Reserved)->create(['number' => 'RN-TOMORROW', 'start_at' => '2026-12-11 15:00', 'end_at' => '2026-12-12 15:00']);
        MaintenanceTask::factory()->create();

        $page = $this->actingAs($this->userWithRole('staff'))->get(route('dashboard'));

        $page->assertOk()
            ->assertSee('RN-LATE')
            ->assertSee('3 days late')
            ->assertSee('RN-DUE')
            ->assertSee('RN-PICKUP')
            ->assertSee('Open maintenance');
    }

    public function test_the_fleet_counts_every_status(): void
    {
        Unit::factory()->count(2)->create();
        Unit::factory()->status(UnitStatus::Rented)->create();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Available', '2', 'Reserved', '0', 'Rented', '1']);
    }

    public function test_revenue_is_shown_only_to_those_who_may_see_reports(): void
    {
        Carbon::setTestNow('2026-12-10 12:00');
        Rental::factory()->status(RentalStatus::Returned)->create(['start_at' => '2026-12-02 09:00', 'end_at' => '2026-12-04 09:00', 'total' => '250.00']);

        $this->actingAs($this->userWithRole('manager'))->get(route('dashboard'))->assertSee('Revenue this month')->assertSee('USD 250.00');
        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard'))->assertDontSee('Revenue this month');
    }

    public function test_the_dashboard_shows_totals_upcoming_pickups_and_recent_rentals(): void
    {
        Carbon::setTestNow('2026-12-10 12:00');
        Unit::factory()->count(3)->create();
        Unit::factory()->status(UnitStatus::Rented)->create();
        Customer::factory()->count(2)->create();
        Rental::factory()->status(RentalStatus::Reserved)->create(['number' => 'RN-NEXT-WEEK', 'start_at' => '2026-12-14 10:00', 'end_at' => '2026-12-15 10:00']);
        Rental::factory()->status(RentalStatus::Reserved)->create(['number' => 'RN-LATER', 'start_at' => '2026-12-30 10:00', 'end_at' => '2026-12-31 10:00']);

        $page = $this->actingAs($this->userWithRole('staff'))->get(route('dashboard'));

        $page->assertOk()
            ->assertSee('Fleet in use')
            ->assertSee('25%')
            ->assertSee('Reserved rentals')
            ->assertSee('Pick-ups in the next 7 days')
            ->assertSee('Recent rentals')
            ->assertSee('New rental')
            // Both are listed as recent rentals, but only the one starting within a week is a pick-up.
            ->assertSee('RN-LATER');
        $this->assertCount(1, $page->viewData('stats')['upcoming']);
        $this->assertSame(Customer::count(), $page->viewData('stats')['customers']);
    }

    public function test_unpaid_invoices_and_the_revenue_chart_follow_permissions(): void
    {
        Carbon::setTestNow('2026-12-10 12:00');
        Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'total' => '100.00', 'due_at' => '2026-12-01']);
        Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'total' => '50.50', 'due_at' => '2026-12-20']);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'total' => '999.00']);
        Rental::factory()->status(RentalStatus::Returned)->create(['start_at' => '2026-10-02 09:00', 'end_at' => '2026-10-04 09:00', 'total' => '400.00']);

        $manager = $this->actingAs($this->userWithRole('manager'))->get(route('dashboard'));

        $manager->assertSee('Unpaid invoices')->assertSee('USD 150.50')->assertSee('1 past due')->assertSee('Revenue, last 6 months');
        $this->assertSame('400.00', collect($manager->viewData('stats')['months'])->firstWhere('label', 'Oct')['total']);

        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard'))->assertDontSee('Revenue, last 6 months');
    }

    public function test_booking_requests_are_counted_only_when_the_public_page_is_on(): void
    {
        BookingRequest::factory()->count(2)->create();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('dashboard'))->assertDontSee('New booking requests');

        config(['rentdesk.public_booking' => true]);
        $this->actingAs($staff)->get(route('dashboard'))->assertSee('New booking requests');
    }
}
