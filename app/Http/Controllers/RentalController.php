<?php

namespace App\Http\Controllers;

use App\Enums\RentalStatus;
use App\Http\Requests\RentalRequest;
use App\Models\Customer;
use App\Models\Kit;
use App\Models\Location;
use App\Models\Product;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\Rule;

#[Authorize('viewAny', Rental::class, only: ['index'])]
#[Authorize('view', 'rental', only: ['show'])]
#[Authorize('create', Rental::class, only: ['create', 'store'])]
#[Authorize('update', 'rental', only: ['edit', 'update'])]
class RentalController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(RentalStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return view('rentals.index', [
            'rentals' => Rental::query()
                ->with(['customer:id,name', 'location:id,name'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('number', 'like', '%'.addcslashes($term, '%_\\').'%'))
                ->latest('start_at')
                ->paginate($this->perPage())
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('rentals.form', [
            'rental' => new Rental(['customer_id' => $request->integer('customer_id') ?: null, 'discount' => 0]),
            ...$this->formOptions(),
        ]);
    }

    public function store(RentalRequest $request): RedirectResponse
    {
        $rental = $this->rentals->createQuote($request->validated());

        return to_route('rentals.show', $rental)->with('status', __('Quote created. Add items to it next.'));
    }

    public function show(Rental $rental): View
    {
        $rental->load([
            'customer',
            'location',
            'items.product',
            'items.kit',
            'items.unit',
            'invoices',
            'deposit',
            'lateFee',
            'damages.unit',
            'inspections' => fn ($query) => $query->with(['unit', 'photos', 'inspector'])->orderBy('id'),
        ]);

        return view('rentals.show', [
            'rental' => $rental,
            'products' => $rental->status->isEditable() ? Product::orderBy('name')->get(['id', 'name', 'sku', 'tracking']) : collect(),
            'kits' => $rental->status->isEditable() ? Kit::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function edit(Rental $rental): View
    {
        abort_unless($rental->status->isEditable(), 403, __('Only quotes can be changed.'));

        return view('rentals.form', ['rental' => $rental, ...$this->formOptions()]);
    }

    public function update(RentalRequest $request, Rental $rental): RedirectResponse
    {
        $this->rentals->updateQuote($rental, $request->validated());

        return to_route('rentals.show', $rental)->with('status', __('Quote updated.'));
    }

    /**
     * @return array{customers: Collection<int, Customer>, locations: Collection<int, Location>}
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::notBlacklisted()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
