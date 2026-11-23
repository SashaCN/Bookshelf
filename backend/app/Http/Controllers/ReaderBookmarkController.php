<?php

namespace App\Http\Controllers;

use App\Actions\Reader\DeleteBookmark;
use App\Actions\Reader\SaveBookmark;
use App\Http\Requests\StoreReaderBookmarkRequest;
use App\Http\Resources\ReaderBookmarkResource;
use App\Models\ReaderBookmark;
use App\Models\ReaderDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReaderBookmarkController extends Controller
{
    public function index(ReaderDocument $readerDocument): AnonymousResourceCollection
    {
        Gate::authorize('view', $readerDocument);

        return ReaderBookmarkResource::collection(
            $readerDocument->bookmarks()->with('readerDocument')->orderBy('chapter')->get()
        );
    }

    public function store(StoreReaderBookmarkRequest $request, ReaderDocument $readerDocument, SaveBookmark $save): JsonResponse
    {
        $bookmark = $save->handle(
            $readerDocument,
            $request->integer('chapter'),
            $request->validated('note'),
            $request->has('note'),
        );

        return (new ReaderBookmarkResource($bookmark->setRelation('readerDocument', $readerDocument)))
            ->response()
            ->setStatusCode($bookmark->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(ReaderDocument $readerDocument, ReaderBookmark $bookmark, DeleteBookmark $delete): Response
    {
        Gate::authorize('update', $readerDocument);

        $delete->handle($bookmark);

        return response()->noContent();
    }
}
