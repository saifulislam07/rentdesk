<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('rentdesk.locales.'.app()->getLocale().'.dir', 'ltr') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <title>{{ config('rentdesk.company_name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <x-js-config />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 font-sans text-gray-900 antialiased">
        <main class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">{{ config('rentdesk.company_name') }}</h1>
                <x-locale-switcher />
            </div>

            <x-flash />

            {{ $slot }}
        </main>
    </body>
</html>
