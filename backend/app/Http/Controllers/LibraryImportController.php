<?php

namespace App\Http\Controllers;

use App\Actions\Library\ImportGoodreadsLibrary;
use App\Http\Requests\ImportLibraryRequest;
use Illuminate\Http\JsonResponse;

class LibraryImportController extends Controller
{
    /**
     * Import a Goodreads CSV export into the library: `{imported, skipped_existing, skipped_invalid, errors}`.
     */
    public function store(ImportLibraryRequest $request, ImportGoodreadsLibrary $import): JsonResponse
    {
        $contents = (string) file_get_contents($request->file('file')->getRealPath());

        return response()->json(['data' => $import->handle($request->user(), $contents)]);
    }
}
