<x-app-layout>
    <x-page-header :title="__('Edit unit :serial', ['serial' => $unit->serial_no])" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ route('units.update', $unit) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-field name="serial_no" :label="__('Serial number')">
                <x-text-input id="serial_no" name="serial_no" :value="old('serial_no', $unit->serial_no)" required autofocus />
            </x-field>

            <x-field name="location_id" :label="__('Location')">
                <x-select id="location_id" name="location_id" required>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $unit->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="condition" :label="__('Condition')">
                    <x-select id="condition" name="condition" required>
                        @foreach (App\Enums\UnitCondition::cases() as $condition)
                            <option value="{{ $condition->value }}" @selected(old('condition', $unit->condition->value) === $condition->value)>{{ $condition->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="status" :label="__('Status')">
                    <x-select id="status" name="status" required>
                        @foreach (App\Enums\UnitStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $unit->status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </div>

            @if ($unit->product->tracking === App\Enums\ProductTracking::Bulk)
                <x-field name="quantity" :label="__('Quantity')">
                    <x-text-input id="quantity" name="quantity" type="number" min="1" :value="old('quantity', $unit->quantity)" />
                </x-field>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="purchased_at" :label="__('Purchase date')">
                    <x-text-input id="purchased_at" name="purchased_at" type="date" :value="old('purchased_at', $unit->purchased_at?->toDateString())" />
                </x-field>

                <x-field name="purchase_cost" :label="__('Purchase cost')">
                    <x-text-input id="purchase_cost" name="purchase_cost" type="number" step="0.01" min="0" :value="old('purchase_cost', $unit->purchase_cost)" />
                </x-field>

                <x-field name="meter_reading" :label="__('Meter reading')">
                    <x-text-input id="meter_reading" name="meter_reading" type="number" min="0" :value="old('meter_reading', $unit->meter_reading)" />
                </x-field>
            </div>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('products.show', $unit->product_id)">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
