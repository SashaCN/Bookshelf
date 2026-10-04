<?php

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

it('requires authentication', function () {
    $this->getJson('/api/catalog/search?q=atomic')->assertUnauthorized();
});

it('returns normalized results', function () {
    fakeOpenLibrary([openLibraryDoc()]);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/catalog/search?q=atomic habits')
        ->assertOk()
        ->assertJsonPath('data.0.work_key', '/works/OL17930368W')
        ->assertJsonPath('data.0.title', 'Atomic Habits')
        ->assertJsonPath('data.0.authors', ['James Clear'])
        ->assertJsonPath('data.0.page_count', 323)
        ->assertJsonPath('data.0.in_library', false);
});

it('flags only the books that are in the current reader\'s library', function () {
    fakeOpenLibrary([
        openLibraryDoc(),
        openLibraryDoc(['key' => '/works/OL2W', 'title' => 'Other Book']),
    ]);
    $me = User::factory()->create();
    $someoneElse = User::factory()->create();

    $mine = Book::factory()->create(['openlibrary_work_key' => '/works/OL17930368W']);
    UserBook::factory()->create(['user_id' => $me->id, 'book_id' => $mine->id]);
    $theirs = Book::factory()->create(['openlibrary_work_key' => '/works/OL2W']);
    UserBook::factory()->create(['user_id' => $someoneElse->id, 'book_id' => $theirs->id]);

    $this->actingAs($me)->getJson('/api/catalog/search?q=books')
        ->assertOk()
        ->assertJsonPath('data.0.in_library', true)
        ->assertJsonPath('data.1.in_library', false);
});

it('validates the query', function (array $query) {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/catalog/search?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');

    Http::assertNothingSent();
})->with([
    'missing' => [[]],
    'too short' => [['q' => 'a']],
    'too long' => [['q' => str_repeat('x', 101)]],
]);

it('answers 503 so the client can fall back to manual entry', function () {
    Http::fake(['openlibrary.org/*' => Http::response('', 500)]);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/catalog/search?q=atomic')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'catalog.unavailable']);
});

it('throttles a reader who searches too often', function () {
    fakeOpenLibrary([openLibraryDoc()]);
    $this->actingAs(User::factory()->create());

    foreach (range(1, 30) as $i) {
        $this->getJson('/api/catalog/search?q=atomic')->assertOk();
    }

    $this->getJson('/api/catalog/search?q=atomic')->assertTooManyRequests();
});
