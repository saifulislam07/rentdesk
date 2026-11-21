<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The date range of a report, defaulting to the current month.
 */
class ReportRangeRequest extends FormRequest
{
    private const MAX_DAYS = 366;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$this->input('from').' +'.self::MAX_DAYS.' days'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'from' => now('UTC')->startOfMonth()->toDateString(),
            'to' => now('UTC')->endOfMonth()->toDateString(),
        ]);
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('from'), 'UTC')->startOfDay();
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('to'), 'UTC')->endOfDay();
    }
}
