<?php

namespace App\Http\Controllers;

use App\Enums\ProductTracking;
use App\Http\Requests\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

#[Authorize('viewAny', Product::class, only: ['index'])]
#[Authorize('view', 'product', only: ['show'])]
#[Authorize('create', Product::class, only: ['create', 'store'])]
#[Authorize('update', 'product', only: ['edit', 'update'])]
#[Authorize('delete', 'product', only: ['destroy'])]
class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::with(['category', 'brand'])
                ->withCount('units')
                ->orderBy('name')
                ->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return view('products.form', [
            'product' => new Product(['tracking' => ProductTracking::Serial, 'buffer_minutes' => 0]),
            ...$this->formOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::create($request->safe()->except('rates'));
            $product->syncRates($request->validated('rates', []));

            return $product;
        });

        return to_route('products.show', $product)->with('status', __('Product created.'));
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'brand', 'rates', 'maintenancePlan']);

        return view('products.show', [
            'product' => $product,
            'units' => $product->units()->with('location')->orderBy('serial_no')->paginate($this->perPage()),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.form', [
            'product' => $product->load('rates'),
            ...$this->formOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->update($request->safe()->except('rates'));
            $product->syncRates($request->validated('rates', []));
        });

        return to_route('products.show', $product)->with('status', __('Product updated.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->units()->exists()) {
            return back()->with('error', __('This product still has units and cannot be deleted.'));
        }

        $product->delete();

        return to_route('products.index')->with('status', __('Product deleted.'));
    }

    /**
     * @return array{categories: Collection<int, Category>, brands: Collection<int, Brand>}
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }
}
