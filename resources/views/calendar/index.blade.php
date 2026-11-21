<x-app-layout>
    <x-page-header :title="__('Calendar')">
        <x-slot name="actions">
            <label for="product_id" class="sr-only">{{ __('Product') }}</label>
            <x-select id="product_id" class="w-56">
                <option value="">{{ __('All products') }}</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                @endforeach
            </x-select>
        </x-slot>
    </x-page-header>

    <x-card class="p-4">
        <div id="calendar" data-events-url="{{ route('calendar.events') }}"></div>
    </x-card>

    <p class="mt-3 text-xs text-gray-500">{{ __('Times are shown in UTC. Blue: reserved, yellow: out.') }}</p>

    @push('scripts')
        @vite('resources/js/calendar.js')
    @endpush
</x-app-layout>
