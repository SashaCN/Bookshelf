<?php

use App\Http\Controllers\LibraryImportController;
use Illuminate\Support\Facades\Route;

// Importing a library from another service. Loaded from routes/api.php inside the "auth:sanctum" group, under /api.

Route::post('/library/import', [LibraryImportController::class, 'store'])
    ->middleware('throttle:library-import')
    ->name('library.import');
