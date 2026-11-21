<?php

namespace App\Enums;

enum RentalStatus: string
{
    case Quote = 'quote';
    case Reserved = 'reserved';
    case Out = 'out';
    case Returned = 'returned';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Quote => __('Quote'),
            self::Reserved => __('Reserved'),
            self::Out => __('Out'),
            self::Returned => __('Returned'),
            self::Closed => __('Closed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * The badge colour used to display this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Quote => 'gray',
            self::Reserved => 'blue',
            self::Out => 'yellow',
            self::Returned => 'green',
            self::Closed => 'green',
            self::Cancelled => 'red',
        };
    }

    /**
     * Whether items of a rental in this status keep their units unavailable to others.
     */
    public function blocksAvailability(): bool
    {
        return match ($this) {
            self::Reserved, self::Out => true,
            default => false,
        };
    }

    /**
     * Only quotes may have their dates, discount and items changed.
     */
    public function isEditable(): bool
    {
        return $this === self::Quote;
    }

    public function canCheckOut(): bool
    {
        return $this === self::Reserved;
    }

    public function canCheckIn(): bool
    {
        return $this === self::Out;
    }

    public function canSettle(): bool
    {
        return $this === self::Returned;
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Quote, self::Reserved], true);
    }

    /**
     * Statuses that block availability.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->blocksAvailability()));
    }
}
