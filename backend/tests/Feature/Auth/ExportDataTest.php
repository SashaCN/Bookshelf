<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Quote;
use App\Models\ReadingGoal;
use App\Models\ReadingLog;
use App\Models\Report;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function exportJson($test): array
{
    $response = $test->getJson('/api/me/export')->assertOk();

    return json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Ada Reader',
        'email' => 'ada@example.test',
        'handle' => 'ada',
        'bio' => 'Reads a lot',
        'timezone' => 'Europe/Kyiv',
    ]);
    $this->actAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

it('requires authentication', function () {
    $this->actAsGuest();

    $this->getJson('/api/me/export')->assertUnauthorized();
});

it('downloads a JSON file named by the date of the reader', function () {
    Carbon::setTestNow('2026-11-23 23:30:00'); // already the 24th in Kyiv

    $response = $this->getJson('/api/me/export')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('bookshelf-export-2026-11-24.json')
        ->and($response->headers->get('Content-Type'))->toContain('application/json');
});

it('exports an empty account as a complete document', function () {
    $data = exportJson($this);

    expect($data)->toMatchArray(['format' => 'bookshelf-export', 'version' => 1])
        ->and($data['exported_at'])->toBeString()
        ->and($data['profile'])->toMatchArray(['handle' => 'ada', 'name' => 'Ada Reader', 'email' => 'ada@example.test', 'bio' => 'Reads a lot', 'timezone' => 'Europe/Kyiv'])
        ->and($data)->toHaveKeys(['library', 'reading_logs', 'quotes', 'goals', 'posts', 'comments', 'likes', 'following', 'followers', 'blocks', 'reports_filed', 'uploaded_books']);

    foreach (['library', 'reading_logs', 'quotes', 'goals', 'posts', 'comments', 'likes', 'following', 'followers', 'blocks', 'reports_filed', 'uploaded_books'] as $key) {
        expect($data[$key])->toBe([], $key);
    }
});

it('exports the whole account of a rich reader', function () {
    $book = Book::factory()->create(['title' => 'Кобзар', 'isbn_13' => '9786175850001']);
    $book->authors()->attach([
        Author::factory()->create(['name' => 'Тарас Шевченко'])->id => ['position' => 0],
        Author::factory()->create(['name' => 'Second Author'])->id => ['position' => 1],
    ]);
    $entry = UserBook::factory()->for($this->user)->create([
        'book_id' => $book->id, 'status' => 'finished', 'total_pages' => 300, 'current_page' => 300, 'rating' => 5,
    ]);
    $entry->reads()->create(['started_at' => '2025-01-01 10:00:00', 'finished_at' => '2025-02-01 10:00:00']);
    ReadingLog::factory()->create(['user_book_id' => $entry->id, 'user_id' => $this->user->id, 'from_page' => 0, 'to_page' => 40, 'logged_on' => '2026-05-01']);
    ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);

    $text = BookText::factory()->create(['user_book_id' => $entry->id, 'user_id' => $this->user->id, 'source_name' => 'kobzar.epub', 'chapters_count' => 2, 'characters_count' => 900]);
    BookChapter::factory()->create(['book_text_id' => $text->id, 'position' => 0, 'content' => '<p>SECRET-CHAPTER-TEXT</p>']);

    $quote = Quote::factory()->create([
        'user_book_id' => $entry->id, 'user_id' => $this->user->id, 'content' => 'Реве та стогне', 'note' => 'private note', 'page' => 7,
        'book_text_id' => $text->id, 'chapter_position' => 0, 'start_offset' => 3, 'end_offset' => 17,
    ]);
    $tag = Tag::query()->create(['user_id' => $this->user->id, 'name' => 'poetry']);
    $quote->tags()->attach($tag->id);

    $friend = User::factory()->create(['handle' => 'friend']);
    $fan = User::factory()->create(['handle' => 'fan']);
    $troll = User::factory()->create(['handle' => 'troll']);
    $this->user->following()->attach($friend->id);
    $fan->following()->attach($this->user->id);
    $this->user->blockedReaders()->attach($troll->id);

    $review = Post::factory()->review(4)->create(['user_id' => $this->user->id, 'book_id' => $book->id, 'content' => 'Great book']);
    $quotePost = Post::factory()->create(['user_id' => $this->user->id, 'book_id' => $book->id, 'content' => 'Реве та стогне', 'comment' => 'my take', 'page' => 7]);
    $theirs = Post::factory()->create(['user_id' => $friend->id]);
    Comment::factory()->create(['post_id' => $theirs->id, 'user_id' => $this->user->id, 'content' => 'Nice one']);
    DB::table('likes')->insert(['user_id' => $this->user->id, 'post_id' => $theirs->id, 'created_at' => now()]);
    Report::factory()->about($theirs)->create(['reporter_id' => $this->user->id, 'note' => 'looks like spam']);

    $data = exportJson($this);

    expect($data['library'])->toHaveCount(1)
        ->and($data['library'][0])->toMatchArray([
            'title' => 'Кобзар', 'authors' => ['Тарас Шевченко', 'Second Author'], 'isbn' => '9786175850001',
            'status' => 'finished', 'total_pages' => 300, 'rating' => 5,
        ])
        ->and($data['library'][0]['previous_reads'])->toHaveCount(1)
        ->and($data['reading_logs'])->toHaveCount(1)
        ->and($data['reading_logs'][0])->toMatchArray(['book' => 'Кобзар', 'from_page' => 0, 'to_page' => 40, 'logged_on' => '2026-05-01'])
        ->and($data['goals'])->toBe([['year' => 2026, 'target_books' => 12]])
        ->and($data['quotes'])->toHaveCount(1)
        ->and($data['quotes'][0])->toMatchArray([
            'content' => 'Реве та стогне', 'note' => 'private note', 'page' => 7, 'tags' => ['poetry'],
            'anchor' => ['chapter' => 0, 'start' => 3, 'end' => 17],
        ])
        ->and($data['posts'])->toHaveCount(2)
        ->and($data['posts'][0])->toMatchArray(['id' => $review->id, 'type' => 'review', 'rating' => 4, 'content' => 'Great book', 'book' => 'Кобзар'])
        ->and($data['posts'][1])->toMatchArray(['id' => $quotePost->id, 'type' => 'quote', 'content' => 'Реве та стогне', 'comment' => 'my take', 'page' => 7])
        ->and($data['comments'])->toHaveCount(1)
        ->and($data['comments'][0])->toMatchArray(['post_id' => $theirs->id, 'post_author' => 'friend', 'content' => 'Nice one'])
        ->and($data['likes'])->toHaveCount(1)
        ->and($data['likes'][0])->toMatchArray(['post_id' => $theirs->id, 'post_author' => 'friend'])
        ->and(array_column($data['following'], 'handle'))->toBe(['friend'])
        ->and(array_column($data['followers'], 'handle'))->toBe(['fan'])
        ->and($data['blocks'])->toBe(['troll'])
        ->and($data['reports_filed'])->toHaveCount(1)
        ->and($data['reports_filed'][0])->toMatchArray(['about' => 'post', 'id' => $theirs->id, 'reason' => 'spam', 'note' => 'looks like spam'])
        ->and($data['uploaded_books'])->toHaveCount(1)
        ->and($data['uploaded_books'][0])->toMatchArray(['book' => 'Кобзар', 'file_name' => 'kobzar.epub', 'chapters_count' => 2, 'characters_count' => 900]);

    // The text of the uploaded book is never part of it.
    expect(json_encode($data))->not->toContain('SECRET-CHAPTER-TEXT');
});

