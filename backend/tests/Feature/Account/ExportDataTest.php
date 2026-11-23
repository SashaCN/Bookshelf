<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Quote;
use App\Models\QuoteComment;
use App\Models\ReaderBookmark;
use App\Models\ReaderChapterRead;
use App\Models\ReaderDocument;
use App\Models\ReadingGoal;
use App\Models\ReadingLog;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserBook;
use App\Models\UserBookRead;
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
        'username' => 'ada',
        'is_public' => true,
        'timezone' => 'Europe/Kyiv',
    ]);
    $this->actingAs($this->user);
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
        ->and($data['profile'])->toMatchArray(['name' => 'Ada Reader', 'email' => 'ada@example.test', 'username' => 'ada', 'is_public' => true, 'timezone' => 'Europe/Kyiv'])
        ->and($data)->toHaveKeys(['library', 'reading_logs', 'quotes', 'comments', 'goals', 'following', 'followers', 'blocks', 'reader_documents'])
        ->and($data['library'])->toBe([])
        ->and($data['quotes'])->toBe([])
        ->and($data['following'])->toBe([]);
});

it('exports the whole account of a rich reader', function () {
    $book = Book::factory()->create(['title' => 'Кобзар', 'isbn_13' => '9786175850001']);
    $book->authors()->attach([
        Author::factory()->create(['name' => 'Тарас Шевченко'])->id => ['position' => 0],
        Author::factory()->create(['name' => 'Second Author'])->id => ['position' => 1],
    ]);
    $entry = UserBook::factory()->for($this->user)->for($book)->finished()->create([
        'rating' => 5,
        'review' => 'Great, "quoted" \ слеш / ok',
        'review_visibility' => 'public',
        'total_pages' => 400,
        'current_page' => 400,
    ]);
    UserBookRead::create(['user_book_id' => $entry->id, 'started_at' => '2025-01-01 10:00:00', 'finished_at' => '2025-02-01 10:00:00']);
    ReadingLog::factory()->for($this->user)->for($entry)->create(['from_page' => 0, 'to_page' => 30, 'logged_on' => '2026-11-01']);

    $quote = Quote::factory()->for($this->user)->for($entry)->create([
        'content' => "Line one\nLine two 😀",
        'note' => 'My note',
        'page' => 12,
        'type' => 'insight',
        'is_favorite' => true,
        'visibility' => 'public',
    ]);
    $tag = Tag::create(['user_id' => $this->user->id, 'name' => 'poetry']);
    $quote->tags()->attach($tag->id);

    ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);

    $other = User::factory()->create(['username' => 'bob', 'is_public' => true]);
    $othersQuote = Quote::factory()->for($other)->for(UserBook::factory()->for($other)->create())->create(['visibility' => 'public']);
    QuoteComment::factory()->create(['quote_id' => $othersQuote->id, 'user_id' => $this->user->id, 'body' => 'My comment']);
    QuoteComment::factory()->create(['quote_id' => $quote->id, 'user_id' => $other->id, 'body' => 'Bob comment']);
    $othersQuote->likes()->attach($this->user->id);

    DB::table('follows')->insert(['follower_id' => $this->user->id, 'followed_id' => $other->id, 'created_at' => now()]);
    DB::table('follows')->insert(['follower_id' => $other->id, 'followed_id' => $this->user->id, 'created_at' => now()]);
    $blocked = User::factory()->create(['username' => 'troll']);
    DB::table('user_blocks')->insert(['blocker_id' => $this->user->id, 'blocked_id' => $blocked->id, 'created_at' => now()]);

    $document = ReaderDocument::factory()->for($this->user)->create([
        'user_book_id' => $entry->id,
        'title' => 'EPUB title',
        'author' => 'EPUB author',
        'position_chapter' => 3,
        'position_fraction' => 0.5,
    ]);
    ReaderBookmark::factory()->create(['reader_document_id' => $document->id, 'chapter' => 2, 'note' => 'Mark']);
    ReaderChapterRead::create(['reader_document_id' => $document->id, 'chapter' => 1, 'read_on' => '2026-11-02']);

    $data = exportJson($this);

    expect($data['library'])->toHaveCount(1)
        ->and($data['library'][0])->toMatchArray([
            'title' => 'Кобзар',
            'authors' => ['Тарас Шевченко', 'Second Author'],
            'isbn' => '9786175850001',
            'status' => 'finished',
            'total_pages' => 400,
            'current_page' => 400,
            'rating' => 5,
            'review' => 'Great, "quoted" \ слеш / ok',
            'review_visibility' => 'public',
        ])
        ->and($data['library'][0]['started_at'])->toBeString()
        ->and($data['library'][0]['finished_at'])->toBeString()
        ->and($data['library'][0]['previous_reads'])->toHaveCount(1)
        ->and($data['reading_logs'])->toHaveCount(1)
        ->and($data['reading_logs'][0])->toMatchArray(['book' => 'Кобзар', 'from_page' => 0, 'to_page' => 30, 'logged_on' => '2026-11-01'])
        ->and($data['quotes'])->toHaveCount(1)
        ->and($data['quotes'][0])->toMatchArray([
            'id' => $quote->id,
            'book' => 'Кобзар',
            'type' => 'insight',
            'content' => "Line one\nLine two 😀",
            'note' => 'My note',
            'page' => 12,
            'is_favorite' => true,
            'visibility' => 'public',
            'tags' => ['poetry'],
        ])
        ->and($data['comments'])->toHaveCount(1)
        ->and($data['comments'][0])->toMatchArray(['quote_id' => $othersQuote->id, 'body' => 'My comment'])
        ->and($data['goals'])->toBe([['year' => 2026, 'target_books' => 12]])
        ->and(array_column($data['following'], 'username'))->toBe(['bob'])
        ->and(array_column($data['followers'], 'username'))->toBe(['bob'])
        ->and($data['blocks'])->toBe(['troll'])
        ->and($data['reader_documents'])->toHaveCount(1)
        ->and($data['reader_documents'][0])->toMatchArray(['title' => 'EPUB title', 'author' => 'EPUB author', 'library_book' => 'Кобзар', 'position_chapter' => 3, 'position_fraction' => 0.5])
        ->and($data['reader_documents'][0]['bookmarks'])->toHaveCount(1)
        ->and($data['reader_documents'][0]['bookmarks'][0])->toMatchArray(['chapter' => 2, 'note' => 'Mark'])
        ->and($data['reader_documents'][0]['chapter_reads'][0])->toMatchArray(['chapter' => 1, 'read_on' => '2026-11-02']);
});

