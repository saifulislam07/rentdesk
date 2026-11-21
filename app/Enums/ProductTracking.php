<?php

namespace App\Enums;

enum ProductTracking: string
{
    case Serial = 'serial';
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Serial => __('Serial (per unit)'),
            self::Bulk => __('Bulk (quantity)'),
        };
    }
}
