<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdministratorRequest;
use App\Http\Requests\DatabaseSettingsRequest;
use App\Services\InstallationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The step-by-step web installer: requirements, database, administrator.
 */
class InstallController extends Controller
{
    private const DATABASE_DONE = 'install.database';

    public function __construct(private readonly InstallationService $installation) {}

    public function welcome(): View
    {
        return view('install.welcome', [
            'checks' => $this->installation->requirements(),
            'canContinue' => $this->installation->requirementsMet(),
        ]);
    }

    public function database(): View|RedirectResponse
    {
        if (! $this->installation->requirementsMet()) {
            return to_route('install.welcome');
        }

        return view('install.database', [
            'defaults' => [
                'driver' => old('driver', 'mysql'),
                'host' => old('host', '127.0.0.1'),
                'port' => old('port', '3306'),
                'database' => old('database'),
                'username' => old('username', 'root'),
            ],
        ]);
    }

    public function saveDatabase(DatabaseSettingsRequest $request): RedirectResponse
    {
        try {
            $this->installation->configureDatabase($request->settings(), $request->getSchemeAndHttpHost());
            $this->installation->migrate();
        } catch (RuntimeException $exception) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $exception->getMessage()]);
        }

        $request->session()->put(self::DATABASE_DONE, true);

        return to_route('install.administrator');
    }

    public function administrator(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::DATABASE_DONE)) {
            return to_route('install.database');
        }

        return view('install.administrator', ['defaultCompany' => config('rentdesk.company_name')]);
    }

    public function install(AdministratorRequest $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::DATABASE_DONE)) {
            return to_route('install.database');
        }

        $admin = $this->installation->finish($request->validated());

        $request->session()->flush();

        return view('install.finished', ['admin' => $admin, 'path' => base_path()]);
    }
}
