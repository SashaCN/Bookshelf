<?php

namespace App\Actions\Library;

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;

class RecordReadingLog
{
    /**
     * Writes down a move of the bookmark; nothing is written when the page did not change.
     * The entry belongs to the reader's calendar day, not to the server's.
     */
    public function handle(UserBook $userBook, int $fromPage, int $toPage): ?ReadingLog
    {
        if ($fromPage === $toPage) {
            return null;
        }

        $timezone = $userBook->user->timezone ?: User::DEFAULT_TIMEZONE;

        return $userBook->readingLogs()->create([
            'user_id' => $userBook->user_id,
            'from_page' => $fromPage,
            'to_page' => $toPage,
            'logged_on' => now($timezone)->toDateString(),
        ]);
    }
}
