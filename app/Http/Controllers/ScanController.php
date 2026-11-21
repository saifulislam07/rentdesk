<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('viewAny', Unit::class)]
class ScanController extends Controller
{
    /**
     * Phone-camera QR scanner that jumps to the scanned unit.
     */
    public function __invoke(): View
    {
        return view('scan');
    }
}
