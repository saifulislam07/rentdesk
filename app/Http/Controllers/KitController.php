<?php

namespace App\Http\Controllers;

use App\Http\Requests\KitRequest;
use App\Models\Kit;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

#[Authorize('viewAny', Kit::class, only: ['index'])]
#[Authorize('create', Kit::class, only: ['create', 'store'])]
#[Authorize('update', 'kit', only: ['edit', 'update'])]
#[Authorize('delete', 'kit', only: ['destroy'])]
class KitController extends Controller
{
    public function index(): View
    {
        return view('kits.index', [
            'kits' => Kit::with('items.product:id,name')->orderBy('name')->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return view('kits.form', ['kit' => new Kit, 'products' => $this->products(), 'rows' => old('items', [['product_id' => '', 'qty' => 1]])]);
    }

    public function store(KitRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $kit = Kit::create($request->safe()->only(['name', 'price']));
            $kit->items()->createMany($request->validated('items'));
        });

        return to_route('kits.index')->with('status', __('Kit created.'));
    }

    public function edit(Kit $kit): View
    {
        return view('kits.form', [
            'kit' => $kit,
            'products' => $this->products(),
            'rows' => old('items', $kit->items()->get(['product_id', 'qty'])->toArray()),
        ]);
    }

    public function update(KitRequest $request, Kit $kit): RedirectResponse
    {
        DB::transaction(function () use ($request, $kit): void {
            $kit->update($request->safe()->only(['name', 'price']));
            $kit->items()->delete();
            $kit->items()->createMany($request->validated('items'));
        });

        return to_route('kits.index')->with('status', __('Kit updated.'));
    }

    public function destroy(Kit $kit): RedirectResponse
    {
        $kit->delete();

        return to_route('kits.index')->with('status', __('Kit deleted.'));
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::orderBy('name')->get(['id', 'name', 'sku']);
    }
}
