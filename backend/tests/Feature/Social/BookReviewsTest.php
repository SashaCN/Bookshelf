<?php

use App\Models\Book;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->withAuthors()->create();
});

function reviewOf(Book $book, int $rating, array $attributes = []): Post
{
    return Post::factory()->review($rating)->create(['book_id' => $book->id] + $attributes);
}

it('requires authentication', function () {
    $this->getJson("/api/books/{$this->book->id}/reviews")->assertUnauthorized();
});

it('lists the reviews of the book, newest first, with their number and average rating', function () {
    $first = reviewOf($this->book, 5);
    $second = reviewOf($this->book, 4);
    $third = reviewOf($this->book, 4);

    $response = $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")->assertOk();

    expect($response->json('data.*.id'))->toBe([$third->id, $second->id, $first->id])
        ->and($response->json('meta.reviews_count'))->toBe(3)
        ->and($response->json('meta.average_rating'))->toBe(4.3)
        ->and($response->json('data.0'))->toMatchArray([
            'type' => 'review',
            'rating' => 4,
            'author' => ['handle' => $third->user->handle, 'name' => $third->user->name],
        ]);
});

it('names the review of the reader, wherever it is in the list', function () {
    $mine = reviewOf($this->book, 2, ['user_id' => $this->user->id]);
    foreach (range(1, 25) as $unused) {
        reviewOf($this->book, 4);
    }

    // The oldest of 26 is on the second page.
    $first = $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")->assertOk();
    expect($first->json('data.*.id'))->not->toContain($mine->id)
        ->and($first->json('meta.my_review_id'))->toBe($mine->id);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews?page=2")
        ->assertJsonPath('meta.my_review_id', $mine->id);
});

it('has no review of the reader to name when there is none, or only the review of somebody else', function () {
    reviewOf($this->book, 5);
    reviewOf(Book::factory()->withAuthors()->create(), 1, ['user_id' => $this->user->id]);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.my_review_id', null);
});

it('names the review of the reader that moderation hid, which only they can see', function () {
    $mine = reviewOf($this->book, 1, ['user_id' => $this->user->id, 'hidden_at' => now()]);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")->assertJsonPath('meta.my_review_id', $mine->id);
    $this->actingAs(User::factory()->create())->getJson("/api/books/{$this->book->id}/reviews")->assertJsonPath('meta.my_review_id', null);
});

it('does not name a review once it is deleted', function () {
    $mine = reviewOf($this->book, 3, ['user_id' => $this->user->id]);
    $this->actingAs($this->user)->deleteJson("/api/posts/{$mine->id}")->assertNoContent();

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")->assertJsonPath('meta.my_review_id', null);
});

it('says there is no rating yet for a book nobody has reviewed', function () {
    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.reviews_count', 0)
        ->assertJsonPath('meta.average_rating', null);
});

it('averages exactly, and rounds to one decimal', function (array $ratings, int|float $average) {
    foreach ($ratings as $rating) {
        reviewOf($this->book, $rating);
    }

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")->assertJsonPath('meta.average_rating', $average);
})->with([
    'one review' => [[3], 3],
    'whole' => [[5, 3], 4],
    'rounded up' => [[5, 5, 4], 4.7],
    'rounded down' => [[1, 1, 2], 1.3],
    'half' => [[1, 2], 1.5],
]);

it('lists only reviews, not quote posts', function () {
    $review = reviewOf($this->book, 5);
    Post::factory()->count(3)->create(['book_id' => $this->book->id]);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $review->id)
        ->assertJsonPath('meta.reviews_count', 1);
});

it('lists only the reviews of this book', function () {
    reviewOf($this->book, 5);
    reviewOf(Book::factory()->withAuthors()->create(), 1);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.average_rating', 5);
});

it('leaves hidden reviews out of the list, the number and the average', function () {
    reviewOf($this->book, 5);
    reviewOf($this->book, 1, ['hidden_at' => now()]);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.reviews_count', 1)
        ->assertJsonPath('meta.average_rating', 5);
});

it('shows the author their own hidden review, flagged, and counts it for them only', function () {
    reviewOf($this->book, 5);
    $hidden = reviewOf($this->book, 1, ['hidden_at' => now(), 'user_id' => $this->user->id]);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $hidden->id)
        ->assertJsonPath('data.0.is_hidden', true)
        ->assertJsonPath('meta.reviews_count', 2)
        ->assertJsonPath('meta.average_rating', 3);
});

it('leaves out the reviews of readers who have a block with me, in either direction', function () {
    $fine = reviewOf($this->book, 4);
    $blockedByMe = reviewOf($this->book, 1);
    $blockingMe = reviewOf($this->book, 1);
    $this->user->blockedReaders()->attach($blockedByMe->user_id);
    $blockingMe->user->blockedReaders()->attach($this->user->id);

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $fine->id)
        ->assertJsonPath('meta.reviews_count', 1)
        ->assertJsonPath('meta.average_rating', 4);
});

it('pages by 20 and keeps the summary on every page', function () {
    foreach (range(1, 22) as $unused) {
        reviewOf($this->book, 3);
    }

    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.reviews_count', 22)
        ->assertJsonPath('links.next', fn ($next) => $next !== null);
    $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews?page=2")
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.reviews_count', 22)
        ->assertJsonPath('meta.average_rating', 3)
        ->assertJsonPath('links.next', null);
});

it('tells which reviews are mine and which I have liked', function () {
    $mine = reviewOf($this->book, 5, ['user_id' => $this->user->id]);
    $liked = reviewOf($this->book, 4);
    $liked->likedBy()->attach($this->user->id);

    $data = collect($this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")->json('data'))->keyBy('id');

    expect($data[$mine->id]['is_mine'])->toBeTrue()
        ->and($data[$liked->id]['liked_by_me'])->toBeTrue()
        ->and($data[$mine->id]['liked_by_me'])->toBeFalse();
});

it('never gives away the e-mail or the time zone of a reviewer', function () {
    $review = reviewOf($this->book, 5);

    $body = $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/reviews")->getContent();

    expect($body)->not->toContain($review->user->email)->not->toContain('timezone');
});

it('answers 404 for a book that does not exist', function () {
    $this->actingAs($this->user)->getJson('/api/books/999999/reviews')->assertNotFound();
});

describe('a book entered by hand', function () {
    it('is open to the reader who entered it', function () {
        $manual = Book::factory()->manual($this->user)->create();
        reviewOf($manual, 4, ['user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson("/api/books/{$manual->id}/reviews")->assertOk()->assertJsonCount(1, 'data');
    });

    it('is "not found" for everybody else', function () {
        $manual = Book::factory()->manual(User::factory()->create())->create();

        $this->actingAs($this->user)->getJson("/api/books/{$manual->id}/reviews")->assertNotFound();
    });
});
