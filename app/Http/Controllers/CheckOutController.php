<?php

namespace App\Http\Controllers;

use App\Exceptions\RentalException;
use App\Http\Requests\CheckOutRequest;
use App\Models\Rental;
use App\Services\HandoverService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('update', 'rental')]
class CheckOutController extends Controller
{
    public function create(Rental $rental): View
    {
        if (! $rental->status->canCheckOut()) {
            throw new RentalException(__('Only reserved rentals can be checked out.'));
        }

        return view('handover.check-out', [
            'rental' => $rental->load(['customer', 'items.product', 'items.unit']),
        ]);
    }

    public function store(CheckOutRequest $request, Rental $rental, HandoverService $handover): RedirectResponse
    {
        $handover->checkOut(
            $rental,
            $request->user(),
            $request->validated('inspections'),
            $request->signatureImage(),
            ['amount' => $request->validated('deposit_amount'), 'method' => $request->validated('deposit_method')],
        );

        return to_route('rentals.show', $rental)->with('status', __('Rental checked out.'));
    }
}
