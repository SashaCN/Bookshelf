<?php

namespace App\Actions\Library;

use App\Enums\BookStatus;
use App\Exceptions\BookAlreadyInLibraryException;
use App\Models\User;
use App\Services\Goodreads\GoodreadsCsvReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds the books of a Goodreads export to the reader's library as hand-entered books. Nothing is looked up
 * externally. A book the reader already has (same ISBN, or same title and first author) is left alone.
 */
class ImportGoodreadsLibrary
{
    private const CHUNK = 100;

    private const MAX_REPORTED_ERRORS = 20;

    public function __construct(
        private readonly GoodreadsCsvReader $reader,
        private readonly AddBookToLibrary $addBook,
    ) {}

    /**
     * @return array{imported: int, skipped_existing: int, skipped_invalid: int, errors: list<array{row: int, reason: string}>}
     *
     * @throws ValidationException when the file is not a usable export
     */
    public function handle(User $user, string $contents): array
    {
        $rows = $this->reader->read($contents);

        [$knownIsbns, $knownTitles] = $this->libraryKeys($user);

        $imported = 0;
        $skippedExisting = 0;
        $skippedInvalid = 0;
        $errors = [];

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::transaction(function () use ($user, $chunk, &$knownIsbns, &$knownTitles, &$imported, &$skippedExisting, &$skippedInvalid, &$errors): void {
                foreach ($chunk as ['row' => $row, 'fields' => $fields]) {
                    $data = $this->map($fields);

                    if (is_string($data)) {
                        $skippedInvalid++;
                        $this->report($errors, $row, $data);

                        continue;
                    }

                    $titleKey = $this->titleKey($data['title'], $data['authors'][0] ?? '');
                    $isbn = $data['isbn_13'] ?? null;

                    if (isset($knownTitles[$titleKey]) || ($isbn !== null && isset($knownIsbns[$isbn]))) {
                        $skippedExisting++;

                        continue;
                    }

                    try {
                        $this->addBook->handle($user, $data);
                    } catch (ValidationException $e) {
                        $skippedInvalid++;
                        $this->report($errors, $row, (string) collect($e->errors())->flatten()->first());

                        continue;
                    } catch (BookAlreadyInLibraryException) {
                        $skippedExisting++;

                        continue;
                    }

                    $imported++;
                    $knownTitles[$titleKey] = true;

                    if ($isbn !== null) {
                        $knownIsbns[$isbn] = true;
                    }
                }
            });
        }

        return [
            'imported' => $imported,
            'skipped_existing' => $skippedExisting,
            'skipped_invalid' => $skippedInvalid,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array{row: int, reason: string}>  $errors
     */
    private function report(array &$errors, int $row, string $reason): void
    {
        if (count($errors) < self::MAX_REPORTED_ERRORS) {
            $errors[] = ['row' => $row, 'reason' => $reason];
        }
    }

    /**
     * @return array{0: array<string, true>, 1: array<string, true>} ISBNs and normalized title keys already in the library
     */
    private function libraryKeys(User $user): array
    {
        $isbns = [];
        $titles = [];

        foreach ($user->userBooks()->with('book.authors')->get() as $userBook) {
            $book = $userBook->book;

            if ($book->isbn_13 !== null) {
                $isbns[$book->isbn_13] = true;
            }

            $titles[$this->titleKey($book->title, $book->authors->first()->name ?? '')] = true;
        }

        return [$isbns, $titles];
    }

    private function titleKey(string $title, string $author): string
    {
        return $this->normalize($title).'|'.$this->normalize($author);
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)));
    }

    /**
     * Turns one export row into the data AddBookToLibrary takes, or returns a reason code why the row is unusable.
     *
     * @param  array<string, string>  $fields
     * @return array<string, mixed>|string
     */
    private function map(array $fields): array|string
    {
        $get = fn (string $name): string => trim($fields[$name] ?? '');

        $title = $get('Title');

        if ($title === '') {
            return 'missing_title';
        }

        if (mb_strlen($title) > 255) {
            return 'title_too_long';
        }

        $authors = $this->authors($get('Author'), $get('Additional Authors'));

        if ($authors === null) {
            return 'author_too_long';
        }

        $status = match ($get('Exclusive Shelf')) {
            'read' => BookStatus::Finished,
            'currently-reading' => BookStatus::Reading,
            'to-read' => BookStatus::Want,
            default => null,
        };

        if ($status === null) {
            return 'unknown_shelf';
        }

        $rating = $get('My Rating');

        if ($rating !== '' && (! ctype_digit($rating) || (int) $rating > 5)) {
            return 'invalid_rating';
        }

        $pages = $get('Number of Pages');
        $pages = ctype_digit($pages) && (int) $pages >= 1 && (int) $pages <= 20000 ? (int) $pages : null;

        $data = [
            'title' => $title,
            'authors' => $authors,
            'total_pages' => $pages,
            'published_year' => $this->year($get('Original Publication Year')) ?? $this->year($get('Year Published')),
            'isbn_13' => $this->isbn13($get('ISBN13')) ?? $this->isbn13($get('ISBN')),
        ];

        // "currently-reading" needs a page count to start a book; without one it waits on the want-to-read shelf.
        if ($status === BookStatus::Reading && $pages === null) {
            $status = BookStatus::Want;
        }

        if ($status !== BookStatus::Want) {
            $data['status'] = $status->value;
        }

        if ($status === BookStatus::Finished) {
            $date = $get('Date Read') !== '' ? $get('Date Read') : $get('Date Added');
            $finishedAt = $this->date($date);

            if ($finishedAt === false) {
                return 'invalid_date';
            }

            $data['finished_at'] = $finishedAt;
            $data['rating'] = (int) $rating > 0 ? (int) $rating : null;
            // Imported reviews stay private (the column default); the reader can publish them one by one.
            $data['review'] = $get('My Review') !== '' ? mb_substr($get('My Review'), 0, 5000) : null;
        }

        return $data;
    }

    /**
     * @return list<string>|null null when a name is too long for the catalog
     */
    private function authors(string $first, string $additional): ?array
    {
        $names = [];

        foreach ([$first, ...explode(',', $additional)] as $name) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $name));

            if ($name === '') {
                continue;
            }

            if (mb_strlen($name) > 255) {
                return null;
            }

            $names[] = $name;
        }

        return array_slice(array_values(array_unique($names)), 0, 5);
    }

    private function year(string $value): ?int
    {
        return ctype_digit($value) && (int) $value >= 1 && (int) $value <= (int) date('Y') + 1 ? (int) $value : null;
    }

    /** Goodreads wraps identifiers as ="9780140449136" so spreadsheets keep them as text. */
    private function isbn13(string $value): ?string
    {
        $digits = preg_replace('/[^0-9Xx]/', '', $value);

        return strlen((string) $digits) === 13 && ctype_digit((string) $digits) ? $digits : null;
    }

    /**
     * @return string|false|null Y-m-d, null when empty, false when malformed
     */
    private function date(string $value): string|false|null
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('#^(\d{4})[/-](\d{2})[/-](\d{2})$#', $value, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return false;
        }

        return "$m[1]-$m[2]-$m[3]";
    }
}
