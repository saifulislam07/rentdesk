<?php

namespace App\Http\Requests;

use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CustomerType::class)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'is_blacklisted' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_blacklisted' => $this->boolean('is_blacklisted')]);
    }
}
