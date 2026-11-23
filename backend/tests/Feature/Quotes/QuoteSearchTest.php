<?php

use App\Models\Quote;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

/*
 * InnoDB FULLTEXT indexes only see committed rows, and RefreshDatabase keeps every test inside one transaction, so a
 * plain create() would never be found. Committing (and deleting again) per test cost ~3 s each, so the whole fixture
 * set is built and committed once, by the first test of the file. Every test owns one reader (own user, own books),
 * so searches never see another test's rows. afterAll removes everything again (users cascade to entries, quotes and
 * tokens; books are deleted by hand) and asserts nothing is left behind for the rest of the suite.
 */

const SEARCH_EMAIL_DOMAIN = '@quote-search.test';

/** @return array<string, array{user: User, book: UserBook, other: UserBook, quotes: array<string, Quote>}> */
function searchFixtures(): array
{
    static $fixtures = null;

    if ($fixtures !== null) {
        return $fixtures;
    }

    // A run that was killed halfway may have left committed rows behind.
    DB::table('users')->where('email', 'like', '%'.SEARCH_EMAIL_DOMAIN)->delete();

    $reader = function (string $name): array {
        $user = User::factory()->create(['email' => $name.SEARCH_EMAIL_DOMAIN]);

        return ['user' => $user, 'book' => UserBook::factory()->for($user)->create(), 'other' => UserBook::factory()->for($user)->create(), 'quotes' => []];
    };
    $quote = function (UserBook $entry, string $content, ?string $note = null, array $attributes = []): Quote {
        return Quote::factory()->for($entry)->create(['content' => $content, 'note' => $note, ...$attributes]);
    };

    $f = [];
    foreach (['cyrillic', 'partial', 'note', 'ranking', 'filters', 'pagination', 'plain', 'operators', 'own', 'export', 'validation', 'tags'] as $name) {
        $f[$name] = $reader($name);
    }

    $f['cyrillic']['quotes'] = [
        'hit' => $quote($f['cyrillic']['book'], 'Людина народжена для щастя, як птах для польоту.'),
        'miss' => $quote($f['cyrillic']['book'], 'Совість — найкращий порадник.'),
    ];
    $f['partial']['quotes'] = [
        'hit' => $quote($f['partial']['book'], 'Прокрастинація краде час у тих, хто його має.'),
        'miss' => $quote($f['partial']['book'], 'Зовсім інша думка.'),
    ];
    $f['note']['quotes'] = [
        'hit' => $quote($f['note']['book'], 'Nothing special here.', 'Нагадує розмову з дідом про осінь.'),
        'miss' => $quote($f['note']['book'], 'Nothing special here either.'),
    ];
    $f['ranking']['quotes'] = [
        'weak' => $quote($f['ranking']['book'], 'Альфа і ще щось.'),
        'both' => $quote($f['ranking']['book'], 'Альфа та бета разом.'),
        'newerBoth' => $quote($f['ranking']['book'], 'Бета та альфа знову.'),
    ];
    $f['filters']['quotes'] = [
        'inBook' => $quote($f['filters']['book'], 'Спільне слово зорі.'),
        'elsewhere' => $quote($f['filters']['other'], 'Спільне слово зорі.'),
        'favorite' => $quote($f['filters']['book'], 'Ще зорі.', null, ['is_favorite' => true]),
        'insight' => $quote($f['filters']['book'], 'Інсайт про зорі.', null, ['type' => 'insight']),
    ];
    foreach (range(1, 21) as $i) {
        $f['pagination']['quotes'][] = $quote($f['pagination']['book'], "Однакове слово номер $i");
    }
    $f['plain']['quotes'] = ['hit' => $quote($f['plain']['book'], 'Звичайний текст цитати.')];
    $f['operators']['quotes'] = ['hit' => $quote($f['operators']['book'], 'Звичайний текст цитати.')];

    $stranger = User::factory()->create(['email' => 'stranger'.SEARCH_EMAIL_DOMAIN]);
    $f['own']['quotes'] = [
        'mine' => $quote($f['own']['book'], 'Моя таємна цитата.'),
        'foreign' => $quote(UserBook::factory()->for($stranger)->create(), 'Чужа таємна цитата.', null),
    ];
    $f['export']['quotes'] = [
        $quote($f['export']['book'], 'Зірки далеко.'),
        $quote($f['export']['book'], 'Земля близько.'),
    ];

    $tag = Tag::create(['user_id' => $f['tags']['user']->id, 'name' => 'космос']);
    $f['tags']['quotes'] = [
        'tagged' => $quote($f['tags']['book'], 'Мандрівка крізь простір.'),
        'untagged' => $quote($f['tags']['book'], 'Мандрівка крізь час.'),
        'otherBook' => $quote($f['tags']['other'], 'Мандрівка крізь простір і час.'),
    ];
    $f['tags']['quotes']['tagged']->tags()->attach($tag);
    $f['tags']['quotes']['otherBook']->tags()->attach($tag);

    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    // RefreshDatabase re-migrates the whole schema after a test that ends outside a transaction, so open a fresh
    // (empty) one for it to roll back.
    DB::beginTransaction();

    return $fixtures = $f;
}

