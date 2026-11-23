<?php

use App\Models\BookText;
use App\Models\Post;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;

/*
 * Tags and the private note of a quote belong to the reader alone: publishing a quote copies its text only, and no
 * community response (post, profile, feed) may carry them.
 */

beforeEach(function () {
    $this->user = User::factory()->create(['handle' => 'owner']);
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->finished()->create();
});

function privateTaggedQuote(mixed $test): int
{
    return $test->actAs($test->user)->postJson("/api/library/{$test->userBook->id}/quotes", [
        'content' => 'A public line.',
        'note' => 'SECRET-NOTE private thoughts',
        'tags' => ['secret-tag'],
    ])->assertCreated()->json('data.id');
}

it('keeps tags and the note out of a published post and everything that shows it', function () {
    $quoteId = privateTaggedQuote($this);

    $created = $this->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quoteId, 'comment' => 'Mine.'])->assertCreated();
    $postId = $created->json('data.id');

    expect($created->json('data'))->not->toHaveKeys(['tags', 'note'])
        ->and($created->getContent())->not->toContain('secret-tag')->not->toContain('SECRET-NOTE');

    // The author, a stranger, a follower and a guest-free community surface: none of them gets a trace.
    $this->stranger->following()->attach($this->user->id);

    foreach ([$this->user, $this->stranger] as $viewer) {
        $this->actAs($viewer);

        foreach (["/api/posts/{$postId}", '/api/feed?scope=all', '/api/profiles/owner/posts', '/api/profiles/owner', "/api/books/{$this->userBook->book_id}/reviews"] as $uri) {
            $body = $this->getJson($uri)->assertOk()->getContent();

            expect($body)->not->toContain('secret-tag', $uri)->not->toContain('SECRET-NOTE', $uri);
        }
    }

    // Nothing of the private fields was copied into the snapshot row.
    expect(Post::query()->sole()->getAttributes())->not->toHaveKeys(['tags', 'note']);
});

it('keeps the snapshot as it was when the tags change afterwards', function () {
    $quoteId = privateTaggedQuote($this);
    $postId = $this->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quoteId])->assertCreated()->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", ['tags' => ['other']])->assertOk();

    expect($this->getJson("/api/posts/{$postId}")->assertOk()->getContent())->not->toContain('other')->not->toContain('SECRET-NOTE');
});

it('does not let other readers see tags of a quote through the quote endpoints', function () {
    $quoteId = privateTaggedQuote($this);

    $this->actAs($this->stranger);
    $this->getJson("/api/quotes/{$quoteId}")->assertNotFound();
    $this->getJson('/api/quotes?tag=secret-tag')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/tags')->assertOk()->assertExactJson(['data' => []]);
});

describe('quotes saved from the reader', function () {
    beforeEach(function () {
        $this->text = BookText::factory()->for($this->userBook)->withChapters([
            ['One', '<p>Hello brave new world.</p><p>Second paragraph here.</p>'],
        ])->create();
        $this->actAs($this->user);
    });

    it('saves tags together with the anchor and keeps both', function () {
        $this->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'brave new world',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
            'tags' => ['  Utopia ', 'READER'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.tags', ['reader', 'utopia'])
            ->assertJsonPath('data.anchor', ['book_text_id' => $this->text->id, 'chapter' => 0, 'start' => 6, 'end' => 21]);

        $quote = Quote::query()->sole();
        expect($quote->book_text_id)->toBe($this->text->id)->and($quote->start_offset)->toBe(6);
    });

    it('does not keep tags when the anchor is rejected', function () {
        $this->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'not in the text',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
            'tags' => ['ghost'],
        ])->assertUnprocessable();

        $this->getJson('/api/tags')->assertOk()->assertExactJson(['data' => []]);
        expect(Quote::count())->toBe(0);
    });

    it('edits the tags of an anchored quote without losing the anchor, and finds it by tag', function () {
        $id = $this->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'brave new world',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/quotes/{$id}", ['tags' => ['later']])
            ->assertOk()
            ->assertJsonPath('data.tags', ['later'])
            ->assertJsonPath('data.anchor.start', 6);

        $this->getJson('/api/quotes?tag=later')->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.anchor.chapter', 0);
        $this->get('/api/quotes/export?tag=later')->assertOk();
    });

    it('lists and exports an anchored quote that has no tags', function () {
        $this->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'brave new world',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
        ])->assertCreated()->assertJsonPath('data.tags', []);

        $this->getJson('/api/quotes')->assertOk()->assertJsonPath('data.0.tags', [])->assertJsonPath('data.0.anchor.start', 6);
        expect($this->get('/api/quotes/export')->assertOk()->streamedContent())->toContain('> brave new world');
    });
});
