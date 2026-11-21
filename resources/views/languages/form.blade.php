<x-app-layout>
    <x-page-header :title="$language->exists ? __('Edit language') : __('Add language')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" enctype="multipart/form-data" action="{{ $language->exists ? route('languages.update', $language) : route('languages.store') }}" class="space-y-5">
            @csrf
            @if ($language->exists)
                @method('PUT')
            @endif

            <x-field name="code" :label="__('Code')" :hint="$language->exists ? __('The code cannot be changed.') : __('A language code such as fr, ar or pt-BR.')">
                <x-text-input id="code" name="code" maxlength="12" :value="old('code', $language->code)" :disabled="$language->exists" :required="! $language->exists" />
            </x-field>

            <x-field name="name" :label="__('Name')" :hint="__('Shown in the language menu, in the language itself, for example Français.')">
                <x-text-input id="name" name="name" :value="old('name', $language->name)" required />
            </x-field>

            <x-field name="dir" :label="__('Text direction')">
                <x-select id="dir" name="dir">
                    <option value="ltr" @selected(old('dir', $language->dir) === 'ltr')>{{ __('Left to right') }}</option>
                    <option value="rtl" @selected(old('dir', $language->dir) === 'rtl')>{{ __('Right to left') }}</option>
                </x-select>
            </x-field>

            @unless ($language->exists && $language->isBase())
                <x-field name="translations" :label="__('Translation file (JSON)')" :hint="__('Optional. Download the file from the language list, translate it and upload it here.')">
                    <input id="translations" name="translations" type="file" accept=".json,application/json" class="block w-full text-sm text-gray-700">
                </x-field>
            @endunless

            @if ($language->exists && $language->isProtected())
                <p class="text-sm text-gray-500">{{ __('This is the base or the default language, so it always stays active.') }}</p>
            @else
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" @checked(old('is_active', $language->is_active))>
                    {{ __('Offer this language in the language menu') }}
                </label>
            @endif

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('languages.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
