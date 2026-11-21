<?php

namespace App\Console\Commands;

use App\Enums\RentalStatus;
use App\Mail\OverdueReminderMail;
use App\Models\Rental;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('rentals:send-overdue-reminders')]
#[Description('Email customers whose rentals are overdue, at most once a day per rental')]
class SendOverdueReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Rental::query()
            ->with('customer')
            ->where('status', RentalStatus::Out)
            ->where('end_at', '<', now())
            ->where(fn ($query) => $query
                ->whereNull('overdue_reminded_at')
                ->orWhere('overdue_reminded_at', '<', now()->startOfDay()))
            ->each(function (Rental $rental) use (&$sent): void {
                if (! $rental->customer?->email) {
                    return;
                }

                Mail::to($rental->customer->email)->send(new OverdueReminderMail($rental));
                $rental->update(['overdue_reminded_at' => now()]);
                $sent++;
            });

        $this->info(trans_choice(':count reminder sent.|:count reminders sent.', $sent));

        return self::SUCCESS;
    }
}
