<?php

use App\Http\Controllers\CatalogSearchController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UserBookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [MeController::class, 'show'])->name('me.show');
    Route::patch('/me', [MeController::class, 'update'])->name('me.update');

    Route::get('/catalog/search', CatalogSearchController::class)
        ->middleware('throttle:catalog')
        ->name('catalog.search');

    Route::apiResource('library', UserBookController::class)->parameters(['library' => 'userBook']);

    Route::post('/library/{userBook}/progress', [ReadingProgressController::class, 'store'])->name('library.progress');
    Route::get('/library/{userBook}/logs', [ReadingProgressController::class, 'index'])->name('library.logs');

    Route::post('/library/{userBook}/quotes', [QuoteController::class, 'store'])->name('library.quotes.store');
    // Before the {quote} routes, or "daily" would be taken for a quote id.
    Route::get('/quotes/daily', [QuoteController::class, 'daily'])->name('quotes.daily');
    Route::apiResource('quotes', QuoteController::class)->except('store');

    Route::get('/stats/summary', [StatsController::class, 'summary'])->name('stats.summary');
    Route::get('/stats/daily', [StatsController::class, 'daily'])->name('stats.daily');

    Route::get('/goals/{year}', [GoalController::class, 'show'])->whereNumber('year')->name('goals.show');
    Route::put('/goals/{year}', [GoalController::class, 'update'])->whereNumber('year')->name('goals.update');
    Route::delete('/goals/{year}', [GoalController::class, 'destroy'])->whereNumber('year')->name('goals.destroy');
});
