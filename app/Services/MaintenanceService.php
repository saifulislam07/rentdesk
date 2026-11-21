<?php

namespace App\Services;

use App\Enums\MaintenanceStatus;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Exceptions\InventoryException;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\RentalItem;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps units serviced: plans raise tasks, open tasks block the unit, finishing the last one frees it.
 */
class MaintenanceService
{
    /**
     * Raise a task for every unit whose plan says it is due.
     *
     * @return int Number of tasks created.
     */
    public function generateDueTasks(): int
    {
        $created = 0;

        MaintenancePlan::query()->with('product')->each(function (MaintenancePlan $plan) use (&$created): void {
            $plan->product->units()
                ->where('status', '!=', UnitStatus::Retired)
                ->each(function (Unit $unit) use ($plan, &$created): void {
                    if ($this->isDue($unit, $plan)) {
                        $this->open($unit, $plan);
                        $created++;
                    }
                });
        });

        return $created;
    }

    public function isDue(Unit $unit, MaintenancePlan $plan): bool
    {
        if ($unit->maintenanceTasks()->open()->exists()) {
            return false;
        }

        $baseline = $this->lastServiced($unit);

        if ($plan->every_days && $baseline->copy()->addDays($plan->every_days)->lessThanOrEqualTo(now())) {
            return true;
        }

        return $plan->every_rentals && $this->rentalsSince($unit, $baseline) >= $plan->every_rentals;
    }

    /**
     * Open a task and block the unit, unless it is out on rent, in which case it is blocked when it comes back.
     */
    public function open(Unit $unit, ?MaintenancePlan $plan = null, ?string $notes = null): MaintenanceTask
    {
        return DB::transaction(function () use ($unit, $plan, $notes): MaintenanceTask {
            $task = $unit->maintenanceTasks()->create([
                'plan_id' => $plan?->id,
                'due_at' => now(),
                'notes' => $notes,
            ]);

            if (in_array($unit->status, [UnitStatus::Available, UnitStatus::Reserved], true)) {
                $unit->update(['status' => UnitStatus::Maintenance]);
            }

            return $task;
        });
    }

    /**
     * @throws InventoryException
     */
    public function complete(MaintenanceTask $task, string|float|null $cost, ?string $notes): MaintenanceTask
    {
        return $this->close($task, MaintenanceStatus::Done, ['cost' => $cost, 'notes' => $notes ?? $task->notes, 'done_at' => now()]);
    }

    /**
     * @throws InventoryException
     */
    public function cancel(MaintenanceTask $task): MaintenanceTask
    {
        return $this->close($task, MaintenanceStatus::Cancelled, []);
    }

    /**
     * Put a blocked unit back in stock once nothing is left to do on it.
     */
    public function releaseIfClear(Unit $unit): void
    {
        if ($unit->status === UnitStatus::Maintenance && ! $unit->maintenanceTasks()->open()->exists()) {
            $unit->update(['status' => UnitStatus::Available]);
        }
    }

    /**
     * Blocking bookings still ahead for the unit, which a maintenance stop may disturb.
     */
    public function upcomingBookings(Unit $unit): int
    {
        return RentalItem::query()
            ->where('unit_id', $unit->id)
            ->where('end_at', '>', now())
            ->whereHas('rental', fn ($rental) => $rental->blocking())
            ->count();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function close(MaintenanceTask $task, MaintenanceStatus $status, array $attributes): MaintenanceTask
    {
        return DB::transaction(function () use ($task, $status, $attributes): MaintenanceTask {
            $task = MaintenanceTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($task->status !== MaintenanceStatus::Open) {
                throw new InventoryException(__('This task is already closed.'));
            }

            $task->update([...$attributes, 'status' => $status]);
            $this->releaseIfClear($task->unit()->lockForUpdate()->firstOrFail());

            return $task;
        });
    }

    private function lastServiced(Unit $unit): CarbonInterface
    {
        $lastDone = $unit->maintenanceTasks()->where('status', MaintenanceStatus::Done)->max('done_at');

        return $lastDone
            ? Carbon::parse($lastDone)
            : ($unit->purchased_at ?? $unit->created_at)->copy()->startOfDay();
    }

    private function rentalsSince(Unit $unit, CarbonInterface $since): int
    {
        return RentalItem::query()
            ->where('unit_id', $unit->id)
            ->whereHas('rental', fn ($rental) => $rental
                ->whereIn('status', [RentalStatus::Returned, RentalStatus::Closed])
                ->where('returned_at', '>', $since))
            ->distinct()
            ->count('rental_id');
    }
}
