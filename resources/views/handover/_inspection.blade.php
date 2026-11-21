@php
    $key = "inspections.{$item->id}";
    $name = "inspections[{$item->id}]";
    $checked = old("{$key}.checklist", []);
@endphp

<x-card class="mb-6">
    <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="font-semibold text-gray-900">{{ $item->product->name }} · {{ $item->unit->serial_no }}</h2>
        <p class="text-xs text-gray-500">{{ __('Last meter reading: :reading', ['reading' => $item->unit->meter_reading ?? '—']) }}</p>
    </div>

    <div class="grid gap-5 p-6 sm:grid-cols-2">
        <x-field :name="$key.'.condition'" :for="'condition-'.$item->id" :label="__('Condition')">
            <x-select :id="'condition-'.$item->id" :name="$name.'[condition]'" required>
                @foreach (App\Enums\UnitCondition::cases() as $condition)
                    <option value="{{ $condition->value }}" @selected(old("{$key}.condition", $item->unit->condition->value) === $condition->value)>{{ $condition->label() }}</option>
                @endforeach
            </x-select>
        </x-field>

        <x-field :name="$key.'.meter_reading'" :for="'meter-'.$item->id" :label="__('Meter reading')">
            <x-text-input :id="'meter-'.$item->id" :name="$name.'[meter_reading]'" type="number" min="0" :value="old("{$key}.meter_reading", $item->unit->meter_reading)" />
        </x-field>

        <fieldset class="sm:col-span-2">
            <legend class="text-sm font-medium text-gray-700">{{ __('Checklist') }}</legend>
            <div class="mt-2 space-y-2">
                @foreach (App\Enums\ChecklistItem::cases() as $check)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="{{ $name }}[checklist][]" value="{{ $check->value }}" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(in_array($check->value, $checked))>
                        {{ $check->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-field :name="$key.'.notes'" :for="'notes-'.$item->id" :label="__('Notes')" class="sm:col-span-2">
            <x-textarea :id="'notes-'.$item->id" :name="$name.'[notes]'" rows="2">{{ old("{$key}.notes") }}</x-textarea>
        </x-field>

        <x-field :name="$key.'.photos'" :for="'photos-'.$item->id" :label="__('Photos')" :hint="__('Up to 6 images. On a phone this opens the camera.')" class="sm:col-span-2">
            <input id="photos-{{ $item->id }}" name="{{ $name }}[photos][]" type="file" accept="image/*" capture="environment" multiple class="block w-full text-sm text-gray-700">
            <x-input-error :messages="$errors->get($key.'.photos.*')" class="mt-1" />
        </x-field>

        @if ($type === 'in')
            <fieldset class="rounded-md border border-gray-200 p-4 sm:col-span-2">
                <legend class="px-1 text-sm font-medium text-gray-700">{{ __('Damage (leave empty if none)') }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :name="$key.'.damage.description'" :for="'damage-'.$item->id" :label="__('Description')" class="sm:col-span-2">
                        <x-textarea :id="'damage-'.$item->id" :name="$name.'[damage][description]'" rows="2">{{ old("{$key}.damage.description") }}</x-textarea>
                    </x-field>
                    <x-field :name="$key.'.damage.cost'" :for="'cost-'.$item->id" :label="__('Repair cost')">
                        <x-text-input :id="'cost-'.$item->id" :name="$name.'[damage][cost]'" type="number" step="0.01" min="0" :value="old("{$key}.damage.cost")" />
                    </x-field>
                    <x-field :name="$key.'.damage.charged'" :for="'charged-'.$item->id" :label="__('Charged to customer')">
                        <x-text-input :id="'charged-'.$item->id" :name="$name.'[damage][charged]'" type="number" step="0.01" min="0" :value="old("{$key}.damage.charged")" />
                    </x-field>
                </div>
            </fieldset>

            <label class="flex items-center gap-2 text-sm text-gray-700 sm:col-span-2">
                <input type="checkbox" name="{{ $name }}[needs_maintenance]" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old("{$key}.needs_maintenance"))>
                {{ __('Send this unit to maintenance') }}
            </label>
        @endif
    </div>
</x-card>
