<?php

namespace App\Actions\Reader;

use App\Actions\Library\UpdateProgress;
use App\Enums\BookStatus;
use App\Enums\ReaderError;
use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Remembers where in the text the reader is, works out how far into the whole text that is, and takes the page
 * of the library entry along when the reader has got further than it says.
 */
class SaveReadingPosition
{
    public function __construct(private readonly UpdateProgress $updateProgress) {}

    /**
     * @param  float  $chapterProgress  how far into the chapter, from 0 to 1
     *
     * @throws ValidationException when the text has no such chapter
     */
    public function handle(BookText $text, int $chapter, float $chapterProgress): PositionSaved
    {
        return DB::transaction(function () use ($text, $chapter, $chapterProgress): PositionSaved {
            // The same lock the progress update takes, so a tap on "+10" and a save from the reader do not interleave.
            $entry = UserBook::query()->whereKey($text->user_book_id)->lockForUpdate()->firstOrFail();

            $progress = $this->progressAt($text, $chapter, $chapterProgress);

            $text->update([
                'chapter_position' => $chapter,
                'chapter_progress' => $chapterProgress,
                'progress' => $progress,
                'position_updated_at' => now(),
            ]);

            return new PositionSaved($text, $this->raisePage($entry, $progress)->load('book.authors', 'bookText'));
        });
    }

    /**
     * (the characters of the chapters before + the part of this one that is behind the reader) / all the characters.
     *
     * @throws ValidationException
     */
    private function progressAt(BookText $text, int $chapter, float $chapterProgress): float
    {
        $characters = BookChapter::query()->where('book_text_id', $text->getKey())->where('position', $chapter)->value('characters');

        if ($characters === null) {
            throw ValidationException::withMessages(['chapter' => [ReaderError::ChapterOutOfRange->value]]);
        }

        if ($text->characters_count < 1) {
            return 0.0;
        }

        $before = (int) BookChapter::query()->where('book_text_id', $text->getKey())->where('position', '<', $chapter)->sum('characters');

        return min(1.0, max(0.0, ($before + $chapterProgress * $characters) / $text->characters_count));
    }

    /**
     * The page the reader has got to, if that is further than the page of the entry. The reader never takes the
     * page back, and a finished book is left alone. The progress update does the rest: the reading log, the streak and
     * the statistics, and the start of a book that was only wanted or put aside.
     */
    private function raisePage(UserBook $entry, float $progress): UserBook
    {
        if ($entry->status === BookStatus::Finished || ! $entry->total_pages) {
            return $entry;
        }

        $page = (int) round($progress * $entry->total_pages);

        if ($page <= $entry->current_page) {
            return $entry;
        }

        return $this->updateProgress->handle($entry, $page)->userBook;
    }
}
