<x-install-layout :step="1">
    <x-card class="p-6">
        <h2 class="mb-1 text-lg font-semibold">{{ __('Server requirements') }}</h2>
        <p class="mb-4 text-sm text-gray-500">{{ __('Everything marked as required must pass before you can continue.') }}</p>

        <ul class="divide-y divide-gray-100 text-sm">
            @foreach ($checks as $check)
                <li class="flex items-center justify-between gap-3 py-2">
                    <span>
                        {{ $check['label'] }}
                        @if ($check['hint'])
                            <span class="block text-xs text-gray-500">{{ $check['hint'] }}</span>
                        @endif
                    </span>
                    @if ($check['passed'])
                        <x-badge color="green">{{ __('OK') }}</x-badge>
                    @else
                        <x-badge :color="$check['required'] ? 'red' : 'yellow'">{{ $check['required'] ? __('Missing') : __('Optional') }}</x-badge>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            @if ($canContinue)
                <x-button :href="route('install.database')">{{ __('Continue') }}</x-button>
            @else
                <p class="mb-3 text-sm text-red-700" role="alert">{{ __('Fix the missing items above, then reload this page.') }}</p>
                <x-button variant="secondary" :href="route('install.welcome')">{{ __('Check again') }}</x-button>
            @endif
        </div>
    </x-card>
</x-install-layout>
