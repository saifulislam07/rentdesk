<?php

namespace App\Http\Requests;

use App\Enums\UnitCondition;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'serials' => ['required', 'string', 'max:20000'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'condition' => ['required', Rule::enum(UnitCondition::class)],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'purchased_at' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * The submitted serial numbers, one per line or comma-separated.
     *
     * @return list<string>
     */
    public function serialNumbers(): array
    {
        $serials = preg_split('/[\r\n,]+/', (string) $this->input('serials'), flags: PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(array_map('trim', $serials), 'strlen')));
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('serials')) {
                    return;
                }

                $serials = $this->serialNumbers();

                if ($serials === []) {
                    $validator->errors()->add('serials', __('Enter at least one serial number.'));

                    return;
                }

                /** @var Product $product */
                $product = $this->route('product');

                $existing = $product->units()->whereIn('serial_no', $serials)->pluck('serial_no');

                if ($existing->isNotEmpty()) {
                    $validator->errors()->add(
                        'serials',
                        __('These serial numbers already exist: :serials', ['serials' => $existing->implode(', ')])
                    );
                }
            },
        ];
    }
}
