<x-app-layout>
    <x-page-header :title="__('Maintenance')" />

    <form method="GET" action="{{ route('maintenance.index') }}" class="mb-4 flex gap-2">
        <x-select name="status" class="max-w-xs" aria-label="{{ __('Status') }}">
            @foreach (App\Enums\MaintenanceStatus::cases() as $option)
                <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </x-select>
        <x-button variant="secondary">{{ __('Filter') }}</x-button>
    </form>

    <x-card>
        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Unit') }}</th>
                    <th class="px-4 py-3">{{ __('Location') }}</th>
                    <th class="px-4 py-3">{{ __('Due') }}</th>
                    <th class="px-4 py-3">{{ __('Source') }}</th>
                    <th class="px-4 py-3">{{ __('Notes') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($tasks as $task)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('units.show', $task->unit) }}" class="hover:text-brand-600">{{ $task->unit->product->name }} · {{ $task->unit->serial_no }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $task->unit->location->name }}</td>
                        <td class="px-4 py-3">{{ $task->due_at->toAppDate() }}</td>
                        <td class="px-4 py-3">{{ $task->plan_id ? __('Plan') : __('Manual') }}</td>
                        <td class="max-w-xs px-4 py-3">{{ $task->notes ?: '—' }}@if ($task->cost !== null) <span class="text-gray-500">({{ $task->cost }})</span>@endif</td>
                        <td class="px-4 py-3"><x-badge :color="$task->status->color()">{{ $task->status->label() }}</x-badge></td>
                        <td class="px-4 py-3">
                            @can('update', $task)
                                @if ($task->status === App\Enums\MaintenanceStatus::Open)
                                    <form method="POST" action="{{ route('maintenance.complete', $task) }}" class="flex flex-wrap items-center justify-end gap-2">
                                        @csrf
                                        <x-text-input name="cost" type="number" step="0.01" min="0" :placeholder="__('Cost')" class="!w-24" aria-label="{{ __('Cost') }}" />
                                        <x-button size="sm">{{ __('Complete') }}</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('maintenance.cancel', $task) }}" class="mt-1 flex justify-end">
                                        @csrf
                                        <button class="text-xs text-gray-500 hover:text-gray-700">{{ __('Cancel task') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No tasks.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $tasks->links() }}</div>
</x-app-layout>
