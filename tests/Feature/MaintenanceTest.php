<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Exceptions\InventoryException;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\HandoverService;
use App\Services\MaintenanceService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesReservedRentals;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use CreatesReservedRentals;
    use RefreshDatabase;

    private MaintenanceService $maintenance;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->maintenance = app(MaintenanceService::class);
    }

    /**
     * @param  array<string, mixed>  $planOverrides
     */
    private function unitWithPlan(array $planOverrides = [], string $purchased = '-100 days'): Unit
    {
        $product = Product::factory()->create();
        MaintenancePlan::factory()->for($product)->create($planOverrides);

        return Unit::factory()->for($product)->create(['purchased_at' => now()->modify($purchased)]);
    }

    public function test_a_unit_past_its_day_interval_gets_a_task_and_is_blocked(): void
    {
        $unit = $this->unitWithPlan(['every_days' => 90]);

        $this->assertSame(1, $this->maintenance->generateDueTasks());

        $task = $unit->maintenanceTasks()->firstOrFail();
        $this->assertSame(MaintenanceStatus::Open, $task->status);
        $this->assertNotNull($task->plan_id);
        $this->assertSame(UnitStatus::Maintenance, $unit->refresh()->status);
    }

    public function test_a_unit_inside_its_interval_is_left_alone(): void
    {
        $unit = $this->unitWithPlan(['every_days' => 90], '-30 days');

        $this->assertSame(0, $this->maintenance->generateDueTasks());
        $this->assertSame(UnitStatus::Available, $unit->refresh()->status);
    }

    public function test_the_interval_restarts_from_the_last_completed_service(): void
    {
        $unit = $this->unitWithPlan(['every_days' => 90]);
        MaintenanceTask::factory()->done()->for($unit)->create(['done_at' => now()->subDays(10)]);

        $this->assertSame(0, $this->maintenance->generateDueTasks());
    }

    public function test_the_rental_count_triggers_a_task(): void
    {
        $unit = $this->unitWithPlan(['every_days' => null, 'every_rentals' => 2], '-10 days');

        foreach ([3, 2] as $daysAgo) {
            RentalItem::factory()->create([
                'rental_id' => Rental::factory()->status(RentalStatus::Returned)->create(['returned_at' => now()->subDays($daysAgo)]),
                'product_id' => $unit->product_id,
                'unit_id' => $unit->id,
            ]);
        }

        $this->assertSame(1, $this->maintenance->generateDueTasks());
    }

    public function test_rentals_before_the_last_service_do_not_count(): void
    {
        $unit = $this->unitWithPlan(['every_days' => null, 'every_rentals' => 1], '-10 days');
        RentalItem::factory()->create([
            'rental_id' => Rental::factory()->status(RentalStatus::Returned)->create(['returned_at' => now()->subDays(8)]),
            'product_id' => $unit->product_id,
            'unit_id' => $unit->id,
        ]);
        MaintenanceTask::factory()->done()->for($unit)->create(['done_at' => now()->subDays(5)]);

        $this->assertSame(0, $this->maintenance->generateDueTasks());
    }

    public function test_an_open_task_prevents_duplicates_and_retired_units_are_skipped(): void
    {
        $unit = $this->unitWithPlan();
        Unit::factory()->for($unit->product)->status(UnitStatus::Retired)->create(['purchased_at' => now()->subYear()]);

        $this->assertSame(1, $this->maintenance->generateDueTasks());
        $this->assertSame(0, $this->maintenance->generateDueTasks());
        $this->assertSame(1, MaintenanceTask::count());
    }

    public function test_the_scheduled_command_reports_what_it_created(): void
    {
        $this->unitWithPlan();

        $this->artisan('maintenance:generate-tasks')->expectsOutput('1 maintenance task created.')->assertSuccessful();
    }

    public function test_a_unit_on_rent_is_blocked_only_when_it_comes_back(): void
    {
        $rental = $this->reservedRental(1);
        $handover = app(HandoverService::class);
        $handover->checkOut($rental, User::factory()->create(), $this->inspectionsFor($rental), $this->signatureBytes(), ['amount' => '10', 'method' => 'cash']);
        $unit = $rental->items()->first()->unit;

        $this->maintenance->open($unit->refresh());
        $this->assertSame(UnitStatus::Rented, $unit->refresh()->status);

        $handover->checkIn($rental, User::factory()->create(), $this->inspectionsFor($rental));

        $this->assertSame(UnitStatus::Maintenance, $unit->refresh()->status);
    }

    public function test_flagging_a_unit_at_check_in_opens_a_task_that_can_be_closed(): void
    {
        $rental = $this->reservedRental(1);
        $handover = app(HandoverService::class);
        $handover->checkOut($rental, User::factory()->create(), $this->inspectionsFor($rental), $this->signatureBytes(), ['amount' => '10', 'method' => 'cash']);

        $handover->checkIn($rental, User::factory()->create(), $this->inspectionsFor($rental, ['needs_maintenance' => '1']));

        $unit = $rental->items()->first()->unit;
        $task = $unit->maintenanceTasks()->firstOrFail();
        $this->assertSame(UnitStatus::Maintenance, $unit->status);
        $this->assertStringContainsString($rental->number, $task->notes);

        $this->maintenance->complete($task, '35.50', 'Replaced the housing');

        $this->assertSame(UnitStatus::Available, $unit->refresh()->status);
    }

    public function test_completing_records_cost_and_time_and_frees_the_unit(): void
    {
        $unit = Unit::factory()->create();
        $task = $this->maintenance->open($unit, notes: 'Oil change');
        $this->assertSame(UnitStatus::Maintenance, $unit->refresh()->status);

        $this->maintenance->complete($task, '42.00', null);

        $task->refresh();
        $this->assertSame(MaintenanceStatus::Done, $task->status);
        $this->assertSame('42.00', $task->cost);
        $this->assertSame('Oil change', $task->notes);
        $this->assertNotNull($task->done_at);
        $this->assertSame(UnitStatus::Available, $unit->refresh()->status);
    }

    public function test_the_unit_stays_blocked_while_another_task_is_open(): void
    {
        $unit = Unit::factory()->create();
        $first = $this->maintenance->open($unit);
        $this->maintenance->open($unit->refresh());

        $this->maintenance->complete($first, null, null);
        $this->assertSame(UnitStatus::Maintenance, $unit->refresh()->status);

        $this->maintenance->cancel($unit->maintenanceTasks()->open()->first());
        $this->assertSame(UnitStatus::Available, $unit->refresh()->status);
    }

    public function test_a_closed_task_cannot_be_closed_again(): void
    {
        $task = $this->maintenance->open(Unit::factory()->create());
        $this->maintenance->cancel($task);

        $this->expectException(InventoryException::class);

        $this->maintenance->complete($task, null, null);
    }

    public function test_upcoming_bookings_are_counted(): void
    {
        $rental = $this->reservedRental(1);
        $unit = $rental->items()->first()->unit;

        $rental->items()->update(['start_at' => now()->subDays(3), 'end_at' => now()->subDay()]);
        $this->assertSame(0, $this->maintenance->upcomingBookings($unit));

        $rental->items()->update(['end_at' => now()->addDay()]);
        $this->assertSame(1, $this->maintenance->upcomingBookings($unit));

        $rental->update(['status' => RentalStatus::Cancelled]);
        $this->assertSame(0, $this->maintenance->upcomingBookings($unit));
    }

    public function test_the_task_list_filters_by_status(): void
    {
        $open = MaintenanceTask::factory()->create(['notes' => 'Open job']);
        MaintenanceTask::factory()->done()->create(['notes' => 'Finished job']);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('maintenance.index'))->assertOk()->assertSee('Open job')->assertDontSee('Finished job');
        $this->actingAs($staff)->get(route('maintenance.index', ['status' => 'done']))->assertSee('Finished job')->assertDontSee('Open job');
        $this->actingAs($staff)->get(route('maintenance.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
        $this->assertNotNull($open);
    }

    public function test_managers_can_schedule_complete_and_cancel_tasks(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = Unit::factory()->create();

        $this->actingAs($manager)->post(route('units.maintenance.store', $unit), ['notes' => 'Blade is loose'])->assertSessionHas('status');
        $task = $unit->maintenanceTasks()->firstOrFail();
        $this->assertSame(UnitStatus::Maintenance, $unit->refresh()->status);

        $this->actingAs($manager)->post(route('maintenance.complete', $task), ['cost' => '12.00', 'notes' => 'Tightened'])->assertSessionHas('status');
        $this->assertSame(MaintenanceStatus::Done, $task->refresh()->status);

        $second = $this->maintenance->open($unit->refresh());
        $this->actingAs($manager)->post(route('maintenance.cancel', $second))->assertSessionHas('status');
        $this->assertSame(MaintenanceStatus::Cancelled, $second->refresh()->status);
    }

    public function test_completing_a_closed_task_returns_to_the_page_with_the_message(): void
    {
        $task = MaintenanceTask::factory()->done()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->from(route('maintenance.index'))
            ->post(route('maintenance.complete', $task))
            ->assertRedirect(route('maintenance.index'))
            ->assertSessionHas('error', 'This task is already closed.');
    }

    public function test_task_input_is_validated(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = Unit::factory()->create();

        $this->actingAs($manager)->post(route('units.maintenance.store', $unit), ['notes' => ''])->assertSessionHasErrors('notes');
        $this->actingAs($manager)
            ->post(route('maintenance.complete', MaintenanceTask::factory()->create()), ['cost' => '-5'])
            ->assertSessionHasErrors('cost');
    }

    public function test_staff_can_view_but_not_change_maintenance(): void
    {
        $staff = $this->userWithRole('staff');
        $unit = Unit::factory()->create();
        $task = MaintenanceTask::factory()->create();

        $this->actingAs($staff)->post(route('units.maintenance.store', $unit), ['notes' => 'x'])->assertForbidden();
        $this->actingAs($staff)->post(route('maintenance.complete', $task))->assertForbidden();
        $this->actingAs($staff)->post(route('maintenance.cancel', $task))->assertForbidden();
        $this->actingAs($staff)->put(route('products.maintenance-plan.update', $unit->product), ['every_days' => 30])->assertForbidden();
        $this->actingAs($staff)->get(route('products.show', $unit->product))->assertOk()->assertSee('No plan set.');
    }

    public function test_users_without_maintenance_access_are_forbidden(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())->get(route('maintenance.index'))->assertForbidden();
    }

    public function test_a_plan_can_be_saved_updated_and_removed_from_the_product_page(): void
    {
        $manager = $this->userWithRole('manager');
        $product = Product::factory()->create();

        $this->actingAs($manager)->put(route('products.maintenance-plan.update', $product), ['every_days' => 60, 'every_rentals' => ''])->assertSessionHas('status');
        $this->assertSame(60, $product->maintenancePlan()->first()->every_days);

        $this->actingAs($manager)->put(route('products.maintenance-plan.update', $product), ['every_rentals' => 5])->assertSessionHas('status');
        $this->assertDatabaseCount('maintenance_plans', 1);
        $this->assertSame(5, $product->maintenancePlan()->first()->every_rentals);

        $this->actingAs($manager)->get(route('products.show', $product))->assertOk()->assertSee('Save plan');

        $this->actingAs($manager)->delete(route('products.maintenance-plan.destroy', $product))->assertSessionHas('status');
        $this->assertDatabaseCount('maintenance_plans', 0);
    }

    public function test_a_plan_needs_at_least_one_limit(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->put(route('products.maintenance-plan.update', Product::factory()->create()), [])
            ->assertSessionHasErrors(['every_days', 'every_rentals']);
    }

    public function test_the_unit_page_lists_its_maintenance_history(): void
    {
        $unit = Unit::factory()->create();
        MaintenanceTask::factory()->for($unit)->create(['notes' => 'Replace the belt']);

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('units.show', $unit))
            ->assertOk()
            ->assertSee('Replace the belt')
            ->assertSee('Block for maintenance');
    }
}
