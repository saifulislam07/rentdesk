<?php

namespace App\Enums;

enum RatePeriod: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Hour => __('Hourly'),
            self::Day => __('Daily'),
            self::Week => __('Weekly'),
            self::Month => __('Monthly'),
        };
    }
}
