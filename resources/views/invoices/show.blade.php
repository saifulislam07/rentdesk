<x-app-layout>
    <x-page-header :title="$invoice->number">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('rentals.show', $invoice->rental)">{{ __('View rental') }}</x-button>
            <x-button variant="secondary" :href="route('invoices.pdf', $invoice)">{{ __('Download PDF') }}</x-button>
            <x-button type="button" variant="secondary" onclick="window.print()">{{ __('Print') }}</x-button>
            @if ($invoice->status->isEditable())
                @can('update', $invoice)
                    <x-button variant="secondary" :href="route('invoices.edit', $invoice)">{{ __('Edit') }}</x-button>
                @endcan
            @endif
            @can('update', $invoice)
                @if (in_array($invoice->status, [App\Enums\InvoiceStatus::Draft, App\Enums\InvoiceStatus::Sent], true))
                    <form method="POST" action="{{ route('invoices.paid', $invoice) }}">
                        @csrf
                        <x-button variant="secondary">{{ __('Mark as paid') }}</x-button>
                    </form>
                @endif
                @if ($invoice->status !== App\Enums\InvoiceStatus::Void)
                    <form method="POST" action="{{ route('invoices.send', $invoice) }}">
                        @csrf
                        <x-button>{{ $invoice->sent_at ? __('Email again') : __('Email to customer') }}</x-button>
                    </form>
                @endif
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-6 p-6">
        <dl class="grid gap-4 text-sm sm:grid-cols-4">
            <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></dd></div>
            <div><dt class="text-gray-500">{{ __('Customer') }}</dt><dd class="font-medium">{{ $invoice->rental->customer->name }}</dd></div>
            <div><dt class="text-gray-500">{{ __('Issued') }}</dt><dd class="font-medium">{{ $invoice->issued_at->toAppDate() }}</dd></div>
            <div><dt class="text-gray-500">{{ __('Due') }}</dt><dd class="font-medium">{{ $invoice->due_at->toAppDate() }}</dd></div>
        </dl>
        @if ($invoice->sent_at)
            <p class="mt-3 text-xs text-gray-500">{{ __('Last emailed :date', ['date' => $invoice->sent_at->toDayDateTimeString()]) }}</p>
        @endif
    </x-card>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Description') }}</th>
                    <th class="px-4 py-3">{{ __('Qty') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Price') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="px-4 py-3">{{ $item->description }}</td>
                        <td class="px-4 py-3">{{ $item->qty }}</td>
                        <td class="px-4 py-3 text-end">{{ $item->price }}</td>
                        <td class="px-4 py-3 text-end">{{ $item->lineTotal() }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="text-sm">
                <tr><td colspan="3" class="px-4 py-2 text-end text-gray-500">{{ __('Subtotal') }}</td><td class="px-4 py-2 text-end">{{ $invoice->subtotal }}</td></tr>
                <tr><td colspan="3" class="px-4 py-2 text-end text-gray-500">{{ __('Discount') }}</td><td class="px-4 py-2 text-end">−{{ $invoice->discount }}</td></tr>
                <tr><td colspan="3" class="px-4 py-2 text-end text-gray-500">{{ __('Tax') }}</td><td class="px-4 py-2 text-end">{{ $invoice->tax }}</td></tr>
                <tr class="font-semibold"><td colspan="3" class="px-4 py-3 text-end">{{ __('Total') }}</td><td class="px-4 py-3 text-end">{{ $invoice->currency }} {{ $invoice->total }}</td></tr>
            </tfoot>
        </x-table>
    </x-card>
</x-app-layout>
