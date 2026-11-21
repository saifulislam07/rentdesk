<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'to_location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
        ];
    }
}
