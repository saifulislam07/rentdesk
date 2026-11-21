<?php

namespace App\Enums;

enum DepositStatus: string
{
    case Held = 'held';
    case Refunded = 'refunded';
    case Partial = 'partial';
    case Forfeited = 'forfeited';

    public function label(): string
    {
        return match ($this) {
            self::Held => __('Held'),
            self::Refunded => __('Refunded'),
            self::Partial => __('Partially refunded'),
            self::Forfeited => __('Forfeited'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Held => 'blue',
            self::Refunded => 'green',
            self::Partial => 'yellow',
            self::Forfeited => 'red',
        };
    }
}
