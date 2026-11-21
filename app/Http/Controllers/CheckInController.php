<?php

namespace App\Http\Controllers;

use App\Exceptions\RentalException;
use App\Http\Requests\CheckInRequest;
use App\Models\Rental;
use App\Services\HandoverService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('update', 'rental')]
class CheckInController extends Controller
{
    public function create(Rental $rental): View
    {
        if (! $rental->status->canCheckIn()) {
            throw new RentalException(__('Only rentals that are out can be checked in.'));
        }

        return view('handover.check-in', [
            'rental' => $rental->load(['customer', 'items.product', 'items.unit']),
        ]);
    }

    public function store(CheckInRequest $request, Rental $rental, HandoverService $handover): RedirectResponse
    {
        $handover->checkIn($rental, $request->user(), $request->validated('inspections'), $request->signatureImage());

        return to_route('rentals.show', $rental)->with('status', __('Rental checked in.'));
    }
}
