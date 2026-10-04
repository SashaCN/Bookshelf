<?php

use App\Enums\QuoteType;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);
});

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/quotes'],
    ['GET', '/api/quotes/daily'],
    ['GET', '/api/quotes/1'],
    ['PATCH', '/api/quotes/1'],
    ['DELETE', '/api/quotes/1'],
    ['POST', '/api/library/1/quotes'],
]);

describe('saving a quote', function () {
    it('saves a quote with its page and tells where it comes from', function () {
        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'We are what we repeatedly do.',
            'note' => 'Habits again.',
            'page' => 42,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'quote')
            ->assertJsonPath('data.content', 'We are what we repeatedly do.')
            ->assertJsonPath('data.note', 'Habits again.')
            ->assertJsonPath('data.page', 42)
            ->assertJsonPath('data.is_favorite', false)
            ->assertJsonPath('data.user_book_id', $this->userBook->id)
            ->assertJsonPath('data.book.title', $this->userBook->book->title)
            ->assertJsonPath('data.book.authors.0', $this->userBook->book->authors->first()->name);

        $quote = Quote::query()->sole();
        expect($quote->user_id)->toBe($this->user->id)
            ->and($quote->user_book_id)->toBe($this->userBook->id)
            ->and($quote->type)->toBe(QuoteType::Quote);
    });

    it('saves an insight', function () {
        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/quotes", [
            'type' => 'insight',
            'content' => 'Small steps beat big plans.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'insight')
            ->assertJsonPath('data.page', null);
    });

    it('saves a quote as a favorite right away', function () {
        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'Keep this one.',
            'is_favorite' => true,
        ])->assertCreated()->assertJsonPath('data.is_favorite', true);
    });

    it('accepts a quote from a book whose page count is unknown', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => null]);

        $this->actingAs($this->user)->postJson("/api/library/{$userBook->id}/quotes", ['content' => 'Text', 'page' => 900])
            ->assertCreated();
    });

    it('accepts a quote from a book that has not been started', function () {
        $userBook = UserBook::factory()->for($this->user)->create();

        $this->actingAs($this->user)->postJson("/api/library/{$userBook->id}/quotes", ['content' => 'Text'])
            ->assertCreated();
    });

    it('refuses a page the book does not have', function () {
        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/quotes", ['content' => 'Text', 'page' => 321])
            ->assertUnprocessable()
            ->assertJsonPath('errors.page', ['quotes.page_above_total']);

        expect(Quote::query()->count())->toBe(0);
    });

    it('validates the body', function (array $body, string $field) {
        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/quotes", $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'no text' => [[], 'content'],
        'empty text' => [['content' => ''], 'content'],
        'too long' => [['content' => str_repeat('a', 2001)], 'content'],
        'unknown type' => [['content' => 'x', 'type' => 'poem'], 'type'],
        'page zero' => [['content' => 'x', 'page' => 0], 'page'],
        'page as text' => [['content' => 'x', 'page' => 'ten'], 'page'],
        'note too long' => [['content' => 'x', 'note' => str_repeat('a', 2001)], 'note'],
    ]);

    it('treats an empty note as no note', function () {
        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/quotes", ['content' => 'x', 'note' => ''])
            ->assertCreated()
            ->assertJsonPath('data.note', null);
    });

    it('answers "not found" for somebody else\'s library entry, whatever is sent', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();

        $this->actingAs($this->user)->postJson("/api/library/{$theirs->id}/quotes", ['content' => 'Mine now'])->assertNotFound();
        $this->actingAs($this->user)->postJson("/api/library/{$theirs->id}/quotes", [])->assertNotFound();

        expect(Quote::query()->count())->toBe(0);
    });
});

