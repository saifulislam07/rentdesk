<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\RentalStatus;
use App\Exceptions\RentalException;
use App\Models\Damage;
use App\Models\Deposit;
use App\Models\Rental;
use App\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositServiceTest extends TestCase
{
    use RefreshDatabase;

    private DepositService $deposits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deposits = new DepositService;
    }

    private function returnedRental(string $held = '100.00'): Rental
    {
        $rental = Rental::factory()->status(RentalStatus::Returned)->create();
        Deposit::factory()->for($rental)->create(['amount' => $held]);

        return $rental;
    }

    public function test_a_full_refund_marks_the_deposit_refunded_and_closes_the_rental(): void
    {
        $rental = $this->returnedRental();

        $this->deposits->settle($rental, '100.00');

        $deposit = $rental->deposit()->first();
        $this->assertSame(DepositStatus::Refunded, $deposit->status);
        $this->assertSame('100.00', $deposit->refunded_amount);
        $this->assertNotNull($deposit->settled_at);
        $this->assertSame(RentalStatus::Closed, $rental->refresh()->status);
    }

    public function test_a_partial_refund_is_recorded(): void
    {
        $rental = $this->returnedRental();

        $this->deposits->settle($rental, '60.50');

        $deposit = $rental->deposit()->first();
        $this->assertSame(DepositStatus::Partial, $deposit->status);
        $this->assertSame('60.50', $deposit->refunded_amount);
    }

    public function test_no_refund_forfeits_the_deposit(): void
    {
        $rental = $this->returnedRental();

        $this->deposits->settle($rental, null);

        $this->assertSame(DepositStatus::Forfeited, $rental->deposit()->first()->status);
    }

    public function test_the_refund_cannot_exceed_the_deposit(): void
    {
        $rental = $this->returnedRental();

        try {
            $this->deposits->settle($rental, '100.01');
            $this->fail('Expected the refund to be refused.');
        } catch (RentalException) {
            $this->assertSame(RentalStatus::Returned, $rental->refresh()->status);
            $this->assertTrue($rental->deposit()->first()->isHeld());
        }
    }

    public function test_a_rental_without_a_deposit_is_simply_closed(): void
    {
        $rental = Rental::factory()->status(RentalStatus::Returned)->create();

        $this->deposits->settle($rental, null);

        $this->assertSame(RentalStatus::Closed, $rental->refresh()->status);
    }

    public function test_only_returned_rentals_can_be_settled(): void
    {
        $rental = Rental::factory()->status(RentalStatus::Out)->create();

        $this->expectException(RentalException::class);

        $this->deposits->settle($rental, null);
    }

    public function test_a_settled_rental_cannot_be_settled_twice(): void
    {
        $rental = $this->returnedRental();
        $this->deposits->settle($rental, '100.00');

        $this->expectException(RentalException::class);

        $this->deposits->settle($rental->refresh(), '100.00');
    }

    public function test_the_suggested_refund_deducts_charged_damage_and_never_goes_negative(): void
    {
        $rental = $this->returnedRental('100.00');
        Damage::factory()->for($rental)->create(['charged_amount' => '30.00']);
        Damage::factory()->for($rental)->create(['charged_amount' => '20.25']);

        $this->assertSame('49.75', $this->deposits->suggestedRefund($rental->refresh()));

        Damage::factory()->for($rental)->create(['charged_amount' => '500.00']);

        $this->assertSame('0.00', $this->deposits->suggestedRefund($rental->refresh()));
    }

    public function test_the_suggested_refund_is_zero_without_a_deposit(): void
    {
        $this->assertSame('0.00', $this->deposits->suggestedRefund(Rental::factory()->create()));
    }
}
