<?php

use App\Enums\BookSource;
use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const GR_HEADER = 'Book Id,Title,Author,Author l-f,Additional Authors,ISBN,ISBN13,My Rating,Average Rating,Publisher,Binding,Number of Pages,Year Published,Original Publication Year,Date Read,Date Added,Bookshelves,Exclusive Shelf,Read Count,My Review';

/**
 * One export line with the given overrides, columns in the Goodreads order.
 *
 * @param  array<string, string>  $over
 */
function grRow(array $over = []): string
{
    $row = array_merge([
        'Book Id' => '1', 'Title' => 'Dune', 'Author' => 'Frank Herbert', 'Author l-f' => 'Herbert, Frank',
        'Additional Authors' => '', 'ISBN' => '="0441013597"', 'ISBN13' => '="9780441013593"', 'My Rating' => '5',
        'Average Rating' => '4.27', 'Publisher' => 'Ace', 'Binding' => 'Paperback', 'Number of Pages' => '528',
        'Year Published' => '2005', 'Original Publication Year' => '1965', 'Date Read' => '2026/03/14',
        'Date Added' => '2026/01/02', 'Bookshelves' => '', 'Exclusive Shelf' => 'read', 'Read Count' => '1', 'My Review' => '',
    ], $over);

    $out = fopen('php://temp', 'r+');
    fputcsv($out, array_values($row), escape: '');
    rewind($out);

    return rtrim((string) stream_get_contents($out), "\n")."\n";
}

function grCsv(string ...$rows): string
{
    return GR_HEADER."\n".implode('', $rows);
}

function grUpload(string $csv, string $name = 'goodreads_library_export.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $csv);
}

function grImport(User $user, string|UploadedFile $csv): TestResponse
{
    $file = is_string($csv) ? grUpload($csv) : $csv;

    return test()->actingAs($user)->post('/api/library/import', ['file' => $file], ['Accept' => 'application/json']);
}

beforeEach(function () {
    Cache::flush();
    Http::fake();
    $this->user = User::factory()->create();
});

it('requires authentication', function () {
    $this->postJson('/api/library/import')->assertUnauthorized();
});

