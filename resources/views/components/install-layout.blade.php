@props(['step' => 1])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('rentdesk.locales.'.app()->getLocale().'.dir', 'ltr') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <title>{{ __('Install :app', ['app' => 'RentDesk']) }}</title>

        <x-js-config />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 font-sans text-gray-900 antialiased">
        <main class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ __('Install :app', ['app' => 'RentDesk']) }}</h1>
                <x-locale-switcher />
            </div>

            <ol class="mb-6 flex gap-2 text-xs font-medium" aria-label="{{ __('Installation steps') }}">
                @foreach ([1 => __('Requirements'), 2 => __('Database'), 3 => __('Administrator')] as $number => $label)
                    <li @class([
                        'flex-1 rounded-md px-3 py-2 text-center',
                        'bg-brand-600 text-white' => $number === $step,
                        'bg-brand-100 text-brand-900' => $number < $step,
                        'bg-gray-200 text-gray-500' => $number > $step,
                    ]) @if ($number === $step) aria-current="step" @endif>{{ $number }}. {{ $label }}</li>
                @endforeach
            </ol>

            {{ $slot }}
        </main>
    </body>
</html>
