<x-app-layout>
    <x-page-header :title="$customer->exists ? __('Edit customer') : __('Add customer')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="space-y-5">
            @csrf
            @if ($customer->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="type" :label="__('Type')">
                    <x-select id="type" name="type" required>
                        @foreach (App\Enums\CustomerType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('type', $customer->type?->value ?? 'person') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="name" :label="__('Name')">
                    <x-text-input id="name" name="name" :value="old('name', $customer->name)" required autofocus />
                </x-field>

                <x-field name="email" :label="__('Email')">
                    <x-text-input id="email" name="email" type="email" :value="old('email', $customer->email)" />
                </x-field>

                <x-field name="phone" :label="__('Phone')">
                    <x-text-input id="phone" name="phone" type="tel" :value="old('phone', $customer->phone)" />
                </x-field>

                <x-field name="address" :label="__('Address')">
                    <x-text-input id="address" name="address" :value="old('address', $customer->address)" />
                </x-field>

                <x-field name="tax_no" :label="__('Tax number')">
                    <x-text-input id="tax_no" name="tax_no" :value="old('tax_no', $customer->tax_no)" />
                </x-field>
            </div>

            <x-field name="notes" :label="__('Notes')">
                <x-textarea id="notes" name="notes">{{ old('notes', $customer->notes) }}</x-textarea>
            </x-field>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_blacklisted" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('is_blacklisted', $customer->is_blacklisted))>
                {{ __('Blacklisted (cannot be given new quotes or rentals)') }}
            </label>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="$customer->exists ? route('customers.show', $customer) : route('customers.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
