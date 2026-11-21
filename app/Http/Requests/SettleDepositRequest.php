<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettleDepositRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'refund_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }
}
