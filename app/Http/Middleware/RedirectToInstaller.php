<?php

namespace App\Http\Middleware;

use App\Services\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the app is installed every page leads to the installer.
 */
class RedirectToInstaller
{
    public function __construct(private readonly InstallationService $installation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installation->isInstalled() && ! $request->is('install', 'install/*', 'locale')) {
            return redirect()->route('install.welcome');
        }

        return $next($request);
    }
}
