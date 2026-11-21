<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rental;
use App\Models\User;
use App\Services\InstallationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The installer rewrites .env and the database connection, so these tests point both at a scratch folder.
 */
class InstallerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rentdesk-install-'.uniqid();
        mkdir($this->directory);
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Laravel\nAPP_DEBUG=true\nDB_CONNECTION=mysql\n");

        config([
            'rentdesk.installed' => false,
            'rentdesk.env_path' => $this->directory.DIRECTORY_SEPARATOR.'.env',
            'rentdesk.install_lock' => $this->directory.DIRECTORY_SEPARATOR.'installed.lock',
            'rentdesk.link_storage' => false,
        ]);
    }

    protected function tearDown(): void
    {
        // Windows keeps the SQLite file locked until the connection is garbage collected; leftovers in the temp folder are harmless.
        DB::purge('sqlite');
        gc_collect_cycles();

        foreach (array_filter(glob($this->directory.DIRECTORY_SEPARATOR.'{,.}*', GLOB_BRACE | GLOB_NOSORT) ?: [], 'is_file') as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);

        parent::tearDown();
    }

    private function databasePath(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'rentdesk.sqlite';
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseForm(array $overrides = []): array
    {
        return ['driver' => 'sqlite', 'database' => $this->databasePath(), ...$overrides];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminForm(array $overrides = []): array
    {
        return [
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'a-Long-Passw0rd!',
            'password_confirmation' => 'a-Long-Passw0rd!',
            'company_name' => 'Acme Rentals',
            'currency' => 'eur',
            ...$overrides,
        ];
    }

    private function env(string $key): ?string
    {
        return $this->app->make(InstallationService::class)->environment()->get($key);
    }

    public function test_every_page_leads_to_the_installer_until_the_app_is_installed(): void
    {
        foreach (['/', '/dashboard', '/login', '/book', '/availability'] as $url) {
            $this->get($url)->assertRedirect(route('install.welcome'));
        }
    }

    public function test_the_installer_is_gone_once_the_app_is_installed(): void
    {
        config(['rentdesk.installed' => true]);

        foreach ([route('install.welcome'), route('install.database'), route('install.administrator')] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post(route('install.database.save'), $this->databaseForm())->assertNotFound();
        $this->post(route('install.administrator.save'), $this->adminForm())->assertNotFound();
        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'.env');
    }

    public function test_the_lock_file_marks_the_app_as_installed_when_nothing_forces_it(): void
    {
        config(['rentdesk.installed' => null]);
        $installation = $this->app->make(InstallationService::class);

        $this->assertFalse($installation->isInstalled());

        touch(config('rentdesk.install_lock'));

        $this->assertTrue($installation->isInstalled());
    }

    public function test_the_requirements_page_lists_the_checks(): void
    {
        $this->get(route('install.welcome'))
            ->assertOk()
            ->assertSee('Server requirements')
            ->assertSee('PHP 8.3.0+')
            ->assertSee('PHP extension: mbstring')
            ->assertSee('storage/ is writable')
            ->assertSee('Continue');
    }

    public function test_unmet_requirements_block_the_next_step(): void
    {
        config(['rentdesk.env_path' => $this->directory.DIRECTORY_SEPARATOR.'missing'.DIRECTORY_SEPARATOR.'.env']);

        $this->get(route('install.welcome'))->assertOk()->assertSee('Fix the missing items above')->assertDontSee('Continue');
        $this->get(route('install.database'))->assertRedirect(route('install.welcome'));
    }

    public function test_the_database_form_renders(): void
    {
        $this->get(route('install.database'))->assertOk()->assertSee('Test connection and continue');
    }

    public function test_the_database_step_validates_its_input(): void
    {
        $this->post(route('install.database.save'), [])->assertSessionHasErrors(['driver', 'database']);
        $this->post(route('install.database.save'), ['driver' => 'mysql', 'database' => 'rentdesk'])->assertSessionHasErrors(['host', 'username']);
        $this->post(route('install.database.save'), ['driver' => 'oracle', 'database' => 'x'])->assertSessionHasErrors('driver');
    }

    public function test_a_working_database_is_saved_and_migrated(): void
    {
        $appUrl = config('app.url');

        $this->post(route('install.database.save'), $this->databaseForm())
            ->assertRedirect(route('install.administrator'))
            ->assertSessionHas('install.database', true);

        $this->assertSame('sqlite', $this->env('DB_CONNECTION'));
        $this->assertSame($this->databasePath(), $this->env('DB_DATABASE'));
        $this->assertSame($appUrl, $this->env('APP_URL'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('rentals'));
        $this->assertSame(0, User::count());
    }

    public function test_an_unreachable_database_shows_the_error_and_changes_nothing(): void
    {
        $this->post(route('install.database.save'), [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 9,
            'database' => 'rentdesk',
            'username' => 'nobody',
            'password' => 'secret-pass',
        ])
            ->assertSessionHasErrors('database')
            ->assertSessionMissing('install.database');

        $this->assertStringContainsString('Could not connect to the database', session('errors')->first('database'));
        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'.env');
        $this->assertFileDoesNotExist(config('rentdesk.install_lock'));
    }

    public function test_the_password_is_not_sent_back_to_the_form(): void
    {
        $response = $this->from(route('install.database'))->post(route('install.database.save'), [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 9, 'database' => 'x', 'username' => 'u', 'password' => 'secret-pass',
        ]);

        $this->assertNull(session()->getOldInput('password'));
        $response->assertRedirect(route('install.database'));
    }

    public function test_the_administrator_step_needs_the_database_step_first(): void
    {
        $this->get(route('install.administrator'))->assertRedirect(route('install.database'));
        $this->post(route('install.administrator.save'), $this->adminForm())->assertRedirect(route('install.database'));

        $this->assertFileDoesNotExist(config('rentdesk.install_lock'));
    }

    public function test_the_administrator_form_is_validated(): void
    {
        $session = ['install.database' => true];

        $this->withSession($session)->post(route('install.administrator.save'), [])->assertSessionHasErrors(['name', 'email', 'password', 'company_name', 'currency']);
        $this->withSession($session)->post(route('install.administrator.save'), $this->adminForm(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->withSession($session)->post(route('install.administrator.save'), $this->adminForm(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->withSession($session)->post(route('install.administrator.save'), $this->adminForm(['currency' => 'EURO']))->assertSessionHasErrors('currency');
        $this->withSession($session)->post(route('install.administrator.save'), $this->adminForm(['email' => 'nope']))->assertSessionHasErrors('email');

        $this->assertFileDoesNotExist(config('rentdesk.install_lock'));
    }

    public function test_a_full_install_creates_the_admin_saves_settings_and_locks_the_installer(): void
    {
        $this->post(route('install.database.save'), $this->databaseForm());

        $response = $this->withSession(['install.database' => true])->post(route('install.administrator.save'), $this->adminForm());

        $response->assertOk()->assertSee('Installation complete')->assertSee('owner@example.test')->assertSee('artisan schedule:run');

        $admin = User::firstWhere('email', 'owner@example.test');
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotSame('a-Long-Passw0rd!', $admin->password);
        $this->assertTrue(password_verify('a-Long-Passw0rd!', $admin->password));
        $this->assertSame(0, Product::count());

        $this->assertSame('Acme Rentals', $this->env('RENTDESK_COMPANY_NAME'));
        $this->assertSame('EUR', $this->env('RENTDESK_CURRENCY'));
        $this->assertSame('false', $this->env('APP_DEBUG'));
        $this->assertFileExists(config('rentdesk.install_lock'));

        config(['rentdesk.installed' => null]);
        $this->get(route('install.welcome'))->assertNotFound();
        $this->get(route('login'))->assertOk();
    }

    public function test_the_sample_data_option_adds_a_demo_business_but_no_demo_accounts(): void
    {
        $this->post(route('install.database.save'), $this->databaseForm());

        $this->withSession(['install.database' => true])
            ->post(route('install.administrator.save'), $this->adminForm(['demo_data' => '1']))
            ->assertOk();

        $this->assertGreaterThan(0, Product::count());
        $this->assertGreaterThanOrEqual(5, Rental::count());
        $this->assertSame(1, User::count());
        $this->assertSame('owner@example.test', User::first()->email);
    }

    public function test_a_fresh_copy_gets_an_app_key_written_to_its_env_file(): void
    {
        config(['app.key' => '']);

        $this->app->make(InstallationService::class)->ensureAppKey();

        $key = $this->env('APP_KEY');
        $this->assertStringStartsWith('base64:', $key);
        $this->assertSame(32, strlen(base64_decode(substr($key, 7))));
        $this->assertSame($key, config('app.key'));
    }

    public function test_an_existing_app_key_is_never_replaced(): void
    {
        config(['app.key' => 'base64:existing']);

        $this->app->make(InstallationService::class)->ensureAppKey();

        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'.env');
        $this->assertSame('base64:existing', config('app.key'));
    }
}
