@props(['action'])

<form method="POST" action="{{ $action }}" data-confirm="{{ __('Are you sure?') }}" {{ $attributes }}>
    @csrf
    @method('DELETE')
    {{ $slot }}
</form>
