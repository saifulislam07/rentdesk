<x-app-layout>
    <x-page-header :title="__('Customers')">
        @can('create', App\Models\Customer::class)
            <x-slot name="actions">
                <x-button :href="route('customers.create')">{{ __('Add customer') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('customers.index') }}" class="mb-4 flex gap-2">
        <x-text-input name="q" type="search" :value="request('q')" :placeholder="__('Search name, email or phone')" class="max-w-sm" />
        <x-button variant="secondary">{{ __('Search') }}</x-button>
    </form>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Phone') }}</th>
                    <th class="px-4 py-3">{{ __('Rentals') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('customers.show', $customer) }}" class="hover:text-brand-600">{{ $customer->name }}</a>
                            @if ($customer->is_blacklisted)
                                <x-badge color="red" class="ms-2">{{ __('Blacklisted') }}</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $customer->type->label() }}</td>
                        <td class="px-4 py-3">{{ $customer->email ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $customer->phone ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $customer->rentals_count }}</td>
                        <td class="px-4 py-3">
                            <x-row-actions :show="route('customers.show', $customer)" :edit="auth()->user()->can('update', $customer) ? route('customers.edit', $customer) : null">
                                @can('delete', $customer)
                                    <x-delete-form :action="route('customers.destroy', $customer)">
                                        <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                    </x-delete-form>
                                @endcan
                            </x-row-actions>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No customers found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $customers->links() }}</div>
</x-app-layout>
