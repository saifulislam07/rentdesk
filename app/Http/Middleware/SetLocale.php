<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language the visitor picked, when it is one the app offers.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if ($locale && array_key_exists($locale, config('rentdesk.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
