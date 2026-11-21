@props(['show', 'edit' => null])

<div class="flex justify-end gap-2">
    <x-button variant="secondary" size="sm" :href="$show">{{ __('View') }}</x-button>
    <x-button variant="secondary" size="sm" :href="$show.'?print=1'" target="_blank" rel="noopener">{{ __('Print') }}</x-button>
    @if ($edit)
        <x-button variant="secondary" size="sm" :href="$edit">{{ __('Edit') }}</x-button>
    @endif
    {{ $slot }}
</div>
