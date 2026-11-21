<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;

#[Authorize('viewAny', Brand::class, only: ['index'])]
#[Authorize('create', Brand::class, only: ['create', 'store'])]
#[Authorize('update', 'brand', only: ['edit', 'update'])]
#[Authorize('delete', 'brand', only: ['destroy'])]
class BrandController extends Controller
{
    public function index(): View
    {
        return view('brands.index', [
            'brands' => Brand::withCount('products')->orderBy('name')->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return view('brands.form', ['brand' => new Brand]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        Brand::create($this->attributes($request));

        return to_route('brands.index')->with('status', __('Brand created.'));
    }

    public function edit(Brand $brand): View
    {
        return view('brands.form', ['brand' => $brand]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $attributes = $this->attributes($request);

        if (isset($attributes['logo']) && $brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->update($attributes);

        return to_route('brands.index')->with('status', __('Brand updated.'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->delete();

        return to_route('brands.index')->with('status', __('Brand deleted.'));
    }

    /**
     * Validated attributes with an uploaded logo replaced by its stored path.
     *
     * @return array<string, mixed>
     */
    private function attributes(BrandRequest $request): array
    {
        $attributes = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            $attributes['logo'] = $request->file('logo')->store('brands', 'public');
        }

        return $attributes;
    }
}
