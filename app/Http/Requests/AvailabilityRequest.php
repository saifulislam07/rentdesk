<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityRequest extends FormRequest
{
    /**
     * Every field is optional so the empty search form can be shown; once a product is chosen the period is required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'required', 'integer', Rule::exists('products', 'id')],
            'start_at' => ['required_with:product_id', 'date'],
            'end_at' => ['required_with:product_id', 'date', 'after:start_at'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ];
    }

    public function isSearch(): bool
    {
        return $this->has('product_id');
    }

    public function startAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('start_at'), 'UTC');
    }

    public function endAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('end_at'), 'UTC');
    }
}
