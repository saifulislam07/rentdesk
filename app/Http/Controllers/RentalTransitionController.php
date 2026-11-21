<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('update', 'rental')]
class RentalTransitionController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    public function confirm(Rental $rental): RedirectResponse
    {
        $this->rentals->confirm($rental);

        return back()->with('status', __('Rental confirmed and units reserved.'));
    }

    public function cancel(Rental $rental): RedirectResponse
    {
        $this->rentals->cancel($rental);

        return back()->with('status', __('Rental cancelled.'));
    }
}
