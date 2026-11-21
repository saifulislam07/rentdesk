<?php

namespace App\Policies;

class RentalPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'rentals';
    }
}
