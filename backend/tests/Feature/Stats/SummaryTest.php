<?php

use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(0)->create(['total_pages' => 400]);
    // Noon UTC is 15:00 in Kyiv, so "today" is the same calendar day for both.
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'UTC'));
});

function summary(): TestResponse
{
    return test()->actingAs(test()->user)->getJson('/api/stats/summary');
}

it('requires authentication', function () {
    $this->getJson('/api/stats/summary')->assertUnauthorized();
});

it('is all zeros for a reader who has not read anything', function () {
    $this->userBook->delete();

    summary()->assertOk()->assertExactJson(['data' => [
        'today' => ['date' => '2026-10-15', 'pages' => 0],
        'streak' => ['current' => 0, 'longest' => 0, 'read_today' => false],
        'pace' => 0,
        'month' => ['pages' => 0, 'books_finished' => 0],
        'year' => ['pages' => 0, 'books_finished' => 0],
        'forecasts' => [],
    ]]);
});

describe('pages of a day', function () {
    it('adds up the moves of the day', function () {
        readingLog($this->userBook, 0, 20, '2026-10-15');
        readingLog($this->userBook, 20, 35, '2026-10-15');

        summary()->assertJsonPath('data.today.pages', 35);
    });

    it('lets a correction cancel the mistake instead of inflating the day', function () {
        readingLog($this->userBook, 10, 300, '2026-10-15');
        readingLog($this->userBook, 300, 30, '2026-10-15');

        summary()->assertOk()->assertJsonPath('data.today.pages', 20);
    });

    it('never counts a day below zero', function () {
        readingLog($this->userBook, 100, 50, '2026-10-15');

        summary()->assertJsonPath('data.today.pages', 0)->assertJsonPath('data.month.pages', 0);
    });

    it('counts the pages of every book', function () {
        $other = UserBook::factory()->for($this->user)->reading(0)->create();
        readingLog($this->userBook, 0, 10, '2026-10-15');
        readingLog($other, 0, 25, '2026-10-15');

        summary()->assertJsonPath('data.today.pages', 35);
    });

    it('ignores other readers', function () {
        $theirs = UserBook::factory()->for($this->stranger)->reading(0)->create();
        readingLog($theirs, 0, 90, '2026-10-15');

        summary()->assertJsonPath('data.today.pages', 0);
    });

    it('takes today from the reader\'s calendar, not the server\'s', function () {
        // 22:30 UTC is already the next morning in Kyiv.
        $this->travelTo(Carbon::parse('2026-10-15 22:30:00', 'UTC'));
        readingLog($this->userBook, 0, 12, '2026-10-16');

        summary()->assertJsonPath('data.today.date', '2026-10-16')->assertJsonPath('data.today.pages', 12);
    });
});

describe('streak', function () {
    it('counts the days in a row up to today', function () {
        readingLog($this->userBook, 0, 10, '2026-10-13');
        readingLog($this->userBook, 10, 20, '2026-10-14');
        readingLog($this->userBook, 20, 30, '2026-10-15');

        summary()->assertJsonPath('data.streak', ['current' => 3, 'longest' => 3, 'read_today' => true]);
    });

    it('is still alive when the last day read was yesterday', function () {
        readingLog($this->userBook, 0, 10, '2026-10-13');
        readingLog($this->userBook, 10, 20, '2026-10-14');

        summary()->assertJsonPath('data.streak', ['current' => 2, 'longest' => 2, 'read_today' => false]);
    });

    it('is over when the last day read was before yesterday', function () {
        readingLog($this->userBook, 0, 10, '2026-10-12');
        readingLog($this->userBook, 10, 20, '2026-10-13');

        summary()->assertJsonPath('data.streak', ['current' => 0, 'longest' => 2, 'read_today' => false]);
    });

    it('remembers the longest run, not only the current one', function () {
        foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05'] as $i => $day) {
            readingLog($this->userBook, $i * 10, $i * 10 + 10, $day);
        }
        readingLog($this->userBook, 50, 60, '2026-10-14');
        readingLog($this->userBook, 60, 70, '2026-10-15');

        summary()->assertJsonPath('data.streak', ['current' => 2, 'longest' => 5, 'read_today' => true]);
    });

    it('is broken by a day without reading', function () {
        readingLog($this->userBook, 0, 10, '2026-10-12');
        readingLog($this->userBook, 10, 20, '2026-10-14');
        readingLog($this->userBook, 20, 30, '2026-10-15');

        summary()->assertJsonPath('data.streak.current', 2);
    });

    it('does not count a day that ended up with nothing read', function () {
        readingLog($this->userBook, 0, 10, '2026-10-14');
        readingLog($this->userBook, 10, 0, '2026-10-14');
        readingLog($this->userBook, 0, 10, '2026-10-15');

        summary()->assertJsonPath('data.streak', ['current' => 1, 'longest' => 1, 'read_today' => true]);
    });

    it('does not mix in the days of other readers', function () {
        $theirs = UserBook::factory()->for($this->stranger)->reading(0)->create();
        readingLog($theirs, 0, 10, '2026-10-15');
        readingLog($theirs, 10, 20, '2026-10-14');

        summary()->assertJsonPath('data.streak.current', 0);
    });
});

