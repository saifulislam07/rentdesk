<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
        };
    }

    /**
     * Drafts and sent invoices can still be corrected; paid and void ones are final.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }

    /**
     * The badge colour used to display this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'blue',
            self::Paid => 'green',
            self::Void => 'red',
        };
    }
}
