<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettleDepositRequest;
use App\Models\Rental;
use App\Services\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('update', 'rental')]
class DepositSettlementController extends Controller
{
    public function store(SettleDepositRequest $request, Rental $rental, DepositService $deposits): RedirectResponse
    {
        $deposits->settle($rental, $request->validated('refund_amount'));

        return to_route('rentals.show', $rental)->with('status', __('Rental settled and closed.'));
    }
}
