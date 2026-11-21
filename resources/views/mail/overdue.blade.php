<x-mail::message>
# {{ __('Your rental is overdue') }}

{{ __('Hello :name,', ['name' => $rental->customer->name]) }}

{{ __('Rental :number was due back on :date (UTC). Please return the equipment as soon as possible. Late fees apply for every day past the due date.', [
    'number' => $rental->number,
    'date' => $rental->end_at->toAppDateTime(),
]) }}

{{ __('Thank you,') }}<br>
{{ config('rentdesk.company_name') }}
</x-mail::message>
