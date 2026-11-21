<x-public-layout>
    <x-card class="p-6">
        <h2 class="mb-1 text-lg font-semibold text-gray-900">{{ __('Request a booking') }}</h2>
        <p class="mb-6 text-sm text-gray-500">{{ __('Tell us what you need and when. This is a request, not a reservation: we will confirm it with you.') }}</p>

        <form method="POST" action="{{ route('book.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="started" value="{{ $started }}">
            <x-input-error :messages="$errors->get('started')" />

            {{-- Decoy field: people never see it, bots fill it in. --}}
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website">{{ __('Leave this field empty') }}</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="name" :label="__('Your name')">
                    <x-text-input id="name" name="name" :value="old('name')" required autocomplete="name" />
                </x-field>

                <x-field name="email" :label="__('Email')">
                    <x-text-input id="email" name="email" type="email" :value="old('email')" required autocomplete="email" />
                </x-field>

                <x-field name="phone" :label="__('Phone')">
                    <x-text-input id="phone" name="phone" type="tel" :value="old('phone')" autocomplete="tel" />
                </x-field>

                <x-field name="location_id" :label="__('Pick-up location')">
                    <x-select id="location_id" name="location_id" required>
                        <option value="">{{ __('Select a location') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="product_id" :label="__('Equipment')">
                    <x-select id="product_id" name="product_id" required>
                        <option value="">{{ __('Select equipment') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="qty" :label="__('Quantity')">
                    <x-text-input id="qty" name="qty" type="number" min="1" max="100" :value="old('qty', 1)" required />
                </x-field>

                <x-field name="start_at" :label="__('From (UTC)')">
                    <x-text-input id="start_at" name="start_at" type="datetime-local" :value="old('start_at')" required />
                </x-field>

                <x-field name="end_at" :label="__('Until (UTC)')">
                    <x-text-input id="end_at" name="end_at" type="datetime-local" :value="old('end_at')" required />
                </x-field>
            </div>

            <x-field name="message" :label="__('Anything we should know?')">
                <x-textarea id="message" name="message">{{ old('message') }}</x-textarea>
            </x-field>

            <x-button>{{ __('Send request') }}</x-button>
        </form>
    </x-card>
</x-public-layout>
