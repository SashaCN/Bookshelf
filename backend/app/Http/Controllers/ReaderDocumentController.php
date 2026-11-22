<?php

namespace App\Http\Controllers;

use App\Actions\Reader\ImportEpub;
use App\Http\Requests\StoreReaderDocumentRequest;
use App\Http\Requests\UpdateReaderPositionRequest;
use App\Http\Resources\ReaderDocumentResource;
use App\Models\ReaderDocument;
use App\Services\Reader\EpubParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ReaderDocumentController extends Controller
{
    /**
     * The reader's uploaded books, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ReaderDocumentResource::collection($request->user()->readerDocuments()->latest('id')->get());
    }

    public function store(StoreReaderDocumentRequest $request, ImportEpub $import): JsonResponse
    {
        $document = $import->handle($request->user(), $request->file('file'));

        return (new ReaderDocumentResource($document))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ReaderDocument $readerDocument): ReaderDocumentResource
    {
        Gate::authorize('view', $readerDocument);

        return new ReaderDocumentResource($readerDocument);
    }

    public function destroy(ReaderDocument $readerDocument): Response
    {
        Gate::authorize('delete', $readerDocument);

        Storage::disk('local')->delete($readerDocument->path);
        $readerDocument->delete();

        return response()->noContent();
    }

    public function position(UpdateReaderPositionRequest $request, ReaderDocument $readerDocument): ReaderDocumentResource
    {
        $readerDocument->update([
            'position_chapter' => $request->integer('chapter'),
            'position_fraction' => $request->float('fraction'),
        ]);

        return new ReaderDocumentResource($readerDocument);
    }

    /**
     * One chapter as HTML. The client must still treat it as untrusted.
     */
    public function chapter(ReaderDocument $readerDocument, int $n, EpubParser $parser): JsonResponse
    {
        Gate::authorize('view', $readerDocument);

        $entry = $readerDocument->chapters[$n] ?? null;
        $chapter = $entry !== null && Storage::disk('local')->exists($readerDocument->path)
            ? $parser->chapter(Storage::disk('local')->path($readerDocument->path), $entry)
            : null;

        abort_if($chapter === null, Response::HTTP_NOT_FOUND);

        return response()->json(['data' => [
            'index' => $n,
            'count' => $readerDocument->chapters_count,
            'title' => $chapter['title'],
            'html' => $chapter['html'],
        ]]);
    }
}
