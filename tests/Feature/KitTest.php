<?php

namespace Tests\Feature;

use App\Enums\RatePeriod;
use App\Enums\RentalStatus;
use App\Exceptions\RentalException;
use App\Exceptions\UnitUnavailableException;
use App\Models\Customer;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Rental;
use App\Models\Unit;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = Location::factory()->create();
    }

    /**
     * A serial product with the given daily price and unit count at the test location.
     */
    private function product(string $dailyPrice, int $units = 2): Product
    {
        $product = Product::factory()->create(['buffer_minutes' => 0, 'deposit_amount' => 0]);
        ProductRate::factory()->for($product)->create(['period' => RatePeriod::Day, 'price' => $dailyPrice]);
        Unit::factory()->count($units)->for($product)->for($this->location)->create();

        return $product;
    }

    /**
     * A kit of one drill (25 a day) and two lights (10 a day each), so 45 a day when priced by its parts.
     */
    private function kit(?string $price = null): Kit
    {
        $kit = Kit::factory()->create(['price' => $price]);
        KitItem::factory()->for($kit)->create(['product_id' => $this->product('25')->id, 'qty' => 1]);
        KitItem::factory()->for($kit)->create(['product_id' => $this->product('10', 4)->id, 'qty' => 2]);

        return $kit;
    }

    private function quote(string $end = '2026-12-03 10:00'): Rental
    {
        return app(RentalService::class)->createQuote([
            'customer_id' => Customer::factory()->create()->id,
            'location_id' => $this->location->id,
            'start_at' => '2026-12-01 10:00',
            'end_at' => $end,
        ]);
    }

    public function test_a_kit_expands_into_priced_items_for_each_product(): void
    {
        $rental = $this->quote();

        $items = app(RentalService::class)->addKit($rental, $this->kit(), 1);

        $this->assertCount(3, $items);
        $this->assertSame(3, $rental->items()->whereNotNull('kit_id')->count());
        // Two days: drill 50 + two lights 40.
        $this->assertSame('90.00', $rental->refresh()->subtotal);
    }

    public function test_the_kit_quantity_multiplies_every_product(): void
    {
        $rental = $this->quote();

        app(RentalService::class)->addKit($rental, $this->kit(), 2);

        $this->assertSame(6, $rental->items()->count());
        $this->assertSame('180.00', $rental->refresh()->subtotal);
    }

    public function test_a_flat_kit_price_replaces_the_sum_of_its_parts_exactly(): void
    {
        $rental = $this->quote();

        app(RentalService::class)->addKit($rental, $this->kit('40.00'), 1);

        // Flat 40 a day for two days is 80, shared out in proportion to 50 : 20 : 20.
        $this->assertSame('80.00', $rental->refresh()->subtotal);
        $amounts = $rental->items()->orderBy('id')->pluck('amount')->all();
        $this->assertSame(['44.44', '17.78', '17.78'], $amounts);
        $this->assertSame(8000, array_sum(array_map(fn (string $amount): int => (int) round($amount * 100), $amounts)));
    }

    public function test_a_flat_price_is_billed_per_started_day_and_per_kit(): void
    {
        $rental = $this->quote('2026-12-03 12:00');

        app(RentalService::class)->addKit($rental, $this->kit('40.00'), 2);

        // 3 started days x 40 x 2 kits.
        $this->assertSame('240.00', $rental->refresh()->subtotal);
    }

    public function test_a_kit_can_be_removed_as_a_whole(): void
    {
        $rental = $this->quote();
        $service = app(RentalService::class);
        $kit = $this->kit();
        $service->addKit($rental, $kit, 1);
        $service->addItem($rental, $this->product('5'), 1);

        $service->removeKit($rental, $kit);

        $this->assertSame(1, $rental->items()->count());
        $this->assertSame('10.00', $rental->refresh()->subtotal);
    }

    public function test_a_reserved_rental_confirms_a_unit_for_every_kit_line(): void
    {
        $rental = $this->quote();
        $service = app(RentalService::class);
        $service->addKit($rental, $this->kit(), 1);

        $service->confirm($rental);

        $this->assertSame(RentalStatus::Reserved, $rental->refresh()->status);
        $this->assertSame(3, $rental->items()->whereNotNull('unit_id')->distinct()->count('unit_id'));
    }

    public function test_a_kit_whose_stock_runs_out_cannot_be_confirmed(): void
    {
        $rental = $this->quote();
        $service = app(RentalService::class);
        $service->addKit($rental, $this->kit(), 3);

        $this->expectException(UnitUnavailableException::class);

        $service->confirm($rental);
    }

    public function test_an_empty_kit_cannot_be_added(): void
    {
        $this->expectException(RentalException::class);

        app(RentalService::class)->addKit($this->quote(), Kit::factory()->create(), 1);
    }

    public function test_only_quotes_accept_kits(): void
    {
        $rental = Rental::factory()->status(RentalStatus::Reserved)->create();

        $this->expectException(RentalException::class);

        app(RentalService::class)->addKit($rental, $this->kit(), 1);
    }

    public function test_staff_can_add_and_remove_a_kit_on_a_quote_over_http(): void
    {
        $staff = $this->userWithRole('staff');
        $rental = $this->quote();
        $kit = $this->kit();

        $this->actingAs($staff)->post(route('rentals.kits.store', $rental), ['kit_id' => $kit->id, 'qty' => 1])->assertSessionHas('status');
        $this->actingAs($staff)->get(route('rentals.show', $rental))->assertOk()->assertSee($kit->name)->assertSee('Remove kit');

        $this->actingAs($staff)->delete(route('rentals.kits.destroy', [$rental, $kit]))->assertSessionHas('status');
        $this->assertSame(0, $rental->items()->count());
    }

    public function test_the_kit_form_is_validated(): void
    {
        $manager = $this->userWithRole('manager');
        $product = $this->product('10');

        $this->actingAs($manager)->post(route('kits.store'), ['name' => '', 'items' => []])->assertSessionHasErrors(['name', 'items']);
        $this->actingAs($manager)
            ->post(route('kits.store'), ['name' => 'Dup', 'items' => [['product_id' => $product->id, 'qty' => 1], ['product_id' => $product->id, 'qty' => 2]]])
            ->assertSessionHasErrors('items.1.product_id');
        $this->actingAs($manager)
            ->post(route('rentals.kits.store', $this->quote()), ['kit_id' => 999, 'qty' => 0])
            ->assertSessionHasErrors(['kit_id', 'qty']);
    }

    public function test_managers_can_create_update_and_admins_delete_kits(): void
    {
        $manager = $this->userWithRole('manager');
        [$a, $b] = [$this->product('10'), $this->product('20')];

        $this->actingAs($manager)
            ->post(route('kits.store'), ['name' => 'Wedding kit', 'price' => '99.00', 'items' => [['product_id' => $a->id, 'qty' => 2], ['product_id' => $b->id, 'qty' => 1]]])
            ->assertRedirect(route('kits.index'));

        $kit = Kit::firstWhere('name', 'Wedding kit');
        $this->assertSame('99.00', $kit->price);
        $this->assertSame(2, $kit->items()->count());

        $this->actingAs($manager)
            ->put(route('kits.update', $kit), ['name' => 'Party kit', 'price' => '', 'items' => [['product_id' => $b->id, 'qty' => 4]]])
            ->assertRedirect(route('kits.index'));

        $kit->refresh();
        $this->assertSame('Party kit', $kit->name);
        $this->assertNull($kit->price);
        $this->assertSame([4], $kit->items()->pluck('qty')->all());

        $this->actingAs($manager)->delete(route('kits.destroy', $kit))->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->delete(route('kits.destroy', $kit))->assertRedirect(route('kits.index'));
        $this->assertModelMissing($kit);
    }

    public function test_staff_can_only_view_kits(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('kits.index'))->assertOk();
        $this->actingAs($staff)->get(route('kits.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('kits.store'), [])->assertForbidden();
    }

    public function test_kit_pages_render(): void
    {
        $kit = $this->kit('40.00');
        $manager = $this->userWithRole('manager');

        foreach ([route('kits.index'), route('kits.create'), route('kits.edit', $kit)] as $page) {
            $this->actingAs($manager)->get($page)->assertOk();
        }
    }
}
