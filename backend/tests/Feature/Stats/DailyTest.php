<?php

use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->userBook = UserBook::factory()->for($this->user)->reading(0)->create(['total_pages' => 400]);
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'UTC'));
});

it('requires authentication', function () {
    $this->getJson('/api/stats/daily')->assertUnauthorized();
});

it('covers the last 30 days by default, with zeros for the days without reading', function () {
    readingLog($this->userBook, 0, 20, '2026-10-15');
    readingLog($this->userBook, 20, 25, '2026-09-16');

    $response = $this->actingAs($this->user)->getJson('/api/stats/daily')->assertOk();

    $response->assertJsonCount(30, 'data')
        ->assertJsonPath('data.0', ['date' => '2026-09-16', 'pages' => 5])
        ->assertJsonPath('data.1', ['date' => '2026-09-17', 'pages' => 0])
        ->assertJsonPath('data.29', ['date' => '2026-10-15', 'pages' => 20])
        ->assertJsonPath('meta', ['from' => '2026-09-16', 'to' => '2026-10-15', 'total_pages' => 25]);
});

it('covers the last N days when asked for N', function () {
    readingLog($this->userBook, 0, 20, '2026-10-15');

    $this->actingAs($this->user)->getJson('/api/stats/daily?days=7')
        ->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0.date', '2026-10-09')
        ->assertJsonPath('data.6', ['date' => '2026-10-15', 'pages' => 20]);

    $this->actingAs($this->user)->getJson('/api/stats/daily?days=1')->assertJsonCount(1, 'data');
});

it('refuses a number of days that makes no sense', function (int $days) {
    $this->actingAs($this->user)->getJson("/api/stats/daily?days={$days}")->assertUnprocessable()->assertJsonValidationErrors('days');
})->with([0, 367]);

it('returns the requested range, both ends included', function () {
    readingLog($this->userBook, 0, 10, '2026-10-01');
    readingLog($this->userBook, 10, 30, '2026-10-03');
    readingLog($this->userBook, 30, 31, '2026-10-04');

    $this->actingAs($this->user)->getJson('/api/stats/daily?from=2026-10-01&to=2026-10-03')
        ->assertOk()
        ->assertJsonPath('data', [
            ['date' => '2026-10-01', 'pages' => 10],
            ['date' => '2026-10-02', 'pages' => 0],
            ['date' => '2026-10-03', 'pages' => 20],
        ])
        ->assertJsonPath('meta.total_pages', 30);
});

it('counts a mistake and its correction as the net pages of the day', function () {
    readingLog($this->userBook, 0, 300, '2026-10-15');
    readingLog($this->userBook, 300, 30, '2026-10-15');

    $this->actingAs($this->user)->getJson('/api/stats/daily?from=2026-10-15&to=2026-10-15')
        ->assertJsonPath('data.0.pages', 30);
});

it('never reports a negative day', function () {
    readingLog($this->userBook, 100, 40, '2026-10-15');

    $this->actingAs($this->user)->getJson('/api/stats/daily?from=2026-10-15&to=2026-10-15')
        ->assertJsonPath('data.0.pages', 0);
});

it('ignores the pages of other readers', function () {
    $stranger = UserBook::factory()->for(User::factory())->reading(0)->create();
    readingLog($stranger, 0, 99, '2026-10-15');

    $this->actingAs($this->user)->getJson('/api/stats/daily?from=2026-10-15&to=2026-10-15')
        ->assertJsonPath('data.0.pages', 0)
        ->assertJsonPath('meta.total_pages', 0);
});

it('uses the reader\'s calendar for the default range', function () {
    $this->travelTo(Carbon::parse('2026-10-15 22:30:00', 'UTC'));

    $this->actingAs($this->user)->getJson('/api/stats/daily')
        ->assertJsonPath('meta.to', '2026-10-16')
        ->assertJsonPath('data.29.date', '2026-10-16');
});

it('allows a whole year and refuses more', function () {
    $this->actingAs($this->user)->getJson('/api/stats/daily?from=2025-10-15&to=2026-10-15')
        ->assertOk()
        ->assertJsonCount(366, 'data');

    $this->actingAs($this->user)->getJson('/api/stats/daily?from=2025-10-14&to=2026-10-15')
        ->assertUnprocessable()
        ->assertJsonPath('errors.from', ['stats.range_too_long']);
});

it('rejects dates it cannot read and ranges that run backwards', function (string $query, string $field) {
    $this->actingAs($this->user)->getJson("/api/stats/daily?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'bad from' => ['from=yesterday', 'from'],
    'bad to' => ['to=15.10.2026', 'to'],
    'backwards' => ['from=2026-10-10&to=2026-10-01', 'to'],
]);
