<?php

use App\Models\BookText;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;

/**
 * Chapter 0 is "Hello brave new world.Second paragraph 😀 here." (47 UTF-16 code units: the emoji is two),
 * chapter 1 is "Друга глава".
 */
function postAnchoredQuote(mixed $test, User $user, UserBook $userBook, array $body)
{
    return $test->actingAs($user)->postJson("/api/library/{$userBook->id}/quotes", $body);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);
    $this->text = BookText::factory()->for($this->userBook)->withChapters([
        ['One', '<p>Hello brave new world.</p><p>Second paragraph 😀 here.</p>'],
        ['Two', '<h2>Друга</h2><p>глава <em>книги</em></p>'],
    ])->create();
});

describe('saving a quote with its place in the text', function () {
    it('keeps the place and says it in the answer', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'brave new world',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
        ])
            ->assertCreated()
            ->assertJsonPath('data.content', 'brave new world')
            ->assertJsonPath('data.anchor', ['book_text_id' => $this->text->id, 'chapter' => 0, 'start' => 6, 'end' => 21]);

        $quote = Quote::query()->sole();
        expect($quote->book_text_id)->toBe($this->text->id)
            ->and($quote->chapter_position)->toBe(0)
            ->and($quote->start_offset)->toBe(6)
            ->and($quote->end_offset)->toBe(21);
    });

    it('takes the place only from a valid anchor, never from fields of the body that happen to be named like the columns', function () {
        $other = BookText::factory()->for(UserBook::factory()->for($this->stranger))->withChapters([['X', '<p>Not yours at all.</p>']])->create();

        // Without an anchor: nothing is stored of the fields, whatever they say.
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'brave',
            'book_text_id' => $this->text->id,
            'chapter_position' => 0,
            'start_offset' => 6,
            'end_offset' => 11,
        ])->assertCreated()->assertJsonPath('data.anchor', null);

        // Somebody else's text, and offsets that have nothing to do with the quote.
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'typed in',
            'book_text_id' => $other->id,
            'chapter_position' => 7,
            'start_offset' => 100,
            'end_offset' => 90,
        ])->assertCreated()->assertJsonPath('data.anchor', null);

        // With an anchor: the stored values are the ones the anchor proved, not the ones beside it.
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'brave',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 11],
            'book_text_id' => $other->id,
            'chapter_position' => 1,
            'start_offset' => 0,
            'end_offset' => 3,
        ])->assertCreated()->assertJsonPath('data.anchor', ['book_text_id' => $this->text->id, 'chapter' => 0, 'start' => 6, 'end' => 11]);

        $stored = Quote::query()->orderBy('id')->get(['book_text_id', 'chapter_position', 'start_offset', 'end_offset'])->map->only(['book_text_id', 'chapter_position', 'start_offset', 'end_offset'])->all();

        expect($stored)->toBe([
            ['book_text_id' => null, 'chapter_position' => null, 'start_offset' => null, 'end_offset' => null],
            ['book_text_id' => null, 'chapter_position' => null, 'start_offset' => null, 'end_offset' => null],
            ['book_text_id' => $this->text->id, 'chapter_position' => 0, 'start_offset' => 6, 'end_offset' => 11],
        ]);
    });

    it('does not let an edit of a quote move its place, or give it one', function () {
        $plain = Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->patchJson("/api/quotes/{$plain->id}", [
            'content' => 'edited',
            'book_text_id' => $this->text->id,
            'chapter_position' => 0,
            'start_offset' => 6,
            'end_offset' => 11,
        ])->assertOk()->assertJsonPath('data.anchor', null);

        expect($plain->refresh()->only(['book_text_id', 'chapter_position', 'start_offset', 'end_offset']))
            ->toBe(['book_text_id' => null, 'chapter_position' => null, 'start_offset' => null, 'end_offset' => null]);
    });

    it('has no place for a quote that was typed in', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, ['content' => 'typed in'])
            ->assertCreated()
            ->assertJsonPath('data.anchor', null);

        postAnchoredQuote($this, $this->user, $this->userBook, ['content' => 'typed in', 'anchor' => null])
            ->assertCreated()
            ->assertJsonPath('data.anchor', null);
    });

    it('counts in UTF-16 code units, an emoji is two', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'paragraph 😀 here',
            'anchor' => ['chapter' => 0, 'start' => 29, 'end' => 46],
        ])->assertCreated();

        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => '😀',
            'anchor' => ['chapter' => 0, 'start' => 39, 'end' => 41],
        ])->assertCreated();

        // Counted in characters instead, the emoji would be one and this would be one short.
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'paragraph 😀 here',
            'anchor' => ['chapter' => 0, 'start' => 29, 'end' => 45],
        ])->assertUnprocessable()->assertJsonPath('errors.anchor', ['reader.anchor_mismatch']);
    });

    it('refuses a range that cuts an emoji in two', function (int $start, int $end) {
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'paragraph 😀 here',
            'anchor' => ['chapter' => 0, 'start' => $start, 'end' => $end],
        ])->assertUnprocessable()->assertJsonPath('errors.anchor', ['reader.anchor_mismatch']);
    })->with([[39, 40], [40, 41], [40, 46], [29, 40]]);

    it('reads Cyrillic, and text that sits in different elements', function () {
        // "Друга" (a heading) and "глава книги" (a paragraph with an emphasis) are one run of text of 16 characters.
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => "Друга\nглава книги",
            'anchor' => ['chapter' => 1, 'start' => 0, 'end' => 16],
        ])->assertCreated();

        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'книги',
            'anchor' => ['chapter' => 1, 'start' => 11, 'end' => 16],
        ])->assertCreated();
    });

    it('does not mind white space that a browser adds between paragraphs and lines', function () {
        // The text of the chapter has "world.Second", a browser says "world.\nSecond".
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => "new world.\n\nSecond   paragraph",
            'anchor' => ['chapter' => 0, 'start' => 12, 'end' => 38],
        ])->assertCreated();
    });

    it('refuses a place whose text is not the text of the quote', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'something else entirely',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
        ])->assertUnprocessable()->assertJsonPath('errors.anchor', ['reader.anchor_mismatch']);

        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'brave new worl',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
        ])->assertUnprocessable()->assertJsonPath('errors.anchor', ['reader.anchor_mismatch']);

        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'BRAVE NEW WORLD',
            'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 21],
        ])->assertUnprocessable();

        expect(Quote::query()->count())->toBe(0);
    });

    it('refuses a place that is outside of the chapter or of the text', function (array $anchor) {
        postAnchoredQuote($this, $this->user, $this->userBook, ['content' => 'brave', 'anchor' => $anchor])
            ->assertUnprocessable()
            ->assertJsonPath('errors.anchor', ['reader.anchor_out_of_range']);

        expect(Quote::query()->count())->toBe(0);
    })->with([
        'past the end of the chapter' => [['chapter' => 0, 'start' => 40, 'end' => 48]],
        'far past the end' => [['chapter' => 0, 'start' => 1000, 'end' => 1005]],
        'a chapter that does not exist' => [['chapter' => 2, 'start' => 0, 'end' => 5]],
        'a huge range' => [['chapter' => 0, 'start' => 0, 'end' => 50_000_000]],
    ]);

    it('validates the shape of the place', function (array $anchor, string $field) {
        postAnchoredQuote($this, $this->user, $this->userBook, ['content' => 'brave', 'anchor' => $anchor])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'no chapter' => [['start' => 0, 'end' => 5], 'anchor.chapter'],
        'no start' => [['chapter' => 0, 'end' => 5], 'anchor.start'],
        'no end' => [['chapter' => 0, 'start' => 0], 'anchor.end'],
        'an end that is not after the start' => [['chapter' => 0, 'start' => 5, 'end' => 5], 'anchor.end'],
        'an end before the start' => [['chapter' => 0, 'start' => 9, 'end' => 5], 'anchor.end'],
        'a negative start' => [['chapter' => 0, 'start' => -1, 'end' => 5], 'anchor.start'],
        'a chapter as text' => [['chapter' => 'one', 'start' => 0, 'end' => 5], 'anchor.chapter'],
    ]);

    it('refuses a place in a book that has no text', function () {
        $without = UserBook::factory()->for($this->user)->create();

        postAnchoredQuote($this, $this->user, $without, ['content' => 'brave', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 11]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.anchor', ['reader.no_text']);

        postAnchoredQuote($this, $this->user, $without, ['content' => 'brave'])->assertCreated();
    });

    it('only knows the text of the entry the quote is saved to', function () {
        $other = UserBook::factory()->for($this->user)->create();
        BookText::factory()->for($other)->withChapters([['X', '<p>Another text altogether.</p>']])->create();

        postAnchoredQuote($this, $this->user, $other, ['content' => 'brave', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 11]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.anchor', ['reader.anchor_mismatch']);
    });

    it('answers "not found" for somebody else\'s entry and its text', function () {
        postAnchoredQuote($this, $this->stranger, $this->userBook, [
            'content' => 'brave', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 11],
        ])->assertNotFound();

        expect(Quote::query()->count())->toBe(0);
    });
});

