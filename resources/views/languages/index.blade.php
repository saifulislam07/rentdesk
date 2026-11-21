<x-app-layout>
    <x-page-header :title="__('Languages')">
        <x-slot name="actions">
            <x-button :href="route('languages.create')">{{ __('Add language') }}</x-button>
        </x-slot>
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Language') }}</th>
                    <th class="px-4 py-3">{{ __('Code') }}</th>
                    <th class="px-4 py-3">{{ __('Direction') }}</th>
                    <th class="px-4 py-3">{{ __('Translated') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($languages as $language)
                    @php($done = $progress[$language->code])
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $language->name }}</td>
                        <td class="px-4 py-3">{{ $language->code }}</td>
                        <td class="px-4 py-3">{{ $language->dir === 'rtl' ? __('Right to left') : __('Left to right') }}</td>
                        <td class="px-4 py-3">{{ $done['translated'] }} / {{ $done['total'] }}</td>
                        <td class="px-4 py-3">
                            @if ($language->isDefault())
                                <x-badge color="blue">{{ __('Default') }}</x-badge>
                            @elseif ($language->is_active)
                                <x-badge color="green">{{ __('Active') }}</x-badge>
                            @else
                                <x-badge>{{ __('Inactive') }}</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <x-button variant="secondary" size="sm" :href="route('languages.export', $language)">{{ __('Download') }}</x-button>
                                <x-button variant="secondary" size="sm" :href="route('languages.edit', $language)">{{ __('Edit') }}</x-button>
                                @unless ($language->isProtected())
                                    <x-delete-form :action="route('languages.destroy', $language)">
                                        <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                    </x-delete-form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>

    <p class="mt-4 max-w-3xl text-sm text-gray-500">{{ __('To add a translation: add the language, download its file, translate the text on the right of each line, then edit the language and upload the file. Anything left untranslated is shown in English.') }}</p>
</x-app-layout>
