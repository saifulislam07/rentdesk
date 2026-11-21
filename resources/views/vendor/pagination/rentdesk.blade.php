@php
    $paginator->withQueryString();
    $keptQuery = collect(request()->except(['page', 'per_page']))->filter(fn ($value): bool => is_scalar($value));
    $link = 'inline-flex min-w-9 items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium ring-1 ring-gray-300';
@endphp

@if ($paginator->total() > 0)
    <nav role="navigation" aria-label="{{ __('Page navigation') }}" class="flex flex-col items-center justify-between gap-3 text-sm text-gray-600 sm:flex-row">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p>{{ __('Showing :from to :to of :total results', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>

            <form method="GET" class="flex items-center gap-2">
                @foreach ($keptQuery as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <label for="per_page" class="whitespace-nowrap">{{ __('Per page') }}</label>
                <select id="per_page" name="per_page" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 ps-2 pe-8 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach (App\Http\Controllers\Controller::PER_PAGE_OPTIONS as $option)
                        <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="rounded-md px-2 py-1 ring-1 ring-gray-300">{{ __('Apply') }}</button></noscript>
            </form>
        </div>

        @if ($paginator->hasPages())
            <ul class="flex flex-wrap items-center justify-center gap-1">
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="{{ $link }} cursor-not-allowed bg-gray-50 text-gray-400" aria-disabled="true" aria-label="{{ __('Previous') }}">
                            <svg class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} bg-white text-gray-700 hover:bg-gray-50" aria-label="{{ __('Previous') }}">
                            <svg class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        </a>
                    @endif
                </li>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="px-2 text-gray-400">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span class="{{ $link }} bg-brand-600 text-white ring-brand-600" aria-current="page">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="{{ $link }} bg-white text-gray-700 hover:bg-gray-50" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach

                <li>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} bg-white text-gray-700 hover:bg-gray-50" aria-label="{{ __('Next') }}">
                            <svg class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    @else
                        <span class="{{ $link }} cursor-not-allowed bg-gray-50 text-gray-400" aria-disabled="true" aria-label="{{ __('Next') }}">
                            <svg class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </span>
                    @endif
                </li>
            </ul>
        @endif
    </nav>
@endif
