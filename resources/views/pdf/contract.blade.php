<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $rental->number }}</title>
    @include('pdf.styles')
</head>
<body>
    <table class="plain">
        <tr>
            <td><h1>{{ config('rentdesk.company_name') }}</h1></td>
            <td class="right">
                <h2>{{ $rental->status->isEditable() ? __('Quote') : __('Rental contract') }} {{ $rental->number }}</h2>
                <div>{{ __('Status') }}: {{ $rental->status->label() }}</div>
            </td>
        </tr>
    </table>

    <table class="plain">
        <tr>
            <td>
                <p class="muted">{{ __('Customer') }}</p>
                <strong>{{ $rental->customer->name }}</strong><br>
                @if ($rental->customer->address) {{ $rental->customer->address }}<br> @endif
                @if ($rental->customer->phone) {{ $rental->customer->phone }} @endif
            </td>
            <td>
                <p class="muted">{{ __('Rental period (UTC)') }}</p>
                {{ $rental->start_at->toAppDateTime() }}<br>
                → {{ $rental->end_at->toAppDateTime() }}<br>
                {{ __('Pick-up location') }}: {{ $rental->location->name }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('Product') }}</th>
                <th>{{ __('Serial no.') }}</th>
                <th class="right">{{ __('Qty') }}</th>
                <th class="right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rental->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->unit?->serial_no ?? '—' }}</td>
                    <td class="right">{{ $item->qty }}</td>
                    <td class="right">{{ $item->amount }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ $rental->subtotal }}</td></tr>
        <tr><td>{{ __('Discount') }}</td><td class="right">−{{ $rental->discount }}</td></tr>
        <tr><td>{{ __('Tax') }}</td><td class="right">{{ $rental->tax }}</td></tr>
        <tr class="strong"><td>{{ __('Total') }}</td><td class="right">{{ $rental->currency }} {{ $rental->total }}</td></tr>
        <tr><td>{{ __('Deposit required') }}</td><td class="right">{{ $rental->deposit_required }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 40px">
        {{ __('The customer agrees to return the equipment in the condition received and accepts liability for loss or damage during the rental period.') }}
    </p>

    <table class="plain" style="margin-top: 50px">
        <tr>
            <td>________________________<br>{{ __('Customer signature') }}</td>
            <td class="right">________________________<br>{{ config('rentdesk.company_name') }}</td>
        </tr>
    </table>
</body>
</html>
