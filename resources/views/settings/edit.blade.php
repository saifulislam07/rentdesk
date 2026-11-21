<x-app-layout>
    <x-page-header :title="__('Settings')" />

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-card class="p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">{{ __('Business') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="company_name" :label="__('Company name')">
                    <x-text-input id="company_name" name="company_name" :value="old('company_name', $values['company_name'])" required />
                </x-field>

                <x-field name="currency" :label="__('Currency')" :hint="__('Three-letter code, for example USD or BDT. Applies to new rentals.')">
                    <x-text-input id="currency" name="currency" maxlength="3" class="uppercase" :value="old('currency', $values['currency'])" required />
                </x-field>

                <x-field name="tax_rate" :label="__('Tax rate (%)')">
                    <x-text-input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" :value="old('tax_rate', $values['tax_rate'])" required />
                </x-field>

                <x-field name="invoice_due_days" :label="__('Invoice due after (days)')">
                    <x-text-input id="invoice_due_days" name="invoice_due_days" type="number" min="0" max="365" :value="old('invoice_due_days', $values['invoice_due_days'])" required />
                </x-field>
            </div>
        </x-card>

        <x-card class="p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">{{ __('Language and formats') }}</h2>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="locale" :label="__('Default language')">
                    <x-select id="locale" name="locale">
                        @foreach (config('rentdesk.locales') as $code => $language)
                            <option value="{{ $code }}" @selected(old('locale', $values['locale']) === $code)>{{ $language['name'] }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="date_format" :label="__('Date format')">
                    <x-select id="date_format" name="date_format">
                        @foreach ($dateFormats as $format)
                            <option value="{{ $format }}" @selected(old('date_format', $values['date_format']) === $format)>{{ $sample->format($format) }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="time_format" :label="__('Time format')">
                    <x-select id="time_format" name="time_format">
                        @foreach ($timeFormats as $format)
                            <option value="{{ $format }}" @selected(old('time_format', $values['time_format']) === $format)>{{ $sample->format($format) }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </div>
            <p class="mt-3 text-xs text-gray-500">{{ __('Times are stored and shown in UTC.') }}</p>
        </x-card>

        <x-card class="p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">{{ __('Late fees') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="late_fee_percent" :label="__('Late fee per day (% of the daily charge)')">
                    <x-text-input id="late_fee_percent" name="late_fee_percent" type="number" step="0.01" min="0" max="1000" :value="old('late_fee_percent', $values['late_fee_percent'])" required />
                </x-field>

                <x-field name="late_fee_grace_minutes" :label="__('Grace period (minutes)')">
                    <x-text-input id="late_fee_grace_minutes" name="late_fee_grace_minutes" type="number" min="0" max="10080" :value="old('late_fee_grace_minutes', $values['late_fee_grace_minutes'])" required />
                </x-field>
            </div>
        </x-card>

        <x-card class="p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">{{ __('Public booking') }}</h2>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="public_booking" value="0">
                <input type="checkbox" name="public_booking" value="1" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" @checked(old('public_booking', $values['public_booking']))>
                {{ __('Let visitors request a booking at :url', ['url' => route('book.create')]) }}
            </label>
        </x-card>

        <x-button>{{ __('Save settings') }}</x-button>
    </form>
</x-app-layout>
