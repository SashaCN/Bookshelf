<?php

use App\Enums\BookStatus;
use App\Models\BookText;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;

/**
 * Three chapters of 100, 300 and 100 characters.
 */
function textOfFiveHundredCharacters(UserBook $userBook): BookText
{
    return BookText::factory()->for($userBook)->withChapters([
        '<p>'.str_repeat('a', 100).'</p>',
        '<p>'.str_repeat('b', 300).'</p>',
        '<p>'.str_repeat('c', 100).'</p>',
    ])->create();
}

function putReadingPosition(mixed $test, User $user, BookText $text, array $body)
{
    return $test->actingAs($user)->putJson("/api/book-texts/{$text->id}/position", $body);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(40)->create(['total_pages' => 200]);
    $this->text = textOfFiveHundredCharacters($this->userBook);
});

describe('saving the position', function () {
    it('remembers the chapter and how far into it the reader is, and works out how far into the text that is', function () {
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])
            ->assertOk()
            ->assertJsonPath('data.position.chapter', 1)
            ->assertJsonPath('data.position.chapter_progress', 0.5)
            ->assertJsonPath('data.progress', 0.5);

        $text = $this->text->refresh();
        expect($text->chapter_position)->toBe(1)
            ->and($text->chapter_progress)->toBe(0.5)
            ->and($text->progress)->toBe(0.5)
            ->and($text->position_updated_at)->not->toBeNull();
    });

    it('measures the progress in characters, not in chapters', function (int $chapter, float $inChapter, float $expected) {
        $progress = putReadingPosition($this, $this->user, $this->text, ['chapter' => $chapter, 'chapter_progress' => $inChapter])
            ->assertOk()
            ->json('data.progress');

        expect($progress)->toEqualWithDelta($expected, 0.0000001);
    })->with([
        'the very start' => [0, 0.0, 0.0],
        'the end of the first chapter' => [0, 1.0, 0.2],
        'a start of the second' => [1, 0.0, 0.2],
        'a third of the second' => [1, 1 / 3, 0.4],
        'the start of the last' => [2, 0.0, 0.8],
        'the very end' => [2, 1.0, 1.0],
    ]);

    it('lets the reader go back', function () {
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 2, 'chapter_progress' => 0.5])->assertOk();
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 0, 'chapter_progress' => 0.1])
            ->assertOk()
            ->assertJsonPath('data.position.chapter', 0);

        expect($this->text->refresh()->chapter_position)->toBe(0);
    });

    it('is shown in the description of the text and of the entry', function () {
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])->assertOk();

        $this->actingAs($this->user)->getJson("/api/library/{$this->userBook->id}/text")->assertJsonPath('data.position.chapter', 1);
        $this->actingAs($this->user)->getJson("/api/library/{$this->userBook->id}")->assertJsonPath('data.text_progress', 0.5);
    });

    it('validates the body', function (array $body, string $field) {
        putReadingPosition($this, $this->user, $this->text, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'nothing' => [[], 'chapter'],
        'no progress' => [['chapter' => 0], 'chapter_progress'],
        'a negative chapter' => [['chapter' => -1, 'chapter_progress' => 0], 'chapter'],
        'a chapter as text' => [['chapter' => 'one', 'chapter_progress' => 0], 'chapter'],
        'progress below 0' => [['chapter' => 0, 'chapter_progress' => -0.1], 'chapter_progress'],
        'progress above 1' => [['chapter' => 0, 'chapter_progress' => 1.1], 'chapter_progress'],
        'progress as text' => [['chapter' => 0, 'chapter_progress' => 'half'], 'chapter_progress'],
    ]);

    it('refuses a chapter the text does not have', function () {
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 3, 'chapter_progress' => 0.1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.chapter', ['reader.chapter_out_of_range']);

        expect($this->text->refresh()->position_updated_at)->toBeNull();
    });

    it('answers "not found" for somebody else\'s text, whatever is sent', function () {
        putReadingPosition($this, $this->stranger, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])->assertNotFound();
        putReadingPosition($this, $this->stranger, $this->text, [])->assertNotFound();

        expect($this->text->refresh()->position_updated_at)->toBeNull()
            ->and($this->userBook->refresh()->current_page)->toBe(40);
    });
});

describe('how often the position may be saved', function () {
    it('is 120 times a minute for a reader, and no more', function () {
        for ($i = 0; $i < 120; $i++) {
            putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])->assertOk();
        }

        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.6])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        // The refused one was not saved.
        expect($this->text->refresh()->chapter_progress)->toBe(0.5);
    });

    it('is counted for each reader on their own', function () {
        for ($i = 0; $i < 121; $i++) {
            putReadingPosition($this, $this->user, $this->text, ['chapter' => 0, 'chapter_progress' => 0.1]);
        }

        $theirs = textOfFiveHundredCharacters(UserBook::factory()->for($this->stranger)->create(['total_pages' => 200]));

        putReadingPosition($this, $this->stranger, $theirs, ['chapter' => 0, 'chapter_progress' => 0.1])->assertOk();
    });

    it('starts again after a minute', function () {
        for ($i = 0; $i < 121; $i++) {
            putReadingPosition($this, $this->user, $this->text, ['chapter' => 0, 'chapter_progress' => 0.1]);
        }

        $this->travel(61)->seconds();

        putReadingPosition($this, $this->user, $this->text, ['chapter' => 0, 'chapter_progress' => 0.2])->assertOk();
    });
});

