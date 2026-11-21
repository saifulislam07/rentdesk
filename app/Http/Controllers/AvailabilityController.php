<?php

namespace App\Http\Controllers;

use App\Enums\ProductTracking;
use App\Http\Requests\AvailabilityRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\Rental;
use App\Services\AvailabilityChecker;
use App\Services\PriceCalculator;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('viewAny', Rental::class)]
class AvailabilityController extends Controller
{
    public function __invoke(
        AvailabilityRequest $request,
        AvailabilityChecker $availability,
        PriceCalculator $prices,
    ): View {
        $data = [
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
            'result' => null,
        ];

        if (! $request->isSearch()) {
            return view('availability.index', $data);
        }

        $product = Product::with('rates')->findOrFail($request->validated('product_id'));
        $start = $request->startAt();
        $end = $request->endAt();
        $locationId = $request->validated('location_id') ?: null;

        try {
            $quote = $prices->calculate($product, $start, $end);
        } catch (DomainException) {
            $quote = null;
        }

        $data['result'] = [
            'product' => $product,
            'quantity' => $availability->availableQuantity($product, $start, $end, $locationId),
            'units' => $product->tracking === ProductTracking::Serial
                ? $availability->availableUnits($product, $start, $end, $locationId)
                : collect(),
            'quote' => $quote,
        ];

        return view('availability.index', $data);
    }
}