it('names other readers by handle only', function () {
    $friend = User::factory()->create(['handle' => 'friend', 'email' => 'friend-secret@example.test', 'name' => 'Friend Realname']);
    $this->user->following()->attach($friend->id);
    $friend->following()->attach($this->user->id);
    $this->user->blockedReaders()->attach($friend->id);

    $following = exportJson($this)['following'];

    expect($following)->toHaveCount(1)
        ->and(array_keys($following[0]))->toBe(['handle', 'since']);

    $raw = $this->getJson('/api/me/export')->streamedContent();
    expect($raw)->not->toContain('friend-secret@example.test')->not->toContain('Friend Realname');
});

it('contains nothing of another reader', function () {
    $other = User::factory()->create(['handle' => 'stranger', 'email' => 'stranger@example.test', 'bio' => 'STRANGER-BIO']);
    $book = Book::factory()->create(['title' => 'Foreign Book']);
    $entry = UserBook::factory()->for($other)->create(['book_id' => $book->id, 'rating' => 2]);
    ReadingLog::factory()->create(['user_book_id' => $entry->id, 'user_id' => $other->id]);
    Quote::factory()->create(['user_book_id' => $entry->id, 'user_id' => $other->id, 'content' => 'STRANGER-QUOTE', 'note' => 'STRANGER-NOTE']);
    ReadingGoal::factory()->for($other)->create(['year' => 2026, 'target_books' => 99]);
    BookText::factory()->create(['user_book_id' => $entry->id, 'user_id' => $other->id, 'source_name' => 'stranger.epub']);
    $post = Post::factory()->create(['user_id' => $other->id, 'book_id' => $book->id, 'content' => 'STRANGER-POST']);
    Comment::factory()->create(['post_id' => $post->id, 'user_id' => $other->id, 'content' => 'STRANGER-COMMENT']);
    DB::table('likes')->insert(['user_id' => $other->id, 'post_id' => $post->id, 'created_at' => now()]);
    Report::factory()->about($post)->create(['reporter_id' => $other->id, 'note' => 'STRANGER-REPORT']);
    $other->blockedReaders()->attach($this->user->id);
    $third = User::factory()->create(['handle' => 'third']);
    $other->following()->attach($third->id);

    $raw = $this->getJson('/api/me/export')->streamedContent();
    $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

    foreach (['library', 'reading_logs', 'quotes', 'goals', 'posts', 'comments', 'likes', 'following', 'followers', 'blocks', 'reports_filed', 'uploaded_books'] as $key) {
        expect($data[$key])->toBe([], $key);
    }
    foreach (['STRANGER', 'stranger', 'Foreign Book', 'third'] as $needle) {
        expect($raw)->not->toContain($needle);
    }
});

it('does not include reports about the reader or comments of others under their posts', function () {
    $other = User::factory()->create();
    $mine = Post::factory()->create(['user_id' => $this->user->id, 'content' => 'mine']);
    Report::factory()->about($mine)->create(['reporter_id' => $other->id, 'note' => 'REPORTER-NOTE']);
    Comment::factory()->create(['post_id' => $mine->id, 'user_id' => $other->id, 'content' => 'OTHER-COMMENT']);

    $raw = $this->getJson('/api/me/export')->streamedContent();

    expect($raw)->not->toContain('REPORTER-NOTE')->not->toContain('OTHER-COMMENT');
});

it('keeps the document valid with a large library', function () {
    UserBook::factory()->count(30)->for($this->user)->create();

    expect(exportJson($this)['library'])->toHaveCount(30);
});

it('limits exports to three an hour', function () {
    foreach (range(1, 3) as $i) {
        $this->getJson('/api/me/export')->assertOk()->streamedContent();
    }

    $this->getJson('/api/me/export')->assertTooManyRequests();
});
