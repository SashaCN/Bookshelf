<?php

namespace App\Actions\Library;

use App\Enums\BookStatus;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts another pass through a finished book: the finished pass is archived, the bookmark goes back to the start.
 * Reading logs, rating and review stay as they are.
 */
class StartRereading
{
    /**
     * @throws ValidationException
     */
    public function handle(UserBook $userBook): UserBook
    {
        DB::transaction(function () use ($userBook): void {
            // Decide on what is stored now, and make a double tap wait for the first one.
            UserBook::query()->whereKey($userBook->getKey())->lockForUpdate()->firstOrFail();
            $userBook->refresh();

            if ($userBook->status !== BookStatus::Finished) {
                throw ValidationException::withMessages(['status' => ['library.reread_not_finished']]);
            }

            $userBook->reads()->create([
                'started_at' => $userBook->started_at,
                // A finished entry always has the date; "now" only guards against hand-made data.
                'finished_at' => $userBook->finished_at ?? now(),
            ]);

            $userBook->forceFill([
                'status' => BookStatus::Reading,
                'current_page' => 0,
                'started_at' => now(),
                'finished_at' => null,
            ])->save();
        });

        return $userBook->refresh()->load('book.authors');
    }
}
