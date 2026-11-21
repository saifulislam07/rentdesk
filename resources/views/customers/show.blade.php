<x-app-layout>
    <x-page-header :title="$customer->name">
        <x-slot name="actions">
            @can('create', App\Models\Rental::class)
                @unless ($customer->is_blacklisted)
                    <x-button variant="secondary" :href="route('rentals.create', ['customer_id' => $customer->id])">{{ __('New quote') }}</x-button>
                @endunless
            @endcan
            @can('update', $customer)
                <x-button :href="route('customers.edit', $customer)">{{ __('Edit') }}</x-button>
            @endcan
        </x-slot>
    </x-page-header>

    @if ($customer->is_blacklisted)
        <div role="alert" class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
            {{ __('This customer is blacklisted and cannot be given new quotes or rentals.') }}
        </div>
    @endif

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <x-card class="p-6 lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">{{ __('Type') }}</dt><dd class="font-medium">{{ $customer->type->label() }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Email') }}</dt><dd class="font-medium">{{ $customer->email ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Phone') }}</dt><dd class="font-medium">{{ $customer->phone ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Address') }}</dt><dd class="font-medium">{{ $customer->address ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Tax number') }}</dt><dd class="font-medium">{{ $customer->tax_no ?: '—' }}</dd></div>
            </dl>
            @if ($customer->notes)
                <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $customer->notes }}</p>
            @endif
        </x-card>

        <x-card class="p-6">
            <h2 class="mb-3 font-semibold text-gray-900">{{ __('ID documents') }}</h2>
            <ul class="space-y-3 text-sm">
                @forelse ($customer->documents as $document)
                    <li class="flex items-start justify-between gap-2">
                        <div>
                            <a href="{{ route('documents.show', $document) }}" class="font-medium hover:text-brand-600">{{ $document->type->label() }}</a>
                            <p class="text-xs text-gray-500">{{ $document->original_name }}</p>
                            <x-badge :color="$document->isVerified() ? 'green' : 'yellow'">
                                {{ $document->isVerified() ? __('Verified') : __('Unverified') }}
                            </x-badge>
                        </div>
                        @can('update', $customer)
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('documents.update', $document) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-button variant="secondary" size="sm">{{ $document->isVerified() ? __('Unverify') : __('Verify') }}</x-button>
                                </form>
                                <x-delete-form :action="route('documents.destroy', $document)">
                                    <x-button variant="danger" size="sm">{{ __('Delete') }}</x-button>
                                </x-delete-form>
                            </div>
                        @endcan
                    </li>
                @empty
                    <li class="text-gray-500">{{ __('No documents uploaded.') }}</li>
                @endforelse
            </ul>

            @can('update', $customer)
                <form method="POST" enctype="multipart/form-data" action="{{ route('customers.documents.store', $customer) }}" class="mt-5 space-y-3 border-t border-gray-100 pt-4">
                    @csrf
                    <x-field name="type" :label="__('Document type')">
                        <x-select id="type" name="type">
                            @foreach (App\Enums\DocumentType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field name="file" :label="__('File')" :hint="__('JPG, PNG or PDF, up to 5 MB.')">
                        <input id="file" name="file" type="file" accept=".jpg,.jpeg,.png,.pdf" required class="block w-full text-sm text-gray-700">
                    </x-field>
                    <x-button size="sm">{{ __('Upload') }}</x-button>
                </form>
            @endcan
        </x-card>
    </div>

    <x-card>
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">{{ __('Rental history') }}</h2>
        </div>

        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Period') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rentals as $rental)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('rentals.show', $rental) }}" class="hover:text-brand-600">{{ $rental->number }}</a></td>
                        <td class="px-4 py-3">{{ $rental->start_at->toAppDateTime() }} → {{ $rental->end_at->toAppDateTime() }}</td>
                        <td class="px-4 py-3"><x-badge :color="$rental->status->color()">{{ $rental->status->label() }}</x-badge></td>
                        <td class="px-4 py-3 text-end">{{ $rental->currency }} {{ $rental->total }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('No rentals yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $rentals->links() }}</div>
</x-app-layout>
