<?php

namespace App\Services;

use App\Enums\TransferStatus;
use App\Enums\UnitStatus;
use App\Exceptions\InventoryException;
use App\Models\Location;
use App\Models\RentalItem;
use App\Models\StockTransfer;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Moves units between locations. A unit in transit cannot be booked or rented.
 */
class TransferService
{
    /**
     * @throws InventoryException
     */
    public function send(Unit $unit, Location $destination): StockTransfer
    {
        return DB::transaction(function () use ($unit, $destination): StockTransfer {
            $unit = Unit::query()->lockForUpdate()->findOrFail($unit->id);

            if ($unit->status !== UnitStatus::Available) {
                throw new InventoryException(__('Only available units can be transferred.'));
            }

            if ($unit->location_id === $destination->id) {
                throw new InventoryException(__('The unit is already at that location.'));
            }

            $hasUpcomingBookings = RentalItem::query()
                ->where('unit_id', $unit->id)
                ->where('end_at', '>', now())
                ->whereHas('rental', fn ($rental) => $rental->blocking())
                ->exists();

            if ($hasUpcomingBookings) {
                throw new InventoryException(__('This unit has upcoming bookings and cannot be moved.'));
            }

            $transfer = StockTransfer::create([
                'unit_id' => $unit->id,
                'from_location_id' => $unit->location_id,
                'to_location_id' => $destination->id,
                'status' => TransferStatus::InTransit,
                'sent_at' => now(),
            ]);

            $unit->update(['status' => UnitStatus::InTransit]);

            return $transfer;
        });
    }

    /**
     * @throws InventoryException
     */
    public function receive(StockTransfer $transfer): StockTransfer
    {
        return $this->finish($transfer, TransferStatus::Received, function (Unit $unit, StockTransfer $transfer): void {
            $unit->update([
                'location_id' => $transfer->to_location_id,
                'status' => $unit->maintenanceTasks()->open()->exists() ? UnitStatus::Maintenance : UnitStatus::Available,
            ]);
        });
    }

    /**
     * @throws InventoryException
     */
    public function cancel(StockTransfer $transfer): StockTransfer
    {
        return $this->finish($transfer, TransferStatus::Cancelled, function (Unit $unit): void {
            $unit->update(['status' => UnitStatus::Available]);
        });
    }

    /**
     * @param  callable(Unit, StockTransfer): void  $updateUnit
     */
    private function finish(StockTransfer $transfer, TransferStatus $status, callable $updateUnit): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $status, $updateUnit): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== TransferStatus::InTransit) {
                throw new InventoryException(__('This transfer is already closed.'));
            }

            $updateUnit(Unit::query()->lockForUpdate()->findOrFail($transfer->unit_id), $transfer);

            $transfer->update([
                'status' => $status,
                'received_at' => $status === TransferStatus::Received ? now() : null,
            ]);

            return $transfer;
        });
    }
}
