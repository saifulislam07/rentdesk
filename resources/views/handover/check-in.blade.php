<x-app-layout>
    <x-page-header :title="__('Check in :number', ['number' => $rental->number])">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('rentals.show', $rental)">{{ __('Back') }}</x-button>
        </x-slot>
    </x-page-header>

    <p class="mb-6 text-sm text-gray-600">
        {{ __('Customer: :name', ['name' => $rental->customer->name]) }}
    </p>

    <x-input-error :messages="$errors->get('inspections')" class="mb-4" />

    <form method="POST" enctype="multipart/form-data" action="{{ route('rentals.check-in.store', $rental) }}">
        @csrf

        @forelse ($rental->items->whereNotNull('unit_id') as $item)
            @include('handover._inspection', ['item' => $item, 'type' => 'in'])
        @empty
            <p class="mb-6 text-sm text-gray-500">{{ __('This rental has no serial-tracked units to inspect.') }}</p>
        @endforelse

        @include('handover._signature', ['required' => false])

        <x-button>{{ __('Complete check-in') }}</x-button>
    </form>
</x-app-layout>
