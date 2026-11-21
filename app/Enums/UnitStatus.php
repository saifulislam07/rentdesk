<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Rented = 'rented';
    case Maintenance = 'maintenance';
    case Retired = 'retired';
    case InTransit = 'in_transit';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Reserved => __('Reserved'),
            self::Rented => __('Rented'),
            self::Maintenance => __('Maintenance'),
            self::Retired => __('Retired'),
            self::InTransit => __('In transit'),
        };
    }

    /**
     * Whether a unit in this status may be booked. Reserved and rented describe the
     * present; future availability is decided by the rental items.
     */
    public function isRentable(): bool
    {
        return ! in_array($this, [self::Maintenance, self::Retired, self::InTransit], true);
    }

    /**
     * The badge colour used to display this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Reserved => 'blue',
            self::Rented => 'yellow',
            self::Maintenance => 'red',
            self::Retired => 'gray',
            self::InTransit => 'blue',
        };
    }
}
