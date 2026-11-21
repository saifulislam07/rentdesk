<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_mark_a_sent_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('invoices.show', $invoice))->assertSee(route('invoices.paid', $invoice));

        $this->actingAs($staff)
            ->post(route('invoices.paid', $invoice))
            ->assertSessionHas('status');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->actingAs($staff)->get(route('invoices.show', $invoice))->assertDontSee(route('invoices.paid', $invoice));
    }

    public function test_void_invoices_cannot_be_marked_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Void]);

        $this->actingAs($this->userWithRole('staff'))
            ->post(route('invoices.paid', $invoice))
            ->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }

    public function test_guests_cannot_mark_invoices_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);

        $this->post(route('invoices.paid', $invoice))->assertRedirect(route('login'));
        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
    }
}
