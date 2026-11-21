<?php

namespace App\Policies;

class LocationPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'locations';
    }
}