describe('listing', function () {
    it('returns only my quotes, newest first, with their books', function () {
        $older = Quote::factory()->for($this->userBook)->create();
        $newer = Quote::factory()->for($this->userBook)->create();
        Quote::factory()->for(UserBook::factory()->for($this->stranger))->create();

        $this->actingAs($this->user)->getJson('/api/quotes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.0.book.title', $this->userBook->book->title);
    });

    it('filters favorites', function () {
        Quote::factory()->for($this->userBook)->count(2)->create();
        $favorite = Quote::factory()->for($this->userBook)->favorite()->create();

        $this->actingAs($this->user)->getJson('/api/quotes?favorite=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $favorite->id);

        $this->actingAs($this->user)->getJson('/api/quotes?favorite=0')->assertOk()->assertJsonCount(3, 'data');
    });

    it('filters by book', function () {
        $other = UserBook::factory()->for($this->user)->create();
        Quote::factory()->for($this->userBook)->count(2)->create();
        $mine = Quote::factory()->for($other)->create();

        $this->actingAs($this->user)->getJson("/api/quotes?book={$other->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    });

    it('does not show somebody else\'s quotes when filtering by their book', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();
        Quote::factory()->for($theirs)->create();

        $this->actingAs($this->user)->getJson("/api/quotes?book={$theirs->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('filters by type', function () {
        Quote::factory()->for($this->userBook)->create();
        $insight = Quote::factory()->for($this->userBook)->insight()->create();

        $this->actingAs($this->user)->getJson('/api/quotes?type=insight')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $insight->id);
    });

    it('pages through a long list', function () {
        Quote::factory()->for($this->userBook)->count(25)->create();

        $this->actingAs($this->user)->getJson('/api/quotes')->assertOk()->assertJsonCount(20, 'data');
        $this->actingAs($this->user)->getJson('/api/quotes?page=2')->assertOk()->assertJsonCount(5, 'data');
    });

    it('rejects a filter it does not understand', function () {
        $this->actingAs($this->user)->getJson('/api/quotes?type=poem')->assertUnprocessable()->assertJsonValidationErrors('type');
    });
});

describe('the quote of the day', function () {
    it('is empty when there is nothing to show', function () {
        $this->actingAs($this->user)->getJson('/api/quotes/daily')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    });

    it('is the same quote all day', function () {
        Quote::factory()->for($this->userBook)->count(8)->create();
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00', 'UTC'));

        $first = $this->actingAs($this->user)->getJson('/api/quotes/daily')->assertOk()->json('data.id');
        $this->travelTo(Carbon::parse('2026-10-05 17:00:00', 'UTC'));
        $second = $this->actingAs($this->user)->getJson('/api/quotes/daily')->json('data.id');

        expect($second)->toBe($first)->and($first)->not->toBeNull();
    });

    it('changes from day to day', function () {
        Quote::factory()->for($this->userBook)->count(10)->create();

        $picks = collect(range(1, 14))->map(function (int $day) {
            $this->travelTo(Carbon::parse("2026-10-{$day} 12:00:00", 'UTC'));

            return $this->actingAs($this->user)->getJson('/api/quotes/daily')->json('data.id');
        });

        expect($picks->unique()->count())->toBeGreaterThan(3);
    });

    it('turns over at midnight of the reader, not of the server', function () {
        Quote::factory()->for($this->userBook)->count(10)->create();

        // 22:30 UTC is already 01:30 of the next day in Kyiv (UTC+3 in October).
        $this->travelTo(Carbon::parse('2026-10-05 22:30:00', 'UTC'));
        $lateEvening = $this->actingAs($this->user)->getJson('/api/quotes/daily')->json('data.id');

        $this->travelTo(Carbon::parse('2026-10-06 12:00:00', 'UTC'));
        $nextNoon = $this->actingAs($this->user)->getJson('/api/quotes/daily')->json('data.id');

        expect($lateEvening)->toBe($nextNoon);
    });

    it('can be limited to favorites', function () {
        Quote::factory()->for($this->userBook)->count(5)->create();
        $favorite = Quote::factory()->for($this->userBook)->favorite()->create();

        foreach (range(1, 5) as $day) {
            $this->travelTo(Carbon::parse("2026-10-{$day} 12:00:00", 'UTC'));
            $this->actingAs($this->user)->getJson('/api/quotes/daily?favorite=1')->assertJsonPath('data.id', $favorite->id);
        }
    });

    it('is empty when asked for favorites and there are none', function () {
        Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->getJson('/api/quotes/daily?favorite=1')->assertOk()->assertJsonPath('data', null);
    });

    it('never picks somebody else\'s quote', function () {
        Quote::factory()->for(UserBook::factory()->for($this->stranger))->count(5)->create();

        $this->actingAs($this->user)->getJson('/api/quotes/daily')->assertOk()->assertJsonPath('data', null);
    });

    it('carries the book the quote comes from', function () {
        Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->getJson('/api/quotes/daily')
            ->assertOk()
            ->assertJsonPath('data.book.title', $this->userBook->book->title);
    });
});

describe('one quote', function () {
    it('shows my quote', function () {
        $quote = Quote::factory()->for($this->userBook)->create(['content' => 'Hello', 'page' => 7]);

        $this->actingAs($this->user)->getJson("/api/quotes/{$quote->id}")
            ->assertOk()
            ->assertJsonPath('data.content', 'Hello')
            ->assertJsonPath('data.page', 7)
            ->assertJsonPath('data.book.title', $this->userBook->book->title);
    });

    it('edits the text, the note and the page', function () {
        $quote = Quote::factory()->for($this->userBook)->create(['note' => 'old']);

        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['content' => 'New text', 'note' => null, 'page' => 99])
            ->assertOk()
            ->assertJsonPath('data.content', 'New text')
            ->assertJsonPath('data.note', null)
            ->assertJsonPath('data.page', 99);

        expect($quote->fresh()->content)->toBe('New text');
    });

    it('marks and unmarks a favorite', function () {
        $quote = Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['is_favorite' => true])
            ->assertOk()->assertJsonPath('data.is_favorite', true);
        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['is_favorite' => false])
            ->assertOk()->assertJsonPath('data.is_favorite', false);
    });

    it('turns a quote into an insight', function () {
        $quote = Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['type' => 'insight'])
            ->assertOk()->assertJsonPath('data.type', 'insight');
    });

    it('leaves everything else alone when only one field is sent', function () {
        $quote = Quote::factory()->for($this->userBook)->create(['content' => 'Keep', 'note' => 'Keep too', 'page' => 12]);

        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['is_favorite' => true])->assertOk();

        $fresh = $quote->fresh();
        expect($fresh->content)->toBe('Keep')->and($fresh->note)->toBe('Keep too')->and($fresh->page)->toBe(12);
    });

    it('refuses an empty text and a page the book does not have', function () {
        $quote = Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['content' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['page' => 321])
            ->assertUnprocessable()->assertJsonPath('errors.page', ['quotes.page_above_total']);
    });

    it('deletes a quote', function () {
        $quote = Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->deleteJson("/api/quotes/{$quote->id}")->assertNoContent();

        expect(Quote::query()->count())->toBe(0);
    });

    it('goes away with the book', function () {
        Quote::factory()->for($this->userBook)->count(3)->create();

        $this->actingAs($this->user)->deleteJson("/api/library/{$this->userBook->id}")->assertNoContent();

        expect(Quote::query()->count())->toBe(0);
    });

    it('answers "not found" for somebody else\'s quote and changes nothing', function () {
        $theirs = Quote::factory()->for(UserBook::factory()->for($this->stranger))->create(['content' => 'Theirs']);

        $this->actingAs($this->user)->getJson("/api/quotes/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->user)->patchJson("/api/quotes/{$theirs->id}", ['content' => 'Mine now'])->assertNotFound();
        $this->actingAs($this->user)->patchJson("/api/quotes/{$theirs->id}", ['content' => ''])->assertNotFound();
        $this->actingAs($this->user)->deleteJson("/api/quotes/{$theirs->id}")->assertNotFound();

        expect($theirs->fresh()->content)->toBe('Theirs');
    });

    it('answers "not found" for a quote that does not exist', function () {
        $this->actingAs($this->user)->getJson('/api/quotes/999999')->assertNotFound();
    });
});
