<?php

namespace App\Http\Controllers;

use App\Enums\ProductTracking;
use App\Enums\RentalStatus;
use App\Http\Requests\StoreUnitsRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

#[Authorize('view', 'unit', only: ['show'])]
#[Authorize('create', Unit::class, only: ['create', 'store'])]
#[Authorize('update', 'unit', only: ['edit', 'update'])]
#[Authorize('delete', 'unit', only: ['destroy'])]
class UnitController extends Controller
{
    public function create(Product $product): View
    {
        return view('units.create', [
            'product' => $product,
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUnitsRequest $request, Product $product): RedirectResponse
    {
        $serials = $request->serialNumbers();
        $attributes = $request->safe()->except('serials');
        $attributes['quantity'] = $product->tracking === ProductTracking::Bulk
            ? ($attributes['quantity'] ?? 1)
            : 1;

        DB::transaction(function () use ($product, $serials, $attributes): void {
            foreach ($serials as $serial) {
                $product->units()->create([...$attributes, 'serial_no' => $serial]);
            }
        });

        return to_route('products.show', $product)->with(
            'status',
            trans_choice(':count unit added.|:count units added.', count($serials))
        );
    }

    public function show(Unit $unit): View
    {
        return view('units.show', [
            'unit' => $unit->load(['product', 'location']),
            'tasks' => $unit->maintenanceTasks()->latest('id')->limit(5)->get(),
            'currentRental' => Rental::query()
                ->where('status', RentalStatus::Out)
                ->whereHas('items', fn ($items) => $items->where('unit_id', $unit->id))
                ->with('customer')
                ->first(),
        ]);
    }

    public function edit(Unit $unit): View
    {
        return view('units.edit', [
            'unit' => $unit->load('product'),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        return to_route('products.show', $unit->product_id)->with('status', __('Unit updated.'));
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return to_route('products.show', $unit->product_id)->with('status', __('Unit deleted.'));
    }
}
