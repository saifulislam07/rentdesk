<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'min-w-full border-collapse [&_th]:border [&_th]:border-gray-200 [&_td]:border [&_td]:border-gray-200 text-sm']) }}>
        {{ $slot }}
    </table>
</div>
