@props(['label', 'name', 'hint' => null, 'for' => null])

<div {{ $attributes->class(["has-error" => $errors->has($name)]) }}>
    <x-input-label :for="$for ?? $name" :value="$label" />
    <div class="mt-1">
        {{ $slot }}
    </div>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
