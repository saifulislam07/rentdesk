<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('viewAny', Location::class, only: ['index'])]
#[Authorize('create', Location::class, only: ['create', 'store'])]
#[Authorize('update', 'location', only: ['edit', 'update'])]
#[Authorize('delete', 'location', only: ['destroy'])]
class LocationController extends Controller
{
    public function index(): View
    {
        return view('locations.index', [
            'locations' => Location::withCount('units')->orderBy('name')->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return view('locations.form', ['location' => new Location(['is_active' => true])]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return to_route('locations.index')->with('status', __('Location created.'));
    }

    public function edit(Location $location): View
    {
        return view('locations.form', ['location' => $location]);
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return to_route('locations.index')->with('status', __('Location updated.'));
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->units()->exists()) {
            return back()->with('error', __('This location still has units and cannot be deleted.'));
        }

        $location->delete();

        return to_route('locations.index')->with('status', __('Location deleted.'));
    }
}
