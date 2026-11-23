<?php

use App\Http\Controllers\CatalogSearchController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserBookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [MeController::class, 'show'])->name('me.show');
    Route::patch('/me', [MeController::class, 'update'])->name('me.update');
    Route::delete('/me', [MeController::class, 'destroy'])->middleware('throttle:password-confirm')->name('me.destroy');

    Route::get('/catalog/search', CatalogSearchController::class)
        ->middleware('throttle:catalog')
        ->name('catalog.search');

    Route::apiResource('library', UserBookController::class)->parameters(['library' => 'userBook']);

    Route::post('/library/{userBook}/progress', [ReadingProgressController::class, 'store'])->name('library.progress');
    Route::post('/library/{userBook}/reread', [ReadingProgressController::class, 'reread'])->name('library.reread');
    Route::get('/library/{userBook}/logs', [ReadingProgressController::class, 'index'])->name('library.logs');

    Route::post('/library/{userBook}/quotes', [QuoteController::class, 'store'])->name('library.quotes.store');
    // Before the {quote} routes, or "daily" would be taken for a quote id.
    Route::get('/quotes/export', [QuoteController::class, 'export'])->name('quotes.export');
    Route::get('/quotes/daily', [QuoteController::class, 'daily'])->name('quotes.daily');
    Route::apiResource('quotes', QuoteController::class)->except('store');
    Route::get('/tags', TagController::class)->name('tags.index');

    Route::get('/stats/summary', [StatsController::class, 'summary'])->name('stats.summary');
    Route::get('/stats/daily', [StatsController::class, 'daily'])->name('stats.daily');
    Route::get('/stats/year/{year}', [StatsController::class, 'year'])->where('year', '[0-9]{4}')->name('stats.year');

    Route::get('/goals/{year}', [GoalController::class, 'show'])->where('year', '[0-9]{4}')->name('goals.show');
    Route::put('/goals/{year}', [GoalController::class, 'update'])->where('year', '[0-9]{4}')->name('goals.update');
    Route::delete('/goals/{year}', [GoalController::class, 'destroy'])->where('year', '[0-9]{4}')->name('goals.destroy');
});

// Each area of the app keeps its routes in a file of its own.
Route::middleware('auth:sanctum')->group(base_path('routes/social.php'));
Route::middleware('auth:sanctum')->group(base_path('routes/reader.php'));
