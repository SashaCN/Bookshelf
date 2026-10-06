<?php

use App\Enums\PostType;
use App\Models\Book;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Quote;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->user = User::factory()->create(['handle' => 'olena', 'name' => 'Olena']);
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->finished()->create();
    $this->quote = Quote::factory()->for($this->userBook)->create(['content' => 'We are what we repeatedly do.', 'page' => 42]);
});

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['POST', '/api/posts'],
    ['GET', '/api/posts/1'],
    ['PATCH', '/api/posts/1'],
    ['DELETE', '/api/posts/1'],
]);

describe('publishing a quote', function () {
    it('publishes a snapshot of one of my quotes', function () {
        $this->actingAs($this->user)->postJson('/api/posts', [
            'type' => 'quote',
            'quote_id' => $this->quote->id,
            'comment' => 'My favourite line.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'quote')
            ->assertJsonPath('data.content', 'We are what we repeatedly do.')
            ->assertJsonPath('data.comment', 'My favourite line.')
            ->assertJsonPath('data.page', 42)
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.likes_count', 0)
            ->assertJsonPath('data.comments_count', 0)
            ->assertJsonPath('data.liked_by_me', false)
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.is_hidden', false)
            ->assertJsonPath('data.author', ['handle' => 'olena', 'name' => 'Olena'])
            ->assertJsonPath('data.book.id', $this->userBook->book_id)
            ->assertJsonPath('data.book.title', $this->userBook->book->title)
            ->assertJsonPath('data.book.authors.0', $this->userBook->book->authors->first()->name);

        $post = Post::query()->sole();
        expect($post->user_id)->toBe($this->user->id)
            ->and($post->book_id)->toBe($this->userBook->book_id)
            ->and($post->type)->toBe(PostType::Quote);
    });

    it('does not need a comment', function () {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $this->quote->id])
            ->assertCreated()
            ->assertJsonPath('data.comment', null);
    });

    it('treats an empty comment as no comment', function () {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $this->quote->id, 'comment' => ''])
            ->assertCreated()
            ->assertJsonPath('data.comment', null);
    });

    it('lets the same quote be published again, and any number of quotes about one book', function () {
        $other = Quote::factory()->for($this->userBook)->create();

        foreach ([$this->quote, $this->quote, $other] as $quote) {
            $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id])->assertCreated();
        }

        expect(Post::query()->count())->toBe(3);
    });

    it('does not carry the private note of the quote into the post', function () {
        $this->quote->update(['note' => 'private thought']);

        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $this->quote->id])
            ->assertCreated()
            ->assertJsonMissing(['private thought']);
    });

    it('refuses a quote that is not mine, as if it did not exist', function () {
        $theirs = Quote::factory()->for(UserBook::factory()->for($this->stranger))->create();

        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $theirs->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_id');

        expect(Post::query()->count())->toBe(0);
    });

    it('refuses a quote that does not exist', function () {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_id');
    });

    it('validates the body', function (array $body, string $field) {
        $this->actingAs($this->user)->postJson('/api/posts', $body + ['type' => 'quote', 'quote_id' => $this->quote->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'no type' => [['type' => null], 'type'],
        'unknown type' => [['type' => 'poem'], 'type'],
        'no quote' => [['quote_id' => null], 'quote_id'],
        'quote as text' => [['quote_id' => 'abc'], 'quote_id'],
        'comment too long' => [['comment' => str_repeat('a', 2001)], 'comment'],
    ]);

    it('ignores what only a review needs', function () {
        $this->actingAs($this->user)->postJson('/api/posts', [
            'type' => 'quote',
            'quote_id' => $this->quote->id,
            'rating' => 99,
            'user_book_id' => 'nonsense',
        ])->assertCreated()->assertJsonPath('data.rating', null);
    });
});

