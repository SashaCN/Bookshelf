<?php

namespace App\Actions\Reader;

use App\Enums\ReaderError;
use App\Exceptions\ImportBusyException;
use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\UserBook;
use App\Services\Epub\EpubImporter;
use App\Services\Epub\EpubImportException;
use App\Services\Epub\EpubLimits;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reads an uploaded EPUB and keeps its text as the text of a library entry, replacing the one it had.
 *
 * Everything happens in one transaction: either the whole book is stored or the entry is left as it was, with its
 * previous text and the anchors of its quotes. The book is read chapter by chapter and each chapter goes into the
 * database before the next one is parsed, so memory does not grow with the size of the book.
 *
 * Only one book is read at a time on the whole server (a lock in the cache): reading a hostile book can take a few
 * hundred megabytes, and the server does not have room for several. An upload that cannot get its turn in a few
 * seconds is told to try again. Since imports take turns, the quota of a reader cannot be raced either.
 */
class ImportBookText
{
    public function __construct(
        private readonly EpubImporter $importer,
        private readonly DeleteBookText $deleteText,
        private readonly EpubLimits $limits,
    ) {}

    /**
     * @param  string  $path  the uploaded file
     * @param  string  $sourceName  what the reader's file was called
     *
     * @throws ValidationException with a `reader.*` code on the field `file`
     * @throws ImportBusyException when another book is being read and does not finish in time
     */
    public function handle(UserBook $userBook, string $path, string $sourceName): BookText
    {
        // Longer than any import can take, so that a lock left by a process that died does not stay for ever.
        $lock = Cache::lock('epub-import', 120);

        try {
            $lock->block($this->limits->importLockWait);
        } catch (LockTimeoutException) {
            throw new ImportBusyException;
        }

        try {
            return $this->import($userBook, $path, $sourceName);
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws ValidationException
     */
    private function import(UserBook $userBook, string $path, string $sourceName): BookText
    {
        try {
            $book = $this->importer->open($path);
        } catch (EpubImportException $exception) {
            throw $this->refuse($exception);
        }

        try {
            $text = DB::transaction(function () use ($userBook, $book, $sourceName): BookText {
                // Two uploads for the same entry at once must not both think there was no text.
                UserBook::query()->whereKey($userBook->getKey())->lockForUpdate()->firstOrFail();

                $this->deleteText->handle($userBook);

                // What the reader keeps of other books; the text this one replaces has just been given back.
                $others = BookText::query()->where('user_id', $userBook->user_id);

                if ($others->count() >= $this->limits->maxReaderTexts) {
                    throw new EpubImportException(ReaderError::QuotaExceeded, 'too many texts');
                }

                $kept = (int) $others->sum('characters_count');

                $text = BookText::query()->create([
                    'user_book_id' => $userBook->getKey(),
                    'user_id' => $userBook->user_id,
                    'source_name' => $sourceName,
                    'chapters_count' => 0,
                    'characters_count' => 0,
                ]);

                $chapters = 0;
                $characters = 0;

                foreach ($book->chapters() as $chapter) {
                    // A plain insert: a model would keep the whole chapter in memory for the next ones to share it.
                    BookChapter::query()->insert([
                        'book_text_id' => $text->getKey(),
                        'position' => $chapter->position,
                        'title' => $chapter->title,
                        'content' => $chapter->html,
                        'characters' => $chapter->characters,
                    ]);

                    $chapters++;
                    $characters += $chapter->characters;

                    if ($kept + $characters > $this->limits->maxReaderCharacters) {
                        throw new EpubImportException(ReaderError::QuotaExceeded, 'too much text');
                    }
                }

                if ($chapters === 0) {
                    throw new EpubImportException(ReaderError::Empty);
                }

                $text->update(['chapters_count' => $chapters, 'characters_count' => $characters]);

                return $text;
            });
        } catch (EpubImportException $exception) {
            throw $this->refuse($exception);
        } finally {
            $book->close();
        }

        $userBook->unsetRelation('bookText');

        return $text->load('contents');
    }

    private function refuse(EpubImportException $exception): ValidationException
    {
        return ValidationException::withMessages(['file' => [$exception->error->value]]);
    }
}
