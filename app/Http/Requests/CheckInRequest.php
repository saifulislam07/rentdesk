<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

class CheckInRequest extends HandoverRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->inspectionRules(),
            ...$this->signatureRules(required: false),
            'inspections.*.needs_maintenance' => ['boolean'],
            'inspections.*.damage.description' => ['nullable', 'string', 'max:2000', 'required_with:inspections.*.damage.cost,inspections.*.damage.charged'],
            'inspections.*.damage.cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'inspections.*.damage.charged' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $items = $this->rental()->items()->whereNotNull('unit_id')->with('unit')->get()->keyBy('id');

                foreach ((array) $this->input('inspections') as $itemId => $inspection) {
                    $reading = $inspection['meter_reading'] ?? null;
                    $before = $items->get($itemId)?->unit->meter_reading;

                    if ($reading !== null && $reading !== '' && $before !== null && (int) $reading < $before) {
                        $validator->errors()->add(
                            "inspections.{$itemId}.meter_reading",
                            __('The meter reading cannot be lower than at check-out (:reading).', ['reading' => $before])
                        );
                    }
                }
            },
        ];
    }
}
