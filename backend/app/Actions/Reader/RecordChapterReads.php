<?php

namespace App\Actions\Reader;

use App\Models\ReaderDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecordChapterReads
{
    /** A bigger forward jump is a jump through the table of contents, not reading. */
    public const MAX_JUMP = 50;

    /**
     * Records the chapters left behind by moving forward from one chapter to another. A chapter counts once, on the
     * reader's local day it was first left forward. Moving backwards records nothing; after a jump of more than
     * MAX_JUMP chapters only the chapter that was left is recorded. The unique index settles races (insertOrIgnore).
     */
    public function handle(ReaderDocument $document, User $user, int $from, int $to): void
    {
        if ($to <= $from) {
            return;
        }

        $last = $to - $from > self::MAX_JUMP ? $from : $to - 1;
        $now = CarbonImmutable::now();
        $day = $now->setTimezone($user->timezone)->toDateString();

        $rows = [];
        for ($chapter = $from; $chapter <= $last; $chapter++) {
            $rows[] = [
                'reader_document_id' => $document->id,
                'chapter' => $chapter,
                'read_on' => $day,
                'created_at' => $now->toDateTimeString(),
            ];
        }

        DB::table('reader_chapter_reads')->insertOrIgnore($rows);
    }
}
