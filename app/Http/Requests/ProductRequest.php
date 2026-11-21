<?php

namespace App\Http\Requests;

use App\Enums\ProductTracking;
use App\Enums\RatePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        $rules = [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('products', 'slug')->ignore($product),
            ],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product)],
            'tracking' => ['required', Rule::enum(ProductTracking::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'replacement_value' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'deposit_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'rates' => ['nullable', 'array'],
        ];

        foreach (RatePeriod::cases() as $period) {
            $key = "rates.{$period->value}";

            $rules["{$key}.price"] = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
            $rules["{$key}.min_qty_periods"] = ['nullable', 'integer', 'min:1', 'max:1000'];
            $rules["{$key}.weekend_price"] = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        }

        return $rules;
    }
}
