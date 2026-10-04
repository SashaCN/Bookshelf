<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * One document of an Open Library search response, with sensible defaults.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function openLibraryDoc(array $overrides = []): array
{
    return array_merge([
        'key' => '/works/OL17930368W',
        'title' => 'Atomic Habits',
        'author_name' => ['James Clear'],
        'author_key' => ['OL7422948A'],
        'first_publish_year' => 2016,
        'cover_i' => 12539702,
        'number_of_pages_median' => 323,
        'subject' => ['Habit', 'Habit breaking', 'Behavior modification'],
        'isbn' => ['1698414684', '9786075696140'],
    ], $overrides);
}

/**
 * Fake every Open Library search request with the given documents.
 *
 * @param  list<array<string, mixed>>  $docs
 */
function fakeOpenLibrary(array $docs): void
{
    Http::fake(['openlibrary.org/search.json*' => Http::response([
        'numFound' => count($docs),
        'docs' => $docs,
    ])]);
}
