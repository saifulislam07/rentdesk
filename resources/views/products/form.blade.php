<x-app-layout>
    <x-page-header :title="$product->exists ? __('Edit product') : __('Add product')" />

    @php
        $rates = $product->rates->keyBy(fn ($rate) => $rate->period->value);
    @endphp

    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="space-y-6">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        <x-card class="p-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="name" :label="__('Name')" class="sm:col-span-2">
                    <x-text-input id="name" name="name" :value="old('name', $product->name)" required autofocus />
                </x-field>

                <x-field name="sku" :label="__('SKU')">
                    <x-text-input id="sku" name="sku" :value="old('sku', $product->sku)" required />
                </x-field>

                <x-field name="slug" :label="__('Slug')" :hint="__('Leave blank to generate from the name.')">
                    <x-text-input id="slug" name="slug" :value="old('slug', $product->slug)" />
                </x-field>

                <x-field name="category_id" :label="__('Category')">
                    <x-select id="category_id" name="category_id" required>
                        <option value="">{{ __('Select a category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="brand_id" :label="__('Brand')">
                    <x-select id="brand_id" name="brand_id">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="tracking" :label="__('Tracking')">
                    <x-select id="tracking" name="tracking" required>
                        @foreach (App\Enums\ProductTracking::cases() as $tracking)
                            <option value="{{ $tracking->value }}" @selected(old('tracking', $product->tracking?->value) === $tracking->value)>{{ $tracking->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="buffer_minutes" :label="__('Buffer time (minutes)')" :hint="__('Gap kept free between two rentals.')">
                    <x-text-input id="buffer_minutes" name="buffer_minutes" type="number" min="0" :value="old('buffer_minutes', $product->buffer_minutes)" required />
                </x-field>

                <x-field name="replacement_value" :label="__('Replacement value')">
                    <x-text-input id="replacement_value" name="replacement_value" type="number" step="0.01" min="0" :value="old('replacement_value', $product->replacement_value ?? 0)" required />
                </x-field>

                <x-field name="deposit_amount" :label="__('Deposit amount')">
                    <x-text-input id="deposit_amount" name="deposit_amount" type="number" step="0.01" min="0" :value="old('deposit_amount', $product->deposit_amount ?? 0)" required />
                </x-field>

                <x-field name="description" :label="__('Description')" class="sm:col-span-2">
                    <x-textarea id="description" name="description">{{ old('description', $product->description) }}</x-textarea>
                </x-field>
            </div>
        </x-card>

        <x-card>
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">{{ __('Rates') }}</h2>
                <p class="text-sm text-gray-500">{{ __('Leave the price empty for periods you do not offer.') }}</p>
            </div>

            <x-table>
                <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Period') }}</th>
                        <th class="px-4 py-3">{{ __('Price') }}</th>
                        <th class="px-4 py-3">{{ __('Minimum periods') }}</th>
                        <th class="px-4 py-3">{{ __('Weekend price') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach (App\Enums\RatePeriod::cases() as $period)
                        @php($rate = $rates->get($period->value))
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $period->label() }}</td>
                            <td class="px-4 py-3">
                                <x-text-input name="rates[{{ $period->value }}][price]" type="number" step="0.01" min="0" :value="old('rates.'.$period->value.'.price', $rate?->price)" />
                                <x-input-error :messages="$errors->get('rates.'.$period->value.'.price')" class="mt-1" />
                            </td>
                            <td class="px-4 py-3">
                                <x-text-input name="rates[{{ $period->value }}][min_qty_periods]" type="number" min="1" :value="old('rates.'.$period->value.'.min_qty_periods', $rate?->min_qty_periods ?? 1)" />
                                <x-input-error :messages="$errors->get('rates.'.$period->value.'.min_qty_periods')" class="mt-1" />
                            </td>
                            <td class="px-4 py-3">
                                <x-text-input name="rates[{{ $period->value }}][weekend_price]" type="number" step="0.01" min="0" :value="old('rates.'.$period->value.'.weekend_price', $rate?->weekend_price)" />
                                <x-input-error :messages="$errors->get('rates.'.$period->value.'.weekend_price')" class="mt-1" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        <div class="flex gap-2">
            <x-button>{{ __('Save') }}</x-button>
            <x-button variant="secondary" :href="route('products.index')">{{ __('Cancel') }}</x-button>
        </div>
    </form>
</x-app-layout>
