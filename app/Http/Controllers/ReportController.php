<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRangeRequest;
use App\Models\Damage;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Authorize('reports.view')]
class ReportController extends Controller
{
    public const EXPORTS = ['utilization', 'revenue', 'damages', 'overdue'];

    public function __construct(private readonly ReportService $reports) {}

    public function index(ReportRangeRequest $request): View
    {
        $from = $request->from();
        $to = $request->to();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'utilization' => $this->reports->utilization($from, $to),
            'revenue' => $this->reports->revenue($from, $to),
            'damages' => $this->reports->damages($from, $to),
            'overdue' => $this->reports->overdue(),
        ]);
    }

    public function export(ReportRangeRequest $request, string $report): StreamedResponse
    {
        abort_unless(in_array($report, self::EXPORTS, true), 404);

        [$headings, $rows] = $this->table($report, $request);

        return response()->streamDownload(function () use ($headings, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings);

            foreach ($rows as $row) {
                fputcsv($out, array_map($this->safeCell(...), $row));
            }

            fclose($out);
        }, "{$report}-{$request->from()->toDateString()}-{$request->to()->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: list<string>, 1: iterable<int, list<string|int|float>>}
     */
    private function table(string $report, ReportRangeRequest $request): array
    {
        $from = $request->from();
        $to = $request->to();

        return match ($report) {
            'utilization' => [
                [__('Product'), __('SKU'), __('Units in stock'), __('Booked hours'), __('Utilization %')],
                $this->reports->utilization($from, $to)->map(fn (array $row): array => [
                    $row['product']->name,
                    $row['product']->sku,
                    $row['stock'],
                    round($row['booked_minutes'] / 60, 1),
                    $row['percent'],
                ])->all(),
            ],
            'revenue' => [
                [__('Date'), __('Rentals'), __('Rental total'), __('Late fees'), __('Damage charged'), __('Total')],
                $this->reports->revenue($from, $to)['days']->map(fn (array $day): array => [
                    $day['date'],
                    $day['rentals'],
                    $day['rental_total'] / 100,
                    $day['late_fees'] / 100,
                    $day['damage'] / 100,
                    $day['total'] / 100,
                ])->values()->all(),
            ],
            'damages' => [
                [__('Date'), __('Rental'), __('Unit'), __('Description'), __('Repair cost'), __('Charged')],
                $this->reports->damages($from, $to)['rows']->map(fn (Damage $damage): array => [
                    $damage->created_at->toDateString(),
                    $damage->rental->number,
                    $damage->unit->product->name.' '.$damage->unit->serial_no,
                    $damage->description,
                    $damage->cost,
                    $damage->charged_amount,
                ])->all(),
            ],
            'overdue' => [
                [__('Rental'), __('Customer'), __('Phone'), __('Due'), __('Days late'), __('Late fee')],
                $this->reports->overdue()->map(fn (array $row): array => [
                    $row['rental']->number,
                    $row['rental']->customer?->name ?? '',
                    $row['rental']->customer?->phone ?? '',
                    $row['rental']->end_at->format('Y-m-d H:i'),
                    $row['days_late'],
                    $row['fee'],
                ])->all(),
            ],
        };
    }

    /**
     * Stops spreadsheet apps running user-supplied text as a formula.
     */
    private function safeCell(string|int|float|null $value): string|int|float
    {
        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value ?? '';
    }
}