describe('the page of the entry', function () {
    it('goes up to the page the reader has got to, through the progress update', function () {
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])
            ->assertOk()
            ->assertJsonPath('meta.user_book.id', $this->userBook->id)
            ->assertJsonPath('meta.user_book.current_page', 100)
            ->assertJsonPath('meta.user_book.progress_percent', 50)
            ->assertJsonPath('meta.user_book.has_text', true)
            ->assertJsonPath('meta.user_book.text_progress', 0.5);

        expect($this->userBook->refresh()->current_page)->toBe(100);

        // The log, and so the streak and the statistics, hear about it like about any other move of the bookmark.
        $log = ReadingLog::query()->sole();
        expect($log->from_page)->toBe(40)
            ->and($log->to_page)->toBe(100)
            ->and($log->logged_on->toDateString())->toBe(now($this->user->timezone)->toDateString());

        $this->actingAs($this->user)->getJson('/api/stats/summary')->assertOk()->assertJsonPath('data.today.pages', 60);
    });

    it('rounds the page', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(0)->create(['total_pages' => 33]);
        $text = textOfFiveHundredCharacters($userBook);

        // 0.2 * 33 = 6.6
        putReadingPosition($this, $this->user, $text, ['chapter' => 1, 'chapter_progress' => 0])->assertOk();
        expect($userBook->refresh()->current_page)->toBe(7);
    });

    it('never takes the page back', function () {
        $this->userBook->update(['current_page' => 150]);

        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])
            ->assertOk()
            ->assertJsonPath('meta.user_book.current_page', 150);

        expect($this->userBook->refresh()->current_page)->toBe(150)
            ->and(ReadingLog::query()->count())->toBe(0);
    });

    it('does not write a log entry when the page stays the same', function () {
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])->assertOk();
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])->assertOk();
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.502])->assertOk();

        expect(ReadingLog::query()->count())->toBe(1);
    });

    it('adds up reading in small steps', function () {
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.0])->assertOk();
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 1, 'chapter_progress' => 0.5])->assertOk();
        putReadingPosition($this, $this->user, $this->text, ['chapter' => 2, 'chapter_progress' => 1.0])->assertOk();

        expect($this->userBook->refresh()->current_page)->toBe(200)
            ->and(ReadingLog::query()->pluck('to_page')->all())->toBe([100, 200])
            ->and($this->userBook->status)->toBe(BookStatus::Reading);

        $this->actingAs($this->user)->getJson("/api/library/{$this->userBook->id}")->assertJsonPath('data.progress_percent', 100);
    });

    it('starts a book that was only wanted', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => 200]);
        $text = textOfFiveHundredCharacters($userBook);

        putReadingPosition($this, $this->user, $text, ['chapter' => 1, 'chapter_progress' => 0.5])
            ->assertOk()
            ->assertJsonPath('meta.user_book.status', 'reading')
            ->assertJsonPath('meta.user_book.current_page', 100);

        expect($userBook->refresh()->status)->toBe(BookStatus::Reading)
            ->and($userBook->started_at)->not->toBeNull();
    });

    it('does not start a book that was only wanted for the first few characters', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => 200]);
        $text = textOfFiveHundredCharacters($userBook);

        // 0.001 * 200 = 0.2 of a page
        putReadingPosition($this, $this->user, $text, ['chapter' => 0, 'chapter_progress' => 0.005])->assertOk();

        expect($userBook->refresh()->status)->toBe(BookStatus::Want)
            ->and(ReadingLog::query()->count())->toBe(0);
    });

    it('takes up a book that was put aside', function () {
        $userBook = UserBook::factory()->for($this->user)->abandoned(40)->create(['total_pages' => 200]);
        $text = textOfFiveHundredCharacters($userBook);

        putReadingPosition($this, $this->user, $text, ['chapter' => 1, 'chapter_progress' => 0.5])
            ->assertOk()
            ->assertJsonPath('meta.user_book.status', 'reading');

        expect($userBook->refresh()->current_page)->toBe(100);
    });

    it('leaves a finished book alone and does not mind', function () {
        $userBook = UserBook::factory()->for($this->user)->state(['total_pages' => 200])->finished()->create();
        $text = textOfFiveHundredCharacters($userBook);

        putReadingPosition($this, $this->user, $text, ['chapter' => 1, 'chapter_progress' => 0.5])
            ->assertOk()
            ->assertJsonPath('data.progress', 0.5)
            ->assertJsonPath('meta.user_book.status', 'finished')
            ->assertJsonPath('meta.user_book.current_page', 200);

        expect($userBook->refresh()->status)->toBe(BookStatus::Finished)
            ->and(ReadingLog::query()->count())->toBe(0)
            ->and($text->refresh()->position_updated_at)->not->toBeNull();
    });

    it('does not touch a book whose page count is not known', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['total_pages' => null]);
        $text = textOfFiveHundredCharacters($userBook);

        putReadingPosition($this, $this->user, $text, ['chapter' => 1, 'chapter_progress' => 0.5])
            ->assertOk()
            ->assertJsonPath('data.progress', 0.5)
            ->assertJsonPath('meta.user_book.status', 'want')
            ->assertJsonPath('meta.user_book.current_page', 0);

        expect(ReadingLog::query()->count())->toBe(0);
    });
});
