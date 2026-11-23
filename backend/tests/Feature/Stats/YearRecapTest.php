<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Quote;
use App\Models\ReadingGoal;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;
use App\Models\UserBookRead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->travelTo(Carbon::parse('2026-07-02 12:00:00', 'UTC'));
});

function recapBook(User $user, string $title, string $author, int $pages, ?int $rating, string $finishedAt): UserBook
{
    $book = Book::factory()->create(['title' => $title]);
    $book->authors()->attach(Author::factory()->create(['name' => $author])->id, ['position' => 0]);

    return UserBook::factory()->for($user)->for($book)->finished()->create([
        'total_pages' => $pages,
        'current_page' => $pages,
        'rating' => $rating,
        'started_at' => '2025-01-01 00:00:00',
        'finished_at' => $finishedAt,
    ]);
}

function recapLog(User $user, UserBook $entry, string $day, int $from, int $to): void
{
    ReadingLog::factory()->create(['user_book_id' => $entry->id, 'user_id' => $user->id, 'from_page' => $from, 'to_page' => $to, 'logged_on' => $day]);
}

it('requires authentication', function () {
    $this->getJson('/api/stats/year/2026')->assertUnauthorized();
});

describe('a realistic two-year fixture', function () {
    beforeEach(function () {
        $dune = recapBook($this->user, 'Dune', 'Frank Herbert', 600, 5, '2025-12-31 22:30:00'); // 1 Jan 2026 in Kyiv
        $small = recapBook($this->user, 'Small Book', 'A. Author', 100, 3, '2026-02-10 10:00:00');
        $big = recapBook($this->user, 'Big Book', 'B. Author', 900, 5, '2026-05-20 10:00:00');
        $unrated = recapBook($this->user, 'Unrated', 'C. Author', 200, null, '2026-06-01 10:00:00');
        $old = recapBook($this->user, 'Old Book', 'D. Author', 300, 4, '2025-03-01 10:00:00');
        // Small Book was read again in 2026: the first pass is archived, the second is the current one.
        UserBookRead::create(['user_book_id' => $small->id, 'started_at' => '2025-02-01 00:00:00', 'finished_at' => '2025-04-01 10:00:00']);
        UserBookRead::create(['user_book_id' => $small->id, 'started_at' => '2026-01-05 00:00:00', 'finished_at' => '2026-01-20 10:00:00']);
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);

        // 2025: Dec 30, 31 (evening in Kyiv); 2026: Jan 1, 2 and a day in May.
        recapLog($this->user, $dune, '2025-12-30', 0, 40);
        recapLog($this->user, $dune, '2025-12-31', 40, 80);
        recapLog($this->user, $dune, '2026-01-01', 80, 130);
        recapLog($this->user, $dune, '2026-01-02', 130, 160);
        recapLog($this->user, $big, '2026-05-10', 0, 200);
        recapLog($this->user, $big, '2026-05-10', 200, 150); // a correction
        recapLog($this->user, $old, '2025-03-01', 0, 10);

        Quote::factory()->create(['user_book_id' => $dune->id, 'user_id' => $this->user->id, 'created_at' => '2025-12-31 22:30:00']); // Jan 1 in Kyiv
        Quote::factory()->create(['user_book_id' => $dune->id, 'user_id' => $this->user->id, 'created_at' => '2026-03-01 10:00:00']);
        $big->forceFill(['review' => 'Great', 'reviewed_at' => '2026-05-21 10:00:00'])->save();
        $unrated->forceFill(['review' => 'Meh', 'reviewed_at' => '2025-12-31 21:30:00'])->save(); // still 2025

        // Another reader's data must not leak in.
        $theirs = recapBook($this->stranger, 'Theirs', 'X', 999, 5, '2026-04-01 10:00:00');
        recapLog($this->stranger, $theirs, '2026-04-01', 0, 999);
        Quote::factory()->create(['user_book_id' => $theirs->id, 'user_id' => $this->stranger->id, 'created_at' => '2026-04-01 10:00:00']);
    });

    it('recaps 2026 in the reader calendar, counting a reread per pass', function () {
        $this->actingAs($this->user)->getJson('/api/stats/year/2026')
            ->assertOk()
            ->assertJsonPath('meta', ['first_year' => 2025, 'current_year' => 2026])
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.books_finished', 5) // Dune, Small (archived pass + current), Big, Unrated
            ->assertJsonPath('data.pages_read', 50 + 30 + 150)
            ->assertJsonPath('data.days_with_reading', 3)
            ->assertJsonPath('data.longest_streak_in_year', 2)
            ->assertJsonPath('data.chapters_read', 0)
            ->assertJsonPath('data.quotes_added', 2)
            ->assertJsonPath('data.reviews_written', 1)
            ->assertJsonPath('data.average_rating', 4) // 5, 3, 3, 5 (the unrated is skipped)
            ->assertJsonPath('data.top_rated', [
                ['title' => 'Dune', 'authors' => ['Frank Herbert'], 'rating' => 5, 'finished_at' => '2026-01-01'],
                ['title' => 'Big Book', 'authors' => ['B. Author'], 'rating' => 5, 'finished_at' => '2026-05-20'],
                ['title' => 'Small Book', 'authors' => ['A. Author'], 'rating' => 3, 'finished_at' => '2026-01-20'],
            ])
            ->assertJsonPath('data.longest_book', ['title' => 'Big Book', 'authors' => ['B. Author'], 'total_pages' => 900])
            ->assertJsonPath('data.busiest_month', ['month' => 5, 'pages' => 150])
            ->assertJsonPath('data.goal', ['target' => 12, 'achieved' => 5])
            ->assertJsonPath('data.finished_books.0.title', 'Dune')
            ->assertJsonPath('data.finished_books.0.finished_at', '2026-01-01')
            ->assertJsonCount(5, 'data.finished_books');

        expect(collect($this->getJson('/api/stats/year/2026')->json('data.finished_books'))->pluck('finished_at')->all())
            ->toBe(['2026-01-01', '2026-01-20', '2026-02-10', '2026-05-20', '2026-06-01']);
    });

    it('puts the Dec 31 / Jan 1 boundary by the reader calendar and counts the first pass of a reread in the earlier year', function () {
        $this->actingAs($this->user)->getJson('/api/stats/year/2025')
            ->assertOk()
            ->assertJsonPath('data.books_finished', 2) // Old Book and the archived first pass of Small Book
            ->assertJsonPath('data.pages_read', 80 + 10)
            ->assertJsonPath('data.longest_streak_in_year', 2)
            ->assertJsonPath('data.quotes_added', 0)
            ->assertJsonPath('data.reviews_written', 1)
            ->assertJsonPath('data.goal', null)
            ->assertJsonPath('data.busiest_month.month', 12);
    });

    it('does not show another reader\'s data', function () {
        $this->actingAs($this->stranger)->getJson('/api/stats/year/2026')
            ->assertOk()
            ->assertJsonPath('data.books_finished', 1)
            ->assertJsonPath('data.pages_read', 999)
            ->assertJsonPath('data.quotes_added', 1)
            ->assertJsonPath('data.finished_books.0.title', 'Theirs');
    });

    it('runs a bounded number of queries', function () {
        $this->actingAs($this->user);
        DB::enableQueryLog();
        $this->getJson('/api/stats/year/2026')->assertOk();

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(15);
    });
});

