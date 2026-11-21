<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Rental;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Renders the printable rental contract and invoice.
 */
class DocumentRenderer
{
    public function contract(Rental $rental): PdfDocument
    {
        $rental->load(['customer', 'location', 'items.product', 'items.unit']);

        return $this->render('pdf.contract', ['rental' => $rental]);
    }

    public function invoice(Invoice $invoice): PdfDocument
    {
        $invoice->load(['items', 'rental.customer']);

        return $this->render('pdf.invoice', ['invoice' => $invoice]);
    }

    /**
     * Render in the PDF language whatever the user browses in: dompdf has no Bengali or right-to-left shaping.
     *
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data): PdfDocument
    {
        $userLocale = app()->getLocale();
        app()->setLocale(config('rentdesk.pdf_locale'));

        try {
            return Pdf::loadView($view, $data)->setPaper('a4');
        } finally {
            app()->setLocale($userLocale);
        }
    }
}
