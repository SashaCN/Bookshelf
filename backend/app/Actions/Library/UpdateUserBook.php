<?php

namespace App\Actions\Library;

use App\Enums\BookStatus;
use App\Models\UserBook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUserBook
{
    public function __construct(
        private readonly ChangeUserBookStatus $changeStatus,
        private readonly RecordReadingLog $recordLog,
    ) {}

    /**
     * Applies a partial update. Everything is saved together or not at all.
     *
     * @param  array{status?: string, rating?: int|null, total_pages?: int, started_at?: string|null, finished_at?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(UserBook $userBook, array $data): UserBook
    {
        DB::transaction(function () use ($userBook, $data): void {
            $wasReading = $userBook->status === BookStatus::Reading;
            $pageBefore = $userBook->current_page;

            // Pages go first so that "want" to "reading" can be requested together with the page count.
            if (array_key_exists('total_pages', $data)) {
                $this->setTotalPages($userBook, (int) $data['total_pages']);
            }

            if (array_key_exists('rating', $data)) {
                $userBook->rating = $data['rating'];
            }

            if (isset($data['status'])) {
                $this->changeStatus->apply(
                    $userBook,
                    BookStatus::from($data['status']),
                    isset($data['started_at']) ? Carbon::parse($data['started_at']) : null,
                    isset($data['finished_at']) ? Carbon::parse($data['finished_at']) : null,
                );
            }

            $userBook->save();

            // Finishing a book that is being read jumps the bookmark to the last page. Those pages were read
            // (unlike a book marked as read earlier), so they belong in the log and in the statistics.
            if ($wasReading && $userBook->status === BookStatus::Finished) {
                $this->recordLog->handle($userBook, $pageBefore, $userBook->current_page);
            }
        });

        return $userBook->refresh()->load('book.authors');
    }

    /**
     * @throws ValidationException
     */
    private function setTotalPages(UserBook $userBook, int $totalPages): void
    {
        if ($userBook->status === BookStatus::Finished) {
            // A finished book has been read to the end, whatever its length turns out to be.
            $userBook->current_page = $totalPages;
        } elseif ($totalPages < $userBook->current_page) {
            throw ValidationException::withMessages(['total_pages' => ['library.total_pages_below_current']]);
        }

        $userBook->total_pages = $totalPages;
    }
}