it('answers an empty year with zeros and nulls', function () {
    $entry = recapBook($this->user, 'Only', 'A', 100, 4, '2026-03-01 10:00:00');
    recapLog($this->user, $entry, '2024-06-01', 0, 10); // makes 2024 the first year; 2025 is empty

    $this->actingAs($this->user)->getJson('/api/stats/year/2025')
        ->assertOk()
        ->assertExactJson(['data' => [
            'year' => 2025,
            'books_finished' => 0,
            'pages_read' => 0,
            'days_with_reading' => 0,
            'longest_streak_in_year' => 0,
            'chapters_read' => 0,
            'quotes_added' => 0,
            'reviews_written' => 0,
            'average_rating' => null,
            'top_rated' => [],
            'longest_book' => null,
            'busiest_month' => null,
            'goal' => null,
            'finished_books' => [],
        ], 'meta' => ['first_year' => 2024, 'current_year' => 2026]]);
});

it('gives a new reader the current year only', function () {
    $this->actingAs($this->user)->getJson('/api/stats/year/2026')->assertOk()->assertJsonPath('data.books_finished', 0);
    $this->actingAs($this->user)->getJson('/api/stats/year/2025')->assertNotFound();
});

it('answers 404 outside the years of activity', function () {
    recapBook($this->user, 'Only', 'A', 100, 4, '2025-03-01 10:00:00');

    $this->actingAs($this->user);
    $this->getJson('/api/stats/year/2024')->assertNotFound();
    $this->getJson('/api/stats/year/2027')->assertNotFound();
    $this->getJson('/api/stats/year/2025')->assertOk();
    $this->getJson('/api/stats/year/26')->assertNotFound();
    $this->getJson('/api/stats/year/abcd')->assertNotFound();
});

it('reports a goal of the year even when it is not met', function () {
    $entry = recapBook($this->user, 'Only', 'A', 100, null, '2026-03-01 10:00:00');
    ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 20]);

    $this->actingAs($this->user)->getJson('/api/stats/year/2026')
        ->assertJsonPath('data.goal', ['target' => 20, 'achieved' => 1])
        ->assertJsonPath('data.average_rating', null)
        ->assertJsonPath('data.top_rated', []);
});
