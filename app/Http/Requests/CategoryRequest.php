<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                Rule::notIn(array_filter([$category?->getKey()])),
            ],
        ];
    }
}
