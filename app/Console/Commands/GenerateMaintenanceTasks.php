<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maintenance:generate-tasks')]
#[Description('Create maintenance tasks for units that are due under their product plan and block them')]
class GenerateMaintenanceTasks extends Command
{
    public function handle(MaintenanceService $maintenance): int
    {
        $this->info(trans_choice(':count maintenance task created.|:count maintenance tasks created.', $maintenance->generateDueTasks()));

        return self::SUCCESS;
    }
}
