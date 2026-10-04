<?php

namespace App\Actions\Library;

use App\Enums\BookStatus;
use App\Models\UserBook;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Moves a library entry to another status and keeps its dates and progress consistent.
 * The allowed transitions are listed in BookStatus::allowedTransitions().
 */
class ChangeUserBookStatus
{
    /**
     * Mutates the model without saving it, so callers can persist several changes at once.
     *
     * @param  Carbon|null  $startedAt  only used for "want" to "finished" (a book read earlier)
     * @param  Carbon|null  $finishedAt  only used for "want" to "finished" (a book read earlier)
     *
     * @throws ValidationException
     */
    public function apply(
        UserBook $userBook,
        BookStatus $target,
        ?Carbon $startedAt = null,
        ?Carbon $finishedAt = null,
    ): void {
        $current = $userBook->status;

        if ($current === $target) {
            return;
        }

        if (! $current->canMoveTo($target)) {
            throw ValidationException::withMessages(['status' => ['library.invalid_transition']]);
        }

        match (true) {
            $current === BookStatus::Want && $target === BookStatus::Reading => $this->startReading($userBook),
            $current === BookStatus::Want && $target === BookStatus::Finished => $this->markReadEarlier($userBook, $startedAt, $finishedAt),
            $target === BookStatus::Finished => $this->finishReading($userBook),
            $target === BookStatus::Reading => $this->resumeReading($userBook),
            default => null,
        };

        $userBook->status = $target;
    }

    private function startReading(UserBook $userBook): void
    {
        $this->requireTotalPages($userBook);

        $userBook->started_at = now();
    }

    private function resumeReading(UserBook $userBook): void
    {
        // From "abandoned" the reader continues where they stopped; from "finished" it is a correction.
        $userBook->finished_at = null;
    }

    private function finishReading(UserBook $userBook): void
    {
        $userBook->finished_at = now();
        $userBook->current_page = $userBook->total_pages ?? $userBook->current_page;
    }

    private function markReadEarlier(UserBook $userBook, ?Carbon $startedAt, ?Carbon $finishedAt): void
    {
        $userBook->started_at = $startedAt;
        $userBook->finished_at = $finishedAt ?? now();
        $userBook->current_page = $userBook->total_pages ?? 0;
    }

    /**
     * @throws ValidationException
     */
    private function requireTotalPages(UserBook $userBook): void
    {
        if (! $userBook->total_pages) {
            throw ValidationException::withMessages(['total_pages' => ['library.total_pages_required']]);
        }
    }
}