describe('mapping', function () {
    it('maps a read book with rating, date, isbn and both authors; the review text is not kept', function () {
        grImport($this->user, grCsv(grRow([
            'Additional Authors' => 'Brian Herbert, Kevin Anderson', 'My Review' => "Line one\nLine \"two\", with comma",
        ])))->assertOk()->assertExactJson(['data' => ['imported' => 1, 'skipped_existing' => 0, 'skipped_invalid' => 0, 'errors' => []]]);

        $userBook = UserBook::with('book.authors')->sole();
        expect($userBook->status)->toBe(BookStatus::Finished)
            ->and($userBook->rating)->toBe(5)
            ->and($userBook->total_pages)->toBe(528)
            ->and($userBook->current_page)->toBe(528)
            ->and($userBook->reads()->count())->toBe(0)
            ->and($userBook->finished_at->toDateString())->toBe('2026-03-14')
            ->and($userBook->book->source)->toBe(BookSource::Manual)
            ->and($userBook->book->created_by)->toBe($this->user->id)
            ->and($userBook->book->isbn_13)->toBe('9780441013593')
            ->and($userBook->book->published_year)->toBe(1965)
            ->and($userBook->book->authors->pluck('name')->all())->toBe(['Frank Herbert', 'Brian Herbert', 'Kevin Anderson']);
    });

    it('archives earlier passes for a book read several times', function () {
        grImport($this->user, grCsv(
            grRow(['Title' => 'A', 'ISBN13' => '', 'ISBN' => '', 'Read Count' => '3']),
            grRow(['Title' => 'B', 'ISBN13' => '', 'ISBN' => '', 'Read Count' => '1']),
            grRow(['Title' => 'C', 'ISBN13' => '', 'ISBN' => '', 'Read Count' => '0']),
            grRow(['Title' => 'D', 'ISBN13' => '', 'ISBN' => '', 'Read Count' => '', 'Exclusive Shelf' => 'to-read', 'Date Read' => '']),
            grRow(['Title' => 'E', 'ISBN13' => '', 'ISBN' => '', 'Read Count' => '5', 'Exclusive Shelf' => 'to-read', 'Date Read' => '']),
        ))->assertOk()->assertJsonPath('data.imported', 5);

        $a = UserBook::whereRelation('book', 'title', 'A')->sole();
        expect($a->status)->toBe(BookStatus::Finished)
            ->and($a->reads()->count())->toBe(2)
            ->and($a->reads()->first()->finished_at->toDateString())->toBe('2026-03-14');

        foreach (['B', 'C', 'D', 'E'] as $title) {
            expect(UserBook::whereRelation('book', 'title', $title)->sole()->reads()->count())->toBe(0);
        }
    });

    it('caps the archived passes of an absurd read count', function () {
        grImport($this->user, grCsv(grRow(['Read Count' => '99999'])))->assertOk();

        expect(UserBook::sole()->reads()->count())->toBe(20);
    });

    it('maps the other shelves', function () {
        grImport($this->user, grCsv(
            grRow(['Title' => 'A', 'ISBN13' => '', 'ISBN' => '', 'Exclusive Shelf' => 'currently-reading', 'My Rating' => '0', 'Date Read' => '']),
            grRow(['Title' => 'B', 'ISBN13' => '', 'ISBN' => '', 'Exclusive Shelf' => 'to-read', 'My Rating' => '0', 'Date Read' => '', 'My Review' => 'ignored'])
        ))->assertOk()->assertJsonPath('data.imported', 2);

        $reading = UserBook::whereRelation('book', 'title', 'A')->sole();
        expect($reading->status)->toBe(BookStatus::Reading)->and($reading->current_page)->toBe(0)->and($reading->started_at)->not->toBeNull();

        $want = UserBook::whereRelation('book', 'title', 'B')->sole();
        expect($want->status)->toBe(BookStatus::Want)->and($want->rating)->toBeNull();
    });

    it('turns a rating of 0 into no rating and falls back to the date added', function () {
        grImport($this->user, grCsv(grRow(['My Rating' => '0', 'Date Read' => ''])))->assertOk();

        $userBook = UserBook::sole();
        expect($userBook->rating)->toBeNull()->and($userBook->finished_at->toDateString())->toBe('2026-01-02');
    });

    it('keeps a currently-reading book without a page count on the want shelf', function () {
        grImport($this->user, grCsv(grRow(['Exclusive Shelf' => 'currently-reading', 'Number of Pages' => ''])))
            ->assertOk()->assertJsonPath('data.imported', 1);

        expect(UserBook::sole()->status)->toBe(BookStatus::Want);
    });

    it('never stores a finish date in the future', function () {
        grImport($this->user, grCsv(grRow(['Date Read' => '2999/01/01'])))->assertOk();

        expect(UserBook::sole()->finished_at->isFuture())->toBeFalse();
    });

    it('drops unusable optional values instead of the row', function () {
        grImport($this->user, grCsv(grRow([
            'ISBN13' => '=""', 'ISBN' => '="0441013597"', 'Number of Pages' => '0', 'Original Publication Year' => '', 'Year Published' => '9999',
        ])))->assertOk()->assertJsonPath('data.imported', 1);

        $book = Book::sole();
        expect($book->isbn_13)->toBeNull()->and($book->page_count)->toBeNull()->and($book->published_year)->toBeNull();
    });

    it('reads Ukrainian text, a BOM and CRLF line ends', function () {
        $csv = "\xEF\xBB\xBF".str_replace("\n", "\r\n", grCsv(grRow(['Title' => 'Тіні забутих предків', 'Author' => 'Михайло Коцюбинський'])));

        grImport($this->user, $csv)->assertOk()->assertJsonPath('data.imported', 1);

        expect(Book::sole()->title)->toBe('Тіні забутих предків');
    });

    it('finds columns by header name in any order', function () {
        $csv = "Exclusive Shelf,Author,Title,Extra\nto-read,Ліна Костенко,Берестечко,x\n";

        grImport($this->user, $csv)->assertOk()->assertJsonPath('data.imported', 1);

        expect(UserBook::sole()->status)->toBe(BookStatus::Want);
    });
});

