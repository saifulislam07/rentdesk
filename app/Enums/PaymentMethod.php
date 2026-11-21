<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::BankTransfer => __('Bank transfer'),
            self::Other => __('Other'),
        };
    }
}
