<?php

namespace App\Actions\Library;

use App\Enums\BookStatus;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves the bookmark of a library entry and writes the change to the reading log.
 * A book that is wanted or was put aside starts (or resumes) being read by this.
 */
class UpdateProgress
{
    public function __construct(
        private readonly ChangeUserBookStatus $changeStatus,
        private readonly RecordReadingLog $recordLog,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(UserBook $userBook, int $page): ProgressUpdate
    {
        return DB::transaction(function () use ($userBook, $page): ProgressUpdate {
            // Taps arrive in quick succession; the second one must start from where the first one ended.
            $entry = UserBook::query()->whereKey($userBook->getKey())->lockForUpdate()->firstOrFail();

            if ($entry->status === BookStatus::Finished) {
                throw ValidationException::withMessages(['page' => ['library.progress_finished']]);
            }

            $from = $entry->current_page;

            if ($page !== $from) {
                $this->moveTo($entry, $page);
            }

            return new ProgressUpdate(
                $entry->load('book.authors'),
                $this->recordLog->handle($entry, $from, $page),
            );
        });
    }

    /**
     * @throws ValidationException
     */
    private function moveTo(UserBook $entry, int $page): void
    {
        if ($entry->status !== BookStatus::Reading) {
            // Checks that the page count is known, which every other rule below relies on.
            $this->changeStatus->apply($entry, BookStatus::Reading);
        }

        if ($entry->total_pages !== null && $page > $entry->total_pages) {
            throw ValidationException::withMessages(['page' => ['library.page_above_total']]);
        }

        $entry->current_page = $page;
        $entry->save();
    }
}
