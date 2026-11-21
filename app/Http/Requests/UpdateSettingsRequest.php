<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Date and time layouts offered in the settings form, as PHP date() format => label.
     */
    public const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd M Y'];

    public const TIME_FORMATS = ['H:i', 'h:i A'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'late_fee_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'late_fee_grace_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'public_booking' => ['boolean'],
            'locale' => ['required', Rule::in(array_keys(config('rentdesk.locales')))],
            'date_format' => ['required', Rule::in(self::DATE_FORMATS)],
            'time_format' => ['required', Rule::in(self::TIME_FORMATS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'public_booking' => $this->boolean('public_booking'),
            'currency' => is_string($this->input('currency')) ? strtoupper(trim($this->input('currency'))) : $this->input('currency'),
        ]);
    }
}
