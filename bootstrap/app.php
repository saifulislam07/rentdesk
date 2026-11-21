<?php

use App\Exceptions\InventoryException;
use App\Exceptions\RentalException;
use App\Exceptions\UnitUnavailableException;
use App\Http\Middleware\ApplySettings;
use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Middleware\EnsurePublicBookingEnabled;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'public.booking' => EnsurePublicBookingEnabled::class,
            'not.installed' => EnsureNotInstalled::class,
        ]);
        $middleware->web(prepend: [RedirectToInstaller::class], append: [ApplySettings::class, SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Business-rule failures carry a user-facing message: show it on the form instead of a 500.
        $exceptions->dontReport([RentalException::class, UnitUnavailableException::class, InventoryException::class]);
        $exceptions->render(fn (RentalException|UnitUnavailableException|InventoryException $exception, Request $request) => $request->expectsJson()
            ? response()->json(['message' => $exception->getMessage()], 422)
            : back()->withInput()->with('error', $exception->getMessage()));
    })->create();
