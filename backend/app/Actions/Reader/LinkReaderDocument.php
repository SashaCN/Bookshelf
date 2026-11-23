<?php

namespace App\Actions\Reader;

use App\Models\ReaderDocument;

class LinkReaderDocument
{
    /**
     * Links the document to a library entry (already checked to be the reader's own) or, with null, unlinks it.
     * Only a link: pages and progress are not synced.
     */
    public function handle(ReaderDocument $document, ?int $userBookId): ReaderDocument
    {
        $document->update(['user_book_id' => $userBookId]);

        return $document->load('userBook.book');
    }
}
