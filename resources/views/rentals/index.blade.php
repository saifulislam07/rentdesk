<x-app-layout>
    <x-page-header :title="__('Rentals')">
        @can('create', App\Models\Rental::class)
            <x-slot name="actions">
                <x-button :href="route('rentals.create')">{{ __('New quote') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('rentals.index') }}" class="mb-4 flex flex-wrap gap-2">
        <x-text-input name="q" type="search" :value="request('q')" :placeholder="__('Rental number')" class="max-w-xs" />
        <x-select name="status" class="max-w-xs">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (App\Enums\RentalStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-select>
        <x-button variant="secondary">{{ __('Filter') }}</x-button>
    </form>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="px-4 py-3">{{ __('Location') }}</th>
                    <th class="px-4 py-3">{{ __('Period') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Total') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rentals as $rental)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('rentals.show', $rental) }}" class="hover:text-brand-600">{{ $rental->number }}</a></td>
                        <td class="px-4 py-3">{{ $rental->customer?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $rental->location->name }}</td>
                        <td class="px-4 py-3">{{ $rental->start_at->toAppDateTime() }} → {{ $rental->end_at->toAppDateTime() }}</td>
                        <td class="px-4 py-3"><x-badge :color="$rental->status->color()">{{ $rental->status->label() }}</x-badge></td>
                        <td class="px-4 py-3 text-end">{{ $rental->currency }} {{ $rental->total }}</td>
                        <td class="px-4 py-3">
                            <x-row-actions :show="route('rentals.show', $rental)" :edit="$rental->status->isEditable() && auth()->user()->can('update', $rental) ? route('rentals.edit', $rental) : null" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No rentals found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $rentals->links() }}</div>
</x-app-layout>
