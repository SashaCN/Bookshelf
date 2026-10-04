<?php

use App\Models\ReadingGoal;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create();
    // Day 183 of 365.
    $this->travelTo(Carbon::parse('2026-07-02 12:00:00', 'UTC'));
});

function finishedBook(User $user, string $finishedAt): UserBook
{
    return UserBook::factory()->for($user)->finished()->create(['finished_at' => $finishedAt]);
}

it('requires authentication', function (string $method) {
    $this->json($method, '/api/goals/2026')->assertUnauthorized();
})->with(['GET', 'PUT', 'DELETE']);

describe('reading the goal', function () {
    it('reports the books finished even when no goal is set', function () {
        finishedBook($this->user, '2026-03-01 10:00:00');

        $this->actingAs($this->user)->getJson('/api/goals/2026')
            ->assertOk()
            ->assertExactJson(['data' => [
                'year' => 2026,
                'target_books' => null,
                'finished_books' => 1,
                'expected_books' => null,
                'remaining_books' => null,
                'on_track' => null,
            ]]);
    });

    it('counts only the books finished in that year', function () {
        finishedBook($this->user, '2026-01-10 10:00:00');
        finishedBook($this->user, '2026-06-30 10:00:00');
        finishedBook($this->user, '2025-12-20 10:00:00');
        UserBook::factory()->for($this->user)->reading()->create();
        UserBook::factory()->for($this->user)->abandoned()->create();
        finishedBook($this->stranger, '2026-03-01 10:00:00');

        $this->actingAs($this->user)->getJson('/api/goals/2026')->assertJsonPath('data.finished_books', 2);
        $this->actingAs($this->user)->getJson('/api/goals/2025')->assertJsonPath('data.finished_books', 1);
    });

    it('puts the end of the year by the reader\'s calendar', function () {
        // 23:30 UTC on 31 December is already 1 January in Kyiv.
        finishedBook($this->user, '2026-12-31 23:30:00');

        $this->actingAs($this->user)->getJson('/api/goals/2026')->assertJsonPath('data.finished_books', 0);
        $this->actingAs($this->user)->getJson('/api/goals/2027')->assertJsonPath('data.finished_books', 1);
    });
});

describe('being on track this year', function () {
    it('expects the goal spread evenly over the days', function () {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);

        // 12 books * 183 / 365 days = 6.016 books by now.
        $this->actingAs($this->user)->getJson('/api/goals/2026')
            ->assertJsonPath('data.target_books', 12)
            ->assertJsonPath('data.expected_books', 6)
            ->assertJsonPath('data.remaining_books', 12);
    });

    it('is behind when a whole book is missing', function () {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);
        foreach (range(1, 5) as $month) {
            finishedBook($this->user, "2026-0{$month}-15 10:00:00");
        }

        $this->actingAs($this->user)->getJson('/api/goals/2026')
            ->assertJsonPath('data.finished_books', 5)
            ->assertJsonPath('data.on_track', false);
    });

    it('is on track once the books that are due are done, even if a fraction is still due', function () {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);
        foreach (range(1, 6) as $month) {
            finishedBook($this->user, "2026-0{$month}-15 10:00:00");
        }

        // 6.016 books are due by now; the 6 whole ones are finished.
        $this->actingAs($this->user)->getJson('/api/goals/2026')
            ->assertJsonPath('data.finished_books', 6)
            ->assertJsonPath('data.remaining_books', 6)
            ->assertJsonPath('data.on_track', true);
    });

    it('is on track at the very start of the year with nothing read', function () {
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00', 'UTC'));
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);

        $this->actingAs($this->user)->getJson('/api/goals/2026')
            ->assertJsonPath('data.expected_books', 0)
            ->assertJsonPath('data.on_track', true);
    });

    it('is behind as soon as the first whole book is due', function () {
        // 12 books over 365 days: the first is due on day 31.
        $this->travelTo(Carbon::parse('2026-01-31 12:00:00', 'UTC'));
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);

        $this->actingAs($this->user)->getJson('/api/goals/2026')->assertJsonPath('data.on_track', false);

        finishedBook($this->user, '2026-01-20 10:00:00');

        $this->actingAs($this->user)->getJson('/api/goals/2026')->assertJsonPath('data.on_track', true);
    });

    it('never has remaining books below zero', function () {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 1]);
        finishedBook($this->user, '2026-02-01 10:00:00');
        finishedBook($this->user, '2026-03-01 10:00:00');

        $this->actingAs($this->user)->getJson('/api/goals/2026')
            ->assertJsonPath('data.remaining_books', 0)
            ->assertJsonPath('data.on_track', true);
    });
});

