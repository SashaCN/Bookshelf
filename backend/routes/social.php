<?php

use App\Http\Controllers\BlockController;
use App\Http\Controllers\BookReviewController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Routes of the community: profiles, posts, feed, follows, likes, comments, reports, blocks.
// Loaded from routes/api.php inside the "auth:sanctum" group, under the /api prefix.

Route::get('/profiles', [ProfileController::class, 'index'])->middleware('throttle:search')->name('profiles.index');
Route::get('/profiles/{user:handle}', [ProfileController::class, 'show'])->name('profiles.show');
Route::get('/profiles/{user:handle}/posts', [ProfileController::class, 'posts'])->name('profiles.posts');
Route::put('/profiles/{user:handle}/follow', [FollowController::class, 'store'])->middleware('throttle:follows')->name('profiles.follow');
Route::delete('/profiles/{user:handle}/follow', [FollowController::class, 'destroy'])->middleware('throttle:follows')->name('profiles.unfollow');

Route::get('/feed', FeedController::class)->name('feed');

Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::patch('/posts/{post}', [PostController::class, 'update'])->middleware('throttle:social-writes')->name('posts.update');
Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('throttle:social-writes')->name('posts.destroy');
Route::put('/posts/{post}/like', [LikeController::class, 'store'])->middleware('throttle:likes')->name('posts.like');
Route::delete('/posts/{post}/like', [LikeController::class, 'destroy'])->middleware('throttle:likes')->name('posts.unlike');

Route::get('/posts/{post}/comments', [CommentController::class, 'index'])->name('posts.comments.index');
Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:comments')->name('posts.comments.store');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('throttle:social-writes')->name('comments.destroy');

Route::post('/posts/{post}/report', [ReportController::class, 'post'])->middleware('throttle:reports')->name('posts.report');
Route::post('/comments/{comment}/report', [ReportController::class, 'comment'])->middleware('throttle:reports')->name('comments.report');

Route::get('/blocks', [BlockController::class, 'index'])->name('blocks.index');
Route::put('/blocks/{user:handle}', [BlockController::class, 'store'])->middleware('throttle:social-writes')->name('blocks.store');
Route::delete('/blocks/{user:handle}', [BlockController::class, 'destroy'])->middleware('throttle:social-writes')->name('blocks.destroy');

Route::get('/books/{book}/reviews', [BookReviewController::class, 'index'])->name('books.reviews');
