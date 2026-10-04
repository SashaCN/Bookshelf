<?php

use App\Exceptions\BookAlreadyInLibraryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app is only reachable through the reverse proxy in front of it (Caddy and nginx in
        // production), never directly. Without this every reader would share the proxy's address,
        // and the per-address login throttle would lock everybody out together.
        $middleware->trustProxies(at: '*');

        // Login is a screen of the SPA, so there is no "login" route to redirect guests to. Without this the
        // framework's default tries route('login') and answers 500 to any request that does not ask for JSON.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A normal client error, not worth a log entry.
        $exceptions->dontReport(BookAlreadyInLibraryException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
