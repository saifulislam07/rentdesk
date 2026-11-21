<x-app-layout>
    <x-page-header :title="__('Brands')">
        @can('create', App\Models\Brand::class)
            <x-slot name="actions">
                <x-button :href="route('brands.create')">{{ __('Add brand') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Products') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($brands as $brand)
                    <tr>
                        <td class="flex items-center gap-3 px-4 py-3 font-medium text-gray-900">
                            @if ($brand->logo)
                                <img src="{{ Storage::disk('public')->url($brand->logo) }}" alt="" class="h-8 w-8 rounded object-contain">
                            @endif
                            {{ $brand->name }}
                        </td>
                        <td class="px-4 py-3">{{ $brand->products_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @can('update', $brand)
                                    <x-button variant="secondary" size="sm" :href="route('brands.edit', $brand)">{{ __('Edit') }}</x-button>
                                @endcan
                                @can('delete', $brand)
                                    <x-delete-form :action="route('brands.destroy', $brand)">
                                        <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                    </x-delete-form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">{{ __('No brands yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $brands->links() }}</div>
</x-app-layout>
