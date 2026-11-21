<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\RentalStatus;
use App\Exceptions\RentalException;
use App\Models\Invoice;
use App\Models\Rental;
use App\Models\RentalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Invoice a rental from its current figures. A rental keeps one live invoice: calling this
     * again returns it instead of creating a second one.
     *
     * @throws RentalException
     */
    public function createForRental(Rental $rental): Invoice
    {
        if (in_array($rental->status, [RentalStatus::Quote, RentalStatus::Cancelled], true)) {
            throw new RentalException(__('Only confirmed rentals can be invoiced.'));
        }

        $existing = $rental->invoices()->where('status', '!=', InvoiceStatus::Void)->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($rental): Invoice {
            $invoice = $rental->invoices()->create([
                'number' => (string) Str::ulid(),
                'status' => InvoiceStatus::Draft,
                'subtotal' => $rental->subtotal,
                'discount' => $rental->discount,
                'tax' => $rental->tax,
                'total' => $rental->total,
                'currency' => $rental->currency,
                'issued_at' => today(),
                'due_at' => today()->addDays(config('rentdesk.invoice_due_days')),
            ]);

            $invoice->update(['number' => sprintf('INV-%06d', $invoice->id)]);

            $rental->items()->with(['product', 'unit'])->get()->each(
                fn (RentalItem $item) => $invoice->items()->create([
                    'description' => $this->describe($item),
                    'qty' => $item->qty,
                    'price' => Money::format((int) round(Money::toCents($item->amount) / $item->qty)),
                ])
            );

            return $invoice;
        });
    }

    /**
     * Correct an invoice: its dates, discount, tax and lines. The subtotal and total are recalculated.
     *
     * @param  array{issued_at: string, due_at: string, discount: string|int|float, tax: string|int|float, items: list<array{description: string, qty: int|string, price: string|int|float}>}  $data
     *
     * @throws RentalException
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new RentalException(__('A paid or void invoice cannot be changed.'));
        }

        return DB::transaction(function () use ($invoice, $data): Invoice {
            $subtotal = collect($data['items'])->sum(fn (array $item): int => (int) $item['qty'] * Money::toCents($item['price']));
            $discount = Money::toCents($data['discount']);
            $tax = Money::toCents($data['tax']);

            $invoice->update([
                'issued_at' => $data['issued_at'],
                'due_at' => $data['due_at'],
                'subtotal' => Money::format($subtotal),
                'discount' => Money::format($discount),
                'tax' => Money::format($tax),
                'total' => Money::format($subtotal - $discount + $tax),
            ]);

            $invoice->items()->delete();

            foreach ($data['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'qty' => (int) $item['qty'],
                    'price' => Money::format(Money::toCents($item['price'])),
                ]);
            }

            return $invoice;
        });
    }

    private function describe(RentalItem $item): string
    {
        $name = $item->unit ? "{$item->product->name} ({$item->unit->serial_no})" : $item->product->name;

        return $name.', '.$item->start_at->format('Y-m-d H:i').' – '.$item->end_at->format('Y-m-d H:i').' UTC';
    }
}
