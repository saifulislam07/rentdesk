@foreach (['status' => 'success', 'error' => 'error'] as $key => $icon)
    @if (session($key))
        <div data-toast="{{ $icon }}" data-message="{{ session($key) }}" hidden></div>
        <noscript>
            <div role="{{ $key === 'error' ? 'alert' : 'status' }}" class="mb-4 rounded-md px-4 py-3 text-sm ring-1 {{ $key === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-green-50 text-green-800 ring-green-200' }}">
                {{ session($key) }}
            </div>
        </noscript>
    @endif
@endforeach
