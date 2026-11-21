<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\RatePeriod;
use App\Enums\RentalStatus;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RentalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function stockedProduct(Location $location): Product
    {
        $product = Product::factory()->create(['buffer_minutes' => 0]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => 25]);
        Unit::factory()->count(2)->for($product)->for($location)->create();

        return $product;
    }

    public function test_staff_can_take_a_quote_through_to_a_reserved_rental(): void
    {
        $staff = $this->userWithRole('staff');
        $location = Location::factory()->create();
        $customer = Customer::factory()->create();
        $product = $this->stockedProduct($location);

        $this->actingAs($staff)
            ->post(route('rentals.store'), [
                'customer_id' => $customer->id,
                'location_id' => $location->id,
                'start_at' => '2026-12-01T10:00',
                'end_at' => '2026-12-03T10:00',
            ])
            ->assertRedirect();

        $rental = Rental::firstOrFail();

        $this->actingAs($staff)
            ->post(route('rentals.items.store', $rental), ['product_id' => $product->id, 'qty' => 2])
            ->assertRedirect();

        $this->actingAs($staff)->get(route('rentals.show', $rental))->assertOk()->assertSee('100.00');

        $this->actingAs($staff)->post(route('rentals.confirm', $rental))->assertSessionHas('status');

        $this->assertSame(RentalStatus::Reserved, $rental->refresh()->status);
        $this->assertSame(2, $rental->items()->whereNotNull('unit_id')->count());
    }

    public function test_a_business_rule_failure_returns_to_the_page_with_the_message(): void
    {
        $staff = $this->userWithRole('staff');
        $rental = Rental::factory()->status(RentalStatus::Quote)->create();

        $this->actingAs($staff)
            ->from(route('rentals.show', $rental))
            ->post(route('rentals.confirm', $rental))
            ->assertRedirect(route('rentals.show', $rental))
            ->assertSessionHas('error', 'Add at least one item before confirming.');
    }

    public function test_blacklisted_customers_cannot_be_given_a_quote_over_http(): void
    {
        $customer = Customer::factory()->blacklisted()->create();

        $this->actingAs($this->userWithRole('staff'))
            ->post(route('rentals.store'), [
                'customer_id' => $customer->id,
                'location_id' => Location::factory()->create()->id,
                'start_at' => '2026-12-01T10:00',
                'end_at' => '2026-12-03T10:00',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_rental_dates_are_validated(): void
    {
        $this->actingAs($this->userWithRole('staff'))
            ->post(route('rentals.store'), [
                'customer_id' => Customer::factory()->create()->id,
                'location_id' => Location::factory()->create()->id,
                'start_at' => '2026-12-03T10:00',
                'end_at' => '2026-12-01T10:00',
            ])
            ->assertSessionHasErrors('end_at');
    }

    public function test_a_reserved_rental_cannot_be_edited(): void
    {
        $rental = Rental::factory()->status(RentalStatus::Reserved)->create();

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('rentals.edit', $rental))
            ->assertForbidden();
    }

    public function test_users_without_update_permission_cannot_change_rentals(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create()->givePermissionTo('rentals.view');
        $rental = Rental::factory()->status(RentalStatus::Quote)->create();

        $this->actingAs($viewer)->get(route('rentals.show', $rental))->assertOk();
        $this->actingAs($viewer)->post(route('rentals.confirm', $rental))->assertForbidden();
        $this->actingAs($viewer)->post(route('rentals.cancel', $rental))->assertForbidden();
    }

    public function test_every_rental_page_renders(): void
    {
        $manager = $this->userWithRole('manager');
        $quote = Rental::factory()->status(RentalStatus::Quote)->create();
        $reserved = Rental::factory()->status(RentalStatus::Reserved)->create();
        $invoice = Invoice::factory()->for($reserved)->create();

        foreach ([
            route('rentals.index', ['status' => 'quote', 'q' => 'RN']),
            route('rentals.create'),
            route('rentals.edit', $quote),
            route('rentals.show', $quote),
            route('rentals.show', $reserved),
            route('invoices.index'),
            route('invoices.show', $invoice),
        ] as $page) {
            $this->actingAs($manager)->get($page)->assertOk();
        }
    }

    public function test_contract_downloads_as_a_pdf(): void
    {
        $rental = Rental::factory()->create();

        $response = $this->actingAs($this->userWithRole('staff'))->get(route('rentals.contract', $rental));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_quotes_and_cancelled_rentals_cannot_be_invoiced(): void
    {
        $staff = $this->userWithRole('staff');

        foreach ([RentalStatus::Quote, RentalStatus::Cancelled] as $status) {
            $this->actingAs($staff)
                ->post(route('rentals.invoices.store', Rental::factory()->status($status)->create()))
                ->assertSessionHas('error');
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoicing_copies_the_rental_and_is_idempotent(): void
    {
        $staff = $this->userWithRole('staff');
        $location = Location::factory()->create();
        $product = $this->stockedProduct($location);
        $rental = Rental::factory()->status(RentalStatus::Quote)->create([
            'location_id' => $location->id,
            'start_at' => '2026-12-01 10:00',
            'end_at' => '2026-12-03 10:00',
        ]);
        $this->actingAs($staff)->post(route('rentals.items.store', $rental), ['product_id' => $product->id, 'qty' => 2]);
        $this->actingAs($staff)->post(route('rentals.confirm', $rental));

        $first = $this->actingAs($staff)->post(route('rentals.invoices.store', $rental));
        $this->actingAs($staff)->post(route('rentals.invoices.store', $rental));

        $invoice = Invoice::firstOrFail();

        $first->assertRedirect(route('invoices.show', $invoice));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(sprintf('INV-%06d', $invoice->id), $invoice->number);
        $this->assertSame('100.00', $invoice->total);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame('50.00', $invoice->items()->first()->price);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
    }

    public function test_a_new_invoice_can_be_made_once_the_old_one_is_void(): void
    {
        $rental = Rental::factory()->create();
        Invoice::factory()->for($rental)->create(['status' => InvoiceStatus::Void]);

        $this->actingAs($this->userWithRole('staff'))->post(route('rentals.invoices.store', $rental));

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_invoice_pdf_downloads(): void
    {
        $invoice = Invoice::factory()->create();
        $invoice->items()->create(['description' => 'Hammer Drill', 'qty' => 1, 'price' => 50]);

        $response = $this->actingAs($this->userWithRole('staff'))->get(route('invoices.pdf', $invoice));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_is_emailed_with_a_pdf_and_marked_as_sent(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'client@example.test']);
        $invoice = Invoice::factory()->for(Rental::factory()->for($customer))->create();

        $this->actingAs($this->userWithRole('staff'))
            ->post(route('invoices.send', $invoice))
            ->assertSessionHas('status');

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail): bool => $mail->hasTo('client@example.test')
            && $mail->invoice->is($invoice));
        $this->assertSame(InvoiceStatus::Sent, $invoice->refresh()->status);
        $this->assertNotNull($invoice->sent_at);
    }

    public function test_the_delivered_invoice_mail_carries_the_pdf_attachment(): void
    {
        config(['mail.default' => 'array']);
        $customer = Customer::factory()->create(['email' => 'client@example.test']);
        $invoice = Invoice::factory()->for(Rental::factory()->for($customer))->create();

        $this->actingAs($this->userWithRole('staff'))->post(route('invoices.send', $invoice));

        $message = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $attachments = $message->getAttachments();

        $this->assertCount(1, $attachments);
        $this->assertSame($invoice->number.'.pdf', $attachments[0]->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        $this->assertStringStartsWith('%PDF', $attachments[0]->getBody());
        $this->assertStringContainsString($invoice->number, $message->getSubject());
    }

    public function test_an_invoice_without_a_customer_email_is_not_sent(): void
    {
        Mail::fake();
        $invoice = Invoice::factory()->for(Rental::factory()->for(Customer::factory()->state(['email' => null])))->create();

        $this->actingAs($this->userWithRole('staff'))
            ->post(route('invoices.send', $invoice))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_void_invoices_are_not_sent(): void
    {
        Mail::fake();
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Void]);

        $this->actingAs($this->userWithRole('staff'))
            ->post(route('invoices.send', $invoice))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }
}
