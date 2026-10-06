<?php

namespace App\Http\Controllers;

use App\Actions\Reader\DeleteBookText;
use App\Actions\Reader\ImportBookText;
use App\Http\Requests\StoreBookTextRequest;
use App\Http\Resources\BookTextResource;
use App\Models\UserBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookTextController extends Controller
{
    /**
     * Upload an EPUB for a library entry. Its text is cleaned up and stored (the file itself is not), replacing the
     * text the entry had, together with the places in it that the quotes of the entry pointed at.
     */
    public function store(StoreBookTextRequest $request, UserBook $userBook, ImportBookText $importText): JsonResponse
    {
        $file = $request->file('file');

        $text = $importText->handle($userBook, (string) $file?->getRealPath(), $this->sourceName((string) $file?->getClientOriginalName()));

        return (new BookTextResource($text))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The text of a library entry: its table of contents and where the reader is.
     */
    public function show(UserBook $userBook): BookTextResource
    {
        Gate::authorize('view', $userBook);

        $text = $userBook->bookText()->firstOrFail();

        return new BookTextResource($text->load('contents'));
    }

    /**
     * Remove the text. The quotes that were selected from it stay.
     */
    public function destroy(UserBook $userBook, DeleteBookText $deleteText): Response
    {
        Gate::authorize('delete', $userBook);

        abort_unless($deleteText->handle($userBook), Response::HTTP_NOT_FOUND);

        return response()->noContent();
    }

    /**
     * What the reader's file was called, as something safe to show: no path, no control characters, not too long.
     */
    private function sourceName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';

        return mb_substr($name, 0, 255) ?: 'book.epub';
    }
}
