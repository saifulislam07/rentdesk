<?php

namespace App\Policies;

class MaintenanceTaskPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'maintenance';
    }
}
