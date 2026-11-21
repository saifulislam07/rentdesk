<?php

namespace App\Services;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Everything the web installer does: system checks, database setup, the first admin and the install lock.
 */
class InstallationService
{
    private const REQUIRED_PHP = '8.3.0';

    private const EXTENSIONS = ['openssl', 'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'dom'];

    private const RECOMMENDED_EXTENSIONS = ['gd'];

    public function isInstalled(): bool
    {
        return config('rentdesk.installed') ?? is_file(config('rentdesk.install_lock'));
    }

    public function environment(): EnvironmentFile
    {
        return new EnvironmentFile(config('rentdesk.env_path'));
    }

    /**
     * Make sure the app has an encryption key, generating one into .env on a fresh copy.
     */
    public function ensureAppKey(): void
    {
        if (config('app.key')) {
            return;
        }

        $key = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));

        $this->environment()->set(['APP_KEY' => $key]);
        config(['app.key' => $key]);
    }

    /**
     * @return list<array{label: string, passed: bool, required: bool, hint: string|null}>
     */
    public function requirements(): array
    {
        $checks = [[
            'label' => 'PHP '.self::REQUIRED_PHP.'+',
            'passed' => version_compare(PHP_VERSION, self::REQUIRED_PHP, '>='),
            'required' => true,
            'hint' => 'Installed: '.PHP_VERSION,
        ]];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = ['label' => "PHP extension: {$extension}", 'passed' => extension_loaded($extension), 'required' => true, 'hint' => null];
        }

        $checks[] = [
            'label' => 'PHP extension: pdo_mysql or pdo_sqlite',
            'passed' => extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'),
            'required' => true,
            'hint' => null,
        ];

        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            $checks[] = ['label' => "PHP extension: {$extension}", 'passed' => extension_loaded($extension), 'required' => false, 'hint' => 'Recommended for images in PDFs'];
        }

        $envPath = config('rentdesk.env_path');

        foreach ([
            'The .env file' => is_file($envPath) ? is_writable($envPath) : is_writable(dirname($envPath)),
            'storage/' => is_writable(storage_path()),
            'bootstrap/cache/' => is_writable(base_path('bootstrap/cache')),
        ] as $label => $writable) {
            $checks[] = ['label' => "{$label} is writable", 'passed' => $writable, 'required' => true, 'hint' => null];
        }

        return $checks;
    }

    public function requirementsMet(): bool
    {
        return collect($this->requirements())->where('required', true)->every(fn (array $check): bool => $check['passed']);
    }

    /**
     * Check the credentials work, then save them to .env and point this request at the database.
     *
     * @param  array{driver: string, host?: string|null, port?: string|int|null, database: string, username?: string|null, password?: string|null}  $settings
     *
     * @throws RuntimeException With a message that is safe to show when the database cannot be reached.
     */
    public function configureDatabase(array $settings, string $appUrl): void
    {
        $connection = $this->connectionConfig($settings);

        try {
            if ($settings['driver'] === 'sqlite' && $settings['database'] !== ':memory:' && ! is_file($settings['database'])) {
                touch($settings['database']);
            }

            DB::connectUsing('installer_check', $connection)->getPdo();
        } catch (Throwable $exception) {
            throw new RuntimeException(__('Could not connect to the database: :message', ['message' => $exception->getMessage()]));
        }

        $this->environment()->set([
            'APP_URL' => $appUrl,
            'DB_CONNECTION' => $settings['driver'],
            'DB_HOST' => $settings['host'] ?? '127.0.0.1',
            'DB_PORT' => $settings['port'] ?? '3306',
            'DB_DATABASE' => $settings['database'],
            'DB_USERNAME' => $settings['username'] ?? '',
            'DB_PASSWORD' => $settings['password'] ?? '',
        ]);

        config([
            'app.url' => $appUrl,
            'database.default' => $settings['driver'],
            "database.connections.{$settings['driver']}" => $connection,
        ]);
        DB::purge($settings['driver']);
    }

    /**
     * Create the tables.
     *
     * @throws RuntimeException
     */
    public function migrate(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            throw new RuntimeException(__('The database tables could not be created: :message', ['message' => $exception->getMessage()]));
        }
    }

    /**
     * Roles, the first administrator and optional sample data; then the install lock.
     *
     * @param  array{name: string, email: string, password: string, company_name: string, currency: string, demo_data?: bool}  $data
     */
    public function finish(array $data): User
    {
        $admin = DB::transaction(function () use ($data): User {
            $this->seed(RoleSeeder::class);

            $admin = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $admin->forceFill(['email_verified_at' => now()])->save();
            $admin->assignRole('admin');

            if (! empty($data['demo_data'])) {
                $this->seed(DemoDataSeeder::class);
            }

            return $admin;
        });

        $this->environment()->set([
            'APP_NAME' => $data['company_name'],
            'RENTDESK_COMPANY_NAME' => $data['company_name'],
            'RENTDESK_CURRENCY' => strtoupper($data['currency']),
            'APP_DEBUG' => false,
        ]);

        $this->linkStorage();
        $this->lock();

        return $admin;
    }

    /**
     * @param  class-string<Seeder>  $seeder
     */
    private function seed(string $seeder): void
    {
        (app($seeder)->setContainer(app()))();
    }

    private function lock(): void
    {
        $path = config('rentdesk.install_lock');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, now()->toIso8601String());
    }

    /**
     * Public uploads (brand logos) need the storage link; hosts that forbid symlinks can run it by hand.
     */
    private function linkStorage(): void
    {
        if (! config('rentdesk.link_storage')) {
            return;
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable) {
            // Not fatal: the installer's final page tells the owner how to link manually.
        }
    }

    /**
     * @param  array{driver: string, host?: string|null, port?: string|int|null, database: string, username?: string|null, password?: string|null}  $settings
     * @return array<string, mixed>
     */
    private function connectionConfig(array $settings): array
    {
        if ($settings['driver'] === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => $settings['database'], 'prefix' => '', 'foreign_key_constraints' => true];
        }

        return [
            'driver' => 'mysql',
            'host' => $settings['host'],
            'port' => $settings['port'] ?: '3306',
            'database' => $settings['database'],
            'username' => $settings['username'],
            'password' => $settings['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            // Hosts whose MySQL defaults to MyISAM need DB_ENGINE=InnoDB, or the migrations fail on key length.
            'engine' => config('database.connections.mysql.engine'),
        ];
    }
}
