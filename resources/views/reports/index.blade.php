<x-app-layout>
    <x-page-header :title="__('Reports')" />

    @php
        $range = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
        $currency = config('rentdesk.currency');
    @endphp

    <form method="GET" action="{{ route('reports.index') }}" class="mb-6 flex flex-wrap items-end gap-4">
        <x-field name="from" :label="__('From')">
            <x-text-input id="from" name="from" type="date" :value="$range['from']" required />
        </x-field>
        <x-field name="to" :label="__('To')">
            <x-text-input id="to" name="to" type="date" :value="$range['to']" required />
        </x-field>
        <x-button>{{ __('Apply') }}</x-button>
    </form>

    <x-card class="mb-6">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">{{ __('Revenue') }}</h2>
            <a href="{{ route('reports.export', ['report' => 'revenue', ...$range]) }}" class="text-sm text-brand-600 hover:underline">{{ __('Export CSV') }}</a>
        </div>
        <p class="px-6 pt-4 text-xs text-gray-500">{{ __('Rentals starting in the period that are reserved, out, returned or closed, plus late fees and damage charged.') }}</p>

        <div class="grid gap-4 px-6 py-4 sm:grid-cols-5">
            @foreach ([[__('Rentals'), $revenue['totals']['rentals']], [__('Rental total'), $revenue['totals']['rental_total']], [__('Late fees'), $revenue['totals']['late_fees']], [__('Damage charged'), $revenue['totals']['damage']], [__('Total'), $currency.' '.$revenue['totals']['total']]] as [$label, $value])
                <div>
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Rentals') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Rental total') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Late fees') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Damage charged') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($revenue['days'] as $day)
                    <tr>
                        <td class="px-4 py-2">{{ $day['date'] }}</td>
                        <td class="px-4 py-2 text-end">{{ $day['rentals'] }}</td>
                        <td class="px-4 py-2 text-end">{{ App\Services\Money::format($day['rental_total']) }}</td>
                        <td class="px-4 py-2 text-end">{{ App\Services\Money::format($day['late_fees']) }}</td>
                        <td class="px-4 py-2 text-end">{{ App\Services\Money::format($day['damage']) }}</td>
                        <td class="px-4 py-2 text-end font-medium">{{ App\Services\Money::format($day['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No rentals in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <x-card class="mb-6">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">{{ __('Utilization') }}</h2>
            <a href="{{ route('reports.export', ['report' => 'utilization', ...$range]) }}" class="text-sm text-brand-600 hover:underline">{{ __('Export CSV') }}</a>
        </div>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Units in stock') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Booked hours') }}</th>
                    <th class="w-1/3 px-4 py-3">{{ __('Utilization %') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($utilization as $row)
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $row['product']->name }}</td>
                        <td class="px-4 py-2 text-end">{{ $row['stock'] }}</td>
                        <td class="px-4 py-2 text-end">{{ round($row['booked_minutes'] / 60, 1) }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100" role="presentation">
                                    <div class="h-full rounded-full bg-brand-600" style="width: {{ $row['percent'] }}%"></div>
                                </div>
                                <span class="w-14 text-end text-xs font-medium">{{ $row['percent'] }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No products yet.') }}</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <x-card class="mb-6">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">{{ __('Overdue rentals') }}</h2>
            <a href="{{ route('reports.export', ['report' => 'overdue', ...$range]) }}" class="text-sm text-brand-600 hover:underline">{{ __('Export CSV') }}</a>
        </div>
        <x-table>
            <tbody class="divide-y divide-gray-100">
                @forelse ($overdue as $row)
                    <tr>
                        <td class="px-4 py-2 font-medium"><a href="{{ route('rentals.show', $row['rental']) }}" class="hover:text-brand-600">{{ $row['rental']->number }}</a></td>
                        <td class="px-4 py-2">{{ $row['rental']->customer?->name }}</td>
                        <td class="px-4 py-2">{{ $row['rental']->end_at->toAppDateTime() }}</td>
                        <td class="px-4 py-2"><x-badge color="red">{{ trans_choice(':count day late|:count days late', $row['days_late']) }}</x-badge></td>
                        <td class="px-4 py-2 text-end">{{ $row['fee'] }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-gray-500">{{ __('Nothing is overdue.') }}</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">{{ __('Damage') }}</h2>
            <a href="{{ route('reports.export', ['report' => 'damages', ...$range]) }}" class="text-sm text-brand-600 hover:underline">{{ __('Export CSV') }}</a>
        </div>
        <div class="grid gap-4 px-6 py-4 sm:grid-cols-2">
            <div><p class="text-xs text-gray-500">{{ __('Repair cost') }}</p><p class="text-lg font-semibold">{{ $currency }} {{ $damages['cost'] }}</p></div>
            <div><p class="text-xs text-gray-500">{{ __('Charged to customers') }}</p><p class="text-lg font-semibold">{{ $currency }} {{ $damages['charged'] }}</p></div>
        </div>
        <x-table>
            <tbody class="divide-y divide-gray-100">
                @forelse ($damages['rows'] as $damage)
                    <tr>
                        <td class="px-4 py-2">{{ $damage->created_at->toAppDate() }}</td>
                        <td class="px-4 py-2 font-medium"><a href="{{ route('rentals.show', $damage->rental) }}" class="hover:text-brand-600">{{ $damage->rental->number }}</a></td>
                        <td class="px-4 py-2">{{ $damage->unit->product->name }} · {{ $damage->unit->serial_no }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $damage->description }}</td>
                        <td class="px-4 py-2 text-end">{{ $damage->cost }}</td>
                        <td class="px-4 py-2 text-end">{{ $damage->charged_amount }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-gray-500">{{ __('No damage recorded in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
</x-app-layout>
