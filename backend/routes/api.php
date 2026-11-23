<?php

use App\Http\Controllers\BlockController;
use App\Http\Controllers\CatalogSearchController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\LibraryImportController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteCommentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteLikeController;
use App\Http\Controllers\ReaderBookmarkController;
use App\Http\Controllers\ReaderDocumentController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserBookController;
use App\Http\Controllers\UserSearchController;
use Illuminate\Support\Facades\Route;

// Public profiles can be opened from a shared link without signing in. The session is still honoured,
// so a signed-in reader sees their own private profile and their follow state.
Route::middleware('throttle:public-profile')->group(function (): void {
    Route::get('/users/{username}', [ProfileController::class, 'show'])->name('users.show');
    Route::get('/users/{username}/quotes', [ProfileController::class, 'quotes'])->name('users.quotes');
    Route::get('/users/{username}/reviews', [ProfileController::class, 'reviews'])->name('users.reviews');
    Route::get('/quotes/{quote}/comments', [QuoteCommentController::class, 'index'])->where('quote', '[0-9]+')->name('quotes.comments.index');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [MeController::class, 'show'])->name('me.show');
    Route::patch('/me', [MeController::class, 'update'])->name('me.update');
    Route::delete('/me', [MeController::class, 'destroy'])->middleware('throttle:password-confirm')->name('me.destroy');
    Route::get('/me/export', [MeController::class, 'export'])->middleware('throttle:data-export')->name('me.export');
    Route::post('/me/followers-seen', [MeController::class, 'followersSeen'])->name('me.followers-seen');

    Route::get('/catalog/search', CatalogSearchController::class)
        ->middleware('throttle:catalog')
        ->name('catalog.search');

    Route::post('/library/import', [LibraryImportController::class, 'store'])
        ->middleware('throttle:library-import')
        ->name('library.import');

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
    Route::middleware('throttle:social')->group(function (): void {
        Route::post('/quotes/{quote}/like', [QuoteLikeController::class, 'store'])->where('quote', '[0-9]+')->name('quotes.like');
        Route::delete('/quotes/{quote}/like', [QuoteLikeController::class, 'destroy'])->where('quote', '[0-9]+')->name('quotes.unlike');
    });

    Route::post('/quotes/{quote}/comments', [QuoteCommentController::class, 'store'])
        ->middleware('throttle:comments')
        ->where('quote', '[0-9]+')
        ->name('quotes.comments.store');
    Route::delete('/quotes/{quote}/comments/{comment}', [QuoteCommentController::class, 'destroy'])
        ->where(['quote' => '[0-9]+', 'comment' => '[0-9]+'])
        ->name('quotes.comments.destroy');

    Route::post('/reader', [ReaderDocumentController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('reader.store');
    Route::apiResource('reader', ReaderDocumentController::class)
        ->parameters(['reader' => 'readerDocument'])
        ->except('store');
    Route::put('/reader/{readerDocument}/position', [ReaderDocumentController::class, 'position'])->name('reader.position');
    Route::get('/reader/{readerDocument}/chapters/{n}', [ReaderDocumentController::class, 'chapter'])
        ->where('n', '[0-9]{1,6}')
        ->name('reader.chapter');
    Route::get('/reader/{readerDocument}/bookmarks', [ReaderBookmarkController::class, 'index'])->name('reader.bookmarks.index');
    Route::post('/reader/{readerDocument}/bookmarks', [ReaderBookmarkController::class, 'store'])->name('reader.bookmarks.store');
    Route::delete('/reader/{readerDocument}/bookmarks/{bookmark}', [ReaderBookmarkController::class, 'destroy'])
        ->scopeBindings()
        ->where('bookmark', '[0-9]+')
        ->name('reader.bookmarks.destroy');

    Route::get('/feed', FeedController::class)->name('feed');
    Route::get('/users', UserSearchController::class)->name('users.search');
    Route::get('/users/{username}/followers', [ProfileController::class, 'followers'])->name('users.followers');
    Route::get('/users/{username}/following', [ProfileController::class, 'following'])->name('users.following');
    Route::middleware('throttle:social')->group(function (): void {
        Route::post('/users/{username}/follow', [FollowController::class, 'store'])->name('users.follow');
        Route::delete('/users/{username}/follow', [FollowController::class, 'destroy'])->name('users.unfollow');
        Route::post('/users/{username}/block', [BlockController::class, 'store'])->name('users.block');
        Route::delete('/users/{username}/block', [BlockController::class, 'destroy'])->name('users.unblock');
    });
    Route::get('/me/blocks', [BlockController::class, 'index'])->name('me.blocks');

    Route::get('/stats/summary', [StatsController::class, 'summary'])->name('stats.summary');
    Route::get('/stats/daily', [StatsController::class, 'daily'])->name('stats.daily');

    Route::get('/goals/{year}', [GoalController::class, 'show'])->where('year', '[0-9]{4}')->name('goals.show');
    Route::put('/goals/{year}', [GoalController::class, 'update'])->where('year', '[0-9]{4}')->name('goals.update');
    Route::delete('/goals/{year}', [GoalController::class, 'destroy'])->where('year', '[0-9]{4}')->name('goals.destroy');
});
