<?php

use App\Http\Controllers\BookChapterController;
use App\Http\Controllers\BookTextController;
use App\Http\Controllers\ReadingPositionController;
use Illuminate\Support\Facades\Route;

// Routes of the reader: book texts, chapters, reading position.
// Loaded from routes/api.php inside the "auth:sanctum" group, under the /api prefix.

// Ids and positions are whole numbers of a sensible size: anything else is a path that does not exist (not a number
// that does not fit into an integer and fails somewhere down below).
$id = '[0-9]{1,18}';

// Reading and cleaning up a book costs time and memory: 20 uploads an hour per reader is plenty.
Route::post('/library/{userBook}/text', [BookTextController::class, 'store'])
    ->where('userBook', $id)
    ->middleware('throttle:20,60')
    ->name('library.text.store');
Route::get('/library/{userBook}/text', [BookTextController::class, 'show'])->where('userBook', $id)->name('library.text.show');
Route::delete('/library/{userBook}/text', [BookTextController::class, 'destroy'])->where('userBook', $id)->name('library.text.destroy');

Route::get('/book-texts/{bookText}/chapters/{position}', [BookChapterController::class, 'show'])
    ->where(['bookText' => $id, 'position' => '[0-9]{1,6}'])
    ->name('book-texts.chapters.show');
Route::put('/book-texts/{bookText}/position', [ReadingPositionController::class, 'update'])
    ->where('bookText', $id)
    ->middleware('throttle:reader-position')
    ->name('book-texts.position');
