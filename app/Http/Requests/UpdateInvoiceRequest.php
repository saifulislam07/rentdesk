<?php

namespace App\Http\Requests;

use App\Services\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'issued_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:issued_at'],
            'discount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'tax' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subtotal = collect($this->input('items'))->sum(fn (array $item): int => (int) $item['qty'] * Money::toCents($item['price']));

                if (Money::toCents($this->input('discount')) > $subtotal) {
                    $validator->errors()->add('discount', __('The discount cannot be more than the subtotal.'));
                }
            },
        ];
    }
}
