@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 sm:text-sm']) }}>
