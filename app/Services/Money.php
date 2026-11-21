<?php

namespace App\Services;

/**
 * Converts between decimal strings and integer cents so sums never suffer float drift.
 */
final class Money
{
    public static function toCents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
