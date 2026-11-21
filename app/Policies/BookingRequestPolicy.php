<?php

namespace App\Policies;

class BookingRequestPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'bookings';
    }
}
