@props(['title'])

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
    @isset($actions)
        <div class="flex items-center gap-2 print:hidden">{{ $actions }}</div>
    @endisset
</div>
