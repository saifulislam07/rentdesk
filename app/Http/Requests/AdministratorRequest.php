<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AdministratorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'company_name' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'alpha', 'size:3'],
            'demo_data' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['demo_data' => $this->boolean('demo_data')]);
    }
}
