<?php

namespace App\Http\Controllers;

use App\Enums\BookingRequestStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RentalStatus;
use App\Enums\UnitStatus;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MaintenanceTask;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Unit;
use App\Services\Money;
use App\Services\ReportService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): View
    {
        $user = $request->user();

        if (! $user->can('viewAny', Rental::class)) {
            return view('dashboard', ['stats' => null]);
        }

        $today = now('UTC');
        $withRelations = ['customer:id,name', 'location:id,name'];
        $units = Unit::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $fleet = $units->except(UnitStatus::Retired->value)->sum();

        return view('dashboard', [
            'stats' => [
                'units' => $units,
                'utilization' => $fleet > 0 ? (int) round((($units[UnitStatus::Rented->value] ?? 0) / $fleet) * 100) : 0,
                'products' => Product::count(),
                'customers' => Customer::count(),
                'reserved' => Rental::where('status', RentalStatus::Reserved)->count(),
                'out' => Rental::where('status', RentalStatus::Out)->count(),
                'overdue' => $reports->overdue(),
                'pickups' => Rental::with($withRelations)
                    ->where('status', RentalStatus::Reserved)
                    ->whereBetween('start_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                    ->orderBy('start_at')
                    ->get(),
                'returns' => Rental::with($withRelations)
                    ->where('status', RentalStatus::Out)
                    ->whereBetween('end_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                    ->orderBy('end_at')
                    ->get(),
                'upcoming' => Rental::with($withRelations)
                    ->where('status', RentalStatus::Reserved)
                    ->whereBetween('start_at', [$today->copy()->addDay()->startOfDay(), $today->copy()->addDays(7)->endOfDay()])
                    ->orderBy('start_at')
                    ->limit(8)
                    ->get(),
                'recent' => Rental::with($withRelations)->latest()->limit(8)->get(),
                'maintenance' => $user->can('viewAny', MaintenanceTask::class) ? MaintenanceTask::open()->count() : null,
                'requests' => $user->can('viewAny', BookingRequest::class) && config('rentdesk.public_booking')
                    ? BookingRequest::where('status', BookingRequestStatus::New)->count()
                    : null,
                'unpaid' => $user->can('viewAny', Invoice::class) ? $this->unpaidInvoices() : null,
                'revenue' => $user->can('reports.view')
                    ? $reports->revenue($today->copy()->startOfMonth(), $today->copy()->endOfMonth())['totals']['total']
                    : null,
                'months' => $user->can('reports.view') ? $this->monthlyRevenue($reports, $today) : null,
            ],
        ]);
    }

    /**
     * Invoices that were sent and are still unpaid, with their total and how many are past due.
     *
     * @return array{count: int, amount: string, overdue: int}
     */
    private function unpaidInvoices(): array
    {
        $sent = fn () => Invoice::where('status', InvoiceStatus::Sent);

        return [
            'count' => $sent()->count(),
            'amount' => Money::format(Money::toCents($sent()->sum('total'))),
            'overdue' => $sent()->whereDate('due_at', '<', now('UTC')->toDateString())->count(),
        ];
    }

    /**
     * Revenue of the last six months, oldest first, with each bar's share of the best month.
     *
     * @return list<array{label: string, total: string, percent: int}>
     */
    private function monthlyRevenue(ReportService $reports, CarbonInterface $today): array
    {
        $from = $today->copy()->startOfMonth()->subMonths(5);
        $perMonth = $reports->revenue($from, $today->copy()->endOfMonth())['days']
            ->groupBy(fn (array $day): string => substr($day['date'], 0, 7))
            ->map(fn ($days): int => $days->sum('total'));
        $best = max(1, (int) $perMonth->max());

        return collect(range(0, 5))->map(function (int $offset) use ($from, $perMonth, $best): array {
            $month = $from->copy()->addMonths($offset);
            $cents = (int) ($perMonth[$month->format('Y-m')] ?? 0);

            return ['label' => $month->translatedFormat('M'), 'total' => Money::format($cents), 'percent' => (int) round($cents / $best * 100)];
        })->all();
    }
}
