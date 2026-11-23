<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify registers some routes without a throttle option, so the limiters are attached here by route name. It is
 * listed in the Fortify middleware (config/fortify.php); the limiters themselves live in FortifyServiceProvider.
 */
class ThrottleFortifyRoutes
{
    /** @var array<string, string> Fortify route name => named rate limiter */
    private const LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'forgot-password',
        'user-password.update' => 'password-confirm',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::LIMITERS as $route => $limiter) {
            if ($request->routeIs($route)) {
                return $this->throttle->handle($request, $next, $limiter);
            }
        }

        return $next($request);
    }
}
