<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Http\Requests\StockTransferRequest;
use App\Models\Location;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\TransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('viewAny', StockTransfer::class, only: ['index'])]
#[Authorize('create', StockTransfer::class, only: ['create', 'store'])]
#[Authorize('update', 'transfer', only: ['receive', 'cancel'])]
class StockTransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function index(): View
    {
        return view('transfers.index', [
            'transfers' => StockTransfer::with(['unit.product', 'fromLocation', 'toLocation'])->latest('id')->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return view('transfers.create', [
            'units' => Unit::with(['product:id,name', 'location:id,name'])
                ->where('status', UnitStatus::Available)
                ->orderBy('product_id')
                ->orderBy('serial_no')
                ->get(),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StockTransferRequest $request): RedirectResponse
    {
        $this->transfers->send(
            Unit::findOrFail($request->validated('unit_id')),
            Location::findOrFail($request->validated('to_location_id')),
        );

        return to_route('transfers.index')->with('status', __('Transfer started. The unit is in transit.'));
    }

    public function receive(StockTransfer $transfer): RedirectResponse
    {
        $this->transfers->receive($transfer);

        return back()->with('status', __('Unit received.'));
    }

    public function cancel(StockTransfer $transfer): RedirectResponse
    {
        $this->transfers->cancel($transfer);

        return back()->with('status', __('Transfer cancelled.'));
    }
}
