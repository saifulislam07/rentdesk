<x-install-layout :step="4">
    <x-card class="p-6">
        <h2 class="mb-1 text-lg font-semibold text-green-700">{{ __('Installation complete') }}</h2>
        <p class="mb-5 text-sm text-gray-600">{{ __('You can now sign in as :email.', ['email' => $admin->email]) }}</p>

        <h3 class="mb-1 text-sm font-semibold">{{ __('One more step: the scheduler') }}</h3>
        <p class="mb-2 text-sm text-gray-600">{{ __('Late fees, overdue reminders and maintenance tasks run on a schedule. Add this cron job on your server, to run every minute:') }}</p>
        <pre class="mb-5 overflow-x-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100" dir="ltr">* * * * * php {{ $path }}/artisan schedule:run >> /dev/null 2>&1</pre>

        <h3 class="mb-1 text-sm font-semibold">{{ __('Brand logos') }}</h3>
        <p class="mb-5 text-sm text-gray-600">{{ __('If logos do not show, run this once in the project folder:') }} <code class="text-xs" dir="ltr">php artisan storage:link</code></p>

        <x-button :href="route('login')">{{ __('Go to sign in') }}</x-button>
    </x-card>
</x-install-layout>
