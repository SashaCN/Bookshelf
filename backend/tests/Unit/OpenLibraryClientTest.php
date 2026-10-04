<?php

use App\Exceptions\CatalogUnavailableException;
use App\Services\OpenLibrary\OpenLibraryClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

it('normalizes a catalog document', function () {
    fakeOpenLibrary([openLibraryDoc()]);

    $entry = app(OpenLibraryClient::class)->search('atomic habits')[0];

    expect($entry->toArray())->toBe([
        'work_key' => '/works/OL17930368W',
        'title' => 'Atomic Habits',
        'subtitle' => null,
        'authors' => ['James Clear'],
        'cover_url' => 'https://covers.openlibrary.org/b/id/12539702-M.jpg',
        'page_count' => 323,
        'published_year' => 2016,
        'subjects' => ['Habit', 'Habit breaking', 'Behavior modification'],
        'isbn_13' => '9786075696140',
    ]);
});

it('copes with a document that has only a key and a title', function () {
    fakeOpenLibrary([['key' => '/works/OL1W', 'title' => 'Bare Book']]);

    $entry = app(OpenLibraryClient::class)->search('bare')[0];

    expect($entry->authors)->toBe([])
        ->and($entry->coverUrl)->toBeNull()
        ->and($entry->pageCount)->toBeNull()
        ->and($entry->publishedYear)->toBeNull()
        ->and($entry->subjects)->toBe([])
        ->and($entry->isbn13)->toBeNull();
});

it('skips documents that cannot be added to a library', function () {
    fakeOpenLibrary([
        ['title' => 'No key'],
        ['key' => '/works/OL2W'],
        ['key' => '/authors/OL3A', 'title' => 'Not a work'],
        openLibraryDoc(),
    ]);

    $entries = app(OpenLibraryClient::class)->search('anything');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->title)->toBe('Atomic Habits');
});

it('caps the number of subjects', function () {
    fakeOpenLibrary([openLibraryDoc(['subject' => array_map(fn ($i) => "Subject {$i}", range(1, 40))])]);

    expect(app(OpenLibraryClient::class)->search('subjects')[0]->subjects)->toHaveCount(12);
});

it('identifies itself and sends the search parameters', function () {
    fakeOpenLibrary([openLibraryDoc()]);

    app(OpenLibraryClient::class)->search('  Atomic   HABITS ');

    Http::assertSent(function (Request $request) {
        return $request->hasHeader('User-Agent', config('services.openlibrary.user_agent'))
            && $request['q'] === 'atomic habits'
            && $request['limit'] === 20
            && str_contains($request['fields'], 'number_of_pages_median');
    });
});

it('caches answers so a repeated search does not hit the network again', function () {
    fakeOpenLibrary([openLibraryDoc()]);
    $client = app(OpenLibraryClient::class);

    $client->search('atomic habits');
    $client->search('Atomic  Habits');

    Http::assertSentCount(1);
});

it('finds a single work by its key', function () {
    fakeOpenLibrary([openLibraryDoc()]);

    $client = app(OpenLibraryClient::class);

    expect($client->findByWorkKey('/works/OL17930368W')?->title)->toBe('Atomic Habits')
        ->and($client->findByWorkKey('/works/OL999W'))->toBeNull();

    Http::assertSent(fn (Request $request) => $request['q'] === 'key:/works/OL17930368W');
});

it('reports an unavailable catalog and does not cache the failure', function () {
    Http::fakeSequence('openlibrary.org/search.json*')
        ->push('', 500)->push('', 500)->push('', 500)
        ->push(['docs' => [openLibraryDoc()]]);
    $client = app(OpenLibraryClient::class);

    expect(fn () => $client->search('atomic habits'))->toThrow(CatalogUnavailableException::class);

    // The next attempt must reach the network again and succeed.
    expect($client->search('atomic habits'))->toHaveCount(1);
});

it('reports an unavailable catalog for an unexpected response shape', function () {
    Http::fake(['openlibrary.org/search.json*' => Http::response(['unexpected' => true])]);

    expect(fn () => app(OpenLibraryClient::class)->search('atomic habits'))
        ->toThrow(CatalogUnavailableException::class);
});
