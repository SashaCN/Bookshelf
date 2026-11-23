<?php

namespace App\Actions\Reader;

use App\Models\ReaderBookmark;
use App\Models\ReaderDocument;

class SaveBookmark
{
    /**
     * One bookmark per chapter: a second save of the same chapter only changes the note (when one is sent).
     * The unique index decides a race between two saves, so no check-then-insert.
     */
    public function handle(ReaderDocument $document, int $chapter, ?string $note, bool $hasNote): ReaderBookmark
    {
        $bookmark = $document->bookmarks()->createOrFirst(
            ['chapter' => $chapter],
            ['note' => $note],
        );

        if (! $bookmark->wasRecentlyCreated && $hasNote) {
            $bookmark->update(['note' => $note]);
        }

        return $bookmark;
    }
}
