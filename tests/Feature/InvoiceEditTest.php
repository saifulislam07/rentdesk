<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceEditTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWithLine(InvoiceStatus $status = InvoiceStatus::Draft): Invoice
    {
        $invoice = Invoice::factory()->create(['status' => $status, 'subtotal' => '100.00', 'total' => '100.00']);
        $invoice->items()->create(['description' => 'Hammer Drill', 'qty' => 2, 'price' => '50.00']);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'issued_at' => '2026-12-01',
            'due_at' => '2026-12-10',
            'discount' => '10.00',
            'tax' => '5.00',
            'items' => [
                ['description' => 'Hammer Drill', 'qty' => 3, 'price' => '40.00'],
                ['description' => 'Delivery', 'qty' => 1, 'price' => '20.00'],
            ],
        ], $overrides);
    }

    public function test_a_draft_invoice_can_be_corrected_and_its_totals_are_recalculated(): void
    {
        $invoice = $this->invoiceWithLine();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('Hammer Drill');

        $this->actingAs($this->userWithRole('staff'))
            ->put(route('invoices.update', $invoice), $this->payload())
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status');

        $invoice->refresh();

        $this->assertSame('140.00', $invoice->subtotal);
        $this->assertSame('10.00', $invoice->discount);
        $this->assertSame('5.00', $invoice->tax);
        $this->assertSame('135.00', $invoice->total);
        $this->assertSame('2026-12-10', $invoice->due_at->toDateString());
        $this->assertSame(['Hammer Drill', 'Delivery'], $invoice->items()->orderBy('id')->pluck('description')->all());
    }

    public function test_a_sent_invoice_can_be_corrected_too(): void
    {
        $invoice = $this->invoiceWithLine(InvoiceStatus::Sent);

        $this->actingAs($this->userWithRole('manager'))->put(route('invoices.update', $invoice), $this->payload())->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame('135.00', $invoice->refresh()->total);
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
    }

    public function test_paid_and_void_invoices_cannot_be_changed(): void
    {
        foreach ([InvoiceStatus::Paid, InvoiceStatus::Void] as $status) {
            $invoice = $this->invoiceWithLine($status);
            $user = $this->userWithRole('admin');

            $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertRedirect(route('invoices.show', $invoice))->assertSessionHas('error');
            $this->actingAs($user)->put(route('invoices.update', $invoice), $this->payload())->assertRedirect(route('invoices.show', $invoice))->assertSessionHas('error');

            $this->assertSame('100.00', $invoice->refresh()->total);
        }
    }

    public function test_the_figures_are_validated(): void
    {
        $invoice = $this->invoiceWithLine();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->put(route('invoices.update', $invoice), $this->payload(['due_at' => '2026-11-01']))->assertSessionHasErrors('due_at');
        $this->actingAs($staff)->put(route('invoices.update', $invoice), $this->payload(['items' => []]))->assertSessionHasErrors('items');
        $this->actingAs($staff)->put(route('invoices.update', $invoice), $this->payload(['items' => [['description' => '', 'qty' => 0, 'price' => '-1']]]))
            ->assertSessionHasErrors(['items.0.description', 'items.0.qty', 'items.0.price']);
        $this->actingAs($staff)->put(route('invoices.update', $invoice), $this->payload(['discount' => '500.00']))->assertSessionHasErrors('discount');

        $this->assertSame('100.00', $invoice->refresh()->total);
    }

    public function test_users_who_cannot_update_invoices_are_refused(): void
    {
        $invoice = $this->invoiceWithLine();
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('invoices.update', $invoice), $this->payload())->assertForbidden();
    }

    public function test_the_list_offers_view_print_and_edit_only_where_they_apply(): void
    {
        $draft = $this->invoiceWithLine();
        $paid = $this->invoiceWithLine(InvoiceStatus::Paid);

        $page = $this->actingAs($this->userWithRole('staff'))->get(route('invoices.index'));

        $page->assertOk()
            ->assertSee(route('invoices.show', $draft).'?print=1', false)
            ->assertSee(route('invoices.edit', $draft), false)
            ->assertSee(route('invoices.show', $paid), false)
            ->assertDontSee(route('invoices.edit', $paid), false);
    }

    public function test_the_print_flag_opens_the_print_dialog(): void
    {
        $invoice = $this->invoiceWithLine();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('invoices.show', $invoice))->assertDontSee('setTimeout(() => window.print()', false)->assertSee('onclick="window.print()"', false);
        $this->actingAs($staff)->get(route('invoices.show', ['invoice' => $invoice, 'print' => 1]))->assertSee('setTimeout(() => window.print(), 300)', false);
    }
}
