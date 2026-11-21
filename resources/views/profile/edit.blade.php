<x-app-layout>
    <x-page-header :title="__('Profile')" />

    <div class="max-w-4xl space-y-6">
        <x-card class="p-6">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-card>

        <x-card class="p-6">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-card>

        <x-card class="p-6">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </x-card>
    </div>
</x-app-layout>
