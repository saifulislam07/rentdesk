<x-app-layout>
    <x-page-header :title="__('Dashboard')">
        @if ($stats)
            <x-slot:actions>
                @can('create', App\Models\Rental::class)
                    <a href="{{ route('rentals.create') }}" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('New rental') }}</a>
                @endcan
                @can('create', App\Models\Customer::class)
                    <a href="{{ route('customers.create') }}" class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('New customer') }}</a>
                @endcan
                <a href="{{ route('scan') }}" class="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Scan unit') }}</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if (! $stats)
        <x-card class="p-6 text-sm text-gray-700">
            {{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}
        </x-card>
    @else
        <div class="mb-6 grid gap-3 grid-cols-2 md:grid-cols-4 xl:grid-cols-6">
            <x-card class="px-4 py-3">
                <p class="text-sm text-gray-500">{{ __('Out on rent') }}</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['out'] }}</p>
            </x-card>
            <x-card class="px-4 py-3">
                <p class="text-sm text-gray-500">{{ __('Overdue') }}</p>
                <p class="text-2xl font-bold {{ $stats['overdue']->isNotEmpty() ? 'text-red-600' : 'text-gray-900' }}">{{ $stats['overdue']->count() }}</p>
            </x-card>
            <x-card class="px-4 py-3">
                <p class="text-sm text-gray-500">{{ __('Reserved rentals') }}</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['reserved'] }}</p>
            </x-card>
            <x-card class="px-4 py-3">
                <p class="text-sm text-gray-500">{{ __('Fleet in use') }}</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['utilization'] }}%</p>
                <div class="mt-2 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-brand-600" style="width: {{ $stats['utilization'] }}%"></div></div>
            </x-card>
            <x-card class="px-4 py-3">
                <p class="text-sm text-gray-500">{{ __('Products') }}</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['products'] }}</p>
            </x-card>
            <x-card class="px-4 py-3">
                <p class="text-sm text-gray-500">{{ __('Customers') }}</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['customers'] }}</p>
            </x-card>
            @if ($stats['maintenance'] !== null)
                <x-card class="px-4 py-3">
                    <p class="text-sm text-gray-500">{{ __('Open maintenance') }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['maintenance'] }}</p>
                </x-card>
            @endif
            @if ($stats['requests'] !== null)
                <x-card class="px-4 py-3">
                    <p class="text-sm text-gray-500">{{ __('New booking requests') }}</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <a href="{{ route('booking-requests.index') }}" class="hover:text-brand-600">{{ $stats['requests'] }}</a>
                    </p>
                </x-card>
            @endif
            @if ($stats['unpaid'] !== null)
                <x-card class="px-4 py-3">
                    <p class="text-sm text-gray-500">{{ __('Unpaid invoices') }}</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <a href="{{ route('invoices.index') }}" class="hover:text-brand-600">{{ $stats['unpaid']['count'] }}</a>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ config('rentdesk.currency') }} {{ $stats['unpaid']['amount'] }}
                        @if ($stats['unpaid']['overdue'] > 0)
                            · <span class="text-red-600">{{ __(':count past due', ['count' => $stats['unpaid']['overdue']]) }}</span>
                        @endif
                    </p>
                </x-card>
            @endif
            @if ($stats['revenue'] !== null)
                <x-card class="px-4 py-3">
                    <p class="text-sm text-gray-500">{{ __('Revenue this month') }}</p>
                    <p class="text-xl font-bold text-gray-900">{{ config('rentdesk.currency') }} {{ $stats['revenue'] }}</p>
                </x-card>
            @endif
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-3">
            @if ($stats['months'] !== null)
                <x-card class="p-6 lg:col-span-2">
                    <h2 class="mb-4 font-semibold text-gray-900">{{ __('Revenue, last 6 months') }}</h2>
                    <div class="flex h-40 items-end gap-3">
                        @foreach ($stats['months'] as $month)
                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ config('rentdesk.currency') }} {{ $month['total'] }}">
                                <span class="text-xs text-gray-500">{{ $month['total'] }}</span>
                                <div class="w-full rounded-t bg-brand-600" style="height: {{ max(2, $month['percent']) }}%"></div>
                                <span class="text-xs font-medium text-gray-700">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card class="p-6 {{ $stats['months'] === null ? 'lg:col-span-1' : '' }}">
                <h2 class="mb-3 font-semibold text-gray-900">{{ __('Fleet status') }}</h2>
                <ul class="space-y-2 text-sm">
                    @foreach (App\Enums\UnitStatus::cases() as $status)
                        <li class="flex items-center justify-between">
                            <x-badge :color="$status->color()">{{ $status->label() }}</x-badge>
                            <span class="font-medium">{{ $stats['units'][$status->value] ?? 0 }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <x-card class="p-6">
                <h2 class="mb-3 font-semibold text-gray-900">{{ __('Overdue rentals') }}</h2>
                <ul class="space-y-2 text-sm">
                    @forelse ($stats['overdue']->take(5) as $row)
                        <li class="flex items-center justify-between gap-3">
                            <a href="{{ route('rentals.show', $row['rental']) }}" class="font-medium hover:text-brand-600">{{ $row['rental']->number }}</a>
                            <span class="truncate text-gray-500">{{ $row['rental']->customer?->name }}</span>
                            <x-badge color="red">{{ trans_choice(':count day late|:count days late', $row['days_late']) }}</x-badge>
                        </li>
                    @empty
                        <li class="text-gray-500">{{ __('Nothing is overdue.') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card class="p-6">
                <h2 class="mb-3 font-semibold text-gray-900">{{ __('Pick-ups in the next 7 days') }}</h2>
                <ul class="space-y-2 text-sm">
                    @forelse ($stats['upcoming'] as $rental)
                        <li class="flex items-center justify-between gap-3">
                            <a href="{{ route('rentals.show', $rental) }}" class="font-medium hover:text-brand-600">{{ $rental->number }}</a>
                            <span class="truncate text-gray-500">{{ $rental->customer?->name }} · {{ $rental->location->name }}</span>
                            <span class="text-gray-700">{{ $rental->start_at->toAppDate() }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500">{{ __('None.') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            @foreach ([['pickups', __('Pick-ups today'), 'start_at'], ['returns', __('Returns due today'), 'end_at']] as [$key, $heading, $column])
                <x-card class="p-6">
                    <h2 class="mb-3 font-semibold text-gray-900">{{ $heading }}</h2>
                    <ul class="space-y-2 text-sm">
                        @forelse ($stats[$key] as $rental)
                            <li class="flex items-center justify-between gap-3">
                                <a href="{{ route('rentals.show', $rental) }}" class="font-medium hover:text-brand-600">{{ $rental->number }}</a>
                                <span class="truncate text-gray-500">{{ $rental->customer?->name }} · {{ $rental->location->name }}</span>
                                <span class="text-gray-700">{{ $rental->{$column}->toAppTime() }}</span>
                            </li>
                        @empty
                            <li class="text-gray-500">{{ __('None.') }}</li>
                        @endforelse
                    </ul>
                </x-card>
            @endforeach
        </div>

        <x-card class="p-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">{{ __('Recent rentals') }}</h2>
                <a href="{{ route('rentals.index') }}" class="text-sm text-brand-600 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse [&_th]:border [&_th]:border-gray-200 [&_td]:border [&_td]:border-gray-200 text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($stats['recent'] as $rental)
                            <tr>
                                <td class="px-3 py-2"><a href="{{ route('rentals.show', $rental) }}" class="font-medium hover:text-brand-600">{{ $rental->number }}</a></td>
                                <td class="px-3 py-2 text-gray-500">{{ $rental->customer?->name }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $rental->start_at->toAppDate() }} → {{ $rental->end_at->toAppDate() }}</td>
                                <td class="px-3 py-2"><x-badge :color="$rental->status->color()">{{ $rental->status->label() }}</x-badge></td>
                                <td class="px-3 py-2 text-end text-gray-700">{{ config('rentdesk.currency') }} {{ $rental->total }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-3 py-2 text-gray-500">{{ __('None.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</x-app-layout>
