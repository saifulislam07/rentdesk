<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DatabaseSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver' => ['required', Rule::in(['mysql', 'sqlite'])],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The validated settings, with a relative SQLite path resolved against the project folder.
     *
     * @return array{driver: string, host: string|null, port: string|null, database: string, username: string|null, password: string|null}
     */
    public function settings(): array
    {
        $settings = $this->safe()->only(['driver', 'host', 'port', 'database', 'username', 'password']) + [
            'host' => null, 'port' => null, 'username' => null, 'password' => null,
        ];

        if ($settings['driver'] === 'sqlite' && $settings['database'] !== ':memory:' && ! preg_match('#^([a-zA-Z]:)?[\\\\/]#', $settings['database'])) {
            $settings['database'] = base_path($settings['database']);
        }

        return $settings;
    }
}
