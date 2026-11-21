<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;

class UnitScanController extends Controller
{
    /**
     * Resolve a scanned QR token to its unit.
     */
    public function __invoke(Unit $unit): RedirectResponse
    {
        return to_route('units.show', $unit);
    }
}
