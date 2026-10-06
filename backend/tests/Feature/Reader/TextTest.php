<?php

use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);
});

describe('the text of an entry', function () {
    it('tells the table of contents and where the reader is', function () {
        $text = BookText::factory()->for($this->userBook)->withSampleChapters()->at(1, 0.25, 0.4)->create(['source_name' => 'my.epub']);

        $this->actingAs($this->user)->getJson("/api/library/{$this->userBook->id}/text")
            ->assertOk()
            ->assertJsonPath('data.id', $text->id)
            ->assertJsonPath('data.source_name', 'my.epub')
            ->assertJsonPath('data.chapters_count', 3)
            ->assertJsonPath('data.position.chapter', 1)
            ->assertJsonPath('data.position.chapter_progress', 0.25)
            ->assertJsonPath('data.progress', 0.4)
            ->assertJsonCount(3, 'data.chapters')
            ->assertJsonPath('data.chapters.0.title', 'One')
            ->assertJsonPath('data.chapters.2.title', null)
            ->assertJsonPath('data.chapters.2.characters', 27)
            ->assertJsonMissingPath('data.chapters.0.content');
    });

    it('is not there for an entry without a text', function () {
        $this->actingAs($this->user)->getJson("/api/library/{$this->userBook->id}/text")->assertNotFound();
    });

    it('answers "not found" for somebody else\'s entry', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();
        BookText::factory()->for($theirs)->withSampleChapters()->create();

        $this->actingAs($this->user)->getJson("/api/library/{$theirs->id}/text")->assertNotFound();
        $this->actingAs($this->user)->deleteJson("/api/library/{$theirs->id}/text")->assertNotFound();

        expect(BookText::query()->count())->toBe(1);
    });
});

describe('removing the text', function () {
    it('removes the text and its chapters, and keeps the quotes without their place in it', function () {
        $text = BookText::factory()->for($this->userBook)->withSampleChapters()->create();
        $anchored = Quote::factory()->for($this->userBook)->create([
            'book_text_id' => $text->id, 'chapter_position' => 0, 'start_offset' => 4, 'end_offset' => 9, 'content' => 'first',
        ]);
        $plain = Quote::factory()->for($this->userBook)->create();

        $this->actingAs($this->user)->deleteJson("/api/library/{$this->userBook->id}/text")->assertNoContent();

        expect(BookText::query()->count())->toBe(0)
            ->and(BookChapter::query()->count())->toBe(0)
            ->and(Quote::query()->count())->toBe(2);

        $anchored->refresh();
        expect($anchored->content)->toBe('first')
            ->and($anchored->book_text_id)->toBeNull()
            ->and($anchored->chapter_position)->toBeNull()
            ->and($anchored->start_offset)->toBeNull()
            ->and($anchored->end_offset)->toBeNull();

        $this->actingAs($this->user)->getJson("/api/quotes/{$anchored->id}")->assertOk()->assertJsonPath('data.anchor', null);
        $this->actingAs($this->user)->getJson("/api/library/{$this->userBook->id}")->assertJsonPath('data.has_text', false)->assertJsonPath('data.text_progress', null);
    });

    it('is not possible twice', function () {
        BookText::factory()->for($this->userBook)->withSampleChapters()->create();

        $this->actingAs($this->user)->deleteJson("/api/library/{$this->userBook->id}/text")->assertNoContent();
        $this->actingAs($this->user)->deleteJson("/api/library/{$this->userBook->id}/text")->assertNotFound();
    });

    it('leaves the text of other entries alone', function () {
        BookText::factory()->for($this->userBook)->withSampleChapters()->create();
        $other = UserBook::factory()->for($this->user)->create();
        BookText::factory()->for($other)->withSampleChapters()->create();

        $this->actingAs($this->user)->deleteJson("/api/library/{$this->userBook->id}/text")->assertNoContent();

        expect(BookText::query()->sole()->user_book_id)->toBe($other->id)
            ->and(BookChapter::query()->count())->toBe(3);
    });

    it('goes with the library entry, with the chapters and the quotes', function () {
        $text = BookText::factory()->for($this->userBook)->withSampleChapters()->create();
        Quote::factory()->for($this->userBook)->create(['book_text_id' => $text->id, 'chapter_position' => 0, 'start_offset' => 0, 'end_offset' => 3]);

        $this->actingAs($this->user)->deleteJson("/api/library/{$this->userBook->id}")->assertNoContent();

        expect(BookText::query()->count())->toBe(0)
            ->and(BookChapter::query()->count())->toBe(0)
            ->and(Quote::query()->count())->toBe(0);
    });
});

