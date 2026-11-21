<x-app-layout>
    <x-page-header :title="__('Invoices')" />

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="px-4 py-3">{{ __('Issued') }}</th>
                    <th class="px-4 py-3">{{ __('Due') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Total') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('invoices.show', $invoice) }}" class="hover:text-brand-600">{{ $invoice->number }}</a></td>
                        <td class="px-4 py-3">{{ $invoice->rental->customer?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $invoice->issued_at->toAppDate() }}</td>
                        <td class="px-4 py-3">{{ $invoice->due_at->toAppDate() }}</td>
                        <td class="px-4 py-3"><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></td>
                        <td class="px-4 py-3 text-end">{{ $invoice->currency }} {{ $invoice->total }}</td>
                        <td class="px-4 py-3">
                            <x-row-actions :show="route('invoices.show', $invoice)" :edit="$invoice->status->isEditable() && auth()->user()->can('update', $invoice) ? route('invoices.edit', $invoice) : null" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No invoices yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-app-layout>
