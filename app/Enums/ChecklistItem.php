<?php

namespace App\Enums;

enum ChecklistItem: string
{
    case Clean = 'clean';
    case Accessories = 'accessories';
    case Working = 'working';

    public function label(): string
    {
        return match ($this) {
            self::Clean => __('Clean and undamaged'),
            self::Accessories => __('All accessories included'),
            self::Working => __('Tested and fully working'),
        };
    }
}
