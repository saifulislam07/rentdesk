<?php

namespace App\Http\Middleware;

use App\Services\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The installer disappears once the app is installed, so nobody can reset it.
 */
class EnsureNotInstalled
{
    public function __construct(private readonly InstallationService $installation) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->installation->isInstalled(), 404);

        return $next($request);
    }
}
