<x-app-layout>
    <x-page-header :title="$user->exists ? __('Edit user') : __('Add user')" />

    <x-card class="max-w-2xl p-6">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="space-y-5">
            @csrf
            @if ($user->exists)
                @method('PUT')
            @endif

            <x-field name="name" :label="__('Name')">
                <x-text-input id="name" name="name" :value="old('name', $user->name)" required autofocus autocomplete="off" />
            </x-field>

            <x-field name="email" :label="__('Email')">
                <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="off" />
            </x-field>

            @php($isSelf = $user->exists && $user->is(auth()->user()))
            <x-field name="role" :label="__('Role')" :hint="$isSelf ? __('You cannot change your own role.') : __('Administrators can do everything. Managers can do everything except delete records and change settings. Staff can run rentals, customers and invoices.')">
                <x-select id="role" name="role" :disabled="$isSelf">
                    <option value="admin" @selected(old('role', $role) === 'admin')>{{ __('Administrator') }}</option>
                    <option value="manager" @selected(old('role', $role) === 'manager')>{{ __('Manager') }}</option>
                    <option value="staff" @selected(old('role', $role) === 'staff')>{{ __('Staff') }}</option>
                </x-select>
            </x-field>

            <x-field name="password" :label="__('Password')" :hint="$user->exists ? __('Leave blank to keep the current password.') : null">
                <x-text-input id="password" name="password" type="password" :required="! $user->exists" autocomplete="new-password" />
            </x-field>

            <x-field name="password_confirmation" :label="__('Confirm Password')">
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" :required="! $user->exists" autocomplete="new-password" />
            </x-field>

            <div class="flex gap-2">
                <x-button>{{ __('Save') }}</x-button>
                <x-button variant="secondary" :href="route('users.index')">{{ __('Cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
