<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Exceptions\RentalException;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Rental;
use App\Services\DocumentRenderer;
use App\Services\InvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    #[Authorize('viewAny', Invoice::class)]
    public function index(): View
    {
        return view('invoices.index', [
            'invoices' => Invoice::with('rental.customer')->latest('id')->paginate($this->perPage()),
        ]);
    }

    #[Authorize('view', 'invoice')]
    public function show(Invoice $invoice): View
    {
        return view('invoices.show', ['invoice' => $invoice->load(['items', 'rental.customer'])]);
    }

    #[Authorize('update', 'invoice')]
    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if (! $invoice->status->isEditable()) {
            return to_route('invoices.show', $invoice)->with('error', __('A paid or void invoice cannot be changed.'));
        }

        return view('invoices.edit', ['invoice' => $invoice->load(['items', 'rental.customer'])]);
    }

    #[Authorize('update', 'invoice')]
    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        try {
            $invoices->update($invoice, $request->validated());
        } catch (RentalException $exception) {
            return to_route('invoices.show', $invoice)->with('error', $exception->getMessage());
        }

        return to_route('invoices.show', $invoice)->with('status', __('Invoice updated.'));
    }

    public function store(Rental $rental, InvoiceService $invoices): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        Gate::authorize('view', $rental);

        $invoice = $invoices->createForRental($rental);

        return to_route('invoices.show', $invoice)->with('status', __('Invoice ready.'));
    }

    #[Authorize('view', 'invoice')]
    public function pdf(Invoice $invoice, DocumentRenderer $documents): Response
    {
        return $documents->invoice($invoice)->download($invoice->number.'.pdf');
    }

    #[Authorize('update', 'invoice')]
    public function send(Invoice $invoice): RedirectResponse
    {
        $invoice->load('rental.customer');
        $email = $invoice->rental->customer->email;

        if (! $email) {
            return back()->with('error', __('The customer has no email address.'));
        }

        if ($invoice->status === InvoiceStatus::Void) {
            return back()->with('error', __('A void invoice cannot be sent.'));
        }

        Mail::to($email)->send(new InvoiceMail($invoice));

        $invoice->update([
            'sent_at' => now(),
            'status' => $invoice->status === InvoiceStatus::Draft ? InvoiceStatus::Sent : $invoice->status,
        ]);

        return back()->with('status', __('Invoice emailed to :email.', ['email' => $email]));
    }

    #[Authorize('update', 'invoice')]
    public function markPaid(Invoice $invoice): RedirectResponse
    {
        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Void], true)) {
            return back()->with('error', __('Only draft or sent invoices can be marked as paid.'));
        }

        $invoice->update(['status' => InvoiceStatus::Paid]);

        return back()->with('status', __('Invoice marked as paid.'));
    }
}
