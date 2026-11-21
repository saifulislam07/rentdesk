<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\DocumentRenderer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Invoice :number from :company', [
                'number' => $this->invoice->number,
                'company' => config('rentdesk.company_name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(DocumentRenderer::class)->invoice($this->invoice)->output();

        return [
            Attachment::fromData(fn () => $pdf, $this->invoice->number.'.pdf')->withMime('application/pdf'),
        ];
    }
}