describe('pace', function () {
    it('averages the last 14 days, days without reading included', function () {
        readingLog($this->userBook, 0, 28, '2026-10-15');
        readingLog($this->userBook, 28, 42, '2026-10-10');

        summary()->assertJsonPath('data.pace', 3);
    });

    it('looks back exactly 14 days, today included', function () {
        readingLog($this->userBook, 0, 14, '2026-10-02');  // 13 days ago: inside
        readingLog($this->userBook, 14, 114, '2026-10-01'); // 14 days ago: outside

        summary()->assertJsonPath('data.pace', 1);
    });
});

describe('totals', function () {
    it('sums the pages of the month and of the year', function () {
        readingLog($this->userBook, 0, 10, '2026-10-01');
        readingLog($this->userBook, 10, 30, '2026-10-15');
        readingLog($this->userBook, 30, 130, '2026-03-10');
        readingLog($this->userBook, 130, 150, '2025-12-31');

        summary()->assertJsonPath('data.month.pages', 30)->assertJsonPath('data.year.pages', 130);
    });

    it('counts the books finished this month and this year', function () {
        UserBook::factory()->for($this->user)->finished()->create(['finished_at' => '2026-10-03 10:00:00']);
        UserBook::factory()->for($this->user)->finished()->create(['finished_at' => '2026-02-03 10:00:00']);
        UserBook::factory()->for($this->user)->finished()->create(['finished_at' => '2025-12-20 10:00:00']);
        UserBook::factory()->for($this->stranger)->finished()->create(['finished_at' => '2026-10-03 10:00:00']);

        summary()->assertJsonPath('data.month.books_finished', 1)->assertJsonPath('data.year.books_finished', 2);
    });

    it('puts a book finished late in the evening UTC into the reader\'s own month', function () {
        // 22:30 UTC on 30 September is already 1 October in Kyiv.
        UserBook::factory()->for($this->user)->finished()->create(['finished_at' => '2026-09-30 22:30:00']);

        summary()->assertJsonPath('data.month.books_finished', 1);
    });

    it('does not count a book that was finished and then taken back', function () {
        UserBook::factory()->for($this->user)->reading(10)->create(['finished_at' => null]);

        summary()->assertJsonPath('data.year.books_finished', 0);
    });
});

describe('forecasts', function () {
    it('uses the overall pace for a book that has been read on few days', function () {
        readingLog($this->userBook, 0, 28, '2026-10-15');
        $this->userBook->update(['current_page' => 28]);

        summary()->assertJsonPath('data.forecasts.0', [
            'user_book_id' => $this->userBook->id,
            'title' => $this->userBook->book->title,
            'current_page' => 28,
            'total_pages' => 400,
            'pages_per_day' => 2,
            'days_left' => 186,
            'finish_on' => '2027-04-19',
        ]);
    });

    it('uses the pace of the book itself once it has been read on three days', function () {
        $this->userBook->update(['started_at' => '2026-10-13 09:00:00', 'current_page' => 300]);
        readingLog($this->userBook, 210, 240, '2026-10-13');
        readingLog($this->userBook, 240, 270, '2026-10-14');
        readingLog($this->userBook, 270, 300, '2026-10-15');

        // 90 pages over the 3 days since it was started: 30 pages a day, 100 pages left.
        summary()
            ->assertJsonPath('data.forecasts.0.pages_per_day', 30)
            ->assertJsonPath('data.forecasts.0.days_left', 4)
            ->assertJsonPath('data.forecasts.0.finish_on', '2026-10-19');
    });

    it('has no date when nothing has been read lately', function () {
        summary()
            ->assertJsonPath('data.forecasts.0.pages_per_day', null)
            ->assertJsonPath('data.forecasts.0.days_left', null)
            ->assertJsonPath('data.forecasts.0.finish_on', null);
    });

    it('has no date when it would take years', function () {
        readingLog($this->userBook, 0, 1, '2026-10-15');

        summary()->assertJsonPath('data.forecasts.0.days_left', null);
    });

    it('is done today for a book on its last page', function () {
        $this->userBook->update(['current_page' => 400]);

        summary()->assertJsonPath('data.forecasts.0.days_left', 0)->assertJsonPath('data.forecasts.0.finish_on', '2026-10-15');
    });

    it('lists only books that are being read, with a known length, and only mine', function () {
        UserBook::factory()->for($this->user)->create();
        UserBook::factory()->for($this->user)->finished()->create();
        UserBook::factory()->for($this->user)->abandoned()->create();
        UserBook::factory()->for($this->user)->state(['status' => 'reading', 'total_pages' => null])->create();
        UserBook::factory()->for($this->stranger)->reading()->create();

        summary()->assertJsonCount(1, 'data.forecasts')->assertJsonPath('data.forecasts.0.user_book_id', $this->userBook->id);
    });
});
