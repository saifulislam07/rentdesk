<?php

namespace App\Enums;

enum UnitCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Good => __('Good'),
            self::Fair => __('Fair'),
            self::Poor => __('Poor'),
        };
    }

    /**
     * The badge colour used to display this condition.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'green',
            self::Good => 'blue',
            self::Fair => 'yellow',
            self::Poor => 'red',
        };
    }
}
