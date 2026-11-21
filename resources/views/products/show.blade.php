<x-app-layout>
    <x-page-header :title="$product->name">
        <x-slot name="actions">
            @can('viewAny', App\Models\Unit::class)
                <x-button variant="secondary" :href="route('products.labels', $product)">{{ __('Print QR labels') }}</x-button>
            @endcan
            @can('create', App\Models\Unit::class)
                <x-button variant="secondary" :href="route('products.units.create', $product)">{{ __('Add units') }}</x-button>
            @endcan
            @can('update', $product)
                <x-button :href="route('products.edit', $product)">{{ __('Edit') }}</x-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <x-card class="p-6 lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">{{ __('SKU') }}</dt><dd class="font-medium">{{ $product->sku }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Category') }}</dt><dd class="font-medium">{{ $product->category->name }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Brand') }}</dt><dd class="font-medium">{{ $product->brand?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Tracking') }}</dt><dd class="font-medium">{{ $product->tracking->label() }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Replacement value') }}</dt><dd class="font-medium">{{ $product->replacement_value }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Deposit amount') }}</dt><dd class="font-medium">{{ $product->deposit_amount }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Buffer time (minutes)') }}</dt><dd class="font-medium">{{ $product->buffer_minutes }}</dd></div>
            </dl>
            @if ($product->description)
                <p class="mt-4 text-sm text-gray-700">{{ $product->description }}</p>
            @endif
        </x-card>

        <x-card class="p-6">
            <h2 class="mb-3 font-semibold text-gray-900">{{ __('Rates') }}</h2>
            <ul class="space-y-2 text-sm">
                @forelse ($product->rates->sortBy(fn ($rate) => array_search($rate->period, App\Enums\RatePeriod::cases())) as $rate)
                    <li class="flex justify-between">
                        <span>{{ $rate->period->label() }}</span>
                        <span class="font-medium">{{ $rate->price }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">{{ __('No rates set.') }}</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    @can('viewAny', App\Models\MaintenanceTask::class)
        <x-card class="mb-6 p-6">
            <h2 class="mb-1 font-semibold text-gray-900">{{ __('Maintenance plan') }}</h2>
            <p class="mb-4 text-sm text-gray-500">{{ __('A unit is blocked for service when it reaches either limit since its last service.') }}</p>

            @can('update', App\Models\MaintenanceTask::class)
                <form method="POST" action="{{ route('products.maintenance-plan.update', $product) }}" class="flex flex-wrap items-start gap-4">
                    @csrf
                    @method('PUT')
                    <x-field name="every_days" :label="__('Every … days')">
                        <x-text-input id="every_days" name="every_days" type="number" min="1" :value="old('every_days', $product->maintenancePlan?->every_days)" />
                    </x-field>
                    <x-field name="every_rentals" :label="__('Every … rentals')">
                        <x-text-input id="every_rentals" name="every_rentals" type="number" min="1" :value="old('every_rentals', $product->maintenancePlan?->every_rentals)" />
                    </x-field>
                    <div class="flex items-end gap-2 self-end pb-px">
                        <x-button>{{ __('Save plan') }}</x-button>
                    </div>
                </form>

                @if ($product->maintenancePlan)
                    <x-delete-form :action="route('products.maintenance-plan.destroy', $product)" class="mt-3">
                        <button class="text-sm text-red-600 hover:text-red-800">{{ __('Remove plan') }}</button>
                    </x-delete-form>
                @endif
            @else
                <p class="text-sm">
                    {{ $product->maintenancePlan
                        ? __('Every :days days / every :rentals rentals', ['days' => $product->maintenancePlan->every_days ?? '—', 'rentals' => $product->maintenancePlan->every_rentals ?? '—'])
                        : __('No plan set.') }}
                </p>
            @endcan
        </x-card>
    @endcan

    <x-card>
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">{{ __('Units') }}</h2>
        </div>

        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Serial no.') }}</th>
                    <th class="px-4 py-3">{{ __('Location') }}</th>
                    <th class="px-4 py-3">{{ __('Condition') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($units as $unit)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('units.show', $unit) }}" class="hover:text-brand-600">{{ $unit->serial_no }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $unit->location->name }}</td>
                        <td class="px-4 py-3"><x-badge :color="$unit->condition->color()">{{ $unit->condition->label() }}</x-badge></td>
                        <td class="px-4 py-3"><x-badge :color="$unit->status->color()">{{ $unit->status->label() }}</x-badge></td>
                        <td class="px-4 py-3">
                            <x-row-actions :show="route('units.show', $unit)" :edit="auth()->user()->can('update', $unit) ? route('units.edit', $unit) : null">
                                @can('delete', $unit)
                                    <x-delete-form :action="route('units.destroy', $unit)">
                                        <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                    </x-delete-form>
                                @endcan
                            </x-row-actions>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No units yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $units->links() }}</div>
</x-app-layout>
