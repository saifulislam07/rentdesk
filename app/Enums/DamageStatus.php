<?php

namespace App\Enums;

enum DamageStatus: string
{
    case Pending = 'pending';
    case Charged = 'charged';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Charged => __('Charged'),
            self::Waived => __('Waived'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Charged => 'red',
            self::Waived => 'gray',
        };
    }
}
