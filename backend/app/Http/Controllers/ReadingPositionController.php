<?php

namespace App\Http\Controllers;

use App\Actions\Reader\SaveReadingPosition;
use App\Http\Requests\SaveReadingPositionRequest;
use App\Http\Resources\BookTextResource;
use App\Http\Resources\UserBookResource;
use App\Models\BookText;

class ReadingPositionController extends Controller
{
    /**
     * Save where the reader is. The page of the library entry moves on with the reader (never back), so the answer
     * carries the entry as it is now.
     */
    public function update(SaveReadingPositionRequest $request, BookText $bookText, SaveReadingPosition $savePosition): BookTextResource
    {
        $saved = $savePosition->handle($bookText, $request->integer('chapter'), (float) $request->input('chapter_progress'));

        return (new BookTextResource($saved->text))->additional([
            'meta' => ['user_book' => new UserBookResource($saved->userBook)],
        ]);
    }
}
