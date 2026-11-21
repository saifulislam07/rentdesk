<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Done => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'yellow',
            self::Done => 'green',
            self::Cancelled => 'gray',
        };
    }
}
