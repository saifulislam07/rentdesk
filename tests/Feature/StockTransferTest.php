<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Enums\TransferStatus;
use App\Enums\UnitStatus;
use App\Exceptions\InventoryException;
use App\Models\Location;
use App\Models\MaintenanceTask;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\AvailabilityChecker;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $transfers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transfers = app(TransferService::class);
    }

    public function test_sending_a_unit_puts_it_in_transit_and_out_of_circulation(): void
    {
        $unit = Unit::factory()->create();
        $destination = Location::factory()->create();

        $transfer = $this->transfers->send($unit, $destination);

        $this->assertSame(TransferStatus::InTransit, $transfer->status);
        $this->assertSame($unit->location_id, $transfer->from_location_id);
        $this->assertSame($destination->id, $transfer->to_location_id);
        $this->assertNotNull($transfer->sent_at);
        $this->assertSame(UnitStatus::InTransit, $unit->refresh()->status);
        $this->assertFalse(app(AvailabilityChecker::class)->isUnitAvailable($unit, now()->addDay(), now()->addDays(2)));
        $this->assertSame(0, Unit::rentable()->count());
    }

    public function test_receiving_moves_the_unit_and_returns_it_to_stock(): void
    {
        $unit = Unit::factory()->create();
        $destination = Location::factory()->create();
        $transfer = $this->transfers->send($unit, $destination);

        $this->transfers->receive($transfer);

        $unit->refresh();
        $this->assertSame($destination->id, $unit->location_id);
        $this->assertSame(UnitStatus::Available, $unit->status);
        $this->assertSame(TransferStatus::Received, $transfer->refresh()->status);
        $this->assertNotNull($transfer->received_at);
    }

    public function test_a_unit_with_an_open_maintenance_task_stays_blocked_on_arrival(): void
    {
        $unit = Unit::factory()->create();
        $transfer = $this->transfers->send($unit, Location::factory()->create());
        MaintenanceTask::factory()->for($unit)->create();

        $this->transfers->receive($transfer);

        $this->assertSame(UnitStatus::Maintenance, $unit->refresh()->status);
    }

    public function test_cancelling_leaves_the_unit_where_it_was(): void
    {
        $unit = Unit::factory()->create();
        $origin = $unit->location_id;
        $transfer = $this->transfers->send($unit, Location::factory()->create());

        $this->transfers->cancel($transfer);

        $unit->refresh();
        $this->assertSame($origin, $unit->location_id);
        $this->assertSame(UnitStatus::Available, $unit->status);
        $this->assertSame(TransferStatus::Cancelled, $transfer->refresh()->status);
        $this->assertNull($transfer->received_at);
    }

    public function test_only_available_units_can_be_sent(): void
    {
        foreach ([UnitStatus::Rented, UnitStatus::Maintenance, UnitStatus::Retired, UnitStatus::InTransit] as $status) {
            $unit = Unit::factory()->status($status)->create();

            try {
                $this->transfers->send($unit, Location::factory()->create());
                $this->fail("A {$status->value} unit should not be transferable.");
            } catch (InventoryException) {
                $this->assertSame($status, $unit->refresh()->status);
            }
        }

        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_a_unit_cannot_be_sent_to_where_it_already_is(): void
    {
        $unit = Unit::factory()->create();

        $this->expectException(InventoryException::class);

        $this->transfers->send($unit, $unit->location);
    }

    public function test_a_unit_with_upcoming_bookings_cannot_be_moved(): void
    {
        $unit = Unit::factory()->create();
        RentalItem::factory()->forUnit($unit, now()->addDays(3), now()->addDays(5))->create();

        $this->expectException(InventoryException::class);

        $this->transfers->send($unit, Location::factory()->create());
    }

    public function test_past_and_cancelled_bookings_do_not_block_a_transfer(): void
    {
        $unit = Unit::factory()->create();
        RentalItem::factory()->forUnit($unit, now()->subDays(5), now()->subDays(3))->create();
        RentalItem::factory()->forUnit($unit, now()->addDays(3), now()->addDays(5))->create([
            'rental_id' => Rental::factory()->status(RentalStatus::Cancelled),
        ]);

        $this->assertSame(TransferStatus::InTransit, $this->transfers->send($unit, Location::factory()->create())->status);
    }

    public function test_a_closed_transfer_cannot_be_received_or_cancelled_again(): void
    {
        $transfer = $this->transfers->send(Unit::factory()->create(), Location::factory()->create());
        $this->transfers->receive($transfer);

        foreach (['receive', 'cancel'] as $action) {
            try {
                $this->transfers->{$action}($transfer);
                $this->fail("A received transfer should not be {$action}d.");
            } catch (InventoryException) {
                $this->assertSame(TransferStatus::Received, $transfer->refresh()->status);
            }
        }
    }

    public function test_managers_can_run_a_transfer_over_http(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = Unit::factory()->create();
        $destination = Location::factory()->create();

        $this->actingAs($manager)->get(route('transfers.create', ['unit_id' => $unit->id]))->assertOk()->assertSee($unit->serial_no);
        $this->actingAs($manager)
            ->post(route('transfers.store'), ['unit_id' => $unit->id, 'to_location_id' => $destination->id])
            ->assertRedirect(route('transfers.index'));

        $transfer = StockTransfer::firstOrFail();
        $this->actingAs($manager)->get(route('transfers.index'))->assertOk()->assertSee('Receive');

        $this->actingAs($manager)->post(route('transfers.receive', $transfer))->assertSessionHas('status');
        $this->assertSame($destination->id, $unit->refresh()->location_id);
    }

    public function test_transfer_input_is_validated(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = Unit::factory()->create();
        $inactive = Location::factory()->inactive()->create();

        $this->actingAs($manager)->post(route('transfers.store'), [])->assertSessionHasErrors(['unit_id', 'to_location_id']);
        $this->actingAs($manager)
            ->post(route('transfers.store'), ['unit_id' => $unit->id, 'to_location_id' => $inactive->id])
            ->assertSessionHasErrors('to_location_id');
    }

    public function test_a_business_rule_failure_returns_to_the_form_with_the_message(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->from(route('transfers.create'))
            ->post(route('transfers.store'), ['unit_id' => $unit->id, 'to_location_id' => $unit->location_id])
            ->assertRedirect(route('transfers.create'))
            ->assertSessionHas('error', 'The unit is already at that location.');
    }

    public function test_staff_can_view_transfers_but_not_make_or_close_them(): void
    {
        $staff = $this->userWithRole('staff');
        $transfer = StockTransfer::factory()->create();

        $this->actingAs($staff)->get(route('transfers.index'))->assertOk();
        $this->actingAs($staff)->get(route('transfers.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('transfers.store'), [])->assertForbidden();
        $this->actingAs($staff)->post(route('transfers.receive', $transfer))->assertForbidden();
        $this->actingAs($staff)->post(route('transfers.cancel', $transfer))->assertForbidden();
    }

    public function test_the_unit_page_offers_a_transfer_only_when_it_is_available(): void
    {
        $manager = $this->userWithRole('manager');
        $available = Unit::factory()->create();
        $rented = Unit::factory()->status(UnitStatus::Rented)->create();

        $this->actingAs($manager)->get(route('units.show', $available))->assertSee('Transfer to another location');
        $this->actingAs($manager)->get(route('units.show', $rented))->assertDontSee('Transfer to another location');
    }
}
