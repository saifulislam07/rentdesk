<x-app-layout>
    <x-page-header :title="$category->exists ? __('Edit category') : __('Add category')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="space-y-5">
            @csrf
            @if ($category->exists)
                @method('PUT')
            @endif

            <x-field name="name" :label="__('Name')">
                <x-text-input id="name" name="name" :value="old('name', $category->name)" required autofocus />
            </x-field>

            <x-field name="slug" :label="__('Slug')" :hint="__('Leave blank to generate from the name.')">
                <x-text-input id="slug" name="slug" :value="old('slug', $category->slug)" />
            </x-field>

            <x-field name="parent_id" :label="__('Parent category')">
                <x-select id="parent_id" name="parent_id">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('categories.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
