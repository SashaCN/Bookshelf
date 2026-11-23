<?php

use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

/*
 * InnoDB FULLTEXT indexes only see committed rows, and RefreshDatabase keeps every test inside one transaction, so a
 * plain create() would never be found. Each test therefore commits its fixtures with commitSearchFixtures() and afterEach
 * deletes them (users cascade to entries, quotes, tokens; books are removed by hand).
 */

function commitSearchFixtures(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->create();
});

afterEach(function () {
    DB::table('users')->delete();
    DB::table('books')->delete();
});

function searchQuote(UserBook $entry, string $content, ?string $note = null, array $attributes = []): Quote
{
    return Quote::factory()->for($entry)->create(['content' => $content, 'note' => $note, ...$attributes]);
}

/** @return list<int> */
function searchQuoteIds(mixed $test, string $query): array
{
    return collect($test->getJson('/api/quotes?'.http_build_query($query === '' ? [] : ['q' => $query]))
        ->assertOk()->json('data'))->pluck('id')->all();
}

it('finds a Cyrillic word', function () {
    $hit = searchQuote($this->userBook, 'Людина народжена для щастя, як птах для польоту.');
    searchQuote($this->userBook, 'Совість — найкращий порадник.');
    commitSearchFixtures();

    expect(searchQuoteIds($this->actingAs($this->user), 'щастя'))->toBe([$hit->id]);
});

it('finds partial and short words', function () {
    $hit = searchQuote($this->userBook, 'Прокрастинація краде час у тих, хто його має.');
    searchQuote($this->userBook, 'Зовсім інша думка.');
    commitSearchFixtures();

    $test = $this->actingAs($this->user);

    expect(searchQuoteIds($test, 'крас'))->toBe([$hit->id])
        ->and(searchQuoteIds($test, 'ча'))->toBe([$hit->id])
        ->and(searchQuoteIds($test, 'я с'))->toBeArray();
});

it('finds a word in the note', function () {
    $hit = searchQuote($this->userBook, 'Nothing special here.', 'Нагадує розмову з дідом про осінь.');
    searchQuote($this->userBook, 'Nothing special here either.');
    commitSearchFixtures();

    expect(searchQuoteIds($this->actingAs($this->user), 'розмову'))->toBe([$hit->id]);
});

it('requires every word and ranks better matches first, then newest', function () {
    $weak = searchQuote($this->userBook, 'Альфа і ще щось.');
    $both = searchQuote($this->userBook, 'Альфа та бета разом.');
    $newerBoth = searchQuote($this->userBook, 'Бета та альфа знову.');
    commitSearchFixtures();

    $test = $this->actingAs($this->user);

    expect(searchQuoteIds($test, 'альфа бета'))->toBe([$newerBoth->id, $both->id])
        ->and(searchQuoteIds($test, 'альфа'))->toContain($weak->id);
});

it('combines with the book, favorite and type filters', function () {
    $other = UserBook::factory()->for($this->user)->create();
    $inBook = searchQuote($this->userBook, 'Спільне слово зорі.');
    searchQuote($other, 'Спільне слово зорі.');
    $favorite = searchQuote($this->userBook, 'Ще зорі.', null, ['is_favorite' => true]);
    $insight = searchQuote($this->userBook, 'Інсайт про зорі.', null, ['type' => 'insight']);
    commitSearchFixtures();

    $ids = fn (string $filters) => collect($this->actingAs($this->user)->getJson("/api/quotes?q=зорі&$filters")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids("book={$this->userBook->id}"))->toBe([$inBook->id, $favorite->id, $insight->id])
        ->and($ids('favorite=1'))->toBe([$favorite->id])
        ->and($ids('type=insight'))->toBe([$insight->id]);
});

it('paginates search results', function () {
    foreach (range(1, 21) as $i) {
        searchQuote($this->userBook, "Однакове слово номер $i");
    }
    commitSearchFixtures();

    $page = $this->actingAs($this->user)->getJson('/api/quotes?q=однакове')->assertOk();

    expect($page->json('data'))->toHaveCount(20)
        ->and($page->json('links.next'))->not->toBeNull();
});

it('does not break on boolean operators and punctuation', function (string $input) {
    searchQuote($this->userBook, 'Звичайний текст цитати.');
    commitSearchFixtures();

    $this->actingAs($this->user)->getJson('/api/quotes?'.http_build_query(['q' => $input]))->assertOk();
})->with(['+ -', '+текст -цитати', '<текст> (цитати)', '~текст*', '"текст', '@@', 'te"xt\\', '%_', "текст'; DROP TABLE quotes; --"]);

it('treats operators as plain text', function () {
    $hit = searchQuote($this->userBook, 'Звичайний текст цитати.');
    commitSearchFixtures();

    expect(searchQuoteIds($this->actingAs($this->user), '-текст* +цитати'))->toBe([$hit->id])
        ->and(searchQuoteIds($this->actingAs($this->user), '+-'))->toBe([]);
});

it('only finds the reader\'s own quotes', function () {
    $stranger = User::factory()->create();
    $strangerBook = UserBook::factory()->for($stranger)->create();
    $mine = searchQuote($this->userBook, 'Моя таємна цитата.');
    searchQuote($strangerBook, 'Чужа таємна цитата.', null, ['visibility' => 'public']);
    commitSearchFixtures();

    expect(searchQuoteIds($this->actingAs($this->user), 'таємна'))->toBe([$mine->id]);
});

it('exports only the quotes that match', function () {
    searchQuote($this->userBook, 'Зірки далеко.');
    searchQuote($this->userBook, 'Земля близько.');
    commitSearchFixtures();

    $body = $this->actingAs($this->user)->get('/api/quotes/export?q=зірки')->assertOk()->streamedContent();

    expect($body)->toContain('Зірки далеко.')->not->toContain('Земля близько.');
});

it('validates the search', function (string $query) {
    $this->actingAs($this->user)->getJson("/api/quotes?$query")->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->actingAs($this->user)->getJson("/api/quotes/export?$query")->assertUnprocessable()->assertJsonValidationErrors('q');
})->with(['q=a', 'q=%20a%20', 'q[]=1', 'long' => 'q='.str_repeat('ы', 101)]);