describe('a post is a snapshot', function () {
    beforeEach(function () {
        $this->postId = $this->actingAs($this->user)
            ->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $this->quote->id])
            ->json('data.id');
    });

    it('does not change when the quote is edited', function () {
        $this->actingAs($this->user)->patchJson("/api/quotes/{$this->quote->id}", ['content' => 'Another text', 'page' => 7])->assertOk();

        $this->actingAs($this->user)->getJson("/api/posts/{$this->postId}")
            ->assertOk()
            ->assertJsonPath('data.content', 'We are what we repeatedly do.')
            ->assertJsonPath('data.page', 42);
    });

    it('stays when the quote is deleted', function () {
        $this->actingAs($this->user)->deleteJson("/api/quotes/{$this->quote->id}")->assertNoContent();

        $this->actingAs($this->stranger)->getJson("/api/posts/{$this->postId}")
            ->assertOk()
            ->assertJsonPath('data.content', 'We are what we repeatedly do.');
    });

    it('stays when the book leaves the library', function () {
        $this->actingAs($this->user)->deleteJson("/api/library/{$this->userBook->id}")->assertNoContent();

        $this->actingAs($this->stranger)->getJson("/api/posts/{$this->postId}")
            ->assertOk()
            ->assertJsonPath('data.book.title', $this->userBook->book->title);
    });

    it('leaves the quote alone when the post is deleted', function () {
        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->postId}")->assertNoContent();

        expect(Quote::query()->find($this->quote->id))->not->toBeNull()
            ->and(Post::query()->count())->toBe(0);
    });
});

describe('writing a review', function () {
    it('publishes a rating and a text about a book from my library', function () {
        $this->actingAs($this->user)->postJson('/api/posts', [
            'type' => 'review',
            'user_book_id' => $this->userBook->id,
            'rating' => 4,
            'content' => 'Worth reading twice.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'review')
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.content', 'Worth reading twice.')
            ->assertJsonPath('data.comment', null)
            ->assertJsonPath('data.book.id', $this->userBook->book_id);

        expect(Post::query()->sole()->book_id)->toBe($this->userBook->book_id);
    });

    it('allows a rating alone', function () {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $this->userBook->id, 'rating' => 5])
            ->assertCreated()
            ->assertJsonPath('data.content', null);
    });

    it('allows a review of a book that has not been finished', function () {
        $want = UserBook::factory()->for($this->user)->create();

        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $want->id, 'rating' => 2])
            ->assertCreated();
    });

    it('takes one review per reader per book', function () {
        $body = ['type' => 'review', 'user_book_id' => $this->userBook->id, 'rating' => 4];
        $this->actingAs($this->user)->postJson('/api/posts', $body)->assertCreated();

        $this->actingAs($this->user)->postJson('/api/posts', ['rating' => 1] + $body)
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_book_id', ['social.review_exists']);

        expect(Post::query()->count())->toBe(1)->and(Post::query()->sole()->rating)->toBe(4);
    });

    it('lets another reader review the same book, and me another book', function () {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $this->userBook->id, 'rating' => 4])->assertCreated();

        $theirs = UserBook::factory()->for($this->stranger)->create(['book_id' => $this->userBook->book_id]);
        $this->actingAs($this->stranger)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $theirs->id, 'rating' => 2])->assertCreated();

        $another = UserBook::factory()->for($this->user)->create();
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $another->id, 'rating' => 3])->assertCreated();

        expect(Post::query()->count())->toBe(3);
    });

    it('still lets me publish quotes of a book I have reviewed, and review it after publishing quotes', function () {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $this->quote->id])->assertCreated();
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $this->userBook->id, 'rating' => 4])->assertCreated();
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $this->quote->id])->assertCreated();

        expect(Post::query()->count())->toBe(3);
    });

    it('answers "one review per book" when another request slips in between the check and the insert', function () {
        Post::creating(fn () => throw new UniqueConstraintViolationException('mysql', 'insert into `posts`', [], new Exception('Duplicate entry')));

        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $this->userBook->id, 'rating' => 4])
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_book_id', ['social.review_exists']);

        expect(Post::query()->count())->toBe(0);
    });

    it('lets me write the review again after deleting it', function () {
        $body = ['type' => 'review', 'user_book_id' => $this->userBook->id, 'rating' => 4];
        $id = $this->actingAs($this->user)->postJson('/api/posts', $body)->json('data.id');
        $this->actingAs($this->user)->deleteJson("/api/posts/{$id}")->assertNoContent();

        $this->actingAs($this->user)->postJson('/api/posts', $body)->assertCreated();
    });

    it('is held to one per book by the database too', function () {
        Post::factory()->review()->create(['user_id' => $this->user->id, 'book_id' => $this->userBook->book_id]);

        expect(fn () => Post::factory()->review()->create(['user_id' => $this->user->id, 'book_id' => $this->userBook->book_id]))
            ->toThrow(UniqueConstraintViolationException::class);

        // Quote posts are not restricted.
        Post::factory()->count(2)->create(['user_id' => $this->user->id, 'book_id' => $this->userBook->book_id]);
        expect(Post::query()->count())->toBe(3);
    });

    it('refuses a book that is not in my library, as if it did not exist', function () {
        $theirs = UserBook::factory()->for($this->stranger)->create();

        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'review', 'user_book_id' => $theirs->id, 'rating' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_book_id');

        expect(Post::query()->count())->toBe(0);
    });

    it('validates the body', function (array $body, string $field) {
        $this->actingAs($this->user)->postJson('/api/posts', $body + ['type' => 'review', 'user_book_id' => $this->userBook->id, 'rating' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'no rating' => [['rating' => null], 'rating'],
        'rating zero' => [['rating' => 0], 'rating'],
        'rating six' => [['rating' => 6], 'rating'],
        'rating negative' => [['rating' => -1], 'rating'],
        'rating as text' => [['rating' => 'good'], 'rating'],
        'rating with a fraction' => [['rating' => 3.5], 'rating'],
        'no book' => [['user_book_id' => null], 'user_book_id'],
        'unknown book' => [['user_book_id' => 999999], 'user_book_id'],
        'text too long' => [['content' => str_repeat('a', 5001)], 'content'],
    ]);

    it('accepts every rating from one to five and a text of 5000 characters', function () {
        foreach ([1, 2, 3, 4, 5] as $rating) {
            $book = UserBook::factory()->for($this->user)->create();
            $this->actingAs($this->user)->postJson('/api/posts', [
                'type' => 'review',
                'user_book_id' => $book->id,
                'rating' => $rating,
                'content' => str_repeat('a', 5000),
            ])->assertCreated()->assertJsonPath('data.rating', $rating);
        }
    });

    it('ignores what only a quote post needs', function () {
        $this->actingAs($this->user)->postJson('/api/posts', [
            'type' => 'review',
            'user_book_id' => $this->userBook->id,
            'rating' => 3,
            'quote_id' => 'nonsense',
            'comment' => 'a comment',
        ])->assertCreated()->assertJsonPath('data.comment', null);
    });
});

