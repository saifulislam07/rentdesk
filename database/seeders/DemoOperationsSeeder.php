<?php

namespace Database\Seeders;

use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Kit;
use App\Models\Location;
use App\Models\MaintenancePlan;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use App\Services\DepositService;
use App\Services\HandoverService;
use App\Services\LateFeeService;
use App\Services\MaintenanceService;
use App\Services\RentalService;
use App\Services\TransferService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample business on top of the demo catalogue: customers, kits, maintenance plans and rentals in every
 * stage of their life, all created through the same services the app uses, so the data is always consistent.
 */
class DemoOperationsSeeder extends Seeder
{
    /** A 1x1 transparent PNG standing in for a drawn signature. */
    private const SIGNATURE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Location $main;

    private User $staff;

    public function __construct(
        private readonly RentalService $rentals,
        private readonly HandoverService $handover,
        private readonly DepositService $deposits,
        private readonly LateFeeService $lateFees,
        private readonly MaintenanceService $maintenance,
        private readonly TransferService $transfers,
    ) {}

    public function run(): void
    {
        $this->main = Location::where('code', 'MAIN')->firstOrFail();
        $this->staff = User::role('admin')->first() ?? User::factory()->create();

        $customers = $this->customers();
        $this->kits();
        $this->maintenancePlans();

        $now = now();

        // Late: two days overdue, so the dashboard and the fee have something to show.
        $this->out($customers[0], $now->copy()->subDays(5)->setTime(10, 0), $now->copy()->subDays(2)->setTime(10, 0), ['Hammer Drill' => 1]);
        // Out, due back this afternoon.
        $this->out($customers[1], $now->copy()->subDay()->setTime(9, 0), $now->copy()->setTime(17, 0), ['Concrete Mixer' => 1]);
        // Reserved: picked up later today, and a kit booked for next week.
        $this->reserve($customers[2], $now->copy()->setTime(15, 0), $now->copy()->addDays(2)->setTime(15, 0), ['PA Speaker System' => 1]);
        $kitRental = $this->quote($customers[3], $now->copy()->addDays(7)->setTime(9, 0), $now->copy()->addDays(9)->setTime(9, 0), []);
        $this->rentals->addKit($kitRental, Kit::where('name', 'Photo shoot kit')->firstOrFail(), 1);
        $this->rentals->confirm($kitRental);
        // A draft quote waiting for the customer's answer.
        $this->quote($customers[4], $now->copy()->addDays(14)->setTime(9, 0), $now->copy()->addDays(17)->setTime(9, 0), ['Marquee Tent 6x6' => 1]);

        $this->closedRentalWithDamage($customers[2]);

        BookingRequest::factory()->count(2)->create([
            'location_id' => $this->main->id,
            'product_id' => $this->productId('Folding Table Set'),
            'start_at' => $now->copy()->addDays(21)->setTime(9, 0),
            'end_at' => $now->copy()->addDays(23)->setTime(9, 0),
        ]);

        $this->maintenance->open($this->freeUnit('LED Work Light'), notes: 'Replace the worn cable');
        $this->transfers->send($this->freeUnit('Angle Grinder', $this->main), Location::where('code', 'DTN')->firstOrFail());

        $this->lateFees->calculateAllOverdue();
    }

    /**
     * @return array<int, Customer>
     */
    private function customers(): array
    {
        $customers = [
            Customer::factory()->create(['name' => 'Northwind Builders Ltd', 'type' => 'company', 'tax_no' => 'TAX-4471290']),
            Customer::factory()->create(['name' => 'Amina Rahman']),
            Customer::factory()->create(['name' => 'Daniel Okafor']),
            Customer::factory()->create(['name' => 'Studio Lumen', 'type' => 'company']),
            Customer::factory()->create(['name' => 'Priya Nair']),
        ];

        Customer::factory()->blacklisted()->create(['name' => 'Rick Vandal', 'notes' => 'Returned equipment damaged and refused to pay.']);

        return $customers;
    }

