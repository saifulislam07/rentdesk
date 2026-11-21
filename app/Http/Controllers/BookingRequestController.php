<?php

namespace App\Http\Controllers;

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Services\BookingRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\Rule;

class BookingRequestController extends Controller
{
    public function __construct(private readonly BookingRequestService $requests) {}

    #[Authorize('viewAny', BookingRequest::class)]
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(BookingRequestStatus::class)]])['status'] ?? BookingRequestStatus::New->value;

        return view('booking-requests.index', [
            'status' => $status,
            'requests' => BookingRequest::query()
                ->with(['product:id,name', 'location:id,name', 'rental:id,number'])
                ->where('status', $status)
                ->latest('id')
                ->paginate($this->perPage())
                ->withQueryString(),
        ]);
    }

    #[Authorize('update', 'booking')]
    public function convert(BookingRequest $booking): RedirectResponse
    {
        $rental = $this->requests->convert($booking);

        return to_route('rentals.show', $rental)->with('status', __('Quote created from the request. Review it and confirm.'));
    }

    #[Authorize('update', 'booking')]
    public function reject(BookingRequest $booking): RedirectResponse
    {
        $this->requests->reject($booking);

        return back()->with('status', __('Request rejected.'));
    }
}
