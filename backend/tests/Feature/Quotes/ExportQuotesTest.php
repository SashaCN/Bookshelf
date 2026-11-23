<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;

function exportedBook(User $user, string $title, array $authors = []): UserBook
{
    $book = Book::factory()->create(['title' => $title]);

    foreach ($authors as $position => $name) {
        $book->authors()->attach(Author::factory()->create(['name' => $name])->id, ['position' => $position]);
    }

    return UserBook::factory()->for($user)->for($book)->create();
}

function exportMarkdown($test, string $query = ''): string
{
    return $test->getJson("/api/quotes/export{$query}")->assertOk()->streamedContent();
}

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

it('requires authentication', function () {
    $this->actAsGuest();

    $this->getJson('/api/quotes/export')->assertUnauthorized();
});

it('says there is nothing to export when there are no quotes', function () {
    expect(exportMarkdown($this))->toBe("# Мої цитати\n\nЦитат поки немає.\n");
});

it('groups quotes by book ordered by title, oldest quote first', function () {
    $zebra = exportedBook($this->user, 'Zebra', ['Ann Author', 'Bob Writer']);
    $apple = exportedBook($this->user, 'Apple');
    Quote::factory()->for($this->user)->for($zebra)->create(['content' => 'Z first']);
    Quote::factory()->for($this->user)->for($apple)->create(['content' => 'A first', 'page' => 42, 'note' => 'Mine', 'is_favorite' => true]);
    Quote::factory()->for($this->user)->for($zebra)->create(['content' => 'Z second']);

    expect(exportMarkdown($this))->toBe(<<<'MD'
        # Мої цитати

        ## Apple

        > A first

        — с. 42  
        Нотатка: Mine  
        ★

        ## Zebra

        _Ann Author, Bob Writer_

        > Z first

        > Z second

        MD);
});

it('applies the favorite, book and type filters', function () {
    $first = exportedBook($this->user, 'First');
    $second = exportedBook($this->user, 'Second');
    Quote::factory()->for($this->user)->for($first)->create(['content' => 'plain one']);
    Quote::factory()->for($this->user)->for($first)->create(['content' => 'loved one', 'is_favorite' => true]);
    Quote::factory()->for($this->user)->for($second)->create(['content' => 'other book', 'type' => 'insight']);

    $favorites = exportMarkdown($this, '?favorite=1');
    expect($favorites)->toContain('loved one')->not->toContain('plain one')->not->toContain('Second');

    $book = exportMarkdown($this, "?book={$second->id}");
    expect($book)->toContain('other book')->not->toContain('First');

    $type = exportMarkdown($this, '?type=insight');
    expect($type)->toContain('other book')->not->toContain('plain one');

    $this->getJson('/api/quotes/export?type=nonsense')->assertUnprocessable();
});

it('prefixes every line of a multi-line quote, blank ones included', function () {
    Quote::factory()->for($this->user)->for(exportedBook($this->user, 'Poems'))->create(['content' => "Line one\n\nLine three\r\nLine four"]);

    expect(exportMarkdown($this))->toContain("> Line one\n>\n> Line three\n> Line four\n");
});

it('renders insights as plain paragraphs with a marker', function () {
    Quote::factory()->for($this->user)->for(exportedBook($this->user, 'Thoughts'))->create(['content' => 'My own idea', 'type' => 'insight', 'page' => 7]);

    $markdown = exportMarkdown($this);

    expect($markdown)->toContain("\n💡 My own idea\n\n— с. 7\n")->not->toContain('> My own idea');
});

it('escapes text that would break the structure', function (string $content, string $expected) {
    Quote::factory()->for($this->user)->for(exportedBook($this->user, 'Escapes'))->create(['content' => $content]);

    expect(exportMarkdown($this))->toContain($expected);
})->with([
    'heading' => ['# Heading', '> \\# Heading'],
    'nested quote' => ['> quoted', '> \\> quoted'],
    'dash list' => ['- item', '> \\- item'],
    'star list' => ['* item', '> \\* item'],
    'plus list' => ['+ item', '> \\+ item'],
    'numbered list' => ['1. item', '> 1\\. item'],
    'backticks' => ['use `code` here', '> use \\`code\\` here'],
    'later line' => ["fine\n# not fine", "> fine\n> \\# not fine"],
]);

it('escapes the book title and the note too', function () {
    $book = exportedBook($this->user, '# Sneaky');
    Quote::factory()->for($this->user)->for($book)->create(['content' => 'Text', 'note' => "ok\n- item"]);

    expect(exportMarkdown($this))->toContain("## \\# Sneaky\n")->toContain("Нотатка: ok  \n\\- item");
});

it('sends a markdown attachment named after the reader-local date', function () {
    // 22:30 UTC on the 14th is already the 15th in Kyiv.
    Carbon::setTestNow('2026-03-14 22:30:00');

    $response = $this->getJson('/api/quotes/export')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/markdown; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('filename=bookshelf-quotes-2026-03-15.md');
});

it('exports only the reader\'s own quotes', function () {
    $stranger = User::factory()->create();
    Quote::factory()->for($stranger)->for(exportedBook($stranger, 'Secret book'))->create(['content' => 'Not yours']);
    Quote::factory()->for($this->user)->for(exportedBook($this->user, 'Mine'))->create(['content' => 'Yours']);

    expect(exportMarkdown($this))->toContain('Yours')->not->toContain('Not yours')->not->toContain('Secret book');
});
