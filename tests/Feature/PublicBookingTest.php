<?php

namespace Tests\Feature;

use App\Enums\BookingRequestStatus;
use App\Enums\RatePeriod;
use App\Enums\RentalStatus;
use App\Exceptions\RentalException;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\BookingRequestService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentdesk.public_booking' => true]);
        Carbon::setTestNow('2026-12-01 10:00');

        $this->location = Location::factory()->create();
        $this->product = Product::factory()->create(['buffer_minutes' => 0, 'deposit_amount' => 0]);
        ProductRate::factory()->for($this->product)->create(['period' => RatePeriod::Day, 'price' => 25]);
        Unit::factory()->count(2)->for($this->product)->for($this->location)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Post to the booking form with a clean rate limiter, so tests that submit several times do not trip it.
     *
     * @param  array<string, mixed>  $data
     */
    private function submit(array $data): TestResponse
    {
        Cache::flush();

        return $this->post(route('book.store'), $data);
    }

    /**
     * A valid submission from a person who took a minute over the form.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'phone' => '555-0100',
            'location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'start_at' => '2026-12-10T10:00',
            'end_at' => '2026-12-12T10:00',
            'message' => 'For a shoot',
            'website' => '',
            'started' => Crypt::encryptString((string) now()->subMinute()->timestamp),
            ...$overrides,
        ];
    }

    public function test_the_page_does_not_exist_unless_the_feature_is_on(): void
    {
        config(['rentdesk.public_booking' => false]);

        $this->get(route('book.create'))->assertNotFound();
        $this->submit($this->payload())->assertNotFound();
        $this->assertDatabaseCount('booking_requests', 0);
    }

    public function test_the_form_is_open_to_anyone_and_lists_only_bookable_products(): void
    {
        Product::factory()->create(['name' => 'Hidden Gadget']);

        $this->get(route('book.create'))
            ->assertOk()
            ->assertSee($this->product->name)
            ->assertDontSee('Hidden Gadget')
            ->assertSee($this->location->name)
            ->assertSee('name="website"', false);
    }

    public function test_a_valid_request_is_stored_as_new_and_nothing_is_reserved(): void
    {
        $this->submit($this->payload())
            ->assertRedirect(route('book.create'))
            ->assertSessionHas('status');

        $request = BookingRequest::firstOrFail();
        $this->assertSame(BookingRequestStatus::New, $request->status);
        $this->assertSame('jane@example.test', $request->email);
        $this->assertSame($this->product->id, $request->product_id);
        $this->assertSame('2026-12-10 10:00', $request->start_at->format('Y-m-d H:i'));
        $this->assertDatabaseCount('rentals', 0);
        $this->assertDatabaseMissing('booking_requests', ['name' => '']);
    }

    public function test_the_decoy_field_gets_a_silent_success_and_stores_nothing(): void
    {
        $this->submit($this->payload(['website' => 'http://spam.test']))
            ->assertRedirect(route('book.create'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('booking_requests', 0);
    }

    public function test_a_form_submitted_too_fast_or_with_a_bad_token_is_refused(): void
    {
        $tooFast = Crypt::encryptString((string) now()->timestamp);

        $this->submit($this->payload(['started' => $tooFast]))->assertSessionHasErrors('started');
        $this->submit($this->payload(['started' => 'forged']))->assertSessionHasErrors('started');
        $this->submit($this->payload(['started' => '']))->assertSessionHasErrors('started');

        $this->assertDatabaseCount('booking_requests', 0);
    }

    public function test_the_rate_limit_stops_a_flood_from_one_address(): void
    {
        $this->post(route('book.store'), $this->payload())->assertRedirect();
        $this->post(route('book.store'), $this->payload(['email' => 'two@example.test']))->assertRedirect();

        $this->post(route('book.store'), $this->payload(['email' => 'three@example.test']))->assertStatus(429);

        $this->assertDatabaseCount('booking_requests', 2);

        Carbon::setTestNow(now()->addMinute()->addSecond());
        $this->post(route('book.store'), $this->payload(['email' => 'four@example.test', 'started' => Crypt::encryptString((string) now()->subMinute()->timestamp)]))
            ->assertRedirect();
    }

    public function test_the_request_is_validated(): void
    {
        $inactive = Location::factory()->inactive()->create();

        $this->submit($this->payload(['name' => '', 'email' => 'nope']))->assertSessionHasErrors(['name', 'email']);
        $this->submit($this->payload(['start_at' => '2026-11-01T10:00', 'end_at' => '2026-11-03T10:00']))->assertSessionHasErrors('start_at');
        $this->submit($this->payload(['end_at' => '2026-12-09T10:00']))->assertSessionHasErrors('end_at');
        $this->submit($this->payload(['end_at' => '2027-06-01T10:00']))->assertSessionHasErrors('end_at');
        $this->submit($this->payload(['location_id' => $inactive->id]))->assertSessionHasErrors('location_id');
        $this->submit($this->payload(['qty' => 0]))->assertSessionHasErrors('qty');
        $this->submit($this->payload(['product_id' => 9999]))->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('booking_requests', 0);
    }

    public function test_products_without_rates_and_unavailable_dates_are_refused(): void
    {
        $unpriced = Product::factory()->create();
        Unit::factory()->for($unpriced)->for($this->location)->create();

        $this->submit($this->payload(['product_id' => $unpriced->id]))->assertSessionHasErrors('product_id');

        $this->submit($this->payload(['qty' => 3]))->assertSessionHasErrors('product_id');

        foreach ($this->product->units as $unit) {
            RentalItem::factory()->forUnit($unit, now()->setDate(2026, 12, 9)->setTime(0, 0), now()->setDate(2026, 12, 13)->setTime(0, 0))->create();
        }
        $this->submit($this->payload())->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('booking_requests', 0);
    }

    public function test_staff_can_list_requests_by_status(): void
    {
        BookingRequest::factory()->create(['name' => 'Fresh Fiona']);
        BookingRequest::factory()->status(BookingRequestStatus::Rejected)->create(['name' => 'Rejected Rob']);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('booking-requests.index'))->assertOk()->assertSee('Fresh Fiona')->assertDontSee('Rejected Rob');
        $this->actingAs($staff)->get(route('booking-requests.index', ['status' => 'rejected']))->assertSee('Rejected Rob');
        $this->actingAs($staff)->get(route('booking-requests.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
    }

    public function test_converting_creates_the_customer_and_a_priced_quote(): void
    {
        $request = BookingRequest::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'location_id' => $this->location->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'start_at' => '2026-12-10 10:00',
            'end_at' => '2026-12-12 10:00',
            'message' => 'For a shoot',
        ]);

        $response = $this->actingAs($this->userWithRole('staff'))->post(route('booking-requests.convert', $request));

        $rental = Rental::firstOrFail();
        $response->assertRedirect(route('rentals.show', $rental));
        $this->assertSame(RentalStatus::Quote, $rental->status);
        $this->assertSame('For a shoot', $rental->notes);
        $this->assertSame('Jane Doe', $rental->customer->name);
        $this->assertSame(2, $rental->items()->count());
        $this->assertSame('100.00', $rental->subtotal);
        $this->assertSame(BookingRequestStatus::Converted, $request->refresh()->status);
        $this->assertSame($rental->id, $request->rental_id);
    }

    public function test_an_existing_customer_is_reused_by_email(): void
    {
        $customer = Customer::factory()->create(['email' => 'jane@example.test', 'name' => 'Jane Existing']);
        $request = BookingRequest::factory()->create([
            'email' => 'jane@example.test',
            'location_id' => $this->location->id,
            'product_id' => $this->product->id,
        ]);

        app(BookingRequestService::class)->convert($request);

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame($customer->id, Rental::firstOrFail()->customer_id);
    }

    public function test_a_blacklisted_customer_cannot_be_converted_and_nothing_is_left_behind(): void
    {
        Customer::factory()->blacklisted()->create(['email' => 'bad@example.test']);
        $request = BookingRequest::factory()->create([
            'email' => 'bad@example.test',
            'location_id' => $this->location->id,
            'product_id' => $this->product->id,
        ]);

        $this->actingAs($this->userWithRole('staff'))
            ->from(route('booking-requests.index'))
            ->post(route('booking-requests.convert', $request))
            ->assertRedirect(route('booking-requests.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('rentals', 0);
        $this->assertSame(BookingRequestStatus::New, $request->refresh()->status);
    }

    public function test_a_request_cannot_be_handled_twice(): void
    {
        $request = BookingRequest::factory()->create([
            'location_id' => $this->location->id,
            'product_id' => $this->product->id,
        ]);
        $service = app(BookingRequestService::class);
        $service->convert($request);

        foreach ([fn () => $service->convert($request->refresh()), fn () => $service->reject($request->refresh())] as $again) {
            try {
                $again();
                $this->fail('Expected the request to be refused.');
            } catch (RentalException) {
                $this->assertDatabaseCount('rentals', 1);
            }
        }
    }

    public function test_a_request_can_be_rejected(): void
    {
        $request = BookingRequest::factory()->create();

        $this->actingAs($this->userWithRole('staff'))->post(route('booking-requests.reject', $request))->assertSessionHas('status');

        $this->assertSame(BookingRequestStatus::Rejected, $request->refresh()->status);
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_users_without_booking_access_are_forbidden(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create()->givePermissionTo('bookings.view');
        $request = BookingRequest::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('booking-requests.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('booking-requests.index'))->assertOk();
        $this->actingAs($viewer)->post(route('booking-requests.convert', $request))->assertForbidden();
        $this->actingAs($viewer)->post(route('booking-requests.reject', $request))->assertForbidden();
    }
}
