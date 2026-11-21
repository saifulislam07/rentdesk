<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenancePlanRequest;
use App\Models\MaintenanceTask;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('update', MaintenanceTask::class)]
class MaintenancePlanController extends Controller
{
    public function update(MaintenancePlanRequest $request, Product $product): RedirectResponse
    {
        $product->maintenancePlan()->updateOrCreate([], [
            'every_days' => $request->validated('every_days'),
            'every_rentals' => $request->validated('every_rentals'),
        ]);

        return back()->with('status', __('Maintenance plan saved.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->maintenancePlan()->delete();

        return back()->with('status', __('Maintenance plan removed.'));
    }
}