describe('other years', function () {
    it('judges a past year by the whole goal', function () {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2025, 'target_books' => 3]);
        finishedBook($this->user, '2025-02-01 10:00:00');
        finishedBook($this->user, '2025-05-01 10:00:00');

        $this->actingAs($this->user)->getJson('/api/goals/2025')
            ->assertJsonPath('data.expected_books', 3)
            ->assertJsonPath('data.on_track', false);

        finishedBook($this->user, '2025-11-01 10:00:00');

        $this->actingAs($this->user)->getJson('/api/goals/2025')->assertJsonPath('data.on_track', true);
    });

    it('has not begun for a future year', function () {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2027, 'target_books' => 20]);

        $this->actingAs($this->user)->getJson('/api/goals/2027')
            ->assertJsonPath('data.expected_books', 0)
            ->assertJsonPath('data.on_track', null);
    });
});

describe('setting the goal', function () {
    it('sets a goal for this year', function () {
        $this->actingAs($this->user)->putJson('/api/goals/2026', ['target_books' => 24])
            ->assertOk()
            ->assertJsonPath('data.target_books', 24)
            ->assertJsonPath('data.year', 2026);

        expect(ReadingGoal::query()->sole())->user_id->toBe($this->user->id)->target_books->toBe(24);
    });

    it('changes the goal instead of adding a second one', function () {
        $this->actingAs($this->user)->putJson('/api/goals/2026', ['target_books' => 24])->assertOk();
        $this->actingAs($this->user)->putJson('/api/goals/2026', ['target_books' => 30])->assertOk()->assertJsonPath('data.target_books', 30);

        expect(ReadingGoal::query()->count())->toBe(1);
    });

    it('allows next year', function () {
        $this->actingAs($this->user)->putJson('/api/goals/2027', ['target_books' => 12])->assertOk();
    });

    it('refuses years that are history or far away', function (int $year) {
        $this->actingAs($this->user)->putJson("/api/goals/{$year}", ['target_books' => 12])
            ->assertUnprocessable()
            ->assertJsonPath('errors.year', ['goals.year_out_of_range']);

        expect(ReadingGoal::query()->count())->toBe(0);
    })->with([1999, 2028, 9999]);

    it('validates the target', function (mixed $target) {
        $this->actingAs($this->user)->putJson('/api/goals/2026', ['target_books' => $target])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_books');
    })->with([
        'missing' => [null],
        'zero' => [0],
        'negative' => [-3],
        'too many' => [1001],
        'fraction' => [2.5],
        'text' => ['many'],
    ]);

    it('removes the goal', function () {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026]);

        $this->actingAs($this->user)->deleteJson('/api/goals/2026')->assertNoContent();

        expect(ReadingGoal::query()->count())->toBe(0);
        $this->actingAs($this->user)->getJson('/api/goals/2026')->assertJsonPath('data.target_books', null);
    });
});

describe('isolation', function () {
    it('never shows or touches the goal of another reader', function () {
        ReadingGoal::factory()->for($this->stranger)->create(['year' => 2026, 'target_books' => 50]);

        $this->actingAs($this->user)->getJson('/api/goals/2026')->assertJsonPath('data.target_books', null);

        $this->actingAs($this->user)->putJson('/api/goals/2026', ['target_books' => 5])->assertOk();
        $this->actingAs($this->user)->deleteJson('/api/goals/2026')->assertNoContent();

        expect(ReadingGoal::query()->sole())->user_id->toBe($this->stranger->id)->target_books->toBe(50);
    });
});
