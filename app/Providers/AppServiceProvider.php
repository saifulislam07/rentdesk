<?php

namespace App\Providers;

use App\Services\InstallationService;
use App\Services\Languages;
use App\Services\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.rentdesk');
        $this->prepareForInstallation();
        $this->registerDateFormats();
        $this->app->make(Languages::class)->registerTranslationPath();

        // Scheduled commands read the settings too; web requests get them from the ApplySettings middleware.
        if ($this->app->runningInConsole() && $this->app->make(InstallationService::class)->isInstalled()) {
            $this->app->make(Settings::class)->applyIfAvailable();
        }

        // Lazy loading throws outside production, so N+1 queries are caught in development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Public booking requests: a few per minute and per hour from one address.
        RateLimiter::for('booking', fn (Request $request): array => [
            Limit::perMinute(2)->by($request->ip()),
            Limit::perHour(6)->by($request->ip()),
        ]);
    }

    /**
     * Carbon helpers that show dates and times the way the administrator chose under Settings.
     */
    private function registerDateFormats(): void
    {
        Carbon::macro('toAppDate', fn (): string => $this->format(config('rentdesk.date_format')));
        Carbon::macro('toAppTime', fn (): string => $this->format(config('rentdesk.time_format')));
        Carbon::macro('toAppDateTime', fn (): string => $this->format(config('rentdesk.date_format').' '.config('rentdesk.time_format')));
    }

    /**
     * Before installation there is no database for sessions or cache, and a fresh copy has no app key.
     */
    private function prepareForInstallation(): void
    {
        $installation = $this->app->make(InstallationService::class);

        if ($this->app->runningInConsole() || $installation->isInstalled()) {
            return;
        }

        config(['session.driver' => 'file', 'cache.default' => 'file', 'queue.default' => 'sync']);
        $installation->ensureAppKey();
    }
}
