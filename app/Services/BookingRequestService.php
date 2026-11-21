<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\CustomerType;
use App\Exceptions\RentalException;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;

/**
 * Turns a public booking request into a quote for staff to confirm.
 */
class BookingRequestService
{
    public function __construct(private readonly RentalService $rentals) {}

    /**
     * Find or create the customer by email, then open a quote holding the requested product.
     *
     * @throws RentalException When the request was already handled, the customer is blacklisted or the product cannot be priced.
     */
    public function convert(BookingRequest $request): Rental
    {
        return DB::transaction(function () use ($request): Rental {
            $request = BookingRequest::query()->with('product')->lockForUpdate()->findOrFail($request->id);

            if (! $request->isNew()) {
                throw new RentalException(__('This request has already been handled.'));
            }

            $customer = Customer::firstOrCreate(
                ['email' => $request->email],
                ['type' => CustomerType::Person, 'name' => $request->name, 'phone' => $request->phone],
            );

            $rental = $this->rentals->createQuote([
                'customer_id' => $customer->id,
                'location_id' => $request->location_id,
                'start_at' => $request->start_at,
                'end_at' => $request->end_at,
                'notes' => $request->message,
            ]);
            $this->rentals->addItem($rental, $request->product, $request->qty);

            $request->update(['status' => BookingRequestStatus::Converted, 'rental_id' => $rental->id]);

            return $rental;
        });
    }

    /**
     * @throws RentalException
     */
    public function reject(BookingRequest $request): BookingRequest
    {
        if (! $request->isNew()) {
            throw new RentalException(__('This request has already been handled.'));
        }

        $request->update(['status' => BookingRequestStatus::Rejected]);

        return $request;
    }
}
