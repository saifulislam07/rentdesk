<x-app-layout>
    <x-page-header :title="$kit->exists ? __('Edit kit') : __('Add kit')" />

    <x-card class="max-w-3xl p-6">
        <form method="POST" action="{{ $kit->exists ? route('kits.update', $kit) : route('kits.store') }}" class="space-y-5" x-data="{ rows: @js(array_values($rows)) }">
            @csrf
            @if ($kit->exists)
                @method('PUT')
            @endif

            <x-field name="name" :label="__('Name')">
                <x-text-input id="name" name="name" :value="old('name', $kit->name)" required autofocus />
            </x-field>

            <x-field name="price" :label="__('Flat price per day')" :hint="__('Leave empty to charge the sum of the products\' own rates.')">
                <x-text-input id="price" name="price" type="number" step="0.01" min="0" :value="old('price', $kit->price)" />
            </x-field>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Products in this kit') }}</legend>
                <x-input-error :messages="$errors->get('items')" class="mt-1" />

                <template x-for="(row, index) in rows" :key="index">
                    <div class="mt-3 flex items-start gap-3">
                        <div class="flex-1">
                            <select x-bind:name="`items[${index}][product_id]`" x-model="row.product_id" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm" x-bind:aria-label="@js(__('Product'))">
                                <option value="">{{ __('Select a product') }}</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="number" min="1" x-bind:name="`items[${index}][qty]`" x-model="row.qty" required class="block w-24 rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm" x-bind:aria-label="@js(__('Quantity'))">
                        <button type="button" class="py-2 text-sm text-red-600 hover:text-red-800" x-on:click="rows.splice(index, 1)" x-show="rows.length > 1">{{ __('Remove') }}</button>
                    </div>
                </template>

                @foreach ($errors->get('items.*') as $messages)
                    <x-input-error :messages="$messages" class="mt-1" />
                @endforeach

                <button type="button" class="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700" x-on:click="rows.push({ product_id: '', qty: 1 })">+ {{ __('Add product') }}</button>
            </fieldset>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('kits.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
