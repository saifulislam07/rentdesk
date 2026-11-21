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
            // Not "host"/"port": hosting firewalls (ModSecurity, Imunify360) answer 403 to a POST carrying both.
            'db_host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['db_host' => __('Host'), 'db_port' => __('Port')];
    }

    /**
     * The validated settings, with a relative SQLite path resolved against the project folder.
     *
     * @return array{driver: string, host: string|null, port: string|null, database: string, username: string|null, password: string|null}
     */
    public function settings(): array
    {
        $settings = $this->safe()->only(['driver', 'database', 'username', 'password']) + [
            'host' => $this->validated('db_host'), 'port' => $this->validated('db_port'), 'username' => null, 'password' => null,
        ];

        if ($settings['driver'] === 'mysql' && blank($settings['password']) && $this->matchesSavedConnection($settings)) {
            $settings['password'] = config('database.connections.mysql.password');
        }

        if ($settings['driver'] === 'sqlite' && $settings['database'] !== ':memory:' && ! preg_match('#^([a-zA-Z]:)?[\\\\/]#', $settings['database'])) {
            $settings['database'] = base_path($settings['database']);
        }

        return $settings;
    }

    /**
     * A blank password means "keep the one in .env", but only for the same server, database and user.
     *
     * @param  array{host: string|null, database: string, username: string|null}  $settings
     */
    private function matchesSavedConnection(array $settings): bool
    {
        $saved = config('database.connections.mysql');

        return filled($saved['password'] ?? null)
            && $settings['host'] === ($saved['host'] ?? null)
            && $settings['database'] === ($saved['database'] ?? null)
            && $settings['username'] === ($saved['username'] ?? null);
    }
}
