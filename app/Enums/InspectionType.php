<?php

namespace App\Enums;

enum InspectionType: string
{
    case Out = 'out';
    case In = 'in';

    public function label(): string
    {
        return match ($this) {
            self::Out => __('Check-out'),
            self::In => __('Check-in'),
        };
    }
}
