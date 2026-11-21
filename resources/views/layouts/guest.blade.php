<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('rentdesk.locales.'.app()->getLocale().'.dir', 'ltr') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <x-js-config />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            <aside class="hidden flex-col justify-between bg-brand-900 p-12 text-white lg:flex">
                <a href="/" class="flex items-center gap-3 text-xl font-semibold">
                    <x-application-logo class="h-10 w-10 fill-current text-white" />
                    {{ config('app.name', 'RentDesk') }}
                </a>

                <p class="max-w-xs text-sm text-brand-100">{{ __('Sign in to manage your rentals.') }}</p>

                <p class="text-sm text-brand-100">&copy; {{ date('Y') }} {{ config('app.name', 'RentDesk') }}</p>
            </aside>

            <main class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-6 py-10 lg:min-h-0">
                <div class="mb-6 flex w-full max-w-sm items-center justify-between">
                    <a href="/" class="flex items-center gap-2 font-semibold text-gray-800 lg:invisible">
                        <x-application-logo class="h-8 w-8 fill-current text-brand-600" />
                        {{ config('app.name', 'RentDesk') }}
                    </a>
                    <x-locale-switcher />
                </div>

                <div class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
