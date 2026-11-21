<x-app-layout>
    <x-page-header :title="__('New transfer')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ route('transfers.store') }}" class="space-y-5">
            @csrf

            <x-field name="unit_id" :label="__('Unit')" :hint="__('Only available units without upcoming bookings can be moved.')">
                <x-select id="unit_id" name="unit_id" required>
                    <option value="">{{ __('Select a unit') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id', request('unit_id')) == $unit->id)>{{ $unit->product->name }} · {{ $unit->serial_no }} ({{ $unit->location->name }})</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field name="to_location_id" :label="__('Destination')">
                <x-select id="to_location_id" name="to_location_id" required>
                    <option value="">{{ __('Select a location') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('to_location_id') == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="flex gap-2">
                <x-button>{{ __('Send') }}</x-button>
                <x-button variant="secondary" :href="route('transfers.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
