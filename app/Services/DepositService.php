<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\RentalStatus;
use App\Exceptions\RentalException;
use App\Models\Deposit;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;

class DepositService
{
    /**
     * The refund that leaves the customer paying for damage and late fees, never below zero.
     */
    public function suggestedRefund(Rental $rental): string
    {
        $held = Money::toCents($rental->deposit?->amount ?? 0);
        $charged = Money::toCents($rental->chargedDamageTotal()) + Money::toCents($rental->lateFee?->amount ?? 0);

        return Money::format(max($held - $charged, 0));
    }

    /**
     * Settle the deposit and close a returned rental. A rental without a deposit is simply closed.
     *
     * @param  string|float|null  $refund  Amount handed back to the customer; the rest is kept.
     *
     * @throws RentalException
     */
    public function settle(Rental $rental, string|float|null $refund): Rental
    {
        return DB::transaction(function () use ($rental, $refund): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! $rental->status->canSettle()) {
                throw new RentalException(__('Only returned rentals can be settled.'));
            }

            $deposit = $rental->deposit()->lockForUpdate()->first();

            if ($deposit) {
                $this->settleDeposit($deposit, Money::toCents($refund ?? 0));
            }

            $rental->update(['status' => RentalStatus::Closed]);

            return $rental;
        });
    }

    private function settleDeposit(Deposit $deposit, int $refundCents): void
    {
        $heldCents = Money::toCents($deposit->amount);

        if (! $deposit->isHeld()) {
            throw new RentalException(__('This deposit has already been settled.'));
        }

        if ($refundCents > $heldCents) {
            throw new RentalException(__('The refund cannot be more than the deposit of :amount.', ['amount' => $deposit->amount]));
        }

        $deposit->update([
            'status' => match (true) {
                $refundCents === $heldCents => DepositStatus::Refunded,
                $refundCents === 0 => DepositStatus::Forfeited,
                default => DepositStatus::Partial,
            },
            'refunded_amount' => Money::format($refundCents),
            'settled_at' => now(),
        ]);
    }
}