it('leaves out other readers data, EPUB files and the content of foreign comments and likes', function () {
    $other = User::factory()->create(['username' => 'bob', 'is_public' => true, 'email' => 'bob@example.test']);
    $entry = UserBook::factory()->for($other)->create();
    $theirs = Quote::factory()->for($other)->for($entry)->create(['content' => 'SECRET-BOB-QUOTE', 'visibility' => 'public']);
    ReadingGoal::factory()->for($other)->create(['year' => 2026]);
    ReaderDocument::factory()->for($other)->create(['title' => 'SECRET-BOB-DOC', 'path' => 'epubs/secret-path.epub']);
    $mine = Quote::factory()->for($this->user)->for(UserBook::factory()->for($this->user)->create())->create(['visibility' => 'public']);
    QuoteComment::factory()->create(['quote_id' => $mine->id, 'user_id' => $other->id, 'body' => 'SECRET-BOB-COMMENT']);
    $mine->likes()->attach($other->id);
    ReaderDocument::factory()->for($this->user)->create(['path' => 'epubs/my-path.epub']);

    $json = $this->getJson('/api/me/export')->assertOk()->streamedContent();

    expect($json)->not->toContain('SECRET-BOB')->not->toContain('bob@example.test')->not->toContain('secret-path')->not->toContain('my-path')
        ->and(json_decode($json, true)['quotes'])->toHaveCount(1);
});

it('hides usernames of private followers and followed profiles', function () {
    $public = User::factory()->create(['username' => 'open', 'is_public' => true]);
    $private = User::factory()->create(['username' => 'hidden', 'is_public' => false]);
    foreach ([$public, $private] as $reader) {
        DB::table('follows')->insert(['follower_id' => $reader->id, 'followed_id' => $this->user->id, 'created_at' => now()]);
        DB::table('follows')->insert(['follower_id' => $this->user->id, 'followed_id' => $reader->id, 'created_at' => now()]);
    }

    $json = $this->getJson('/api/me/export')->assertOk()->streamedContent();
    $data = json_decode($json, true);

    expect($json)->not->toContain('hidden')
        ->and(array_column($data['followers'], 'username'))->toEqualCanonicalizing(['open', null])
        ->and(array_column($data['following'], 'username'))->toEqualCanonicalizing(['open', null])
        ->and($data['followers'])->toHaveCount(2);
});

it('is limited to three exports an hour', function () {
    foreach (range(1, 3) as $ignored) {
        $this->getJson('/api/me/export')->assertOk()->streamedContent();
    }

    $this->getJson('/api/me/export')->assertTooManyRequests();
});

it('streams a large library with a bounded number of queries', function () {
    $entries = UserBook::factory()->count(5)->for($this->user)->create();
    $now = now();
    $rows = [];
    foreach (range(1, 2000) as $i) {
        $rows[] = [
            'user_book_id' => $entries[$i % 5]->id,
            'user_id' => $this->user->id,
            'type' => 'quote',
            'content' => "Quote number {$i}",
            'is_favorite' => false,
            'visibility' => 'private',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        Quote::insert($chunk);
    }

    DB::enableQueryLog();
    $data = exportJson($this);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($data['quotes'])->toHaveCount(2000)
        ->and($queries)->toBeLessThan(60);
});