describe('duplicates', function () {
    it('skips a book already in the library by title and first author, ignoring case and punctuation', function () {
        grImport($this->user, grCsv(grRow(['ISBN13' => '', 'ISBN' => ''])))->assertOk();

        grImport($this->user, grCsv(grRow(['Title' => 'DUNE!', 'Author' => 'frank  herbert', 'ISBN13' => '', 'ISBN' => ''])))
            ->assertOk()->assertJsonPath('data.imported', 0)->assertJsonPath('data.skipped_existing', 1);

        expect(UserBook::count())->toBe(1);
    });

    it('skips a book already in the library by ISBN', function () {
        grImport($this->user, grCsv(grRow()))->assertOk();

        grImport($this->user, grCsv(grRow(['Title' => 'Dune (Dune Chronicles, #1)', 'Author' => 'F. Herbert'])))
            ->assertOk()->assertJsonPath('data.skipped_existing', 1);
    });

    it('skips repeats inside the same file and books added by hand', function () {
        $this->actingAs($this->user)->postJson('/api/library', ['title' => 'Берестечко', 'authors' => ['Ліна Костенко']])->assertCreated();

        grImport($this->user, grCsv(
            grRow(['Title' => 'Берестечко', 'Author' => 'Ліна Костенко', 'ISBN13' => '', 'ISBN' => '']),
            grRow(['Title' => 'Emma', 'Author' => 'Jane Austen', 'ISBN13' => '', 'ISBN' => '']),
            grRow(['Title' => 'Emma', 'Author' => 'Jane Austen', 'ISBN13' => '', 'ISBN' => ''])
        ))->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped_existing', 2);
    });

    it('adds a book that only another reader has', function () {
        grImport(User::factory()->create(), grCsv(grRow()))->assertOk();

        grImport($this->user, grCsv(grRow()))->assertOk()->assertJsonPath('data.imported', 1);

        expect(Book::count())->toBe(2);
    });
});

describe('invalid rows', function () {
    it('skips and reports them with the spreadsheet row number, importing the rest', function () {
        grImport($this->user, grCsv(
            grRow(['Title' => 'Good', 'ISBN13' => '', 'ISBN' => '']),
            grRow(['Title' => '']),
            grRow(['Title' => 'Odd shelf', 'Exclusive Shelf' => 'favourites']),
            grRow(['Title' => 'Bad rating', 'My Rating' => '9']),
            grRow(['Title' => 'Bad date', 'Date Read' => '31/12/2025']),
            grRow(['Title' => str_repeat('x', 256)]),
        ))->assertOk()->assertExactJson(['data' => [
            'imported' => 1, 'skipped_existing' => 0, 'skipped_invalid' => 5,
            'errors' => [
                ['row' => 3, 'reason' => 'missing_title'],
                ['row' => 4, 'reason' => 'unknown_shelf'],
                ['row' => 5, 'reason' => 'invalid_rating'],
                ['row' => 6, 'reason' => 'invalid_date'],
                ['row' => 7, 'reason' => 'title_too_long'],
            ],
        ]]);

        expect(UserBook::count())->toBe(1);
    });

    it('lists only the first 20 errors but counts all of them', function () {
        $rows = array_map(fn () => grRow(['Title' => '']), range(1, 25));

        grImport($this->user, grCsv(...$rows))->assertOk()
            ->assertJsonPath('data.skipped_invalid', 25)
            ->assertJsonCount(20, 'data.errors');
    });
});

