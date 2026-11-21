<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;

#[Authorize('viewAny', Customer::class, only: ['index'])]
#[Authorize('view', 'customer', only: ['show'])]
#[Authorize('create', Customer::class, only: ['create', 'store'])]
#[Authorize('update', 'customer', only: ['edit', 'update'])]
#[Authorize('delete', 'customer', only: ['destroy'])]
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        return view('customers.index', [
            'customers' => Customer::query()
                ->search($request->query('q'))
                ->withCount('rentals')
                ->orderBy('name')
                ->paginate($this->perPage())
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return to_route('customers.show', $customer)->with('status', __('Customer created.'));
    }

    public function show(Customer $customer): View
    {
        return view('customers.show', [
            'customer' => $customer->load('documents'),
            'rentals' => $customer->rentals()->latest('start_at')->paginate($this->perPage()),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return to_route('customers.show', $customer)->with('status', __('Customer updated.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->rentals()->exists()) {
            return back()->with('error', __('This customer has rentals and cannot be deleted.'));
        }

        Storage::disk('local')->deleteDirectory('customer-documents/'.$customer->id);
        $customer->delete();

        return to_route('customers.index')->with('status', __('Customer deleted.'));
    }
}
