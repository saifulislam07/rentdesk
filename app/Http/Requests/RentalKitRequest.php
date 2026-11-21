<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RentalKitRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kit_id' => ['required', 'integer', Rule::exists('kits', 'id')],
            'qty' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
