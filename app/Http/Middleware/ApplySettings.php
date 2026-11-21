<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the administrator's settings before the rest of the request is handled.
 */
class ApplySettings
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->applyIfAvailable();

        return $next($request);
    }
}
