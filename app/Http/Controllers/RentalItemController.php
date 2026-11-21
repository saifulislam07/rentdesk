<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalItemRequest;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RentalItemController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    public function store(RentalItemRequest $request, Rental $rental): RedirectResponse
    {
        Gate::authorize('update', $rental);

        $this->rentals->addItem($rental, Product::findOrFail($request->validated('product_id')), $request->validated('qty'));

        return back()->with('status', __('Item added.'));
    }

    public function destroy(RentalItem $item): RedirectResponse
    {
        $rental = $item->rental;

        Gate::authorize('update', $rental);

        $this->rentals->removeItem($rental, $item);

        return back()->with('status', __('Item removed.'));
    }
}
