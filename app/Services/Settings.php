<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Business settings the administrator can change. They are stored in the database and applied over the
 * defaults in config/rentdesk.php, so .env still provides the starting values.
 */
class Settings
{
    private const CACHE_KEY = 'rentdesk.settings';

    public function __construct(private readonly Languages $languages) {}

    /**
     * Each setting: the config key it overrides and how its stored text is cast.
     *
     * @var array<string, array{config: string, type: string}>
     */
    public const FIELDS = [
        'company_name' => ['config' => 'rentdesk.company_name', 'type' => 'string'],
        'currency' => ['config' => 'rentdesk.currency', 'type' => 'string'],
        'tax_rate' => ['config' => 'rentdesk.tax_rate', 'type' => 'float'],
        'invoice_due_days' => ['config' => 'rentdesk.invoice_due_days', 'type' => 'int'],
        'late_fee_percent' => ['config' => 'rentdesk.late_fee_percent', 'type' => 'float'],
        'late_fee_grace_minutes' => ['config' => 'rentdesk.late_fee_grace_minutes', 'type' => 'int'],
        'public_booking' => ['config' => 'rentdesk.public_booking', 'type' => 'bool'],
        'locale' => ['config' => 'app.locale', 'type' => 'string'],
        'date_format' => ['config' => 'rentdesk.date_format', 'type' => 'string'],
        'time_format' => ['config' => 'rentdesk.time_format', 'type' => 'string'],
    ];

    /**
     * Put the stored settings over the config for this request.
     */
    public function apply(): void
    {
        $this->languages->apply();

        foreach ($this->stored() as $key => $value) {
            if (isset(self::FIELDS[$key])) {
                config([self::FIELDS[$key]['config'] => $this->cast($key, $value)]);
            }
        }

        // A default language that was removed or switched off falls back to the base language.
        if (! array_key_exists(config('app.locale'), config('rentdesk.locales'))) {
            config(['app.locale' => Language::BASE]);
        }

        app()->setLocale(config('app.locale'));
    }

    /**
     * Apply the stored settings, doing nothing while there is no database yet (before installation or migration).
     */
    public function applyIfAvailable(): void
    {
        try {
            $this->apply();
        } catch (QueryException) {
            // No database or tables yet (before installation or migration); the config defaults stay in force.
        }
    }

    /**
     * The values in force right now, whether stored or defaulted.
     *
     * @return array<string, bool|float|int|string>
     */
    public function current(): array
    {
        return collect(self::FIELDS)->map(fn (array $field): mixed => config($field['config']))->all();
    }

    /**
     * @param  array<string, mixed>  $values  Only known settings are saved.
     */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::FIELDS) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? (string) (int) $value : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->apply();
    }

    /**
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
    }

    private function cast(string $key, ?string $value): bool|float|int|string
    {
        return match (self::FIELDS[$key]['type']) {
            'bool' => (bool) (int) $value,
            'float' => (float) $value,
            'int' => (int) $value,
            default => (string) $value,
        };
    }
}
