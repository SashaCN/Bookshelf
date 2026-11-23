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

        // An import writes up to 2,000 books in one request.
        RateLimiter::for('library-import', fn (Request $request) => Limit::perHour(5)->by((string) $request->user()->id));

        // Posting is cheap but spammable: a reader may leave a handful of comments a minute.
        RateLimiter::for('comments', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()->id));

        // Follows, likes and blocks are cheap writes that can still be scripted into spam or churn.
        RateLimiter::for('social', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()->id));

        // Shared profile links are open to guests, who are told apart by address only.
        RateLimiter::for('public-profile', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user('sanctum')->id ?? $request->ip())));
    }
}
