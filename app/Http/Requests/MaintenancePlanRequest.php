<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaintenancePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'every_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_without:every_rentals'],
            'every_rentals' => ['nullable', 'integer', 'min:1', 'max:10000', 'required_without:every_days'],
        ];
    }
}
