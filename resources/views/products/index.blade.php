<x-app-layout>
    <x-page-header :title="__('Products')">
        @can('create', App\Models\Product::class)
            <x-slot name="actions">
                <x-button :href="route('products.create')">{{ __('Add product') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('SKU') }}</th>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3">{{ __('Brand') }}</th>
                    <th class="px-4 py-3">{{ __('Tracking') }}</th>
                    <th class="px-4 py-3">{{ __('Units') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('products.show', $product) }}" class="hover:text-brand-600">{{ $product->name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $product->sku }}</td>
                        <td class="px-4 py-3">{{ $product->category->name }}</td>
                        <td class="px-4 py-3">{{ $product->brand?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $product->tracking->label() }}</td>
                        <td class="px-4 py-3">{{ $product->units_count }}</td>
                        <td class="px-4 py-3">
                            <x-row-actions :show="route('products.show', $product)" :edit="auth()->user()->can('update', $product) ? route('products.edit', $product) : null">
                                @can('delete', $product)
                                    <x-delete-form :action="route('products.destroy', $product)">
                                        <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                    </x-delete-form>
                                @endcan
                            </x-row-actions>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No products yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $products->links() }}</div>
</x-app-layout>
