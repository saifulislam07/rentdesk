<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    @include('pdf.styles')
</head>
<body>
    <table class="plain">
        <tr>
            <td><h1>{{ config('rentdesk.company_name') }}</h1></td>
            <td class="right">
                <h2>{{ __('Invoice') }} {{ $invoice->number }}</h2>
                <div>{{ __('Issued') }}: {{ $invoice->issued_at->toAppDate() }}</div>
                <div>{{ __('Due') }}: {{ $invoice->due_at->toAppDate() }}</div>
            </td>
        </tr>
    </table>

    <p class="muted">{{ __('Billed to') }}</p>
    <p>
        <strong>{{ $invoice->rental->customer->name }}</strong><br>
        @if ($invoice->rental->customer->address) {{ $invoice->rental->customer->address }}<br> @endif
        @if ($invoice->rental->customer->tax_no) {{ __('Tax number') }}: {{ $invoice->rental->customer->tax_no }}<br> @endif
        {{ __('Rental') }}: {{ $invoice->rental->number }}
    </p>

    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="right">{{ __('Qty') }}</th>
                <th class="right">{{ __('Price') }}</th>
                <th class="right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->qty }}</td>
                    <td class="right">{{ $item->price }}</td>
                    <td class="right">{{ $item->lineTotal() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ $invoice->subtotal }}</td></tr>
        <tr><td>{{ __('Discount') }}</td><td class="right">−{{ $invoice->discount }}</td></tr>
        <tr><td>{{ __('Tax') }}</td><td class="right">{{ $invoice->tax }}</td></tr>
        <tr class="strong"><td>{{ __('Total') }}</td><td class="right">{{ $invoice->currency }} {{ $invoice->total }}</td></tr>
    </table>
</body>
</html>
