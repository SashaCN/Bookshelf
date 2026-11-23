<?php

use App\Actions\Library\StartRereading;
use App\Enums\BookStatus;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;
use App\Models\UserBookRead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create();
    $this->travelTo(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
});

function reread(UserBook $userBook, ?User $as = null)
{
    return test()->actingAs($as ?? $userBook->user)->postJson("/api/library/{$userBook->id}/reread");
}

it('requires authentication', function () {
    $this->postJson('/api/library/1/reread')->assertUnauthorized();
});

describe('starting a reread', function () {
    it('archives the finished pass and restarts the bookmark', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create([
            'started_at' => '2026-09-01 10:00:00',
            'finished_at' => '2026-09-20 10:00:00',
            'rating' => 5,
            'review' => 'Great.',
        ]);

        reread($userBook)
            ->assertOk()
            ->assertJsonPath('data.status', 'reading')
            ->assertJsonPath('data.current_page', 0)
            ->assertJsonPath('data.finished_at', null)
            ->assertJsonPath('data.times_read', 1)
            ->assertJsonCount(1, 'data.reads');

        $userBook->refresh();
        expect($userBook->status)->toBe(BookStatus::Reading)
            ->and($userBook->current_page)->toBe(0)
            ->and($userBook->started_at->toDateTimeString())->toBe('2026-10-05 12:00:00')
            ->and($userBook->finished_at)->toBeNull()
            ->and($userBook->rating)->toBe(5)
            ->and($userBook->review)->toBe('Great.');

        $read = UserBookRead::query()->sole();
        expect($read->user_book_id)->toBe($userBook->id)
            ->and($read->started_at->toDateTimeString())->toBe('2026-09-01 10:00:00')
            ->and($read->finished_at->toDateTimeString())->toBe('2026-09-20 10:00:00');
    });

    it('keeps the reading logs', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create();
        ReadingLog::factory()->for($userBook)->for($this->user)->create();

        reread($userBook)->assertOk();

        expect(ReadingLog::query()->count())->toBe(1);
    });

    it('can be repeated after the next finish', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create();

        reread($userBook)->assertOk();
        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['status' => 'finished'])->assertOk();
        reread($userBook)->assertOk()->assertJsonPath('data.times_read', 2);

        expect(UserBookRead::query()->count())->toBe(2);
    });

    it('refuses a book that is not finished', function (string $state) {
        $userBook = UserBook::factory()->for($this->user)->{$state}()->create();

        reread($userBook)
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'library.reread_not_finished');

        expect(UserBookRead::query()->count())->toBe(0);
    })->with(['reading', 'unstarted', 'abandoned']);

    it('answers 404 for a foreign entry before looking at its status', function () {
        $userBook = UserBook::factory()->for($this->stranger)->reading()->create();

        reread($userBook, $this->user)->assertNotFound();
    });

    it('archives and resets together or not at all', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create();

        // The reset fails after the archive row was written: the archive must roll back with it.
        UserBook::updating(fn () => throw new RuntimeException('boom'));

        try {
            expect(fn () => app(StartRereading::class)->handle($userBook))->toThrow(RuntimeException::class);
        } finally {
            // Drop the listener, or it would leak into the tests that follow.
            UserBook::clearBootedModels();
        }

        expect(UserBookRead::query()->count())->toBe(0)
            ->and($userBook->fresh()->status)->toBe(BookStatus::Finished);
    });
});

describe('the entry in the API', function () {
    it('lists archived passes newest first with the times read', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create();
        $userBook->reads()->create(['started_at' => '2025-01-01 10:00:00', 'finished_at' => '2025-01-10 10:00:00']);
        $userBook->reads()->create(['started_at' => null, 'finished_at' => '2025-06-10 10:00:00']);

        $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}")
            ->assertOk()
            ->assertJsonPath('data.times_read', 3)
            ->assertJsonCount(2, 'data.reads')
            ->assertJsonPath('data.reads.0.finished_at', '2025-06-10T10:00:00+00:00')
            ->assertJsonPath('data.reads.0.started_at', null)
            ->assertJsonPath('data.reads.1.started_at', '2025-01-01T10:00:00+00:00');
    });

    it('counts a reading book by its archived passes only', function () {
        $userBook = UserBook::factory()->for($this->user)->reading()->create();
        $userBook->reads()->create(['finished_at' => '2025-01-10 10:00:00']);

        $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}")->assertJsonPath('data.times_read', 1);
    });

    it('gives times_read in the list without a query per book', function () {
        foreach (range(1, 4) as $i) {
            $userBook = UserBook::factory()->for($this->user)->finished()->create();
            $userBook->reads()->create(['finished_at' => '2025-01-10 10:00:00']);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($this->user)->getJson('/api/library')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect(collect($response->json('data'))->pluck('times_read')->all())->toBe([2, 2, 2, 2])
            ->and($response->json('data.0'))->not->toHaveKey('reads')
            ->and($queries)->toBeLessThan(10);
    });
});

describe('statistics', function () {
    it('counts a book read twice in a year twice for the goal', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create(['finished_at' => '2026-03-01 10:00:00']);
        $userBook->reads()->create(['finished_at' => '2026-01-15 10:00:00']);
        $userBook->reads()->create(['finished_at' => '2025-12-31 10:00:00']);
        $other = UserBook::factory()->for($this->stranger)->finished()->create(['finished_at' => '2026-03-01 10:00:00']);
        $other->reads()->create(['finished_at' => '2026-02-01 10:00:00']);

        $this->actingAs($this->user)->getJson('/api/goals/2026')
            ->assertOk()
            ->assertJsonPath('data.finished_books', 2);
    });

    it('counts archived passes in the profile of a public reader', function () {
        $alice = User::factory()->public()->create(['username' => 'alice']);
        $userBook = UserBook::factory()->for($alice)->reading()->create();
        $userBook->reads()->create(['finished_at' => '2026-02-01 10:00:00']);
        $userBook->reads()->create(['finished_at' => '2026-04-01 10:00:00']);

        $this->actingAs($this->user)->getJson('/api/users/alice')->assertJsonPath('data.books_finished_this_year', 2);
    });

    it('counts archived passes in the summary totals', function () {
        $userBook = UserBook::factory()->for($this->user)->reading()->create();
        $userBook->reads()->create(['finished_at' => '2026-10-03 10:00:00']);
        $userBook->reads()->create(['finished_at' => '2026-02-03 10:00:00']);

        $this->actingAs($this->user)->getJson('/api/stats/summary')
            ->assertOk()
            ->assertJsonPath('data.month.books_finished', 1)
            ->assertJsonPath('data.year.books_finished', 2);
    });
});
