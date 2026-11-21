<?php

namespace App\Http\Requests;

use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'serial_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'serial_no')
                    ->where('product_id', $unit->product_id)
                    ->ignore($unit),
            ],
            'condition' => ['required', Rule::enum(UnitCondition::class)],
            'status' => ['required', Rule::enum(UnitStatus::class)],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'purchased_at' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'meter_reading' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
