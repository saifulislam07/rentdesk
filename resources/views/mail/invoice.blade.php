<x-mail::message>
# {{ __('Invoice :number', ['number' => $invoice->number]) }}

{{ __('Hello :name,', ['name' => $invoice->rental->customer->name]) }}

{{ __('Please find your invoice attached. The total of :amount is due by :date.', [
    'amount' => $invoice->currency.' '.$invoice->total,
    'date' => $invoice->due_at->toFormattedDateString(),
]) }}

{{ __('Thank you,') }}<br>
{{ config('rentdesk.company_name') }}
</x-mail::message>
