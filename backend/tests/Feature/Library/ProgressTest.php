<?php

use App\Enums\BookStatus;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create();
    $this->travelTo(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
});

function setPage(UserBook $userBook, mixed $page)
{
    return test()->actingAs($userBook->user)->postJson("/api/library/{$userBook->id}/progress", ['page' => $page]);
}

it('requires authentication', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['POST', '/api/library/1/progress'],
    ['GET', '/api/library/1/logs'],
]);

describe('moving the bookmark', function () {
    it('saves the page and tells how many pages it was worth', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);

        setPage($userBook, 118)
            ->assertOk()
            ->assertJsonPath('data.id', $userBook->id)
            ->assertJsonPath('data.current_page', 118)
            ->assertJsonPath('data.progress_percent', 36)
            ->assertJsonPath('data.book.id', $userBook->book_id)
            ->assertJsonPath('meta.pages', 18)
            ->assertJsonPath('meta.reached_end', false);

        expect($userBook->fresh()->current_page)->toBe(118);
    });

    it('writes the change to the reading log', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);

        setPage($userBook, 118)->assertOk();

        $log = ReadingLog::query()->sole();
        expect($log->user_book_id)->toBe($userBook->id)
            ->and($log->user_id)->toBe($this->user->id)
            ->and($log->from_page)->toBe(100)
            ->and($log->to_page)->toBe(118)
            ->and($log->logged_on->toDateString())->toBe('2026-10-05');
    });

    it('files the entry under the reader\'s own calendar day', function () {
        // 22:30 in UTC is already the next morning in Kyiv (UTC+3 in October).
        $this->travelTo(Carbon::parse('2026-10-05 22:30:00', 'UTC'));
        $userBook = UserBook::factory()->for($this->user)->reading(10)->create();

        setPage($userBook, 20)->assertOk();

        expect(ReadingLog::query()->sole()->logged_on->toDateString())->toBe('2026-10-06');
    });

    it('reports how many days in a row the reader has read', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);
        readingLog($userBook, 50, 100, '2026-10-04');

        setPage($userBook, 118)->assertOk()->assertJsonPath('meta.streak', 2);
    });

    it('does nothing when the page has not changed', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create();

        setPage($userBook, 100)
            ->assertOk()
            ->assertJsonPath('meta.pages', 0);

        expect(ReadingLog::query()->count())->toBe(0);
    });

    it('records a correction downwards as a negative change', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(10)->create(['total_pages' => 400]);

        setPage($userBook, 300)->assertOk()->assertJsonPath('meta.pages', 290);
        setPage($userBook, 30)->assertOk()->assertJsonPath('meta.pages', -270);

        expect(ReadingLog::query()->pluck('to_page', 'from_page')->all())->toBe([10 => 300, 300 => 30])
            ->and(ReadingLog::query()->get()->sum(fn (ReadingLog $log) => $log->pages()))->toBe(20);
    });

    it('bumps the entry to the top of the library', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(10)->create(['updated_at' => now()->subWeek()]);

        setPage($userBook, 20)->assertOk();

        expect($userBook->fresh()->updated_at->isToday())->toBeTrue();
    });

    it('offers to finish the book on the last page, without finishing it', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(300)->create(['total_pages' => 320]);

        setPage($userBook, 320)
            ->assertOk()
            ->assertJsonPath('data.status', 'reading')
            ->assertJsonPath('data.finished_at', null)
            ->assertJsonPath('meta.reached_end', true);
    });

    it('accepts the first page and the last page', function (int $page) {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);

        setPage($userBook, $page)->assertOk()->assertJsonPath('data.current_page', $page);
    })->with([0, 320]);
});

