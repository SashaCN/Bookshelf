<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        // The community: how much one reader may do in a given time, each action with a limit of its own.
        $perReader = fn (Request $request): string => (string) ($request->user()->id ?? $request->ip());
        RateLimiter::for('posts', fn (Request $request) => Limit::perHour(20)->by($perReader($request)));
        RateLimiter::for('comments', fn (Request $request) => Limit::perHour(60)->by($perReader($request)));
        RateLimiter::for('likes', fn (Request $request) => Limit::perMinute(120)->by($perReader($request)));
        RateLimiter::for('follows', fn (Request $request) => Limit::perMinute(120)->by($perReader($request)));
        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(20)->by($perReader($request)));
        // Searching goes through the list of all the readers; the rest are the changes with no limit of their own.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($perReader($request)));
        RateLimiter::for('social-writes', fn (Request $request) => Limit::perMinute(60)->by($perReader($request)));

        // The reader saves the place every 15 seconds and when it is left; 120 a minute is far more than that.
        RateLimiter::for('reader-position', fn (Request $request) => Limit::perMinute(120)->by($perReader($request)));

        // A report is stored with "post" or "comment" as its type, not with the name of a class.
        Relation::morphMap(['post' => Post::class, 'comment' => Comment::class]);
    }
}
