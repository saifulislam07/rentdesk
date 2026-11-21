<x-app-layout>
    <x-page-header :title="__('Edit invoice :number', ['number' => $invoice->number])" />

    @php
        $rows = old('items', $invoice->items->map(fn ($item): array => ['description' => $item->description, 'qty' => $item->qty, 'price' => $item->price])->all());
    @endphp

    <x-card class="max-w-4xl p-6">
        <form
            method="POST"
            action="{{ route('invoices.update', $invoice) }}"
            class="space-y-5"
            x-data="{
                rows: @js(array_values($rows)),
                discount: @js((string) old('discount', $invoice->discount)),
                tax: @js((string) old('tax', $invoice->tax)),
                get subtotal() { return this.rows.reduce((sum, row) => sum + (parseInt(row.qty) || 0) * (parseFloat(row.price) || 0), 0); },
                get total() { return this.subtotal - (parseFloat(this.discount) || 0) + (parseFloat(this.tax) || 0); },
            }"
        >
            @csrf
            @method('PUT')

            <p class="text-sm text-gray-600">
                {{ __('Rental :number for :customer.', ['number' => $invoice->rental->number, 'customer' => $invoice->rental->customer?->name ?? '—']) }}
            </p>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="issued_at" :label="__('Issued')">
                    <x-text-input id="issued_at" name="issued_at" type="date" :value="old('issued_at', $invoice->issued_at->toDateString())" required />
                </x-field>

                <x-field name="due_at" :label="__('Due')">
                    <x-text-input id="due_at" name="due_at" type="date" :value="old('due_at', $invoice->due_at->toDateString())" required />
                </x-field>
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Invoice lines') }}</legend>
                <x-input-error :messages="$errors->get('items')" class="mt-1" />

                <template x-for="(row, index) in rows" :key="index">
                    <div class="mt-3 flex items-start gap-3">
                        <div class="flex-1">
                            <input type="text" maxlength="255" x-bind:name="`items[${index}][description]`" x-model="row.description" required placeholder="{{ __('Description') }}" x-bind:aria-label="@js(__('Description'))" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                        </div>
                        <input type="number" min="1" x-bind:name="`items[${index}][qty]`" x-model="row.qty" required x-bind:aria-label="@js(__('Qty'))" class="block w-24 rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                        <input type="number" min="0" step="0.01" x-bind:name="`items[${index}][price]`" x-model="row.price" required x-bind:aria-label="@js(__('Price'))" class="block w-32 rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                        <button type="button" class="py-2 text-sm text-red-600 hover:text-red-800" x-on:click="rows.splice(index, 1)" x-show="rows.length > 1">{{ __('Remove') }}</button>
                    </div>
                </template>

                @foreach ($errors->get('items.*') as $messages)
                    <x-input-error :messages="$messages" class="mt-1" />
                @endforeach

                <button type="button" class="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700" x-on:click="rows.push({ description: '', qty: 1, price: '0.00' })">+ {{ __('Add line') }}</button>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="discount" :label="__('Discount')">
                    <x-text-input id="discount" name="discount" type="number" min="0" step="0.01" x-model="discount" required />
                </x-field>

                <x-field name="tax" :label="__('Tax')">
                    <x-text-input id="tax" name="tax" type="number" min="0" step="0.01" x-model="tax" required />
                </x-field>
            </div>

            <dl class="flex justify-end gap-6 border-t border-gray-200 pt-4 text-sm">
                <div><dt class="text-gray-500">{{ __('Subtotal') }}</dt><dd class="font-medium" x-text="subtotal.toFixed(2)"></dd></div>
                <div><dt class="text-gray-500">{{ __('Total') }}</dt><dd class="font-semibold">{{ $invoice->currency }} <span x-text="total.toFixed(2)"></span></dd></div>
            </dl>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('invoices.show', $invoice)">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
