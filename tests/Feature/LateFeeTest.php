<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Mail\OverdueReminderMail;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\User;
use App\Services\DepositService;
use App\Services\HandoverService;
use App\Services\LateFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesReservedRentals;
use Tests\TestCase;

class LateFeeTest extends TestCase
{
    use CreatesReservedRentals;
    use RefreshDatabase;

    private LateFeeService $lateFees;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->lateFees = app(LateFeeService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An out rental of two days (1 Dec 10:00 to 3 Dec 10:00) with a subtotal of 100, so 50 a day.
     */
    private function outRental(): Rental
    {
        $rental = $this->reservedRental(2, '10.00');
        app(HandoverService::class)->checkOut(
            $rental,
            User::factory()->create(),
            $this->inspectionsFor($rental),
            $this->signatureBytes(),
            ['amount' => '20.00', 'method' => 'cash'],
        );

        return $rental->refresh();
    }

    public function test_a_rental_within_the_grace_period_is_not_late(): void
    {
        $rental = $this->outRental();
        Carbon::setTestNow('2026-12-03 10:59');

        $this->assertNull($this->lateFees->calculate($rental));
        $this->assertDatabaseCount('late_fees', 0);
    }

    public function test_every_started_day_is_charged_a_daily_share_of_the_subtotal(): void
    {
        $rental = $this->outRental();

        Carbon::setTestNow('2026-12-03 11:01');
        $this->assertSame('50.00', $this->lateFees->calculate($rental)->amount);

        Carbon::setTestNow('2026-12-05 10:00');
        $fee = $this->lateFees->calculate($rental);
        $this->assertSame(2, $fee->days_late);
        $this->assertSame('100.00', $fee->amount);

        Carbon::setTestNow('2026-12-05 10:30');
        $this->assertSame(3, $this->lateFees->calculate($rental)->days_late);
        $this->assertSame('150.00', $rental->lateFee()->first()->amount);
        $this->assertDatabaseCount('late_fees', 1);
    }

    public function test_the_percentage_scales_the_daily_charge(): void
    {
        config(['rentdesk.late_fee_percent' => 50]);
        $rental = $this->outRental();
        Carbon::setTestNow('2026-12-04 10:00');

        $this->assertSame('25.00', $this->lateFees->calculate($rental)->amount);
    }

    public function test_only_overdue_rentals_that_are_out_are_charged_by_the_daily_run(): void
    {
        $overdue = $this->outRental();
        $future = Rental::factory()->status(RentalStatus::Out)->create(['start_at' => '2026-12-10 10:00', 'end_at' => '2026-12-12 10:00']);
        $returned = Rental::factory()->status(RentalStatus::Returned)->create(['start_at' => '2026-11-01 10:00', 'end_at' => '2026-11-03 10:00']);
        Carbon::setTestNow('2026-12-05 10:00');

        $this->artisan('rentals:calculate-late-fees')->expectsOutput('1 late fee calculated.')->assertSuccessful();

        $this->assertNotNull($overdue->lateFee()->first());
        $this->assertNull($future->lateFee()->first());
        $this->assertNull($returned->lateFee()->first());
    }

    public function test_check_in_fixes_the_fee_at_the_return_time(): void
    {
        $rental = $this->outRental();
        Carbon::setTestNow('2026-12-04 12:00');

        app(HandoverService::class)->checkIn($rental, User::factory()->create(), $this->inspectionsFor($rental));

        $rental->refresh();
        $this->assertSame(RentalStatus::Returned, $rental->status);
        $this->assertSame(2, $rental->lateFee->days_late);
        $this->assertSame('100.00', $rental->lateFee->amount);

        Carbon::setTestNow('2026-12-20 10:00');
        $this->artisan('rentals:calculate-late-fees')->assertSuccessful();
        $this->assertSame('100.00', $rental->lateFee()->first()->amount);
    }

    public function test_an_on_time_return_has_no_fee(): void
    {
        $rental = $this->outRental();
        Carbon::setTestNow('2026-12-03 09:00');

        app(HandoverService::class)->checkIn($rental, User::factory()->create(), $this->inspectionsFor($rental));

        $this->assertNull($rental->refresh()->lateFee);
    }

    public function test_the_suggested_refund_deducts_the_late_fee(): void
    {
        $rental = $this->outRental();
        Carbon::setTestNow('2026-12-03 15:00');
        app(HandoverService::class)->checkIn($rental, User::factory()->create(), $this->inspectionsFor($rental));

        // Deposit 20, late fee 50 for the one started day.
        $this->assertSame('0.00', app(DepositService::class)->suggestedRefund($rental->refresh()->load('deposit', 'damages')));

        config(['rentdesk.late_fee_percent' => 10]);
        $rental->lateFee->update(['amount' => '5.00']);
        $this->assertSame('15.00', app(DepositService::class)->suggestedRefund($rental->refresh()->load('deposit', 'damages')));
    }

    public function test_the_overdue_rental_shows_its_fee_and_an_overdue_badge(): void
    {
        $rental = $this->outRental();
        Carbon::setTestNow('2026-12-05 10:00');
        $this->lateFees->calculate($rental);

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('rentals.show', $rental))
            ->assertOk()
            ->assertSee('Overdue')
            ->assertSee('Late fee (2 days)');
    }

    public function test_reminders_go_to_overdue_customers_once_a_day(): void
    {
        Mail::fake();
        $rental = $this->outRental();
        $rental->customer->update(['email' => 'late@example.test']);
        Carbon::setTestNow('2026-12-05 08:00');

        $this->artisan('rentals:send-overdue-reminders')->expectsOutput('1 reminder sent.')->assertSuccessful();
        $this->artisan('rentals:send-overdue-reminders')->expectsOutput('0 reminders sent.')->assertSuccessful();

        Mail::assertSent(OverdueReminderMail::class, 1);
        Mail::assertSent(OverdueReminderMail::class, fn (OverdueReminderMail $mail): bool => $mail->hasTo('late@example.test') && $mail->rental->is($rental));

        Carbon::setTestNow('2026-12-06 08:00');
        $this->artisan('rentals:send-overdue-reminders')->expectsOutput('1 reminder sent.')->assertSuccessful();
        Mail::assertSent(OverdueReminderMail::class, 2);
    }

    public function test_reminders_skip_customers_without_email_and_rentals_that_are_not_late(): void
    {
        Mail::fake();
        $noEmail = $this->outRental();
        $noEmail->customer->update(['email' => null]);
        Rental::factory()->status(RentalStatus::Out)->for(Customer::factory())->create(['end_at' => '2026-12-30 10:00', 'start_at' => '2026-12-28 10:00']);
        Carbon::setTestNow('2026-12-05 08:00');

        $this->artisan('rentals:send-overdue-reminders')->expectsOutput('0 reminders sent.');

        Mail::assertNothingSent();
    }

    public function test_the_reminder_mail_names_the_rental(): void
    {
        $rental = Rental::factory()->create(['end_at' => '2026-12-03 10:00']);

        $mail = new OverdueReminderMail($rental);

        $mail->assertSeeInHtml($rental->number);
        $mail->assertHasSubject("Rental {$rental->number} is overdue");
    }
}
