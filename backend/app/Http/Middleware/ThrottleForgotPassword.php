<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify registers the forgot-password route without a throttle option, so it is added here. It is listed in
 * the Fortify middleware (config/fortify.php) and only acts on that one route.
 */
class ThrottleForgotPassword
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('password.email')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'forgot-password');
    }
}
