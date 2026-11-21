<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicBookingRequest;
use App\Models\BookingRequest;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The optional public page where customers ask for a booking. Nothing is reserved: staff turn requests into quotes.
 */
class PublicBookingController extends Controller
{
    public function create(): View
    {
        return view('public.book', [
            'products' => Product::has('rates')->orderBy('name')->get(['id', 'name']),
            'locations' => Location::active()->orderBy('name')->get(['id', 'name']),
            'started' => PublicBookingRequest::startedToken(),
        ]);
    }

    public function store(PublicBookingRequest $request): RedirectResponse
    {
        // Bots get the same answer as people but nothing is stored.
        if (! $request->isBot()) {
            BookingRequest::create($request->safe()->except(['website', 'started']));
        }

        return to_route('book.create')->with('status', __('Thank you! We have your request and will get back to you shortly.'));
    }
}
