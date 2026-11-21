<x-app-layout>
    <x-page-header :title="__('Kits')">
        @can('create', App\Models\Kit::class)
            <x-slot name="actions">
                <x-button :href="route('kits.create')">{{ __('Add kit') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Contents') }}</th>
                    <th class="px-4 py-3">{{ __('Price per day') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($kits as $kit)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $kit->name }}</td>
                        <td class="px-4 py-3">{{ $kit->items->map(fn ($line) => $line->qty.' × '.$line->product->name)->implode(', ') }}</td>
                        <td class="px-4 py-3">{{ $kit->price ?? __('Sum of products') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @can('update', $kit)
                                    <x-button variant="secondary" size="sm" :href="route('kits.edit', $kit)">{{ __('Edit') }}</x-button>
                                @endcan
                                @can('delete', $kit)
                                    <x-delete-form :action="route('kits.destroy', $kit)">
                                        <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                    </x-delete-form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('No kits yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $kits->links() }}</div>
</x-app-layout>
