<?php

namespace App\Http\Requests;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LanguageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Language|null $language */
        $language = $this->route('language');

        return [
            // The code names the translation file and is the locale in URLs and sessions, so it is fixed once created.
            'code' => $language ? [] : ['required', 'string', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', Rule::unique('languages', 'code')],
            'name' => ['required', 'string', 'max:60'],
            'dir' => ['required', Rule::in(['ltr', 'rtl'])],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'file', 'extensions:json', 'max:1024'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => is_string($this->input('code')) ? trim($this->input('code')) : $this->input('code'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('Use a language code such as fr, ar or pt-BR.'),
        ];
    }
}
