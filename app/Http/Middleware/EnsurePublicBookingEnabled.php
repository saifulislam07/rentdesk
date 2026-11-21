<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the public booking page entirely unless the feature is switched on.
 */
class EnsurePublicBookingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('rentdesk.public_booking'), 404);

        return $next($request);
    }
}
