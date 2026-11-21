<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\RentalStatus;
use App\Enums\TransferStatus;
use App\Enums\UnitStatus;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Kit;
use App\Models\MaintenanceTask;
use App\Models\Rental;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_there_is_a_demo_sign_in_for_each_role(): void
    {
        foreach (['admin', 'manager', 'staff'] as $role) {
            $user = User::firstWhere('email', $role === 'admin' ? 'admin@gmail.com' : "{$role}@rentdesk.test");

            $this->assertNotNull($user);
            $this->assertTrue($user->hasRole($role));
            $this->assertTrue(password_verify('111111', $user->password));
        }
    }

    public function test_rentals_exist_in_every_stage_of_their_life(): void
    {
        $statuses = Rental::pluck('status')->map->value->unique()->sort()->values()->all();

        $this->assertSame(['closed', 'out', 'quote', 'reserved'], $statuses);
    }

    public function test_the_overdue_rental_has_a_late_fee_and_the_dashboard_shows_it(): void
    {
        $overdue = Rental::where('status', RentalStatus::Out)->where('end_at', '<', now()->subDay())->firstOrFail();

        $this->assertGreaterThanOrEqual(2, $overdue->lateFee->days_late);
        $this->assertGreaterThan(0, (float) $overdue->lateFee->amount);

        $this->actingAs(User::firstWhere('email', 'manager@rentdesk.test'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($overdue->number)
            ->assertSee('Revenue this month');
    }

    public function test_the_closed_rental_was_returned_on_time_with_damage_and_a_partial_refund(): void
    {
        $closed = Rental::where('status', RentalStatus::Closed)->with(['deposit', 'damages', 'lateFee'])->firstOrFail();

        $this->assertNull($closed->lateFee);
        $this->assertSame('20.00', $closed->chargedDamageTotal());
        $this->assertSame(DepositStatus::Partial, $closed->deposit->status);
        // Angle Grinder deposit is 36; 20 of damage is deducted.
        $this->assertSame('36.00', $closed->deposit->amount);
        $this->assertSame('16.00', $closed->deposit->refunded_amount);
        $this->assertTrue($closed->returned_at->lessThan(now()->subDays(5)));
    }

    public function test_units_match_the_rentals_that_hold_them(): void
    {
        foreach (Rental::where('status', RentalStatus::Out)->with('items.unit')->get() as $rental) {
            $this->assertNotEmpty($rental->items);

            foreach ($rental->items as $item) {
                $this->assertSame(UnitStatus::Rented, $item->unit->status);
            }
        }

        foreach (Rental::where('status', RentalStatus::Reserved)->with('items')->get() as $rental) {
            $this->assertNull($rental->items->firstWhere('unit_id', null), "{$rental->number} has an item without a unit");
        }
    }

    public function test_the_sample_business_has_the_supporting_records(): void
    {
        $this->assertSame(2, Kit::count());
        $this->assertSame(2, BookingRequest::where('status', 'new')->count());
        $this->assertSame(1, Customer::where('is_blacklisted', true)->count());
        $this->assertSame(1, MaintenanceTask::open()->count());
        $this->assertSame(1, StockTransfer::where('status', TransferStatus::InTransit)->count());
        $this->assertSame(1, Unit::where('status', UnitStatus::InTransit)->count());
        $this->assertSame(1, Unit::where('status', UnitStatus::Maintenance)->count());
    }

    public function test_the_photo_kit_is_priced_at_its_flat_rate(): void
    {
        $rental = Rental::whereHas('items', fn ($items) => $items->whereNotNull('kit_id'))->firstOrFail();

        // 110 a day for two days.
        $this->assertSame('220.00', $rental->subtotal);
    }

    public function test_every_demo_page_renders_for_the_admin(): void
    {
        $admin = User::firstWhere('email', 'admin@gmail.com');
        $rental = Rental::where('status', RentalStatus::Closed)->firstOrFail();

        foreach ([
            route('dashboard'), route('rentals.index'), route('rentals.show', $rental), route('reports.index'),
            route('calendar'), route('maintenance.index'), route('transfers.index'), route('kits.index'), route('customers.index'),
        ] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }
}
