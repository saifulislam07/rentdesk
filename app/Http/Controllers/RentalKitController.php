<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalKitRequest;
use App\Models\Kit;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('update', 'rental')]
class RentalKitController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    public function store(RentalKitRequest $request, Rental $rental): RedirectResponse
    {
        $this->rentals->addKit($rental, Kit::findOrFail($request->validated('kit_id')), $request->validated('qty'));

        return back()->with('status', __('Kit added.'));
    }

    public function destroy(Rental $rental, Kit $kit): RedirectResponse
    {
        $this->rentals->removeKit($rental, $kit);

        return back()->with('status', __('Kit removed.'));
    }
}