describe('what the database holds to', function () {
    it('refuses a review without a rating or with a rating out of range', function (?int $rating) {
        expect(fn () => Post::factory()->review()->create(['rating' => $rating]))->toThrow(QueryException::class);
    })->with(['none' => [null], 'zero' => [0], 'six' => [6]]);

    it('refuses a quote that has a rating, and a quote without text', function () {
        expect(fn () => Post::factory()->create(['rating' => 3]))->toThrow(QueryException::class);
        expect(fn () => Post::factory()->create(['content' => null]))->toThrow(QueryException::class);
    });

    it('accepts a review without a text', function () {
        $post = Post::factory()->review(4)->create(['content' => null]);

        expect($post->fresh()->content)->toBeNull();
    });

    it('lets the author delete a post that moderation hid', function () {
        $post = Post::factory()->hidden()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        expect(Post::query()->count())->toBe(0);
    });
});

describe('showing a post', function () {
    it('shows my post and other readers\' posts', function () {
        $post = Post::factory()->create(['user_id' => $this->stranger->id]);

        $this->actingAs($this->user)->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.is_mine', false)
            ->assertJsonPath('data.author.handle', $this->stranger->handle);
    });

    it('never gives away the e-mail or the time zone of the author', function () {
        $post = Post::factory()->create(['user_id' => $this->stranger->id]);

        $body = $this->actingAs($this->user)->getJson("/api/posts/{$post->id}")->assertOk()->getContent();

        expect($body)->not->toContain($this->stranger->email)->not->toContain('timezone')->not->toContain('email');
    });

    it('answers 404 for a post that does not exist', function () {
        $this->actingAs($this->user)->getJson('/api/posts/999999')->assertNotFound();
    });

    it('hides a post hidden by moderation from everybody but its author', function () {
        $post = Post::factory()->hidden()->create(['user_id' => $this->stranger->id]);

        $this->actingAs($this->user)->getJson("/api/posts/{$post->id}")->assertNotFound();
        $this->actingAs($this->stranger)->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.is_hidden', true);
    });

    it('answers 404 for a post of a reader who has a block with me, in either direction', function () {
        $post = Post::factory()->create(['user_id' => $this->stranger->id]);

        $this->stranger->blockedReaders()->attach($this->user->id);
        $this->actingAs($this->user)->getJson("/api/posts/{$post->id}")->assertNotFound();

        $this->stranger->blockedReaders()->detach();
        $this->user->blockedReaders()->attach($this->stranger->id);
        $this->actingAs($this->user)->getJson("/api/posts/{$post->id}")->assertNotFound();
    });
});

