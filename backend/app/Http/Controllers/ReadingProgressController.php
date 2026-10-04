<?php

namespace App\Http\Controllers;

use App\Actions\Library\UpdateProgress;
use App\Http\Requests\UpdateProgressRequest;
use App\Http\Resources\ReadingLogResource;
use App\Http\Resources\UserBookResource;
use App\Models\UserBook;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ReadingProgressController extends Controller
{
    /**
     * Move the bookmark to a page. A book that is wanted or put aside starts being read; a finished one is refused.
     * The answer also says how many pages the move was worth and whether the reader reached the last page.
     */
    public function store(UpdateProgressRequest $request, UserBook $userBook, UpdateProgress $updateProgress): UserBookResource
    {
        $update = $updateProgress->handle($userBook, $request->integer('page'));

        return (new UserBookResource($update->userBook))->additional([
            'meta' => [
                'pages' => $update->pages(),
                'reached_end' => $update->reachedEnd(),
            ],
        ]);
    }

    /**
     * The reading journal of a book, newest change first.
     */
    public function index(UserBook $userBook): AnonymousResourceCollection
    {
        Gate::authorize('view', $userBook);

        return ReadingLogResource::collection(
            $userBook->readingLogs()->latest('id')->simplePaginate(20),
        );
    }
}
