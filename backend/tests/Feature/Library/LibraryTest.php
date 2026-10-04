<?php

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
});

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/library'],
    ['GET', '/api/library/1'],
    ['PATCH', '/api/library/1'],
    ['DELETE', '/api/library/1'],
]);

describe('listing', function () {
    it('returns only my books, newest activity first', function () {
        $older = UserBook::factory()->for($this->user)->create(['updated_at' => now()->subDays(2)]);
        $newer = UserBook::factory()->for($this->user)->create(['updated_at' => now()->subDay()]);
        UserBook::factory()->for($this->stranger)->create();

        $this->actingAs($this->user)->getJson('/api/library')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    });

    it('filters by status', function () {
        UserBook::factory()->for($this->user)->count(2)->create();
        $reading = UserBook::factory()->for($this->user)->reading()->create();

        $this->actingAs($this->user)->getJson('/api/library?status=reading')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reading->id);
    });

    it('rejects an unknown status filter', function () {
        $this->actingAs($this->user)->getJson('/api/library?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('is empty for a reader without books', function () {
        $this->actingAs($this->user)->getJson('/api/library')->assertOk()->assertExactJson(['data' => []]);
    });

    it('loads books and authors without a query per entry', function () {
        UserBook::factory()->for($this->user)->count(5)->create();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($this->user)->getJson('/api/library')->assertOk();

        // session/auth lookups + user_books + books + author_book/authors, independent of the number of entries
        expect($queries)->toBeLessThan(8);
    });
});

describe('showing', function () {
    it('returns the entry with its book', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(160)->create(['total_pages' => 320]);

        $this->actingAs($this->user)->getJson("/api/library/{$userBook->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $userBook->id)
            ->assertJsonPath('data.status', 'reading')
            ->assertJsonPath('data.current_page', 160)
            ->assertJsonPath('data.progress_percent', 50)
            ->assertJsonPath('data.book.id', $userBook->book_id);
    });

    it('answers 404 for somebody else\'s entry', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();

        $this->actingAs($this->user)->getJson("/api/library/{$theirs->id}")->assertNotFound();
    });

    it('answers 404 for an entry that does not exist', function () {
        $this->actingAs($this->user)->getJson('/api/library/999999')->assertNotFound();
    });
});

describe('updating', function () {
    it('cannot touch somebody else\'s entry', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create(['rating' => null]);

        $this->actingAs($this->user)->patchJson("/api/library/{$theirs->id}", ['rating' => 5, 'status' => 'finished'])
            ->assertNotFound();

        expect($theirs->fresh())->rating->toBeNull()->status->value->toBe('want');
    });

    it('sets and clears a rating', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create();
        $this->actingAs($this->user);

        $this->patchJson("/api/library/{$userBook->id}", ['rating' => 4])->assertOk()->assertJsonPath('data.rating', 4);
        $this->patchJson("/api/library/{$userBook->id}", ['rating' => null])->assertOk()->assertJsonPath('data.rating', null);
    });

    it('validates the rating range', function (mixed $rating) {
        $userBook = UserBook::factory()->for($this->user)->create();

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['rating' => $rating])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');
    })->with([0, 6, -1, 'five', 2.5]);

    it('ignores fields that are not editable', function () {
        $userBook = UserBook::factory()->for($this->user)->create(['current_page' => 0]);

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", [
            'current_page' => 200,
            'user_id' => $this->stranger->id,
            'book_id' => 999,
        ])->assertOk();

        expect($userBook->fresh())
            ->current_page->toBe(0)
            ->user_id->toBe($this->user->id);
    });

    it('changes the page count of my edition', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['total_pages' => 352])
            ->assertOk()
            ->assertJsonPath('data.total_pages', 352)
            ->assertJsonPath('data.progress_percent', 28);

        expect(Book::find($userBook->book_id)->page_count)->toBe(320);
    });

    it('does not let the page count drop below the current page', function () {
        $userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['total_pages' => 99])
            ->assertUnprocessable()
            ->assertJsonPath('errors.total_pages', ['library.total_pages_below_current']);

        expect($userBook->fresh()->total_pages)->toBe(320);
    });

    it('keeps a finished book at its last page when the page count changes', function () {
        $userBook = UserBook::factory()->for($this->user)->finished()->create(['total_pages' => 300, 'current_page' => 300]);

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['total_pages' => 280])
            ->assertOk()
            ->assertJsonPath('data.current_page', 280)
            ->assertJsonPath('data.progress_percent', 100);
    });

    it('validates the page count', function (mixed $pages) {
        $userBook = UserBook::factory()->for($this->user)->create();

        $this->actingAs($this->user)->patchJson("/api/library/{$userBook->id}", ['total_pages' => $pages])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('total_pages');
    })->with([0, -5, 20001, 'many', null]);
});

describe('deleting', function () {
    it('removes the entry and keeps a shared catalog book', function () {
        $userBook = UserBook::factory()->for($this->user)->create();

        $this->actingAs($this->user)->deleteJson("/api/library/{$userBook->id}")->assertNoContent();

        expect(UserBook::count())->toBe(0)->and(Book::count())->toBe(1);
    });

    it('removes a hand-entered book nobody else owns', function () {
        $book = Book::factory()->manual($this->user)->create();
        $userBook = UserBook::factory()->for($this->user)->create(['book_id' => $book->id]);

        $this->actingAs($this->user)->deleteJson("/api/library/{$userBook->id}")->assertNoContent();

        expect(Book::count())->toBe(0);
    });

    it('cannot delete somebody else\'s entry', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();

        $this->actingAs($this->user)->deleteJson("/api/library/{$theirs->id}")->assertNotFound();

        expect(UserBook::count())->toBe(1);
    });

    it('does not affect another reader\'s entry for the same book', function () {
        $book = Book::factory()->create();
        $mine = UserBook::factory()->for($this->user)->create(['book_id' => $book->id]);
        UserBook::factory()->for($this->stranger)->create(['book_id' => $book->id]);

        $this->actingAs($this->user)->deleteJson("/api/library/{$mine->id}")->assertNoContent();

        expect(UserBook::where('user_id', $this->stranger->id)->count())->toBe(1);
    });
});
