<x-app-layout>
    <x-page-header :title="$rental->number">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('rentals.contract', $rental)">{{ __('Contract PDF') }}</x-button>

            @can('update', $rental)
                @if ($rental->status->canCheckOut())
                    <x-button :href="route('rentals.check-out', $rental)">{{ __('Check out') }}</x-button>
                @endif
                @if ($rental->status->canCheckIn())
                    <x-button :href="route('rentals.check-in', $rental)">{{ __('Check in') }}</x-button>
                @endif
                @if ($rental->status->isEditable())
                    <x-button variant="secondary" :href="route('rentals.edit', $rental)">{{ __('Edit') }}</x-button>
                    <form method="POST" action="{{ route('rentals.confirm', $rental) }}">
                        @csrf
                        <x-button>{{ __('Confirm & reserve') }}</x-button>
                    </form>
                @endif
                @if ($rental->status->isCancellable())
                    <form method="POST" action="{{ route('rentals.cancel', $rental) }}" data-confirm="{{ __('Are you sure?') }}">
                        @csrf
                        <x-button variant="danger">{{ __('Cancel rental') }}</x-button>
                    </form>
                @endif
            @endcan

            @can('create', App\Models\Invoice::class)
                @unless ($rental->status->isEditable() || $rental->status === App\Enums\RentalStatus::Cancelled)
                    <form method="POST" action="{{ route('rentals.invoices.store', $rental) }}">
                        @csrf
                        <x-button variant="secondary">{{ $rental->invoices->isNotEmpty() ? __('Open invoice') : __('Create invoice') }}</x-button>
                    </form>
                @endunless
            @endcan
        </x-slot>
    </x-page-header>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <x-card class="p-6 lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-badge :color="$rental->status->color()">{{ $rental->status->label() }}</x-badge>@if ($rental->isOverdue()) <x-badge color="red">{{ __('Overdue') }}</x-badge> @endif</dd></div>
                <div>
                    <dt class="text-gray-500">{{ __('Customer') }}</dt>
                    <dd class="font-medium"><a href="{{ route('customers.show', $rental->customer) }}" class="hover:text-brand-600">{{ $rental->customer->name }}</a></dd>
                </div>
                <div><dt class="text-gray-500">{{ __('Location') }}</dt><dd class="font-medium">{{ $rental->location->name }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Period (UTC)') }}</dt><dd class="font-medium">{{ $rental->start_at->toAppDateTime() }} → {{ $rental->end_at->toAppDateTime() }}</dd></div>
            </dl>
            @if ($rental->notes)
                <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $rental->notes }}</p>
            @endif
        </x-card>

        <x-card class="p-6">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Subtotal') }}</dt><dd>{{ $rental->subtotal }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Discount') }}</dt><dd>−{{ $rental->discount }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Tax') }}</dt><dd>{{ $rental->tax }}</dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold"><dt>{{ __('Total') }}</dt><dd>{{ $rental->currency }} {{ $rental->total }}</dd></div>
                <div class="flex justify-between text-gray-500"><dt>{{ __('Deposit required') }}</dt><dd>{{ $rental->deposit_required }}</dd></div>
                @if ($rental->lateFee)
                    <div class="flex justify-between font-medium text-red-700"><dt>{{ __('Late fee (:days days)', ['days' => $rental->lateFee->days_late]) }}</dt><dd>{{ $rental->lateFee->amount }}</dd></div>
                @endif
            </dl>
        </x-card>
    </div>

    <x-card>
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">{{ __('Items') }}</h2>
        </div>

        <x-table>
            <thead class="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Unit') }}</th>
                    <th class="px-4 py-3">{{ __('Qty') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Rate') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Amount') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rental->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $item->product->name }}@if ($item->kit) <x-badge color="blue" class="ms-1">{{ $item->kit->name }}</x-badge>@endif</td>
                        <td class="px-4 py-3">{{ $item->unit?->serial_no ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $item->qty }}</td>
                        <td class="px-4 py-3 text-end">{{ $item->rate }}</td>
                        <td class="px-4 py-3 text-end">{{ $item->amount }}</td>
                        <td class="px-4 py-3">
                            @if ($rental->status->isEditable())
                                @can('update', $rental)
                                    <div class="flex justify-end">
                                        <x-delete-form :action="route('items.destroy', $item)">
                                            <x-button variant="danger" size="sm">{{ __('Remove') }}</x-button>
                                        </x-delete-form>
                                    </div>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No items yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>

        @if ($rental->status->isEditable())
            @can('update', $rental)
                <form method="POST" action="{{ route('rentals.items.store', $rental) }}" class="grid gap-4 border-t border-gray-200 p-6 sm:grid-cols-4">
                    @csrf
                    <x-field name="product_id" :label="__('Product')" class="sm:col-span-2">
                        <x-select id="product_id" name="product_id" required>
                            <option value="">{{ __('Select a product') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field name="qty" :label="__('Quantity')">
                        <x-text-input id="qty" name="qty" type="number" min="1" max="100" :value="old('qty', 1)" required />
                    </x-field>
                    <div class="flex items-end">
                        <x-button>{{ __('Add item') }}</x-button>
                    </div>
                </form>

                @if ($kits->isNotEmpty())
                    <form method="POST" action="{{ route('rentals.kits.store', $rental) }}" class="grid gap-4 border-t border-gray-200 p-6 sm:grid-cols-4">
                        @csrf
                        <x-field name="kit_id" :label="__('Kit')" class="sm:col-span-2">
                            <x-select id="kit_id" name="kit_id" required>
                                <option value="">{{ __('Select a kit') }}</option>
                                @foreach ($kits as $kit)
                                    <option value="{{ $kit->id }}" @selected(old('kit_id') == $kit->id)>{{ $kit->name }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                        <x-field name="qty" for="kit_qty" :label="__('Quantity')">
                            <x-text-input id="kit_qty" name="qty" type="number" min="1" max="100" value="1" required />
                        </x-field>
                        <div class="flex items-end">
                            <x-button variant="secondary">{{ __('Add kit') }}</x-button>
                        </div>
                    </form>
                @endif

                @foreach ($rental->items->pluck('kit')->filter()->unique('id') as $usedKit)
                    <x-delete-form :action="route('rentals.kits.destroy', [$rental, $usedKit])" class="flex items-center gap-3 border-t border-gray-100 px-6 py-3 text-sm">
                        <span class="text-gray-600">{{ $usedKit->name }}</span>
                        <button class="text-red-600 hover:text-red-800">{{ __('Remove kit') }}</button>
                    </x-delete-form>
                @endforeach
            @endcan
        @endif
    </x-card>

    @if ($rental->deposit || $rental->status->canSettle())
        <x-card class="mt-6 p-6">
            <h2 class="mb-3 font-semibold text-gray-900">{{ __('Deposit') }}</h2>

            @if ($rental->deposit)
                <dl class="grid gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-gray-500">{{ __('Held') }}</dt><dd class="font-medium">{{ $rental->currency }} {{ $rental->deposit->amount }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Method') }}</dt><dd class="font-medium">{{ $rental->deposit->method->label() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-badge :color="$rental->deposit->status->color()">{{ $rental->deposit->status->label() }}</x-badge></dd></div>
                    <div><dt class="text-gray-500">{{ __('Refunded') }}</dt><dd class="font-medium">{{ $rental->deposit->refunded_amount }}</dd></div>
                </dl>
            @else
                <p class="text-sm text-gray-500">{{ __('No deposit was taken.') }}</p>
            @endif

            @can('update', $rental)
                @if ($rental->status->canSettle())
                    @php
                        $suggested = app(App\Services\DepositService::class)->suggestedRefund($rental);
                    @endphp
                    <form method="POST" action="{{ route('rentals.settle', $rental) }}" class="mt-5 flex flex-wrap items-end gap-4 border-t border-gray-100 pt-4">
                        @csrf
                        @if ($rental->deposit)
                            <x-field name="refund_amount" :label="__('Refund to customer')" :hint="__('Held minus damage (:damage) and late fee (:late).', ['damage' => $rental->chargedDamageTotal(), 'late' => $rental->lateFee?->amount ?? '0.00'])">
                                <x-text-input id="refund_amount" name="refund_amount" type="number" step="0.01" min="0" :value="old('refund_amount', $suggested)" />
                            </x-field>
                        @endif
                        <x-button>{{ __('Settle & close rental') }}</x-button>
                    </form>
                @endif
            @endcan
        </x-card>
    @endif

    @if ($rental->damages->isNotEmpty())
        <x-card class="mt-6">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">{{ __('Damage') }}</h2>
            </div>
            <x-table>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rental->damages as $damage)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $damage->unit->serial_no }}</td>
                            <td class="px-4 py-3">{{ $damage->description }}</td>
                            <td class="px-4 py-3 text-end">{{ __('Cost') }} {{ $damage->cost }}</td>
                            <td class="px-4 py-3 text-end">{{ __('Charged') }} {{ $damage->charged_amount }}</td>
                            <td class="px-4 py-3"><x-badge :color="$damage->status->color()">{{ $damage->status->label() }}</x-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
    @endif

    @if ($rental->inspections->isNotEmpty())
        <x-card class="mt-6">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">{{ __('Inspections') }}</h2>
            </div>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($rental->inspections as $inspection)
                    <li class="space-y-2 px-6 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :color="$inspection->type->value === 'out' ? 'yellow' : 'green'">{{ $inspection->type->label() }}</x-badge>
                            <span class="font-medium">{{ $inspection->unit->serial_no }}</span>
                            <x-badge :color="$inspection->condition->color()">{{ $inspection->condition->label() }}</x-badge>
                            @if ($inspection->meter_reading !== null)
                                <span class="text-gray-500">{{ __('Meter') }} {{ $inspection->meter_reading }}</span>
                            @endif
                            <span class="text-xs text-gray-500">{{ $inspection->created_at->toAppDateTime() }} · {{ $inspection->inspector?->name }}</span>
                        </div>
                        @if ($inspection->notes)
                            <p class="text-gray-700">{{ $inspection->notes }}</p>
                        @endif
                        <div class="flex flex-wrap gap-3">
                            @foreach ($inspection->photos as $photo)
                                <a href="{{ route('inspection-photos.show', $photo) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('inspection-photos.show', $photo) }}" alt="{{ __('Inspection photo') }}" class="h-20 w-20 rounded object-cover ring-1 ring-gray-200">
                                </a>
                            @endforeach
                            @if ($inspection->signature_path)
                                <a href="{{ route('inspections.signature', $inspection) }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ __('Signature') }}</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-app-layout>
