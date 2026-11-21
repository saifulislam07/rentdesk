<?php

namespace App\Http\Requests;

use App\Enums\ChecklistItem;
use App\Enums\UnitCondition;
use App\Models\Rental;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the check-out and check-in screens: one inspection per serial-tracked item.
 */
abstract class HandoverRequest extends FormRequest
{
    private const SIGNATURE_PREFIX = 'data:image/png;base64,';

    /**
     * @return array<string, mixed>
     */
    protected function inspectionRules(): array
    {
        return [
            'inspections' => ['required', 'array'],
            'inspections.*.condition' => ['required', Rule::enum(UnitCondition::class)],
            'inspections.*.meter_reading' => ['nullable', 'integer', 'min:0'],
            'inspections.*.checklist' => ['nullable', 'array'],
            'inspections.*.checklist.*' => [Rule::enum(ChecklistItem::class)],
            'inspections.*.notes' => ['nullable', 'string', 'max:2000'],
            'inspections.*.photos' => ['nullable', 'array', 'max:6'],
            'inspections.*.photos.*' => ['image', 'max:5120'],
        ];
    }

    /**
     * The drawn signature as PNG bytes, or null when none was provided.
     */
    public function signatureImage(): ?string
    {
        $signature = (string) $this->input('signature');

        if ($signature === '') {
            return null;
        }

        return base64_decode(substr($signature, strlen(self::SIGNATURE_PREFIX)), true) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function signatureRules(bool $required): array
    {
        return [
            'signature' => [
                $required ? 'required' : 'nullable',
                'string',
                'max:700000',
                'starts_with:'.self::SIGNATURE_PREFIX,
                fn (string $attribute, mixed $value, \Closure $fail) => $this->isValidPng($value) ?: $fail(__('The signature is not a valid image.')),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('inspections')) {
                    return;
                }

                $missing = $this->rental()->items()->whereNotNull('unit_id')->with('unit')->get()
                    ->reject(fn ($item): bool => is_array($this->input("inspections.{$item->id}")))
                    ->map(fn ($item): string => $item->unit->serial_no);

                if ($missing->isNotEmpty()) {
                    $validator->errors()->add('inspections', __('Inspect every unit: :serials', ['serials' => $missing->implode(', ')]));
                }
            },
        ];
    }

    protected function rental(): Rental
    {
        return $this->route('rental');
    }

    private function isValidPng(mixed $value): bool
    {
        $bytes = is_string($value) ? base64_decode(substr($value, strlen(self::SIGNATURE_PREFIX)), true) : false;

        return $bytes !== false && str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
    }
}
