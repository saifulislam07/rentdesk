@if (count(config('rentdesk.locales')) > 1)
    <form method="POST" action="{{ route('locale') }}" class="flex items-center gap-2">
        @csrf
        <label for="locale" class="sr-only">{{ __('Language') }}</label>
        <select id="locale" name="locale" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            @foreach (config('rentdesk.locales') as $code => $locale)
                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $locale['name'] }}</option>
            @endforeach
        </select>
        <noscript><x-button variant="secondary" size="sm">{{ __('Change') }}</x-button></noscript>
    </form>
@endif
