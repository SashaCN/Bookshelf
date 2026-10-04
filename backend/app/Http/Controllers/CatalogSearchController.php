<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogSearchRequest;
use App\Models\Book;
use App\Services\OpenLibrary\CatalogEntry;
use App\Services\OpenLibrary\OpenLibraryClient;
use Illuminate\Http\JsonResponse;

class CatalogSearchController extends Controller
{
    /**
     * Search the Open Library catalog. Results the reader already has are flagged with `in_library`.
     */
    public function __invoke(CatalogSearchRequest $request, OpenLibraryClient $catalog): JsonResponse
    {
        $entries = $catalog->search($request->validated('q'));

        $inLibrary = Book::query()
            ->whereIn('openlibrary_work_key', array_map(fn (CatalogEntry $entry) => $entry->workKey, $entries))
            ->whereHas('userBooks', fn ($query) => $query->where('user_id', $request->user()->id))
            ->pluck('openlibrary_work_key')
            ->all();

        return response()->json([
            'data' => array_map(
                fn (CatalogEntry $entry) => $entry->toArray() + ['in_library' => in_array($entry->workKey, $inLibrary, true)],
                $entries,
            ),
        ]);
    }
}
