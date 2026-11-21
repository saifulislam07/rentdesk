<?php

namespace App\Enums;

enum CustomerType: string
{
    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Person => __('Person'),
            self::Company => __('Company'),
        };
    }
}
