<?php

namespace App\Actions\Library;

use App\Models\ReadingLog;
use App\Models\UserBook;

/**
 * The outcome of moving a bookmark: the entry as it is now and the log entry that was written for it
 * (none when the page stayed where it was).
 */
final readonly class ProgressUpdate
{
    public function __construct(
        public UserBook $userBook,
        public ?ReadingLog $log,
    ) {}

    /** Pages gained by this update; negative when the reader corrected the page downwards. */
    public function pages(): int
    {
        return $this->log?->pages() ?? 0;
    }

    /** The reader is on the last page, so it is time to offer marking the book as finished. */
    public function reachedEnd(): bool
    {
        return $this->userBook->total_pages !== null
            && $this->userBook->current_page === $this->userBook->total_pages;
    }
}
