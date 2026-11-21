<x-app-layout>
    <x-page-header :title="$unit->product->name.' · '.$unit->serial_no">
        @can('update', $unit)
            <x-slot name="actions">
                <x-button :href="route('units.edit', $unit)">{{ __('Edit') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="p-6 lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">{{ __('Product') }}</dt><dd class="font-medium"><a href="{{ route('products.show', $unit->product) }}" class="hover:text-brand-600">{{ $unit->product->name }}</a></dd></div>
                <div><dt class="text-gray-500">{{ __('Serial number') }}</dt><dd class="font-medium">{{ $unit->serial_no }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Location') }}</dt><dd class="font-medium">{{ $unit->location->name }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Quantity') }}</dt><dd class="font-medium">{{ $unit->quantity }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Condition') }}</dt><dd><x-badge :color="$unit->condition->color()">{{ $unit->condition->label() }}</x-badge></dd></div>
                <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-badge :color="$unit->status->color()">{{ $unit->status->label() }}</x-badge></dd></div>
                <div><dt class="text-gray-500">{{ __('Purchase date') }}</dt><dd class="font-medium">{{ $unit->purchased_at?->toFormattedDateString() ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Purchase cost') }}</dt><dd class="font-medium">{{ $unit->purchase_cost ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Meter reading') }}</dt><dd class="font-medium">{{ $unit->meter_reading ?? '—' }}</dd></div>
            </dl>

            @if ($currentRental)
                <div class="mt-6 rounded-md bg-yellow-50 p-4 text-sm text-yellow-900 ring-1 ring-yellow-200">
                    {{ __('Currently out on :number with :customer.', ['number' => $currentRental->number, 'customer' => $currentRental->customer->name]) }}
                    <a href="{{ route('rentals.show', $currentRental) }}" class="ms-1 font-medium underline">{{ __('Open rental') }}</a>
                </div>
            @endif
        </x-card>

        <x-card class="flex flex-col items-center p-6">
            <div class="w-40">{!! QrCode::format('svg')->size(160)->margin(1)->generate(route('units.scan', $unit->qr_code)) !!}</div>
            <p class="mt-2 break-all text-center font-mono text-xs text-gray-500">{{ $unit->qr_code }}</p>
        </x-card>
    </div>

    @can('viewAny', App\Models\MaintenanceTask::class)
        <x-card class="mt-6 p-6">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-gray-900">{{ __('Maintenance') }}</h2>
                @can('create', App\Models\StockTransfer::class)
                    @if ($unit->status === App\Enums\UnitStatus::Available)
                        <x-button variant="secondary" size="sm" :href="route('transfers.create', ['unit_id' => $unit->id])">{{ __('Transfer to another location') }}</x-button>
                    @endif
                @endcan
            </div>

            <ul class="space-y-2 text-sm">
                @forelse ($tasks as $task)
                    <li class="flex flex-wrap items-center gap-2">
                        <x-badge :color="$task->status->color()">{{ $task->status->label() }}</x-badge>
                        <span>{{ $task->due_at->toAppDate() }}</span>
                        <span class="text-gray-500">{{ $task->notes }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">{{ __('No maintenance history.') }}</li>
                @endforelse
            </ul>

            @can('create', App\Models\MaintenanceTask::class)
                <form method="POST" action="{{ route('units.maintenance.store', $unit) }}" class="mt-5 flex flex-wrap items-start gap-3 border-t border-gray-100 pt-4">
                    @csrf
                    <x-field name="notes" :label="__('What needs doing?')" class="min-w-64 flex-1">
                        <x-text-input id="notes" name="notes" required />
                    </x-field>
                    <div class="self-end">
                        <x-button variant="secondary">{{ __('Block for maintenance') }}</x-button>
                    </div>
                </form>
            @endcan
        </x-card>
    @endcan
</x-app-layout>