describe('starting a book by moving the bookmark', function () {
    it('starts reading a wanted book', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => 320]);

        setPage($userBook, 25)
            ->assertOk()
            ->assertJsonPath('data.status', 'reading')
            ->assertJsonPath('data.allowed_statuses', ['finished', 'abandoned'])
            ->assertJsonPath('data.current_page', 25);

        $fresh = $userBook->fresh();
        expect($fresh->started_at->toDateTimeString())->toBe('2026-10-05 12:00:00')
            ->and(ReadingLog::query()->sole()->pages())->toBe(25);
    });

    it('resumes a book that was put aside', function () {
        $userBook = UserBook::factory()->for($this->user)->abandoned(40)->create(['total_pages' => 320]);

        setPage($userBook, 60)
            ->assertOk()
            ->assertJsonPath('data.status', 'reading')
            ->assertJsonPath('meta.pages', 20);
    });

    it('needs the page count of a wanted book', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => null]);

        setPage($userBook, 10)
            ->assertUnprocessable()
            ->assertJsonPath('errors.total_pages', ['library.total_pages_required']);

        expect($userBook->fresh()->status)->toBe(BookStatus::Want)
            ->and(ReadingLog::query()->count())->toBe(0);
    });

    it('leaves a wanted book alone when the page is unchanged', function () {
        $userBook = UserBook::factory()->for($this->user)->create();

        setPage($userBook, 0)->assertOk()->assertJsonPath('data.status', 'want');

        expect($userBook->fresh()->started_at)->toBeNull();
    });
});

describe('refusals', function () {
    it('refuses a finished book', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create(['total_pages' => 320]);

        setPage($userBook, 10)
            ->assertUnprocessable()
            ->assertJsonPath('errors.page', ['library.progress_finished']);

        expect($userBook->fresh()->current_page)->toBe(320);
    });

    it('refuses a page beyond the last one and changes nothing', function () {
        $userBook = UserBook::factory()->for($this->user)->abandoned(40)->create(['total_pages' => 320]);

        setPage($userBook, 321)
            ->assertUnprocessable()
            ->assertJsonPath('errors.page', ['library.page_above_total']);

        $fresh = $userBook->fresh();
        expect($fresh->status)->toBe(BookStatus::Abandoned)
            ->and($fresh->current_page)->toBe(40)
            ->and(ReadingLog::query()->count())->toBe(0);
    });

    it('validates the page', function (mixed $page) {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create();

        setPage($userBook, $page)->assertUnprocessable()->assertJsonValidationErrors('page');
    })->with([
        'missing' => [null],
        'negative' => [-1],
        'fractional' => [10.5],
        'text' => ['ten'],
        'absurd' => [20001],
    ]);
});

describe('finishing a book that is being read', function () {
    it('logs the pages that were left, because they were read', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(250)->create(['total_pages' => 320]);

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['status' => 'finished'])->assertOk();

        $log = ReadingLog::query()->sole();
        expect($log->from_page)->toBe(250)
            ->and($log->to_page)->toBe(320);
    });

    it('logs nothing when the bookmark was already on the last page', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(320)->create(['total_pages' => 320]);

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['status' => 'finished'])->assertOk();

        expect(ReadingLog::query()->count())->toBe(0);
    });

    it('logs nothing for a book that was read earlier', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => 320]);

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['status' => 'finished'])->assertOk();

        expect(ReadingLog::query()->count())->toBe(0);
    });
});

describe('consecutive taps', function () {
    it('builds every update on the one before it', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(0)->create(['total_pages' => 320]);

        setPage($userBook, 10)->assertOk();
        setPage($userBook, 20)->assertOk();
        setPage($userBook, 30)->assertOk();

        expect(ReadingLog::query()->orderBy('id')->get()->map(fn (ReadingLog $log) => [$log->from_page, $log->to_page])->all())
            ->toBe([[0, 10], [10, 20], [20, 30]]);
    });
});

describe('isolation', function () {
    it('answers "not found" for somebody else\'s entry and changes nothing', function () {
        $theirs = UserBook::factory()->for($this->stranger)->reading(100)->create();

        $this->actingAs($this->user)->postJson("/api/library/{$theirs->id}/progress", ['page' => 150])->assertNotFound();

        expect($theirs->fresh()->current_page)->toBe(100)
            ->and(ReadingLog::query()->count())->toBe(0);
    });

    it('answers "not found", not a validation error, for somebody else\'s entry', function () {
        $theirs = UserBook::factory()->for($this->stranger)->reading(100)->create();

        $this->actingAs($this->user)->postJson("/api/library/{$theirs->id}/progress", ['page' => 'ten'])->assertNotFound();
        $this->actingAs($this->user)->patchJson("/api/library/{$theirs->id}", ['status' => 'nonsense'])->assertNotFound();
    });
});
