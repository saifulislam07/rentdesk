<x-app-layout>
    <x-page-header :title="__('Booking requests')" />

    <form method="GET" action="{{ route('booking-requests.index') }}" class="mb-4 flex gap-2">
        <x-select name="status" class="max-w-xs" aria-label="{{ __('Status') }}">
            @foreach (App\Enums\BookingRequestStatus::cases() as $option)
                <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </x-select>
        <x-button variant="secondary">{{ __('Filter') }}</x-button>
    </form>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Received') }}</th>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="px-4 py-3">{{ __('Request') }}</th>
                    <th class="px-4 py-3">{{ __('Period (UTC)') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $booking)
                    <tr class="align-top">
                        <td class="px-4 py-3">{{ $booking->created_at->toAppDateTime() }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $booking->name }}</div>
                            <div class="text-xs text-gray-500">{{ $booking->email }}@if ($booking->phone) · {{ $booking->phone }}@endif</div>
                        </td>
                        <td class="px-4 py-3">
                            {{ $booking->qty }} × {{ $booking->product->name }}
                            <div class="text-xs text-gray-500">{{ $booking->location->name }}</div>
                            @if ($booking->message)
                                <p class="mt-1 max-w-xs whitespace-pre-line text-xs text-gray-600">{{ $booking->message }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $booking->start_at->toAppDateTime() }} → {{ $booking->end_at->toAppDateTime() }}</td>
                        <td class="px-4 py-3">
                            <x-badge :color="$booking->status->color()">{{ $booking->status->label() }}</x-badge>
                            @if ($booking->rental)
                                <a href="{{ route('rentals.show', $booking->rental) }}" class="block text-xs text-brand-600 hover:underline">{{ $booking->rental->number }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @can('update', $booking)
                                @if ($booking->isNew())
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('booking-requests.convert', $booking) }}">
                                            @csrf
                                            <x-button size="sm">{{ __('Create quote') }}</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('booking-requests.reject', $booking) }}" data-confirm="{{ __('Are you sure?') }}">
                                            @csrf
                                            <x-button variant="secondary" size="sm">{{ __('Reject') }}</x-button>
                                        </form>
                                    </div>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No requests.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $requests->links() }}</div>
</x-app-layout>
