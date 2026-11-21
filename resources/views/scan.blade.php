<x-app-layout>
    <x-page-header :title="__('Scan a unit')" />

    <x-card class="max-w-md p-6">
        <div
            id="scanner"
            class="overflow-hidden rounded-md bg-gray-100"
            data-scan-url="{{ route('units.scan', '__TOKEN__') }}"
            data-camera-error="{{ __('The camera is not available. Enter the code below instead.') }}"
        ></div>
        <p id="scan-message" class="mt-2 text-sm text-red-600" role="alert"></p>

        <form id="manual-scan" class="mt-4 flex gap-2">
            <label for="code" class="sr-only">{{ __('QR code') }}</label>
            <x-text-input id="code" name="code" :placeholder="__('Or type the QR code')" />
            <x-button variant="secondary">{{ __('Go') }}</x-button>
        </form>
    </x-card>

    <p class="mt-3 max-w-md text-xs text-gray-500">{{ __('Camera access needs a secure (HTTPS) connection on phones.') }}</p>

    @push('scripts')
        @vite('resources/js/scan.js')
    @endpush
</x-app-layout>
