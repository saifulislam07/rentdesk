@props(['required' => true])

<x-card class="mb-6 p-6">
    <h2 class="font-semibold text-gray-900">{{ __('Customer signature') }}@unless ($required) <span class="text-sm font-normal text-gray-500">({{ __('optional') }})</span>@endunless</h2>
    <canvas id="signature-pad" class="mt-3 h-40 w-full touch-none rounded-md border border-dashed border-gray-300 bg-white"></canvas>
    <input type="hidden" id="signature" name="signature" value="{{ old('signature') }}">
    <x-input-error :messages="$errors->get('signature')" class="mt-1" />
    <button type="button" id="signature-clear" class="mt-2 text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</button>
</x-card>

@push('scripts')
    @vite('resources/js/signature.js')
@endpush
