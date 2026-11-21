<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RentalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
