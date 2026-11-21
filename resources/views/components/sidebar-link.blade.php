@props(['active' => false])

<a {{ $attributes->class([
    'block rounded-md px-3 py-2 text-sm font-medium transition',
    'bg-brand-600 text-white' => $active,
    'text-gray-300 hover:bg-gray-800 hover:text-white' => ! $active,
]) }}>
    {{ $slot }}
</a>
