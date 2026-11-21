<x-app-layout>
    <x-page-header :title="$brand->exists ? __('Edit brand') : __('Add brand')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" enctype="multipart/form-data" action="{{ $brand->exists ? route('brands.update', $brand) : route('brands.store') }}" class="space-y-5">
            @csrf
            @if ($brand->exists)
                @method('PUT')
            @endif

            <x-field name="name" :label="__('Name')">
                <x-text-input id="name" name="name" :value="old('name', $brand->name)" required autofocus />
            </x-field>

            <x-field name="slug" :label="__('Slug')" :hint="__('Leave blank to generate from the name.')">
                <x-text-input id="slug" name="slug" :value="old('slug', $brand->slug)" />
            </x-field>

            <x-field name="logo" :label="__('Logo')">
                <input id="logo" name="logo" type="file" accept="image/*" class="block w-full text-sm text-gray-700">
            </x-field>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('brands.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
