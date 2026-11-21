<x-app-layout>
    <x-page-header :title="__('Add units to :product', ['product' => $product->name])" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ route('products.units.store', $product) }}" class="space-y-5">
            @csrf

            <x-field name="serials" :label="__('Serial numbers')" :hint="__('One per line, or separated by commas. A unit is created for each.')">
                <x-textarea id="serials" name="serials" rows="8" required autofocus>{{ old('serials') }}</x-textarea>
            </x-field>

            <x-field name="location_id" :label="__('Location')">
                <x-select id="location_id" name="location_id" required>
                    <option value="">{{ __('Select a location') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field name="condition" :label="__('Condition')">
                <x-select id="condition" name="condition" required>
                    @foreach (App\Enums\UnitCondition::cases() as $condition)
                        <option value="{{ $condition->value }}" @selected(old('condition', 'new') === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </x-select>
            </x-field>

            @if ($product->tracking === App\Enums\ProductTracking::Bulk)
                <x-field name="quantity" :label="__('Quantity')">
                    <x-text-input id="quantity" name="quantity" type="number" min="1" :value="old('quantity', 1)" />
                </x-field>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="purchased_at" :label="__('Purchase date')">
                    <x-text-input id="purchased_at" name="purchased_at" type="date" :value="old('purchased_at')" />
                </x-field>

                <x-field name="purchase_cost" :label="__('Purchase cost')">
                    <x-text-input id="purchase_cost" name="purchase_cost" type="number" step="0.01" min="0" :value="old('purchase_cost')" />
                </x-field>
            </div>

            <div class="flex gap-2">
                <x-button>{{ __('Add units') }}</x-button>
                <x-button variant="secondary" :href="route('products.show', $product)">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
