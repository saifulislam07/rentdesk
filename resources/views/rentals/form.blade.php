<x-app-layout>
    <x-page-header :title="$rental->exists ? __('Edit quote :number', ['number' => $rental->number]) : __('New quote')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $rental->exists ? route('rentals.update', $rental) : route('rentals.store') }}" class="space-y-5">
            @csrf
            @if ($rental->exists)
                @method('PUT')
            @endif

            <x-field name="customer_id" :label="__('Customer')">
                <x-select id="customer_id" name="customer_id" required>
                    <option value="">{{ __('Select a customer') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $rental->customer_id) == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field name="location_id" :label="__('Location')">
                <x-select id="location_id" name="location_id" required>
                    <option value="">{{ __('Select a location') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $rental->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="start_at" :label="__('Start (UTC)')">
                    <x-text-input id="start_at" name="start_at" type="datetime-local" :value="old('start_at', $rental->start_at?->format('Y-m-d\TH:i'))" required />
                </x-field>

                <x-field name="end_at" :label="__('End (UTC)')">
                    <x-text-input id="end_at" name="end_at" type="datetime-local" :value="old('end_at', $rental->end_at?->format('Y-m-d\TH:i'))" required />
                </x-field>
            </div>

            <x-field name="discount" :label="__('Discount')">
                <x-text-input id="discount" name="discount" type="number" step="0.01" min="0" :value="old('discount', $rental->discount ?? 0)" />
            </x-field>

            <x-field name="notes" :label="__('Notes')">
                <x-textarea id="notes" name="notes">{{ old('notes', $rental->notes) }}</x-textarea>
            </x-field>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="$rental->exists ? route('rentals.show', $rental) : route('rentals.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
