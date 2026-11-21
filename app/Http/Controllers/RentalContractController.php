<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\DocumentRenderer;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('view', 'rental')]
class RentalContractController extends Controller
{
    public function __invoke(Rental $rental, DocumentRenderer $documents): Response
    {
        return $documents->contract($rental)->download($rental->number.'.pdf');
    }
}