describe('limits and wrong files', function () {
    it('imports exactly 2000 rows and refuses 2001', function () {
        $rows = array_map(fn (int $i) => grRow(['Title' => "Book $i", 'ISBN13' => '', 'ISBN' => '', 'Exclusive Shelf' => 'to-read', 'Date Read' => '']), range(1, 2000));

        grImport($this->user, grCsv(...$rows))->assertOk()->assertJsonPath('data.imported', 2000);

        $other = User::factory()->create();
        grImport($other, grCsv(...[...$rows, grRow(['Title' => 'One more'])]))
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'library.import_invalid']);
        expect($other->userBooks()->count())->toBe(0);
    });

    it('refuses a file that is not a Goodreads export', function (string $name, string $content) {
        grImport($this->user, grUpload($content, $name))
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'library.import_invalid']);

        expect(UserBook::count())->toBe(0);
    })->with([
        'wrong extension' => ['export.txt', GR_HEADER."\n"],
        'empty file' => ['export.csv', ''],
        'missing Title header' => ['export.csv', "Author,Exclusive Shelf\nA,read\n"],
        'semicolon separated' => ['export.csv', "Title;Author;Exclusive Shelf\nDune;Herbert;read\n"],
        'binary' => ['export.csv', "PK\x03\x04\x00\x00\xFF\xFE"],
        'not UTF-8' => ['export.csv', "Title,Author,Exclusive Shelf\n\xCF\xE5\xF2\xF0,A,read\n"],
    ]);

    it('refuses a missing file and one over 2 MB', function () {
        $this->actingAs($this->user)->postJson('/api/library/import', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'library.import_invalid']);

        grImport($this->user, UploadedFile::fake()->create('big.csv', 2049))
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'library.import_invalid']);
    });

    it('allows five imports an hour per reader', function () {
        foreach (range(1, 5) as $i) {
            grImport($this->user, grCsv(grRow(['Title' => "T$i", 'ISBN13' => '', 'ISBN' => ''])))->assertOk();
        }

        grImport($this->user, grCsv(grRow()))->assertStatus(429);
        grImport(User::factory()->create(), grCsv(grRow()))->assertOk();
    });
});

describe('isolation and effects', function () {
    it('touches only the importing reader\'s library', function () {
        $other = User::factory()->create();
        $otherBook = UserBook::factory()->for($other)->create();

        grImport($this->user, grCsv(grRow()))->assertOk();

        expect($other->userBooks()->pluck('id')->all())->toBe([$otherBook->id])
            ->and($this->user->userBooks()->count())->toBe(1);
        $this->actingAs($other)->getJson('/api/library')->assertJsonCount(1, 'data');
    });

    it('is reflected in the library list, the goal and the statistics', function () {
        $year = now()->year;
        grImport($this->user, grCsv(
            grRow(['Title' => 'One', 'ISBN13' => '', 'ISBN' => '', 'Date Read' => "$year/01/10"]),
            grRow(['Title' => 'Two', 'ISBN13' => '', 'ISBN' => '', 'Date Read' => "$year/01/11"]),
            grRow(['Title' => 'Old', 'ISBN13' => '', 'ISBN' => '', 'Date Read' => ($year - 1).'/05/01']),
            grRow(['Title' => 'Later', 'ISBN13' => '', 'ISBN' => '', 'Exclusive Shelf' => 'to-read', 'Date Read' => '']),
        ))->assertOk()->assertJsonPath('data.imported', 4);

        $this->actingAs($this->user)->getJson('/api/library?status=finished')->assertJsonCount(3, 'data');
        $this->actingAs($this->user)->getJson("/api/goals/$year")->assertOk()->assertJsonPath('data.finished_books', 2);
        $this->actingAs($this->user)->getJson('/api/stats/summary')->assertOk()->assertJsonPath('data.year.books_finished', 2);
    });

    it('makes no outgoing requests', function () {
        grImport($this->user, grCsv(grRow()))->assertOk();

        Http::assertNothingSent();
    });
});
