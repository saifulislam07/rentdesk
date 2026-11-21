<x-app-layout>
    <x-page-header :title="__('Check out :number', ['number' => $rental->number])">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('rentals.show', $rental)">{{ __('Back') }}</x-button>
        </x-slot>
    </x-page-header>

    <p class="mb-6 text-sm text-gray-600">
        {{ __('Customer: :name', ['name' => $rental->customer->name]) }}
    </p>

    <x-input-error :messages="$errors->get('inspections')" class="mb-4" />

    <form method="POST" enctype="multipart/form-data" action="{{ route('rentals.check-out.store', $rental) }}">
        @csrf

        @forelse ($rental->items->whereNotNull('unit_id') as $item)
            @include('handover._inspection', ['item' => $item, 'type' => 'out'])
        @empty
            <p class="mb-6 text-sm text-gray-500">{{ __('This rental has no serial-tracked units to inspect.') }}</p>
        @endforelse

        <x-card class="mb-6 p-6">
            <h2 class="font-semibold text-gray-900">{{ __('Deposit') }}</h2>
            <p class="mb-4 text-sm text-gray-500">{{ __('Required: :currency :amount', ['currency' => $rental->currency, 'amount' => $rental->deposit_required]) }}</p>

            <div class="grid max-w-xl gap-5 sm:grid-cols-2">
                <x-field name="deposit_amount" :label="__('Amount received')">
                    <x-text-input id="deposit_amount" name="deposit_amount" type="number" step="0.01" min="0" :value="old('deposit_amount', $rental->deposit_required)" />
                </x-field>

                <x-field name="deposit_method" :label="__('Method')">
                    <x-select id="deposit_method" name="deposit_method">
                        <option value="">{{ __('Select') }}</option>
                        @foreach (App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}" @selected(old('deposit_method') === $method->value)>{{ $method->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </div>
        </x-card>

        @include('handover._signature', ['required' => true])

        <x-button>{{ __('Complete check-out') }}</x-button>
    </form>
</x-app-layout>
