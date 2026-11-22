<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        // Each search hits the external catalog, so keep one reader from hammering it.
        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()->id ?? $request->ip())));

        // Each upload is unzipped and parsed, which costs far more than a normal request.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()->id ?? $request->ip())));

        // Shared profile links are open to guests, who are told apart by address only.
        RateLimiter::for('public-profile', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user('sanctum')->id ?? $request->ip())));
    }
}