describe('the library entry', function () {
    it('says whether it has a text and how far the reader is in it', function () {
        $withText = UserBook::factory()->for($this->user)->create();
        BookText::factory()->for($withText)->withSampleChapters()->at(0, 0.5, 0.25)->create();

        $response = $this->actingAs($this->user)->getJson('/api/library')->assertOk();

        $byId = collect($response->json('data'))->keyBy('id');
        expect($byId[$withText->id]['has_text'])->toBeTrue()
            ->and($byId[$withText->id]['text_progress'])->toBe(0.25)
            ->and($byId[$this->userBook->id]['has_text'])->toBeFalse()
            ->and($byId[$this->userBook->id]['text_progress'])->toBeNull();
    });

    it('lists the library in a fixed number of queries, however many texts there are', function () {
        foreach (range(1, 6) as $i) {
            BookText::factory()->for(UserBook::factory()->for($this->user))->withSampleChapters()->create();
        }

        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/library')->assertOk()->assertJsonCount(7, 'data');
        $withTexts = count(DB::getQueryLog());

        BookText::query()->delete();
        DB::flushQueryLog();
        $this->actingAs($this->user)->getJson('/api/library')->assertOk();

        // One more query for all the texts, not one for each.
        expect($withTexts)->toBe(count(DB::getQueryLog()));
    });
});

describe('the table of anchors on quotes', function () {
    it('lets a quote have no place in a text, or a whole one', function () {
        $text = BookText::factory()->for($this->userBook)->withSampleChapters()->create();

        Quote::factory()->for($this->userBook)->create();
        Quote::factory()->for($this->userBook)->create(['book_text_id' => $text->id, 'chapter_position' => 1, 'start_offset' => 0, 'end_offset' => 5]);

        expect(Quote::query()->count())->toBe(2);
    });

    it('refuses in the database a place that is only half there', function (array $columns) {
        $quote = Quote::factory()->for($this->userBook)->create();

        expect(fn () => $quote->forceFill($columns)->save())->toThrow(QueryException::class);
    })->with([
        'a chapter only' => [['chapter_position' => 1]],
        'a start only' => [['start_offset' => 1]],
        'an end only' => [['end_offset' => 5]],
        'a start and an end' => [['start_offset' => 1, 'end_offset' => 5]],
        'a chapter and an end' => [['chapter_position' => 1, 'end_offset' => 5]],
    ]);

    it('refuses in the database a place that ends before it starts', function (int $start, int $end) {
        $quote = Quote::factory()->for($this->userBook)->create();

        expect(fn () => $quote->forceFill(['chapter_position' => 0, 'start_offset' => $start, 'end_offset' => $end])->save())
            ->toThrow(QueryException::class);
    })->with([[5, 5], [9, 3]]);

    it('forgets the text of a quote when the text is removed in the database', function () {
        $text = BookText::factory()->for($this->userBook)->withSampleChapters()->create();
        $quote = Quote::factory()->for($this->userBook)->create(['book_text_id' => $text->id, 'chapter_position' => 1, 'start_offset' => 0, 'end_offset' => 5]);

        DB::table('book_texts')->where('id', $text->id)->delete();

        expect($quote->refresh()->book_text_id)->toBeNull()
            ->and($quote->anchor())->toBeNull();
    });
});
