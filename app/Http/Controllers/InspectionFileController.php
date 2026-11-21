<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\InspectionPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves inspection photos and signatures from private storage to staff who may view the rental.
 */
class InspectionFileController extends Controller
{
    public function photo(InspectionPhoto $photo): Response
    {
        Gate::authorize('view', $photo->inspection->rental);

        return Storage::disk('local')->response($photo->path);
    }

    public function signature(Inspection $inspection): Response
    {
        Gate::authorize('view', $inspection->rental);

        abort_unless($inspection->signature_path, 404);

        return Storage::disk('local')->response($inspection->signature_path);
    }
}
