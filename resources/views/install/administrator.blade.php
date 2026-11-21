<x-install-layout :step="3">
    <x-card class="p-6">
        <h2 class="mb-1 text-lg font-semibold">{{ __('Administrator and business') }}</h2>
        <p class="mb-4 text-sm text-gray-500">{{ __('The database is ready. Create the first administrator account.') }}</p>

        <form method="POST" action="{{ route('install.administrator.save') }}" class="space-y-5">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="name" :label="__('Your name')">
                    <x-text-input id="name" name="name" :value="old('name')" required autofocus autocomplete="name" />
                </x-field>
                <x-field name="email" :label="__('Email')">
                    <x-text-input id="email" name="email" type="email" :value="old('email')" required autocomplete="username" />
                </x-field>
                <x-field name="password" :label="__('Password')">
                    <x-text-input id="password" name="password" type="password" required autocomplete="new-password" />
                </x-field>
                <x-field name="password_confirmation" :label="__('Confirm Password')">
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                </x-field>
                <x-field name="company_name" :label="__('Business name')">
                    <x-text-input id="company_name" name="company_name" :value="old('company_name', $defaultCompany)" required />
                </x-field>
                <x-field name="currency" :label="__('Currency code')" :hint="__('Three letters, e.g. USD.')">
                    <x-text-input id="currency" name="currency" :value="old('currency', 'USD')" maxlength="3" required class="uppercase" />
                </x-field>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="demo_data" value="1" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('demo_data'))>
                <span>{{ __('Add sample equipment, customers and rentals so I can look around (you can delete them later).') }}</span>
            </label>

            <x-button>{{ __('Install') }}</x-button>
        </form>
    </x-card>
</x-install-layout>
