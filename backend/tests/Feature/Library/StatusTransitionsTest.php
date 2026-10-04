<?php

use App\Enums\BookStatus;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));
});

function changeStatus(UserBook $userBook, string $status, array $extra = [])
{
    return test()->actingAs($userBook->user)->patchJson("/api/library/{$userBook->id}", ['status' => $status] + $extra);
}

describe('want', function () {
    it('starts reading and records the start time', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => 320]);

        changeStatus($userBook, 'reading')
            ->assertOk()
            ->assertJsonPath('data.status', 'reading')
            ->assertJsonPath('data.allowed_statuses', ['finished', 'abandoned']);

        expect($userBook->fresh()->started_at->toDateTimeString())->toBe('2026-10-05 12:00:00');
    });

    it('needs a page count to start reading', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => null]);

        changeStatus($userBook, 'reading')
            ->assertUnprocessable()
            ->assertJsonPath('errors.total_pages', ['library.total_pages_required']);

        expect($userBook->fresh()->status)->toBe(BookStatus::Want);
    });

    it('accepts the page count in the same request', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => null]);

        changeStatus($userBook, 'reading', ['total_pages' => 250])
            ->assertOk()
            ->assertJsonPath('data.total_pages', 250);
    });

    it('is recorded as read earlier with the given dates and no progress history', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => 200]);

        changeStatus($userBook, 'finished', ['started_at' => '2025-03-01', 'finished_at' => '2025-03-20'])
            ->assertOk()
            ->assertJsonPath('data.current_page', 200);

        $fresh = $userBook->fresh();
        expect($fresh->started_at->toDateString())->toBe('2025-03-01')
            ->and($fresh->finished_at->toDateString())->toBe('2025-03-20');
    });

    it('defaults the finish date to now when read earlier without dates', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => null]);

        changeStatus($userBook, 'finished')->assertOk()->assertJsonPath('data.current_page', 0);

        $fresh = $userBook->fresh();
        expect($fresh->started_at)->toBeNull()
            ->and($fresh->finished_at->toDateTimeString())->toBe('2026-10-05 12:00:00');
    });

    it('cannot be abandoned', function () {
        $userBook = UserBook::factory()->for($this->user)->create();

        changeStatus($userBook, 'abandoned')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status', ['library.invalid_transition']);
    });
});

describe('reading', function () {
    it('finishes at the last page', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(120)->create(['total_pages' => 320]);

        changeStatus($userBook, 'finished')
            ->assertOk()
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.current_page', 320)
            ->assertJsonPath('data.progress_percent', 100)
            ->assertJsonPath('data.allowed_statuses', ['reading']);

        expect($userBook->fresh()->finished_at->toDateTimeString())->toBe('2026-10-05 12:00:00');
    });

    it('ignores the "read earlier" dates when it is a normal finish', function () {
        $userBook = UserBook::factory()->for($this->user)->reading()->create();
        $startedAt = $userBook->started_at;

        changeStatus($userBook, 'finished', ['started_at' => '2020-01-01', 'finished_at' => '2020-02-01'])->assertOk();

        $fresh = $userBook->fresh();
        expect($fresh->started_at->equalTo($startedAt))->toBeTrue()
            ->and($fresh->finished_at->toDateTimeString())->toBe('2026-10-05 12:00:00');
    });

    it('can be abandoned and keeps the progress', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(120)->create();

        changeStatus($userBook, 'abandoned')
            ->assertOk()
            ->assertJsonPath('data.status', 'abandoned')
            ->assertJsonPath('data.current_page', 120)
            ->assertJsonPath('data.allowed_statuses', ['reading']);
    });

    it('cannot go back to want', function () {
        $userBook = UserBook::factory()->for($this->user)->reading()->create();

        changeStatus($userBook, 'want')->assertUnprocessable()->assertJsonPath('errors.status', ['library.invalid_transition']);
    });
});

describe('abandoned', function () {
    it('resumes from the same page and keeps the original start', function () {
        $userBook = UserBook::factory()->for($this->user)->abandoned(40)->create();
        $startedAt = $userBook->started_at;

        changeStatus($userBook, 'reading')->assertOk()->assertJsonPath('data.current_page', 40);

        expect($userBook->fresh()->started_at->equalTo($startedAt))->toBeTrue();
    });

    it('cannot be finished directly', function () {
        $userBook = UserBook::factory()->for($this->user)->abandoned()->create();

        changeStatus($userBook, 'finished')->assertUnprocessable()->assertJsonPath('errors.status', ['library.invalid_transition']);
    });
});

describe('finished', function () {
    it('can be reopened as a correction, which clears the finish date', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create();

        changeStatus($userBook, 'reading')->assertOk()->assertJsonPath('data.status', 'reading');

        expect($userBook->fresh()->finished_at)->toBeNull();
    });

    it('cannot be abandoned or moved back to want', function (string $target) {
        $userBook = UserBook::factory()->for($this->user)->finished()->create();

        changeStatus($userBook, $target)->assertUnprocessable()->assertJsonPath('errors.status', ['library.invalid_transition']);
    })->with(['abandoned', 'want']);
});

it('treats a request for the current status as a no-op', function () {
    $userBook = UserBook::factory()->for($this->user)->reading(50)->create();

    changeStatus($userBook, 'reading')->assertOk()->assertJsonPath('data.current_page', 50);
});

it('rejects an unknown status', function () {
    $userBook = UserBook::factory()->for($this->user)->create();

    changeStatus($userBook, 'archived')->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('saves status, rating and pages together or not at all', function () {
    $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => null]);

    // The status change is invalid (no pages), so the rating from the same request must not be saved.
    test()->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['status' => 'reading', 'rating' => 5])
        ->assertUnprocessable();

    expect($userBook->fresh()->rating)->toBeNull();
});

it('lets a finished book be rated in the same request that finishes it', function () {
    $userBook = UserBook::factory()->for($this->user)->reading()->create();

    changeStatus($userBook, 'finished', ['rating' => 5])->assertOk()->assertJsonPath('data.rating', 5);
});
