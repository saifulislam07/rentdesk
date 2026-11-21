<x-app-layout>
    <x-page-header :title="__('Stock transfers')">
        @can('create', App\Models\StockTransfer::class)
            <x-slot name="actions">
                <x-button :href="route('transfers.create')">{{ __('New transfer') }}</x-button>
            </x-slot>
        @endcan
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Unit') }}</th>
                    <th class="px-4 py-3">{{ __('From') }}</th>
                    <th class="px-4 py-3">{{ __('To') }}</th>
                    <th class="px-4 py-3">{{ __('Sent') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('units.show', $transfer->unit) }}" class="hover:text-brand-600">{{ $transfer->unit->product->name }} · {{ $transfer->unit->serial_no }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $transfer->fromLocation->name }}</td>
                        <td class="px-4 py-3">{{ $transfer->toLocation->name }}</td>
                        <td class="px-4 py-3">{{ $transfer->sent_at->toAppDateTime() }}</td>
                        <td class="px-4 py-3"><x-badge :color="$transfer->status->color()">{{ $transfer->status->label() }}</x-badge></td>
                        <td class="px-4 py-3">
                            @can('update', $transfer)
                                @if ($transfer->status === App\Enums\TransferStatus::InTransit)
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('transfers.receive', $transfer) }}">
                                            @csrf
                                            <x-button size="sm">{{ __('Receive') }}</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('transfers.cancel', $transfer) }}">
                                            @csrf
                                            <x-button variant="secondary" size="sm">{{ __('Cancel') }}</x-button>
                                        </form>
                                    </div>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No transfers yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $transfers->links() }}</div>
</x-app-layout>