describe('quotes with a place in the text', function () {
    it('show it wherever the quote is shown', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'brave', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 11],
        ])->assertCreated();
        $plain = Quote::factory()->for($this->userBook)->create();
        $quote = Quote::query()->whereNotNull('book_text_id')->sole();

        $anchor = ['book_text_id' => $this->text->id, 'chapter' => 0, 'start' => 6, 'end' => 11];

        $this->actingAs($this->user)->getJson("/api/quotes/{$quote->id}")->assertJsonPath('data.anchor', $anchor);
        $this->actingAs($this->user)->getJson("/api/quotes/{$plain->id}")->assertJsonPath('data.anchor', null);

        $list = $this->actingAs($this->user)->getJson('/api/quotes')->assertOk();
        expect(collect($list->json('data'))->keyBy('id')[$quote->id]['anchor'])->toBe($anchor);
    });

    it('keep their place when they are edited', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'brave', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 11],
        ])->assertCreated();
        $quote = Quote::query()->sole();

        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['content' => 'my own words', 'is_favorite' => true, 'anchor' => ['chapter' => 1, 'start' => 0, 'end' => 3]])
            ->assertOk()
            ->assertJsonPath('data.content', 'my own words')
            ->assertJsonPath('data.anchor.chapter', 0)
            ->assertJsonPath('data.anchor.start', 6);
    });

    it('stop being highlighted when they are deleted', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, [
            'content' => 'brave', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 11],
        ])->assertCreated();
        $quote = Quote::query()->sole();

        $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/0")->assertJsonCount(1, 'data.highlights');

        $this->actingAs($this->user)->deleteJson("/api/quotes/{$quote->id}")->assertNoContent();

        $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/0")->assertJsonCount(0, 'data.highlights');
    });

    it('can overlap', function () {
        postAnchoredQuote($this, $this->user, $this->userBook, ['content' => 'brave new', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 15]])->assertCreated();
        postAnchoredQuote($this, $this->user, $this->userBook, ['content' => 'new world', 'anchor' => ['chapter' => 0, 'start' => 12, 'end' => 21]])->assertCreated();
        postAnchoredQuote($this, $this->user, $this->userBook, ['content' => 'brave new', 'anchor' => ['chapter' => 0, 'start' => 6, 'end' => 15]])->assertCreated();

        $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/0")->assertJsonCount(3, 'data.highlights');
    });
});
