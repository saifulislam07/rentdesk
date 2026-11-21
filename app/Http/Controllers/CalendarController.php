<?php

namespace App\Http\Controllers;

use App\Enums\RentalStatus;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('viewAny', Rental::class)]
class CalendarController extends Controller
{
    public function index(): View
    {
        return view('calendar.index', [
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Booked rental items inside the window FullCalendar is showing.
     */
    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $items = RentalItem::query()
            ->with(['product:id,name', 'unit:id,serial_no', 'rental:id,number,status'])
            ->whereHas('rental', fn ($rental) => $rental->blocking())
            ->where('start_at', '<', $validated['end'])
            ->where('end_at', '>', $validated['start'])
            ->when($validated['product_id'] ?? null, fn ($query, $productId) => $query->where('product_id', $productId))
            ->orderBy('start_at')
            ->limit(1000)
            ->get();

        return response()->json($items->map(fn (RentalItem $item): array => [
            'id' => $item->id,
            'title' => trim($item->product->name.' '.($item->unit?->serial_no ?? '×'.$item->qty)),
            'start' => $item->start_at->toIso8601String(),
            'end' => $item->end_at->toIso8601String(),
            'color' => $item->rental->status === RentalStatus::Out ? '#ca8a04' : '#2563eb',
            'extendedProps' => ['rental' => $item->rental->number],
        ]));
    }
}
