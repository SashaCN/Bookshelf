<?php

namespace App\Actions\Library;

use App\Enums\BookSource;
use App\Exceptions\BookAlreadyInLibraryException;
use App\Exceptions\CatalogUnavailableException;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use App\Services\OpenLibrary\CatalogEntry;
use App\Services\OpenLibrary\OpenLibraryClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddBookToLibrary
{
    public function __construct(
        private readonly OpenLibraryClient $catalog,
        private readonly UpdateUserBook $updateUserBook,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input of StoreUserBookRequest
     *
     * @throws BookAlreadyInLibraryException
     * @throws CatalogUnavailableException
     * @throws ValidationException
     */
    public function handle(User $user, array $data): UserBook
    {
        $workKey = isset($data['openlibrary_work_key']) ? $this->normalizeWorkKey($data['openlibrary_work_key']) : null;

        // The slow external call happens before the transaction so no DB connection is held while waiting.
        $knownBook = $workKey !== null ? Book::where('openlibrary_work_key', $workKey)->first() : null;
        $entry = $workKey !== null && $knownBook === null ? $this->lookUp($workKey) : null;

        return DB::transaction(function () use ($user, $data, $knownBook, $entry): UserBook {
            $book = $knownBook
                ?? ($entry !== null ? $this->storeCatalogBook($entry) : $this->createManualBook($user, $data));

            $existing = $user->userBooks()->where('book_id', $book->id)->first();

            if ($existing !== null) {
                throw new BookAlreadyInLibraryException($existing->id);
            }

            $userBook = $user->userBooks()->create([
                'book_id' => $book->id,
                'total_pages' => $data['total_pages'] ?? $book->page_count,
            ]);

            // A different starting status goes through the same rules as any later change.
            $extra = array_intersect_key($data, array_flip(['status', 'started_at', 'finished_at']));

            return $extra === [] ? $userBook->load('book.authors') : $this->updateUserBook->handle($userBook, $extra);
        });
    }

    private function normalizeWorkKey(string $input): string
    {
        return '/works/'.ltrim(Str::after($input, '/works/'), '/');
    }

    /**
     * @throws CatalogUnavailableException
     * @throws ValidationException
     */
    private function lookUp(string $workKey): CatalogEntry
    {
        return $this->catalog->findByWorkKey($workKey)
            ?? throw ValidationException::withMessages(['openlibrary_work_key' => ['catalog.not_found']]);
    }

    private function storeCatalogBook(CatalogEntry $entry): Book
    {
        $book = Book::create([
            'title' => $entry->title,
            'subtitle' => $entry->subtitle,
            'isbn_13' => $entry->isbn13,
            'openlibrary_work_key' => $entry->workKey,
            'cover_url' => $entry->coverUrl,
            'page_count' => $entry->pageCount,
            'published_year' => $entry->publishedYear,
            'subjects' => $entry->subjects,
            'source' => BookSource::OpenLibrary,
        ]);

        $this->attachAuthors($book, $entry->authors);

        return $book;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createManualBook(User $user, array $data): Book
    {
        $book = Book::create([
            'title' => $data['title'],
            'page_count' => $data['total_pages'] ?? null,
            'published_year' => $data['published_year'] ?? null,
            'source' => BookSource::Manual,
            'created_by' => $user->id,
        ]);

        /** @var list<string> $authors */
        $authors = $data['authors'] ?? [];
        $this->attachAuthors($book, $authors);

        return $book;
    }

    /**
     * @param  list<string>  $names
     */
    private function attachAuthors(Book $book, array $names): void
    {
        foreach (array_values(array_unique($names)) as $position => $name) {
            $author = Author::firstOrCreate(['name' => $name, 'openlibrary_key' => null]);

            $book->authors()->attach($author->id, ['position' => $position]);
        }
    }
}
