<?php

namespace App\Enums;

enum TransferStatus: string
{
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InTransit => __('In transit'),
            self::Received => __('Received'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InTransit => 'blue',
            self::Received => 'green',
            self::Cancelled => 'gray',
        };
    }
}
