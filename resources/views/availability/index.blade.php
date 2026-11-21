<x-app-layout>
    <x-page-header :title="__('Availability')" />

    <x-card class="mb-6 p-6">
        <form method="GET" action="{{ route('availability') }}" class="grid gap-5 sm:grid-cols-2">
            <x-field name="product_id" :label="__('Product')" class="sm:col-span-2">
                <x-select id="product_id" name="product_id" required>
                    <option value="">{{ __('Select a product') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field name="start_at" :label="__('Start (UTC)')">
                <x-text-input id="start_at" name="start_at" type="datetime-local" :value="request('start_at')" required />
            </x-field>

            <x-field name="end_at" :label="__('End (UTC)')">
                <x-text-input id="end_at" name="end_at" type="datetime-local" :value="request('end_at')" required />
            </x-field>

            <x-field name="location_id" :label="__('Location')">
                <x-select id="location_id" name="location_id">
                    <option value="">{{ __('Any location') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(request('location_id') == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="flex items-end">
                <x-button>{{ __('Check availability') }}</x-button>
            </div>
        </form>
    </x-card>

    @if ($result)
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card class="p-6">
                <h2 class="font-semibold text-gray-900">{{ $result['product']->name }}</h2>
                <p class="mt-3 text-3xl font-bold {{ $result['quantity'] > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $result['quantity'] }}</p>
                <p class="text-sm text-gray-500">{{ __('available for the selected period') }}</p>

                <div class="mt-5 border-t border-gray-100 pt-4 text-sm">
                    @if ($result['quote'])
                        <p class="text-gray-500">{{ __('Price per item') }}</p>
                        <p class="text-lg font-semibold">{{ $result['quote']->amount }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $result['quote']->periods }} × {{ $result['quote']->period->label() }} @ {{ $result['quote']->rate }}
                        </p>
                    @else
                        <p class="text-gray-500">{{ __('This product has no rates.') }}</p>
                    @endif
                </div>
            </x-card>

            <x-card class="lg:col-span-2">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="font-semibold text-gray-900">{{ __('Available units') }}</h2>
                </div>

                @if ($result['product']->tracking === App\Enums\ProductTracking::Bulk)
                    <p class="px-6 py-8 text-center text-sm text-gray-500">{{ __('This product is tracked by quantity.') }}</p>
                @else
                    <x-table>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($result['units'] as $unit)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a href="{{ route('units.show', $unit) }}" class="hover:text-brand-600">{{ $unit->serial_no }}</a>
                                    </td>
                                    <td class="px-4 py-3">{{ $unit->location->name }}</td>
                                    <td class="px-4 py-3"><x-badge :color="$unit->condition->color()">{{ $unit->condition->label() }}</x-badge></td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-8 text-center text-gray-500">{{ __('No units are free for this period.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>
    @endif
</x-app-layout>
