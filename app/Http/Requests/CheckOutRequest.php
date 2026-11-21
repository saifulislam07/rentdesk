<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class CheckOutRequest extends HandoverRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->inspectionRules(),
            ...$this->signatureRules(required: true),
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'deposit_method' => [
                Rule::requiredIf(fn (): bool => (float) $this->input('deposit_amount') > 0),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
        ];
    }
}
