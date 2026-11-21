<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KitRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
