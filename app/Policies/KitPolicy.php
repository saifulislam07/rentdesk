<?php

namespace App\Policies;

class KitPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'kits';
    }
}
