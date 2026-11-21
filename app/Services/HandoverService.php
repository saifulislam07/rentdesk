<?php

namespace App\Services;

use App\Enums\DamageStatus;
use App\Enums\DepositStatus;
use App\Enums\InspectionType;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Exceptions\RentalException;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hands units to the customer (check-out) and takes them back (check-in).
 *
 * Both steps inspect every serial-tracked unit, store photos and the customer's signature on
 * the private disk, update the units and move the rental on, all in one transaction. Files
 * are removed again if the transaction fails.
 *
 * Inspection input is keyed by rental item id:
 * array{condition: string, meter_reading?: ?int, checklist?: list<string>, notes?: ?string,
 *       photos?: list<UploadedFile>, needs_maintenance?: bool,
 *       damage?: array{description?: ?string, cost?: ?string, charged?: ?string}}
 */
class HandoverService
{
    public function __construct(
        private readonly LateFeeService $lateFees,
        private readonly MaintenanceService $maintenance,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $inspections
     * @param  array{amount?: string|float|null, method?: string|null}  $deposit
     *
     * @throws RentalException
     */
    public function checkOut(Rental $rental, User $inspector, array $inspections, string $signature, array $deposit = []): Rental
    {
        return $this->transaction(function (array &$files) use ($rental, $inspector, $inspections, $signature, $deposit): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! $rental->status->canCheckOut()) {
                throw new RentalException(__('Only reserved rentals can be checked out.'));
            }

            $this->collectDeposit($rental, $deposit);

            $signaturePath = $this->storeSignature($signature, $files);

            foreach ($this->unitItems($rental) as $item) {
                $unit = $item->unit()->lockForUpdate()->firstOrFail();

                if (! in_array($unit->status, [UnitStatus::Available, UnitStatus::Reserved], true)) {
                    throw new RentalException(__('Unit :serial is not available (:status).', [
                        'serial' => $unit->serial_no,
                        'status' => $unit->status->label(),
                    ]));
                }

                $data = $this->dataFor($item, $inspections);
                $this->inspect($rental, $item, InspectionType::Out, $data, $inspector, $signaturePath, $files);

                $unit->update([
                    'status' => UnitStatus::Rented,
                    'condition' => $data['condition'],
                    'meter_reading' => $data['meter_reading'] ?? $unit->meter_reading,
                ]);
            }

            $rental->update(['status' => RentalStatus::Out]);

            return $rental;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $inspections
     *
     * @throws RentalException
     */
    public function checkIn(Rental $rental, User $inspector, array $inspections, ?string $signature = null): Rental
    {
        return $this->transaction(function (array &$files) use ($rental, $inspector, $inspections, $signature): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! $rental->status->canCheckIn()) {
                throw new RentalException(__('Only rentals that are out can be checked in.'));
            }

            $signaturePath = $signature ? $this->storeSignature($signature, $files) : null;

            foreach ($this->unitItems($rental) as $item) {
                $unit = $item->unit()->lockForUpdate()->firstOrFail();
                $data = $this->dataFor($item, $inspections);

                $inspection = $this->inspect($rental, $item, InspectionType::In, $data, $inspector, $signaturePath, $files);
                $this->recordDamage($rental, $item, $inspection, $data['damage'] ?? []);

                if (! empty($data['needs_maintenance'])) {
                    $this->maintenance->open($unit, notes: __('Flagged at check-in of :number.', ['number' => $rental->number]));
                }

                $unit->update([
                    'status' => $unit->maintenanceTasks()->open()->exists() ? UnitStatus::Maintenance : UnitStatus::Available,
                    'condition' => $data['condition'],
                    'meter_reading' => $data['meter_reading'] ?? $unit->meter_reading,
                ]);
            }

            $rental->update(['status' => RentalStatus::Returned, 'returned_at' => now()]);
            $this->lateFees->calculate($rental, $rental->returned_at);

            return $rental;
        });
    }

    /**
     * Run the work in a transaction and delete any files it wrote if it fails.
     *
     * @template T
     *
     * @param  callable(array<int, string>&): T  $work
     * @return T
     */
    private function transaction(callable $work): mixed
    {
        $files = [];

        try {
            return DB::transaction(function () use ($work, &$files) {
                return $work($files);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($files);

            throw $exception;
        }
    }

    /**
     * @param  array{amount?: string|float|null, method?: string|null}  $deposit
     */
    private function collectDeposit(Rental $rental, array $deposit): void
    {
        $received = Money::toCents($deposit['amount'] ?? 0);

        if ($received < Money::toCents($rental->deposit_required)) {
            throw new RentalException(__('The required deposit of :amount must be collected.', [
                'amount' => $rental->currency.' '.$rental->deposit_required,
            ]));
        }

        if ($received > 0) {
            $rental->deposit()->create([
                'amount' => Money::format($received),
                'method' => $deposit['method'],
                'status' => DepositStatus::Held,
            ]);
        }
    }

    /**
     * Serial-tracked items, which are the ones with a unit to inspect.
     *
     * @return Collection<int, RentalItem>
     */
    private function unitItems(Rental $rental): Collection
    {
        return $rental->items()->whereNotNull('unit_id')->with('unit')->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $inspections
     * @return array<string, mixed>
     */
    private function dataFor(RentalItem $item, array $inspections): array
    {
        return $inspections[$item->id]
            ?? throw new RentalException(__('Inspect every unit: :serial is missing.', ['serial' => $item->unit->serial_no]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $files
     */
    private function inspect(
        Rental $rental,
        RentalItem $item,
        InspectionType $type,
        array $data,
        User $inspector,
        ?string $signaturePath,
        array &$files,
    ): Inspection {
        $inspection = $rental->inspections()->create([
            'unit_id' => $item->unit_id,
            'type' => $type,
            'condition' => $data['condition'],
            'meter_reading' => $data['meter_reading'] ?? null,
            'checklist' => $data['checklist'] ?? [],
            'notes' => $data['notes'] ?? null,
            'signature_path' => $signaturePath,
            'inspected_by' => $inspector->id,
        ]);

        /** @var UploadedFile $photo */
        foreach ($data['photos'] ?? [] as $photo) {
            $path = $photo->store('inspections/'.$inspection->id, 'local');
            $files[] = $path;
            $inspection->photos()->create(['path' => $path]);
        }

        return $inspection;
    }

    /**
     * @param  array{description?: ?string, cost?: ?string, charged?: ?string}  $damage
     */
    private function recordDamage(Rental $rental, RentalItem $item, Inspection $inspection, array $damage): void
    {
        if (blank($damage['description'] ?? null)) {
            return;
        }

        $charged = Money::toCents($damage['charged'] ?? 0);

        $rental->damages()->create([
            'unit_id' => $item->unit_id,
            'inspection_id' => $inspection->id,
            'description' => $damage['description'],
            'cost' => Money::format(Money::toCents($damage['cost'] ?? 0)),
            'charged_amount' => Money::format($charged),
            'status' => $charged > 0 ? DamageStatus::Charged : DamageStatus::Pending,
        ]);
    }

    /**
     * @param  array<int, string>  $files
     */
    private function storeSignature(string $png, array &$files): string
    {
        $path = 'signatures/'.Str::uuid().'.png';

        Storage::disk('local')->put($path, $png);
        $files[] = $path;

        return $path;
    }
}