describe('editing a post', function () {
    it('changes the comment of a quote post and leaves the quoted text alone', function () {
        $post = Post::factory()->create(['user_id' => $this->user->id, 'content' => 'Original', 'comment' => 'Old']);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['comment' => 'New', 'content' => 'Forged', 'rating' => 5])
            ->assertOk()
            ->assertJsonPath('data.comment', 'New')
            ->assertJsonPath('data.content', 'Original')
            ->assertJsonPath('data.rating', null);

        expect($post->fresh())->comment->toBe('New')->content->toBe('Original')->rating->toBeNull();
    });

    it('clears the comment of a quote post', function () {
        $post = Post::factory()->create(['user_id' => $this->user->id, 'comment' => 'Old']);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['comment' => null])
            ->assertOk()
            ->assertJsonPath('data.comment', null);
    });

    it('changes the rating and the text of a review', function () {
        $post = Post::factory()->review(3)->create(['user_id' => $this->user->id, 'content' => 'Meh']);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['rating' => 5, 'content' => 'Great after all', 'comment' => 'ignored'])
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.content', 'Great after all')
            ->assertJsonPath('data.comment', null);
    });

    it('keeps what is not sent, and can clear the text of a review', function () {
        $post = Post::factory()->review(3)->create(['user_id' => $this->user->id, 'content' => 'Meh']);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['rating' => 4])
            ->assertOk()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.content', 'Meh');

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['content' => ''])
            ->assertOk()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.content', null);
    });

    it('validates the body of my own post', function (array $body, string $field) {
        $post = Post::factory()->review()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'rating zero' => [['rating' => 0], 'rating'],
        'rating six' => [['rating' => 6], 'rating'],
        'no rating' => [['rating' => null], 'rating'],
        'text too long' => [['content' => str_repeat('a', 5001)], 'content'],
    ]);

    it('validates the comment of my own quote post', function () {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['comment' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    });

    it('answers "not found" to anybody but the author, whatever is sent', function () {
        $post = Post::factory()->review(3)->create(['user_id' => $this->stranger->id, 'content' => 'Mine']);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['rating' => 1])->assertNotFound();
        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['rating' => 99, 'content' => str_repeat('a', 9000)])->assertNotFound();
        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", [])->assertNotFound();

        expect($post->fresh())->rating->toBe(3)->content->toBe('Mine');
    });

    it('answers 404 for a post that does not exist', function () {
        $this->actingAs($this->user)->patchJson('/api/posts/999999', ['comment' => 'x'])->assertNotFound();
    });

    it('lets the author edit a post that moderation hid, which stays hidden', function () {
        $post = Post::factory()->hidden()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['comment' => 'Reworded'])
            ->assertOk()
            ->assertJsonPath('data.is_hidden', true);
    });
});

describe('deleting a post', function () {
    it('deletes my post, with its comments, likes and reports', function () {
        $post = Post::factory()->create(['user_id' => $this->user->id]);
        $comment = Comment::factory()->for($post)->create();
        $post->likedBy()->attach($this->stranger->id);
        Report::factory()->about($post)->create();
        Report::factory()->about($comment)->create();
        $unrelated = Report::factory()->create();

        $this->actingAs($this->user)->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        expect(Post::query()->find($post->id))->toBeNull()
            ->and(Comment::query()->count())->toBe(0)
            ->and(DB::table('likes')->count())->toBe(0)
            ->and(Report::query()->pluck('id')->all())->toBe([$unrelated->id]);
    });

    it('answers "not found" to anybody but the author, and keeps the post', function () {
        $post = Post::factory()->create(['user_id' => $this->stranger->id]);

        $this->actingAs($this->user)->deleteJson("/api/posts/{$post->id}")->assertNotFound();

        expect(Post::query()->find($post->id))->not->toBeNull();
    });

    it('answers 404 for a post that is already gone', function () {
        $post = Post::factory()->create(['user_id' => $this->user->id]);
        $this->actingAs($this->user)->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        $this->actingAs($this->user)->deleteJson("/api/posts/{$post->id}")->assertNotFound();
    });

    it('disappears together with a hand-entered book nobody else has', function () {
        $manual = Book::factory()->manual($this->user)->create();
        $entry = UserBook::factory()->for($this->user)->create(['book_id' => $manual->id]);
        $post = Post::factory()->review()->create(['user_id' => $this->user->id, 'book_id' => $manual->id]);

        $this->actingAs($this->user)->deleteJson("/api/library/{$entry->id}")->assertNoContent();

        expect(Post::query()->find($post->id))->toBeNull();
    });
});
