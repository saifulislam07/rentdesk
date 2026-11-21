<?php

namespace App\Policies;

class StockTransferPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'transfers';
    }
}
