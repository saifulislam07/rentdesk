<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMaintenanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
