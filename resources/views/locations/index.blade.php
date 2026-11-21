<x-app-layout>
    <x-page-header :title="__('Locations')">
        @can('create', App\Models\Location::class)
            <x-slot name="actions">
                <x-button :href="route('locations.create')">{{ __('Add location') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Code') }}</th>
                    <th class="px-4 py-3">{{ __('Phone') }}</th>
                    <th class="px-4 py-3">{{ __('Units') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($locations as $location)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $location->name }}</td>
                        <td class="px-4 py-3">{{ $location->code }}</td>
                        <td class="px-4 py-3">{{ $location->phone ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $location->units_count }}</td>
                        <td class="px-4 py-3">
                            <x-badge :color="$location->is_active ? 'green' : 'gray'">
                                {{ $location->is_active ? __('Active') : __('Inactive') }}
                            </x-badge>
                        </td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            @can('update', $location)
                                <x-button variant="secondary" size="sm" :href="route('locations.edit', $location)">{{ __('Edit') }}</x-button>
                            @endcan
                            @can('delete', $location)
                                <x-delete-form :action="route('locations.destroy', $location)">
                                    <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                </x-delete-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No locations yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $locations->links() }}</div>
</x-app-layout>
