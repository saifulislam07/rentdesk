<?php

namespace App\Console\Commands;

use App\Services\LateFeeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rentals:calculate-late-fees')]
#[Description('Calculate late fees for every rental that is out past its end time')]
class CalculateLateFees extends Command
{
    public function handle(LateFeeService $lateFees): int
    {
        $this->info(trans_choice(':count late fee calculated.|:count late fees calculated.', $lateFees->calculateAllOverdue()));

        return self::SUCCESS;
    }
}
