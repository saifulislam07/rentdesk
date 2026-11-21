<?php

namespace App\Enums;

enum BookingRequestStatus: string
{
    case New = 'new';
    case Converted = 'converted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Converted => __('Converted to quote'),
            self::Rejected => __('Rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'yellow',
            self::Converted => 'green',
            self::Rejected => 'gray',
        };
    }
}
