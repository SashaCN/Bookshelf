<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
});

it('lists the journal of a book, newest change first', function () {
    $userBook = UserBook::factory()->for($this->user)->reading(60)->create();
    $first = ReadingLog::factory()->for($userBook)->create(['user_id' => $this->user->id, 'from_page' => 0, 'to_page' => 20, 'logged_on' => '2026-10-03']);
    $second = ReadingLog::factory()->for($userBook)->create(['user_id' => $this->user->id, 'from_page' => 20, 'to_page' => 60, 'logged_on' => '2026-10-04']);

    $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}/logs")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.from_page', 20)
        ->assertJsonPath('data.0.to_page', 60)
        ->assertJsonPath('data.0.pages', 40)
        ->assertJsonPath('data.0.logged_on', '2026-10-04')
        ->assertJsonPath('data.1.id', $first->id);
});

it('shows only the journal of the requested book', function () {
    $userBook = UserBook::factory()->for($this->user)->reading()->create();
    $other = UserBook::factory()->for($this->user)->reading()->create();
    ReadingLog::factory()->for($other)->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}/logs")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('is empty for a book without progress', function () {
    $userBook = UserBook::factory()->for($this->user)->create();

    $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}/logs")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('pages through a long journal', function () {
    $userBook = UserBook::factory()->for($this->user)->reading()->create();
    ReadingLog::factory()->for($userBook)->count(25)->sequence(fn ($sequence) => [
        'user_id' => $this->user->id,
        'from_page' => $sequence->index,
        'to_page' => $sequence->index + 1,
    ])->create();

    $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}/logs")
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.from_page', 24);

    $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}/logs?page=2")
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('answers "not found" for somebody else\'s journal', function () {
    $theirs = UserBook::factory()->for($this->stranger)->reading()->create();
    ReadingLog::factory()->for($theirs)->create(['user_id' => $this->stranger->id]);

    $this->actingAs($this->user)->getJson("/api/library/{$theirs->id}/logs")->assertNotFound();
});

it('removes the journal together with the book', function () {
    $userBook = UserBook::factory()->for($this->user)->reading()->create();
    ReadingLog::factory()->for($userBook)->count(3)->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)->deleteJson("/api/library/{$userBook->id}")->assertNoContent();

    expect(ReadingLog::query()->count())->toBe(0);
});

it('does not store a change that moves nowhere', function () {
    $userBook = UserBook::factory()->for($this->user)->reading()->create();

    expect(fn () => ReadingLog::factory()->for($userBook)->create(['user_id' => $this->user->id, 'from_page' => 10, 'to_page' => 10]))
        ->toThrow(QueryException::class);
});
