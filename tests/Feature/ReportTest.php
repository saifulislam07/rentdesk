<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Models\Damage;
use App\Models\LateFee;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Unit;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reports;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reports = new ReportService;
        $this->from = CarbonImmutable::parse('2026-12-01', 'UTC')->startOfDay();
        $this->to = CarbonImmutable::parse('2026-12-07', 'UTC')->endOfDay();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function book(Unit $unit, string $from, string $to, RentalStatus $status = RentalStatus::Reserved): RentalItem
    {
        return RentalItem::factory()->forUnit($unit, CarbonImmutable::parse($from, 'UTC'), CarbonImmutable::parse($to, 'UTC'))->create([
            'rental_id' => Rental::factory()->status($status)->create(['start_at' => $from, 'end_at' => $to]),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function utilizationOf(Product $product): ?array
    {
        return $this->reports->utilization($this->from, $this->to)->firstWhere(fn (array $row): bool => $row['product']->is($product));
    }

    public function test_utilization_is_booked_time_over_stock_time(): void
    {
        $product = Product::factory()->create();
        [$a] = Unit::factory()->count(2)->for($product)->create();
        $this->book($a, '2026-12-02 10:00', '2026-12-04 10:00');

        $row = $this->utilizationOf($product);

        $this->assertSame(2, $row['stock']);
        $this->assertSame(2880, $row['booked_minutes']);
        // 2880 of 2 units x 7 days (20160 minutes).
        $this->assertSame(14.3, $row['percent']);
    }

    public function test_bookings_are_clipped_to_the_period(): void
    {
        $product = Product::factory()->create();
        $unit = Unit::factory()->for($product)->create();
        $this->book($unit, '2026-11-28 00:00', '2026-12-02 00:00');
        $this->book($unit, '2026-12-06 12:00', '2026-12-10 00:00');
        $this->book($unit, '2026-12-20 00:00', '2026-12-22 00:00');

        $row = $this->utilizationOf($product);

        // 1 day at the start, 1.5 days at the end, nothing for the booking outside.
        $this->assertSame(1440 + 2160, $row['booked_minutes']);
    }

    public function test_only_business_done_counts_towards_utilization(): void
    {
        $product = Product::factory()->create();
        $unit = Unit::factory()->for($product)->create();
        $this->book($unit, '2026-12-02 00:00', '2026-12-03 00:00', RentalStatus::Quote);
        $this->book($unit, '2026-12-03 00:00', '2026-12-04 00:00', RentalStatus::Cancelled);
        foreach ([RentalStatus::Reserved, RentalStatus::Out, RentalStatus::Returned, RentalStatus::Closed] as $index => $status) {
            $this->book($unit, '2026-12-0'.(4 + $index).' 00:00', '2026-12-0'.(5 + $index).' 00:00', $status);
        }

        $this->assertSame(4 * 1440, $this->utilizationOf($product)['booked_minutes']);
    }

    public function test_bulk_quantity_weights_the_booked_time_and_retired_units_leave_the_stock(): void
    {
        $product = Product::factory()->bulk()->create();
        Unit::factory()->for($product)->create(['quantity' => 8]);
        Unit::factory()->for($product)->status(UnitStatus::Retired)->create(['quantity' => 100]);
        RentalItem::factory()->create([
            'rental_id' => Rental::factory()->create(),
            'product_id' => $product->id,
            'qty' => 4,
            'start_at' => '2026-12-01 00:00',
            'end_at' => '2026-12-02 00:00',
        ]);

        $row = $this->utilizationOf($product);

        $this->assertSame(8, $row['stock']);
        $this->assertSame(4 * 1440, $row['booked_minutes']);
        $this->assertSame(7.1, $row['percent']);
    }

    public function test_products_without_stock_show_zero_and_the_percentage_never_exceeds_one_hundred(): void
    {
        $empty = Product::factory()->create();
        $busy = Product::factory()->create();
        $unit = Unit::factory()->for($busy)->create();
        $this->book($unit, '2026-12-01 00:00', '2026-12-08 00:00');
        $this->book($unit, '2026-12-01 00:00', '2026-12-08 00:00');

        $this->assertSame(0.0, $this->utilizationOf($empty)['percent']);
        $this->assertSame(100.0, $this->utilizationOf($busy)['percent']);
    }

    public function test_utilization_is_sorted_busiest_first(): void
    {
        $quiet = Product::factory()->create();
        $busy = Product::factory()->create();
        Unit::factory()->for($quiet)->create();
        $this->book(Unit::factory()->for($busy)->create(), '2026-12-01 00:00', '2026-12-03 00:00');

        $this->assertTrue($this->reports->utilization($this->from, $this->to)->first()['product']->is($busy));
    }

    public function test_revenue_adds_rental_totals_late_fees_and_damage_by_start_day(): void
    {
        $first = Rental::factory()->status(RentalStatus::Returned)->create(['start_at' => '2026-12-02 09:00', 'total' => '100.10']);
        LateFee::factory()->for($first)->create(['amount' => '25.20']);
        Damage::factory()->for($first)->create(['charged_amount' => '10.30']);
        Damage::factory()->for($first)->create(['charged_amount' => '0.00']);
        Rental::factory()->status(RentalStatus::Out)->create(['start_at' => '2026-12-02 15:00', 'total' => '50.00']);
        Rental::factory()->status(RentalStatus::Reserved)->create(['start_at' => '2026-12-05 15:00', 'total' => '20.00']);

        // Not counted: quote, cancelled and outside the period.
        Rental::factory()->status(RentalStatus::Quote)->create(['start_at' => '2026-12-03 09:00', 'total' => '999']);
        Rental::factory()->status(RentalStatus::Cancelled)->create(['start_at' => '2026-12-03 09:00', 'total' => '999']);
        Rental::factory()->status(RentalStatus::Closed)->create(['start_at' => '2026-12-09 09:00', 'total' => '999']);

        $revenue = $this->reports->revenue($this->from, $this->to);

        $this->assertSame(['2026-12-02', '2026-12-05'], $revenue['days']->keys()->all());
        $this->assertSame(2, $revenue['days']['2026-12-02']['rentals']);
        $this->assertSame(15010 + 2520 + 1030, $revenue['days']['2026-12-02']['rental_total'] + $revenue['days']['2026-12-02']['late_fees'] + $revenue['days']['2026-12-02']['damage']);
        $this->assertSame([
            'rentals' => 3,
            'rental_total' => '170.10',
            'late_fees' => '25.20',
            'damage' => '10.30',
            'total' => '205.60',
        ], $revenue['totals']);
    }

    public function test_revenue_is_empty_without_rentals(): void
    {
        $this->assertSame('0.00', $this->reports->revenue($this->from, $this->to)['totals']['total']);
    }

    public function test_overdue_lists_only_late_rentals_that_are_out_worst_first(): void
    {
        Carbon::setTestNow('2026-12-10 12:00');
        $recent = Rental::factory()->status(RentalStatus::Out)->create(['start_at' => '2026-12-07 10:00', 'end_at' => '2026-12-09 10:00']);
        $old = Rental::factory()->status(RentalStatus::Out)->create(['start_at' => '2026-12-01 10:00', 'end_at' => '2026-12-03 10:00']);
        LateFee::factory()->for($old)->create(['amount' => '80.00']);
        Rental::factory()->status(RentalStatus::Out)->create(['start_at' => '2026-12-09 10:00', 'end_at' => '2026-12-12 10:00']);
        Rental::factory()->status(RentalStatus::Returned)->create(['start_at' => '2026-11-01 10:00', 'end_at' => '2026-11-03 10:00']);

        $overdue = $this->reports->overdue();

        $this->assertSame([$old->id, $recent->id], $overdue->map(fn (array $row): int => $row['rental']->id)->all());
        $this->assertSame([8, 2], $overdue->pluck('days_late')->all());
        $this->assertSame(['80.00', '0.00'], $overdue->pluck('fee')->all());
    }

    public function test_damage_totals_cover_the_period_only(): void
    {
        Damage::factory()->create(['cost' => '80.00', 'charged_amount' => '50.00', 'created_at' => '2026-12-03 10:00']);
        Damage::factory()->create(['cost' => '20.50', 'charged_amount' => '0.00', 'created_at' => '2026-12-06 10:00']);
        Damage::factory()->create(['cost' => '500.00', 'charged_amount' => '500.00', 'created_at' => '2026-11-20 10:00']);

        $damages = $this->reports->damages($this->from, $this->to);

        $this->assertCount(2, $damages['rows']);
        $this->assertSame('100.50', $damages['cost']);
        $this->assertSame('50.00', $damages['charged']);
    }

    public function test_managers_and_admins_can_open_the_reports_but_staff_cannot(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get(route('reports.index'))->assertOk()->assertSee('Utilization')->assertSee('Revenue');
        $this->actingAs($this->userWithRole('admin'))->get(route('reports.index'))->assertOk();

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('reports.export', 'revenue'))->assertForbidden();
        $this->actingAs($staff)->get(route('dashboard'))->assertDontSee('Reports');
    }

    public function test_the_range_defaults_to_the_current_month_and_is_validated(): void
    {
        Carbon::setTestNow('2026-12-15 10:00');
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('reports.index'))->assertOk()->assertSee('value="2026-12-01"', false)->assertSee('value="2026-12-31"', false);
        $this->actingAs($manager)->get(route('reports.index', ['from' => '2026-12-10', 'to' => '2026-12-01']))->assertSessionHasErrors('to');
        $this->actingAs($manager)->get(route('reports.index', ['from' => '2026-01-01', 'to' => '2028-01-01']))->assertSessionHasErrors('to');
        $this->actingAs($manager)->get(route('reports.index', ['from' => 'yesterday', 'to' => '2026-12-01']))->assertSessionHasErrors('from');
    }

    public function test_the_report_page_shows_the_figures(): void
    {
        $unit = Unit::factory()->create();
        $this->book($unit, '2026-12-02 00:00', '2026-12-04 00:00');
        Rental::factory()->status(RentalStatus::Returned)->create(['start_at' => '2026-12-02 09:00', 'total' => '321.00']);
        Damage::factory()->create(['description' => 'Cracked lens', 'created_at' => '2026-12-03 10:00']);

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('reports.index', ['from' => '2026-12-01', 'to' => '2026-12-07']))
            ->assertOk()
            ->assertSee($unit->product->name)
            ->assertSee('321.00')
            ->assertSee('Cracked lens');
    }

    public function test_every_report_exports_as_csv(): void
    {
        $unit = Unit::factory()->create();
        $this->book($unit, '2026-12-02 00:00', '2026-12-04 00:00');
        Damage::factory()->create(['description' => 'Cracked lens', 'created_at' => '2026-12-03 10:00']);
        $manager = $this->userWithRole('manager');
        $query = ['from' => '2026-12-01', 'to' => '2026-12-07'];

        $utilization = $this->actingAs($manager)->get(route('reports.export', ['report' => 'utilization', ...$query]));
        $utilization->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertDownload('utilization-2026-12-01-2026-12-07.csv');
        $this->assertStringContainsString('Product,SKU', $utilization->streamedContent());
        $this->assertStringContainsString($unit->product->name, $utilization->streamedContent());

        $this->assertStringContainsString('Cracked lens', $this->actingAs($manager)->get(route('reports.export', ['report' => 'damages', ...$query]))->streamedContent());
        $this->assertStringContainsString('Date,Rentals', $this->actingAs($manager)->get(route('reports.export', ['report' => 'revenue', ...$query]))->streamedContent());
        $this->assertStringContainsString('Days late', $this->actingAs($manager)->get(route('reports.export', ['report' => 'overdue', ...$query]))->streamedContent());
    }

    public function test_csv_cells_cannot_run_as_spreadsheet_formulas(): void
    {
        Damage::factory()->create(['description' => '=HYPERLINK("http://evil.test","click")', 'created_at' => '2026-12-03 10:00']);

        $csv = $this->actingAs($this->userWithRole('manager'))
            ->get(route('reports.export', ['report' => 'damages', 'from' => '2026-12-01', 'to' => '2026-12-07']))
            ->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_unknown_reports_are_not_found(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get(route('reports.export', 'passwords'))->assertNotFound();
    }
}
