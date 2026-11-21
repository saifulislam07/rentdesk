<?php

namespace App\Policies;

class InvoicePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'invoices';
    }
}
