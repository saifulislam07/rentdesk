<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    @if (config('app.demo'))
    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
        <p class="text-sm font-medium text-gray-700">{{ __('Demo logins (click to fill)') }}</p>
        <table class="mt-2 w-full table-fixed border-collapse border border-gray-300 text-left text-xs text-gray-600">
            <thead>
                <tr class="bg-gray-100 text-xs uppercase text-gray-500">
                    <th class="w-1/5 border border-gray-300 px-1.5 py-1 font-medium">{{ __('Role') }}</th>
                    <th class="w-1/2 border border-gray-300 px-1.5 py-1 font-medium">{{ __('Email') }}</th>
                    <th class="border border-gray-300 px-1.5 py-1 font-medium">{{ __('Password') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ([['Admin', 'admin@gmail.com'], ['Manager', 'manager@rentdesk.test'], ['Staff', 'staff@rentdesk.test']] as [$demoRole, $demoEmail])
                    <tr class="cursor-pointer hover:bg-gray-100"
                        onclick="document.getElementById('email').value = '{{ $demoEmail }}'; document.getElementById('password').value = '111111';">
                        <td class="border border-gray-300 px-1.5 py-1.5 break-words font-medium">{{ $demoRole }}</td>
                        <td class="border border-gray-300 px-1.5 py-1.5 break-words">{{ $demoEmail }}</td>
                        <td class="border border-gray-300 px-1.5 py-1.5 break-words">111111</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</x-guest-layout>
