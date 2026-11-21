<?php

namespace App\Services;

use App\Enums\RentalStatus;
use App\Models\LateFee;
use App\Models\Rental;
use Carbon\CarbonInterface;

/**
 * Charges overdue rentals for every started day they are late.
 *
 * The daily charge is the rental's subtotal spread over its billed days, scaled by the
 * configured percentage. A rental is only late once it is past its end time plus the grace period.
 */
class LateFeeService
{
    /**
     * Calculate (or refresh) the fee for the moment given; the fee only ever grows with time.
     */
    public function calculate(Rental $rental, ?CarbonInterface $at = null): ?LateFee
    {
        $at ??= now();
        $minutesLate = $rental->end_at->diffInMinutes($at, false);

        if ($minutesLate <= config('rentdesk.late_fee_grace_minutes')) {
            return null;
        }

        $daysLate = (int) ceil($minutesLate / 1440);
        $billedDays = max(1, (int) ceil($rental->start_at->diffInMinutes($rental->end_at) / 1440));
        $dailyCents = Money::toCents($rental->subtotal) / $billedDays * (float) config('rentdesk.late_fee_percent') / 100;

        return LateFee::updateOrCreate(
            ['rental_id' => $rental->id],
            [
                'days_late' => $daysLate,
                'amount' => Money::format((int) round($dailyCents * $daysLate)),
                'calculated_at' => now(),
            ],
        );
    }

    /**
     * Refresh the fee of every rental that is out past its end time.
     *
     * @return int How many rentals were charged.
     */
    public function calculateAllOverdue(): int
    {
        $charged = 0;

        Rental::query()
            ->where('status', RentalStatus::Out)
            ->where('end_at', '<', now())
            ->each(function (Rental $rental) use (&$charged): void {
                if ($this->calculate($rental)) {
                    $charged++;
                }
            });

        return $charged;
    }
}
