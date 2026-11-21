<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductLabelController extends Controller
{
    /**
     * Printable QR label sheet for a product's units, optionally limited to the selected ones.
     */
    public function __invoke(Request $request, Product $product): View
    {
        Gate::authorize('viewAny', Unit::class);

        $selected = array_filter((array) $request->query('units'), 'is_numeric');

        return view('units.labels', [
            'product' => $product,
            'units' => $product->units()
                ->when($selected, fn ($query) => $query->whereKey($selected))
                ->orderBy('serial_no')
                ->get(),
        ]);
    }
}
