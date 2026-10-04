<?php

use App\Enums\BookSource;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    $this->user = User::factory()->create();
});

it('requires authentication', function () {
    $this->postJson('/api/library', ['title' => 'Book'])->assertUnauthorized();
});

describe('from Open Library', function () {
    it('adds the book to the catalog and the library', function () {
        fakeOpenLibrary([openLibraryDoc()]);

        $this->actingAs($this->user)->postJson('/api/library', ['openlibrary_work_key' => '/works/OL17930368W'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'want')
            ->assertJsonPath('data.total_pages', 323)
            ->assertJsonPath('data.current_page', 0)
            ->assertJsonPath('data.book.title', 'Atomic Habits')
            ->assertJsonPath('data.book.authors', ['James Clear'])
            ->assertJsonPath('data.book.source', 'openlibrary')
            ->assertJsonPath('data.allowed_statuses', ['reading', 'finished']);

        $book = Book::firstWhere('openlibrary_work_key', '/works/OL17930368W');
        expect($book->source)->toBe(BookSource::OpenLibrary)
            ->and($book->created_by)->toBeNull()
            ->and($book->isbn_13)->toBe('9786075696140')
            ->and($book->subjects)->toContain('Habit');
        expect($this->user->userBooks()->count())->toBe(1);
    });

    it('accepts the bare work id', function () {
        fakeOpenLibrary([openLibraryDoc()]);

        $this->actingAs($this->user)->postJson('/api/library', ['openlibrary_work_key' => 'OL17930368W'])
            ->assertCreated();

        expect(Book::where('openlibrary_work_key', '/works/OL17930368W')->exists())->toBeTrue();
    });

    it('lets the reader override the page count of their edition', function () {
        fakeOpenLibrary([openLibraryDoc()]);

        $this->actingAs($this->user)->postJson('/api/library', [
            'openlibrary_work_key' => '/works/OL17930368W',
            'total_pages' => 400,
        ])->assertCreated()->assertJsonPath('data.total_pages', 400);

        expect(Book::first()->page_count)->toBe(323);
    });

    it('reuses a book that is already in the shared catalog without asking Open Library', function () {
        $book = Book::factory()->withAuthors()->create(['openlibrary_work_key' => '/works/OL17930368W']);
        UserBook::factory()->create(['book_id' => $book->id]);

        $this->actingAs($this->user)->postJson('/api/library', ['openlibrary_work_key' => '/works/OL17930368W'])
            ->assertCreated()
            ->assertJsonPath('data.book.id', $book->id);

        Http::assertNothingSent();
        expect(Book::count())->toBe(1);
    });

    it('rejects a duplicate with the id of the existing entry', function () {
        fakeOpenLibrary([openLibraryDoc()]);
        $this->actingAs($this->user);
        $first = $this->postJson('/api/library', ['openlibrary_work_key' => '/works/OL17930368W'])->json('data.id');

        $this->postJson('/api/library', ['openlibrary_work_key' => '/works/OL17930368W'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'library.already_added', 'user_book_id' => $first]);

        expect($this->user->userBooks()->count())->toBe(1);
    });

    it('reports an unknown work', function () {
        fakeOpenLibrary([]);

        $this->actingAs($this->user)->postJson('/api/library', ['openlibrary_work_key' => '/works/OL1W'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.openlibrary_work_key', ['catalog.not_found']);
    });

    it('answers 503 when the catalog is down and stores nothing', function () {
        Http::fake(['openlibrary.org/*' => Http::response('', 500)]);

        $this->actingAs($this->user)->postJson('/api/library', ['openlibrary_work_key' => '/works/OL1W'])
            ->assertStatus(503);

        expect(Book::count())->toBe(0);
    });

    it('rejects a malformed key', function (string $key) {
        $this->actingAs($this->user)->postJson('/api/library', ['openlibrary_work_key' => $key])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('openlibrary_work_key');
    })->with(['an author key' => '/authors/OL1A', 'text' => 'atomic habits', 'a path trick' => '/works/OL1W/../x']);

    it('does not accept a key together with manual fields', function () {
        $this->actingAs($this->user)->postJson('/api/library', [
            'openlibrary_work_key' => '/works/OL1W',
            'title' => 'Both',
        ])->assertUnprocessable()->assertJsonValidationErrors('title');
    });
});

describe('manually', function () {
    it('creates a private book with its authors in order', function () {
        $this->actingAs($this->user)->postJson('/api/library', [
            'title' => 'Тигролови',
            'authors' => ['Іван Багряний', 'Редактор Другий'],
            'total_pages' => 320,
            'published_year' => 1944,
        ])
            ->assertCreated()
            ->assertJsonPath('data.book.title', 'Тигролови')
            ->assertJsonPath('data.book.authors', ['Іван Багряний', 'Редактор Другий'])
            ->assertJsonPath('data.book.source', 'manual')
            ->assertJsonPath('data.total_pages', 320);

        $book = Book::first();
        expect($book->source)->toBe(BookSource::Manual)
            ->and($book->created_by)->toBe($this->user->id)
            ->and($book->openlibrary_work_key)->toBeNull();
    });

    it('works without authors and without a page count', function () {
        $this->actingAs($this->user)->postJson('/api/library', ['title' => 'Untitled notes'])
            ->assertCreated()
            ->assertJsonPath('data.book.authors', [])
            ->assertJsonPath('data.total_pages', null)
            ->assertJsonPath('data.progress_percent', null);
    });

    it('reuses an existing author instead of duplicating them', function () {
        Author::factory()->create(['name' => 'Іван Багряний', 'openlibrary_key' => null]);

        $this->actingAs($this->user)->postJson('/api/library', ['title' => 'Сад Гетсиманський', 'authors' => ['Іван Багряний']])
            ->assertCreated();

        expect(Author::where('name', 'Іван Багряний')->count())->toBe(1);
    });

    it('lets two readers keep their own copy of the same title', function () {
        $other = User::factory()->create();

        $this->actingAs($this->user)->postJson('/api/library', ['title' => 'Same title'])->assertCreated();
        $this->actingAs($other)->postJson('/api/library', ['title' => 'Same title'])->assertCreated();

        expect(Book::count())->toBe(2);
    });

    it('validates the fields', function (array $payload, string $field) {
        $this->actingAs($this->user)->postJson('/api/library', $payload + ['title' => 'Valid title'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'long title' => [['title' => str_repeat('a', 256)], 'title'],
        'too many authors' => [['authors' => ['a', 'b', 'c', 'd', 'e', 'f']], 'authors'],
        'author not a string' => [['authors' => [['nested']]], 'authors.0'],
        'zero pages' => [['total_pages' => 0], 'total_pages'],
        'absurd page count' => [['total_pages' => 20001], 'total_pages'],
        'year in the far future' => [['published_year' => 3000], 'published_year'],
        'unknown status' => [['status' => 'archived'], 'status'],
    ]);

    it('needs a title or a key', function () {
        $this->actingAs($this->user)->postJson('/api/library', ['authors' => ['Someone']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'openlibrary_work_key']);
    });
});

describe('with a starting status', function () {
    it('starts reading when the page count is known', function () {
        $this->actingAs($this->user)->postJson('/api/library', ['title' => 'Book', 'total_pages' => 100, 'status' => 'reading'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'reading')
            ->assertJsonPath('data.allowed_statuses', ['finished', 'abandoned']);

        expect(UserBook::first()->started_at)->not->toBeNull();
    });

    it('stores nothing when the status cannot be applied', function () {
        $this->actingAs($this->user)->postJson('/api/library', ['title' => 'Book', 'status' => 'reading'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.total_pages', ['library.total_pages_required']);

        expect(Book::count())->toBe(0)->and(UserBook::count())->toBe(0);
    });

    it('records a book that was read earlier, with its dates', function () {
        $this->actingAs($this->user)->postJson('/api/library', [
            'title' => 'Old favourite',
            'total_pages' => 250,
            'status' => 'finished',
            'started_at' => '2025-01-10',
            'finished_at' => '2025-02-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.current_page', 250)
            ->assertJsonPath('data.progress_percent', 100);

        $userBook = UserBook::first();
        expect($userBook->started_at->toDateString())->toBe('2025-01-10')
            ->and($userBook->finished_at->toDateString())->toBe('2025-02-01');
    });

    it('rejects a finish date before the start date', function () {
        $this->actingAs($this->user)->postJson('/api/library', [
            'title' => 'Book',
            'status' => 'finished',
            'started_at' => '2025-02-01',
            'finished_at' => '2025-01-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('finished_at');
    });
});
