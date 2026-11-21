<x-app-layout>
    <x-page-header :title="__('Categories')">
        @can('create', App\Models\Category::class)
            <x-slot name="actions">
                <x-button :href="route('categories.create')">{{ __('Add category') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Parent') }}</th>
                    <th class="px-4 py-3">{{ __('Products') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $category->name }}</td>
                        <td class="px-4 py-3">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $category->products_count }}</td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            @can('update', $category)
                                <x-button variant="secondary" size="sm" :href="route('categories.edit', $category)">{{ __('Edit') }}</x-button>
                            @endcan
                            @can('delete', $category)
                                <x-delete-form :action="route('categories.destroy', $category)">
                                    <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                </x-delete-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('No categories yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $categories->links() }}</div>
</x-app-layout>