afterAll(function () {
    // No application is booted here (a second one would leave its error handlers behind and make tests risky), so a
    // plain PDO connection to the testing database is used.
    $env = fn (string $key, string $default = ''): string => (string) ($_SERVER[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default);
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env('DB_HOST', '127.0.0.1'), $env('DB_PORT', '3306'), 'bookshelf_testing'),
        $env('DB_USERNAME'),
        $env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $like = '%'.SEARCH_EMAIL_DOMAIN;
    $column = function (string $sql) use ($pdo, $like): array {
        $statement = $pdo->prepare($sql);
        $statement->execute([$like]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    };

    $bookIds = $column('SELECT book_id FROM user_books WHERE user_id IN (SELECT id FROM users WHERE email LIKE ?)');
    $pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute([$like]);
    if ($bookIds !== []) {
        $pdo->exec('DELETE FROM books WHERE id IN ('.implode(',', array_map('intval', $bookIds)).')');
    }

    expect($column('SELECT id FROM users WHERE email LIKE ?'))->toBe([])
        ->and($pdo->query('SELECT COUNT(*) FROM quotes q LEFT JOIN user_books ub ON ub.id = q.user_book_id WHERE ub.id IS NULL')->fetchColumn())->toEqual(0)
        ->and($pdo->query('SELECT COUNT(*) FROM quotes')->fetchColumn())->toEqual(0);
});

/** @return array{user: User, book: UserBook, other: UserBook, quotes: array<string, Quote>} */
function searchReader(string $name): array
{
    return searchFixtures()[$name];
}

/** @return list<int> */
function searchQuoteIds(mixed $test, string $query): array
{
    return collect($test->getJson('/api/quotes?'.http_build_query($query === '' ? [] : ['q' => $query]))
        ->assertOk()->json('data'))->pluck('id')->all();
}

it('finds a Cyrillic word', function () {
    $r = searchReader('cyrillic');

    expect(searchQuoteIds($this->actingAs($r['user']), 'щастя'))->toBe([$r['quotes']['hit']->id]);
});

it('finds partial and short words', function () {
    $r = searchReader('partial');
    $test = $this->actingAs($r['user']);

    expect(searchQuoteIds($test, 'крас'))->toBe([$r['quotes']['hit']->id])
        ->and(searchQuoteIds($test, 'ча'))->toBe([$r['quotes']['hit']->id])
        ->and(searchQuoteIds($test, 'я с'))->toBeArray();
});

it('finds a word in the note', function () {
    $r = searchReader('note');

    expect(searchQuoteIds($this->actingAs($r['user']), 'розмову'))->toBe([$r['quotes']['hit']->id]);
});

it('requires every word and ranks better matches first, then newest', function () {
    $r = searchReader('ranking');
    ['weak' => $weak, 'both' => $both, 'newerBoth' => $newerBoth] = $r['quotes'];
    $test = $this->actingAs($r['user']);

    expect(searchQuoteIds($test, 'альфа бета'))->toBe([$newerBoth->id, $both->id])
        ->and(searchQuoteIds($test, 'альфа'))->toContain($weak->id);
});

it('combines with the book, favorite and type filters', function () {
    $r = searchReader('filters');
    ['inBook' => $inBook, 'favorite' => $favorite, 'insight' => $insight] = $r['quotes'];

    $ids = fn (string $filters) => collect($this->actingAs($r['user'])->getJson("/api/quotes?q=зорі&$filters")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids("book={$r['book']->id}"))->toBe([$inBook->id, $favorite->id, $insight->id])
        ->and($ids('favorite=1'))->toBe([$favorite->id])
        ->and($ids('type=insight'))->toBe([$insight->id]);
});

it('combines with the tag and book filters', function () {
    $r = searchReader('tags');
    ['tagged' => $tagged, 'untagged' => $untagged, 'otherBook' => $otherBook] = $r['quotes'];
    $ids = fn (string $filters) => collect($this->actingAs($r['user'])->getJson("/api/quotes?$filters")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids('q=мандрівка&tag='.urlencode(' Космос ')))->toBe([$tagged->id, $otherBook->id])
        ->and($ids("q=мандрівка&tag=космос&book={$r['book']->id}"))->toBe([$tagged->id])
        ->and($ids('q=мандрівка'))->toBe([$tagged->id, $untagged->id, $otherBook->id]);
});

it('paginates search results', function () {
    $page = $this->actingAs(searchReader('pagination')['user'])->getJson('/api/quotes?q=однакове')->assertOk();

    expect($page->json('data'))->toHaveCount(20)
        ->and($page->json('links.next'))->not->toBeNull();
});

it('does not break on boolean operators and punctuation', function (string $input) {
    $this->actingAs(searchReader('plain')['user'])->getJson('/api/quotes?'.http_build_query(['q' => $input]))->assertOk();
})->with(['+ -', '+текст -цитати', '<текст> (цитати)', '~текст*', '"текст', '@@', 'te"xt\\', '%_', "текст'; DROP TABLE quotes; --"]);

it('treats operators as plain text', function () {
    $r = searchReader('operators');

    expect(searchQuoteIds($this->actingAs($r['user']), '-текст* +цитати'))->toBe([$r['quotes']['hit']->id])
        ->and(searchQuoteIds($this->actingAs($r['user']), '+-'))->toBe([]);
});

it('only finds the reader\'s own quotes', function () {
    $r = searchReader('own');

    expect(searchQuoteIds($this->actingAs($r['user']), 'таємна'))->toBe([$r['quotes']['mine']->id]);
});

it('exports only the quotes that match', function () {
    $body = $this->actingAs(searchReader('export')['user'])->get('/api/quotes/export?q=зірки')->assertOk()->streamedContent();

    expect($body)->toContain('Зірки далеко.')->not->toContain('Земля близько.');
});

it('validates the search', function (string $query) {
    $user = searchReader('validation')['user'];

    $this->actingAs($user)->getJson("/api/quotes?$query")->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->actingAs($user)->getJson("/api/quotes/export?$query")->assertUnprocessable()->assertJsonValidationErrors('q');
})->with(['q=a', 'q=%20a%20', 'q[]=1', 'long' => 'q='.str_repeat('ы', 101)]);
