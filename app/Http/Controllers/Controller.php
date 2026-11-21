<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Rows shown on a list page until the user picks another size.
     */
    public const DEFAULT_PER_PAGE = 10;

    /**
     * Page sizes a user can pick in the list footers.
     *
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    /**
     * Rows per page for a list: the size picked in the footer when it is one of the allowed ones.
     */
    protected function perPage(int $default = self::DEFAULT_PER_PAGE): int
    {
        $requested = request()->integer('per_page');

        return in_array($requested, self::PER_PAGE_OPTIONS, true) ? $requested : $default;
    }
}
