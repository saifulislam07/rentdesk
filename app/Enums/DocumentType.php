<?php

namespace App\Enums;

enum DocumentType: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';
    case DrivingLicense = 'driving_license';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => __('National ID'),
            self::Passport => __('Passport'),
            self::DrivingLicense => __('Driving licence'),
            self::Other => __('Other'),
        };
    }
}
