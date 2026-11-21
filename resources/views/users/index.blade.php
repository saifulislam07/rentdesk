<x-app-layout>
    <x-page-header :title="__('Users')">
        <x-slot name="actions">
            <x-button :href="route('users.create')">{{ __('Add user') }}</x-button>
        </x-slot>
    </x-page-header>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Role') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    @php($role = $user->getRoleNames()->first())
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $user->name }}
                            @if ($user->is(auth()->user()))
                                <span class="text-xs font-normal text-gray-500">({{ __('you') }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <x-badge :color="match ($role) { 'admin' => 'red', 'manager' => 'blue', default => 'gray' }">
                                {{ match ($role) { 'admin' => __('Administrator'), 'manager' => __('Manager'), 'staff' => __('Staff'), default => '—' } }}
                            </x-badge>
                        </td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            <x-button variant="secondary" size="sm" :href="route('users.edit', $user)">{{ __('Edit') }}</x-button>
                            @unless ($user->is(auth()->user()))
                                <x-delete-form :action="route('users.destroy', $user)">
                                    <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                </x-delete-form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('No users yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
