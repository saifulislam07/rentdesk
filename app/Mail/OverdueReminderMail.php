<?php

namespace App\Mail;

use App\Models\Rental;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OverdueReminderMail extends Mailable
{
    public function __construct(public Rental $rental) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Rental :number is overdue', ['number' => $this->rental->number]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.overdue');
    }
}