    private function kits(): void
    {
        $kit = Kit::factory()->create(['name' => 'Photo shoot kit', 'price' => 110]);
        $kit->items()->create(['product_id' => $this->productId('Mirrorless Camera Kit'), 'qty' => 1]);
        $kit->items()->create(['product_id' => $this->productId('Wireless Microphone'), 'qty' => 2]);
        $kit->items()->create(['product_id' => $this->productId('LED Work Light'), 'qty' => 2]);

        $party = Kit::factory()->create(['name' => 'Party starter kit', 'price' => null]);
        $party->items()->create(['product_id' => $this->productId('Marquee Tent 6x6'), 'qty' => 1]);
        $party->items()->create(['product_id' => $this->productId('Folding Table Set'), 'qty' => 4]);
    }

    private function maintenancePlans(): void
    {
        foreach (['Hammer Drill', 'Angle Grinder', 'Concrete Mixer'] as $name) {
            MaintenancePlan::factory()->create(['product_id' => $this->productId($name), 'every_days' => 365, 'every_rentals' => 25]);
        }
    }

    /**
     * @param  array<string, int>  $products  Product name => quantity.
     */
    private function quote(Customer $customer, CarbonInterface $start, CarbonInterface $end, array $products): Rental
    {
        $rental = $this->rentals->createQuote([
            'customer_id' => $customer->id,
            'location_id' => $this->main->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        foreach ($products as $name => $quantity) {
            $this->rentals->addItem($rental, Product::where('name', $name)->firstOrFail(), $quantity);
        }

        return $rental;
    }

    /**
     * @param  array<string, int>  $products
     */
    private function reserve(Customer $customer, CarbonInterface $start, CarbonInterface $end, array $products): Rental
    {
        return $this->rentals->confirm($this->quote($customer, $start, $end, $products));
    }

    /**
     * Reserve a rental and hand it over at the time its period began.
     *
     * @param  array<string, int>  $products
     */
    private function out(Customer $customer, CarbonInterface $start, CarbonInterface $end, array $products): Rental
    {
        $rental = $this->reserve($customer, $start, $end, $products);

        return $this->at($start, fn (): Rental => $this->handover->checkOut(
            $rental,
            $this->staff,
            $this->inspections($rental, 'new'),
            base64_decode(self::SIGNATURE),
            ['amount' => $rental->deposit_required, 'method' => 'cash'],
        ));
    }

    /**
     * A finished rental: handed over, returned with a dent, the deposit settled after deducting the damage.
     */
    private function closedRentalWithDamage(Customer $customer): void
    {
        $start = now()->subDays(12)->setTime(9, 0);
        $end = $start->copy()->addDays(2);
        $rental = $this->out($customer, $start, $end, ['Angle Grinder' => 1]);

        $this->at($end, function () use ($rental): void {
            $inspections = $this->inspections($rental, 'fair');
            $inspections[array_key_first($inspections)]['damage'] = ['description' => 'Dented guard', 'cost' => '35.00', 'charged' => '20.00'];

            $this->handover->checkIn($rental, $this->staff, $inspections);
        });

        $rental->refresh()->load('deposit', 'damages', 'lateFee');
        $this->deposits->settle($rental, $this->deposits->suggestedRefund($rental));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inspections(Rental $rental, string $condition): array
    {
        return $rental->items()->whereNotNull('unit_id')->pluck('id')->mapWithKeys(fn (int $id): array => [
            $id => ['condition' => $condition, 'checklist' => ['clean', 'accessories', 'working']],
        ])->all();
    }

    /**
     * Run the work as if it were the given moment, so back-dated hand-overs get honest timestamps and late fees.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function at(CarbonInterface $moment, callable $work): mixed
    {
        Carbon::setTestNow($moment);

        try {
            return $work();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function productId(string $name): int
    {
        return Product::where('name', $name)->firstOrFail()->id;
    }

    /**
     * An available unit of the product, at the given location when one is named, not already committed to a rental.
     */
    private function freeUnit(string $productName, ?Location $at = null): Unit
    {
        return Unit::query()
            ->when($at, fn ($query) => $query->whereBelongsTo($at))
            ->where('product_id', $this->productId($productName))
            ->where('status', 'available')
            ->whereDoesntHave('rentalItems')
            ->firstOrFail();
    }
}
