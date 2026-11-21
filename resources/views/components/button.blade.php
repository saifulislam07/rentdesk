@props(['variant' => 'primary', 'href' => null, 'size' => 'md'])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600',
        'secondary' => 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:outline-brand-600',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-md font-semibold shadow-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 '
        .$variants[$variant].' '.$sizes[$size];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
