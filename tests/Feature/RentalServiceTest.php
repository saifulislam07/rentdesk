<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\RatePeriod;
use App\Enums\RentalStatus;
use App\Exceptions\RentalException;
use App\Exceptions\UnitUnavailableException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalService $service;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RentalService::class);
        $this->location = Location::factory()->create();
    }

    /**
     * A serial product priced 25 per day with a 10 deposit and the given number of units at the test location.
     */
    private function product(int $units = 3): Product
    {
        $product = Product::factory()->create(['buffer_minutes' => 0, 'deposit_amount' => 10]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => 25]);
        Unit::factory()->count($units)->for($product)->for($this->location)->create();

        return $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function quote(array $overrides = []): Rental
    {
        return $this->service->createQuote([
            'customer_id' => Customer::factory()->create()->id,
            'location_id' => $this->location->id,
            'start_at' => '2026-12-01 10:00',
            'end_at' => '2026-12-03 10:00',
            ...$overrides,
        ]);
    }

    public function test_a_quote_gets_a_sequential_number_and_default_currency(): void
    {
        $rental = $this->quote();

        $this->assertSame(sprintf('RN-%06d', $rental->id), $rental->number);
        $this->assertSame(RentalStatus::Quote, $rental->status);
        $this->assertSame('USD', $rental->currency);
    }

    public function test_blacklisted_customers_cannot_get_a_quote(): void
    {
        $this->expectException(RentalException::class);

        $this->quote(['customer_id' => Customer::factory()->blacklisted()->create()->id]);
    }

    public function test_serial_products_are_added_as_one_item_per_unit_and_totals_follow(): void
    {
        config(['rentdesk.tax_rate' => 10]);
        $rental = $this->quote(['discount' => '10.00']);

        $items = $this->service->addItem($rental, $this->product(), 3);
        $rental->refresh();

        $this->assertCount(3, $items);
        $this->assertSame(3, $rental->items()->where('qty', 1)->count());
        // 3 items x 2 days x 25 = 150, minus 10 discount = 140, plus 10% tax = 154.
        $this->assertSame('150.00', $rental->subtotal);
        $this->assertSame('10.00', $rental->discount);
        $this->assertSame('14.00', $rental->tax);
        $this->assertSame('154.00', $rental->total);
        $this->assertSame('30.00', $rental->deposit_required);
    }

    public function test_bulk_products_are_added_as_a_single_item(): void
    {
        $product = Product::factory()->bulk()->create(['deposit_amount' => 1]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => 2]);
        $rental = $this->quote();

        $this->service->addItem($rental, $product, 5);

        $this->assertSame(1, $rental->items()->count());
        $this->assertSame(5, $rental->items()->first()->qty);
        $this->assertSame('20.00', $rental->refresh()->subtotal);
    }

    public function test_adding_a_product_without_rates_is_rejected(): void
    {
        $this->expectException(RentalException::class);

        $this->service->addItem($this->quote(), Product::factory()->create(), 1);
    }

    public function test_removing_an_item_updates_the_totals(): void
    {
        $rental = $this->quote();
        $this->service->addItem($rental, $this->product(), 2);

        $this->service->removeItem($rental, $rental->items()->first());

        $this->assertSame('50.00', $rental->refresh()->subtotal);
    }

    public function test_changing_the_dates_reprices_the_items(): void
    {
        $rental = $this->quote();
        $this->service->addItem($rental, $this->product(), 1);

        $this->service->updateQuote($rental, [
            'customer_id' => $rental->customer_id,
            'location_id' => $rental->location_id,
            'start_at' => '2026-12-01 10:00',
            'end_at' => '2026-12-06 10:00',
        ]);

        $this->assertSame('125.00', $rental->refresh()->subtotal);
        $this->assertSame('125.00', $rental->items()->first()->amount);
    }

    public function test_confirming_reserves_the_rental_and_assigns_distinct_units(): void
    {
        $rental = $this->quote();
        $this->service->addItem($rental, $this->product(3), 3);

        $confirmed = $this->service->confirm($rental);

        $this->assertSame(RentalStatus::Reserved, $confirmed->status);
        $this->assertSame(3, $confirmed->items()->whereNotNull('unit_id')->distinct()->count('unit_id'));
    }

    public function test_confirming_fails_and_stays_a_quote_when_units_run_out(): void
    {
        $product = $this->product(2);
        $rental = $this->quote();
        $this->service->addItem($rental, $product, 3);

        try {
            $this->service->confirm($rental);
            $this->fail('Expected the confirmation to fail.');
        } catch (UnitUnavailableException) {
            $this->assertSame(RentalStatus::Quote, $rental->refresh()->status);
            $this->assertSame(0, $rental->items()->whereNotNull('unit_id')->count());
        }
    }

    public function test_confirming_cannot_double_book_units_held_by_another_rental(): void
    {
        $product = $this->product(1);
        $first = $this->quote();
        $this->service->addItem($first, $product, 1);
        $this->service->confirm($first);

        $second = $this->quote(['start_at' => '2026-12-02 10:00', 'end_at' => '2026-12-04 10:00']);
        $this->service->addItem($second, $product, 1);

        $this->expectException(UnitUnavailableException::class);

        $this->service->confirm($second);
    }

    public function test_confirming_only_uses_units_at_the_rental_location(): void
    {
        $product = $this->product(0);
        Unit::factory()->for($product)->for(Location::factory()->create())->create();
        $rental = $this->quote();
        $this->service->addItem($rental, $product, 1);

        $this->expectException(UnitUnavailableException::class);

        $this->service->confirm($rental);
    }

    public function test_bulk_stock_is_checked_on_confirmation(): void
    {
        $product = Product::factory()->bulk()->create(['buffer_minutes' => 0]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => 2]);
        Unit::factory()->for($product)->for($this->location)->create(['quantity' => 10]);

        $first = $this->quote();
        $this->service->addItem($first, $product, 7);
        $this->service->confirm($first);

        $second = $this->quote();
        $this->service->addItem($second, $product, 4);

        $this->expectException(UnitUnavailableException::class);

        $this->service->confirm($second);
    }

    public function test_an_empty_quote_cannot_be_confirmed(): void
    {
        $this->expectException(RentalException::class);

        $this->service->confirm($this->quote());
    }

    public function test_a_customer_blacklisted_after_quoting_blocks_confirmation(): void
    {
        $rental = $this->quote();
        $this->service->addItem($rental, $this->product(), 1);
        $rental->customer->update(['is_blacklisted' => true]);

        $this->expectException(RentalException::class);

        $this->service->confirm($rental);
    }

    public function test_only_quotes_can_be_changed(): void
    {
        $rental = $this->quote();
        $this->service->addItem($rental, $this->product(), 1);
        $this->service->confirm($rental);

        $this->expectException(RentalException::class);

        $this->service->addItem($rental->refresh(), $this->product(), 1);
    }

    public function test_cancelling_frees_the_units_and_voids_unpaid_invoices(): void
    {
        $product = $this->product(1);
        $rental = $this->quote();
        $this->service->addItem($rental, $product, 1);
        $this->service->confirm($rental);
        $invoice = Invoice::factory()->for($rental)->create();

        $this->service->cancel($rental);

        $this->assertSame(RentalStatus::Cancelled, $rental->refresh()->status);
        $this->assertSame(InvoiceStatus::Void, $invoice->refresh()->status);

        $again = $this->quote();
        $this->service->addItem($again, $product, 1);
        $this->assertSame(RentalStatus::Reserved, $this->service->confirm($again)->status);
    }

    public function test_paid_invoices_are_not_voided_by_cancellation(): void
    {
        $rental = $this->quote();
        $invoice = Invoice::factory()->for($rental)->create(['status' => InvoiceStatus::Paid]);

        $this->service->cancel($rental);

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_a_returned_rental_cannot_be_cancelled(): void
    {
        $rental = Rental::factory()->status(RentalStatus::Returned)->create();

        $this->expectException(RentalException::class);

        $this->service->cancel($rental);
    }

    public function test_item_amount_matches_its_period_and_rate(): void
    {
        $rental = $this->quote();
        $this->service->addItem($rental, $this->product(), 1);

        $item = RentalItem::firstOrFail();

        $this->assertSame('25.00', $item->rate);
        $this->assertSame('50.00', $item->amount);
        $this->assertTrue($item->start_at->equalTo($rental->start_at));
    }
}
