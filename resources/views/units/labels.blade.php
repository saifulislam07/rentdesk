<x-app-layout>
    <div class="print:hidden">
        <x-page-header :title="__('QR labels: :product', ['product' => $product->name])">
            <x-slot name="actions">
                <x-button variant="secondary" :href="route('products.show', $product)">{{ __('Back') }}</x-button>
                <x-button type="button" onclick="window.print()">{{ __('Print') }}</x-button>
            </x-slot>
        </x-page-header>
    </div>

    @if ($units->isEmpty())
        <p class="text-sm text-gray-500">{{ __('No units to print.') }}</p>
    @endif

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 print:grid-cols-4 print:gap-2">
        @foreach ($units as $unit)
            <div class="break-inside-avoid rounded-md border border-gray-300 bg-white p-3 text-center">
                <div class="mx-auto w-28">{!! QrCode::format('svg')->size(112)->margin(0)->generate(route('units.scan', $unit->qr_code)) !!}</div>
                <p class="mt-2 truncate text-xs font-semibold">{{ $product->name }}</p>
                <p class="font-mono text-xs">{{ $unit->serial_no }}</p>
            </div>
        @endforeach
    </div>
</x-app-layout>
