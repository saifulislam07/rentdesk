<x-app-layout>
    <x-page-header :title="$location->exists ? __('Edit location') : __('Add location')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}" class="space-y-5">
            @csrf
            @if ($location->exists)
                @method('PUT')
            @endif

            <x-field name="name" :label="__('Name')">
                <x-text-input id="name" name="name" :value="old('name', $location->name)" required autofocus />
            </x-field>

            <x-field name="code" :label="__('Code')" :hint="__('A short unique code, e.g. MAIN.')">
                <x-text-input id="code" name="code" :value="old('code', $location->code)" maxlength="20" required />
            </x-field>

            <x-field name="address" :label="__('Address')">
                <x-text-input id="address" name="address" :value="old('address', $location->address)" />
            </x-field>

            <x-field name="phone" :label="__('Phone')">
                <x-text-input id="phone" name="phone" type="tel" :value="old('phone', $location->phone)" />
            </x-field>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('is_active', $location->is_active))>
                {{ __('Active') }}
            </label>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('locations.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
