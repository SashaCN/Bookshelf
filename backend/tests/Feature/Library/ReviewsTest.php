<?php

use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->alice = User::factory()->public()->create(['username' => 'alice']);
    $this->hidden = User::factory()->create(['username' => 'hidden', 'is_public' => false]);
    $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));
});

function reviewedBook(User $user, array $attributes = [], string $state = 'finished'): UserBook
{
    return UserBook::factory()->for($user)->{$state}()->create($attributes + ['rating' => 4, 'review' => 'Great.', 'review_visibility' => 'public', 'reviewed_at' => now()]);
}

describe('writing a review', function () {
    it('saves the text, stamps the time and keeps it private by default', function () {
        $userBook = UserBook::factory()->for($this->me)->finished()->create();

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => '  Loved it.  '])
            ->assertOk()
            ->assertJsonPath('data.review', 'Loved it.')
            ->assertJsonPath('data.review_visibility', 'private')
            ->assertJsonPath('data.reviewed_at', now()->toIso8601String());
    });

    it('also allows a review of an abandoned book', function () {
        $userBook = UserBook::factory()->for($this->me)->abandoned()->create();

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => 'Gave up.'])->assertOk();
    });

    it('changes the visibility without touching the time', function () {
        $userBook = reviewedBook($this->me, ['review_visibility' => 'private', 'reviewed_at' => now()->subDay()]);

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review_visibility' => 'public'])
            ->assertOk()->assertJsonPath('data.review_visibility', 'public');

        expect($userBook->fresh()->reviewed_at->toDateTimeString())->toBe('2026-10-04 12:00:00');
    });

    it('moves the time only when the text changes', function () {
        $userBook = reviewedBook($this->me, ['reviewed_at' => now()->subDay()]);

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => 'Great.', 'rating' => 5])->assertOk();
        expect($userBook->fresh()->reviewed_at->toDateTimeString())->toBe('2026-10-04 12:00:00');

        $this->patchJson("/api/library/{$userBook->id}", ['review' => 'Even better.'])->assertOk();
        expect($userBook->fresh()->reviewed_at->toDateTimeString())->toBe('2026-10-05 12:00:00');
    });

    it('clears the review with null or an empty string', function (?string $value) {
        $userBook = reviewedBook($this->me);

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => $value])
            ->assertOk()->assertJsonPath('data.review', null)->assertJsonPath('data.reviewed_at', null);
    })->with([[null], ['']]);

    it('rejects a review of a book that is not read yet', function (string $state) {
        $userBook = UserBook::factory()->for($this->me)->{$state}()->create();

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => 'Too early'])
            ->assertUnprocessable()->assertJsonPath('errors.review.0', 'library.review_not_finished');
        expect($userBook->fresh()->review)->toBeNull();
    })->with(['unstarted', 'reading']);

    it('accepts a review sent together with finishing the book', function () {
        $userBook = UserBook::factory()->for($this->me)->reading(50)->create();

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['status' => 'finished', 'review' => 'Done.'])
            ->assertOk()->assertJsonPath('data.review', 'Done.');
    });

    it('still lets a reader clear the review after the book went back to reading', function () {
        $userBook = reviewedBook($this->me, [], 'reading');

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => null])->assertOk();
    });

    it('lets a reader edit the review of a book that is being read again', function () {
        $userBook = reviewedBook($this->me, [], 'reading');

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => 'Changed my mind.', 'review_visibility' => 'private'])
            ->assertOk()->assertJsonPath('data.review', 'Changed my mind.');
    });

    it('limits the review to 5000 characters', function () {
        $userBook = UserBook::factory()->for($this->me)->finished()->create();

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => str_repeat('a', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->patchJson("/api/library/{$userBook->id}", ['review' => str_repeat('я', 5000)])->assertOk();
    });

    it('rejects an unknown visibility', function () {
        $userBook = reviewedBook($this->me);

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review_visibility' => 'friends'])
            ->assertUnprocessable()->assertJsonValidationErrors('review_visibility');
    });

    it('answers 404 for a foreign entry and changes nothing', function () {
        $userBook = reviewedBook($this->alice);

        $this->actingAs($this->me)->patchJson("/api/library/{$userBook->id}", ['review' => 'Mine now'])->assertNotFound();
        expect($userBook->fresh()->review)->toBe('Great.');
    });

    it('returns the review with the entry', function () {
        $userBook = reviewedBook($this->me);

        $this->actingAs($this->me)->getJson("/api/library/{$userBook->id}")
            ->assertJsonPath('data.review', 'Great.')->assertJsonPath('data.review_visibility', 'public');
    });
});

describe('public reviews', function () {
    it('lists only public reviews, most recent first, with the book and rating', function () {
        $old = reviewedBook($this->alice, ['reviewed_at' => now()->subDays(2)]);
        $new = reviewedBook($this->alice, ['review' => 'Newer', 'reviewed_at' => now()]);
        reviewedBook($this->alice, ['review_visibility' => 'private']);
        reviewedBook($this->alice, ['review' => null]);
        reviewedBook($this->me);

        $response = $this->actingAs($this->me)->getJson('/api/users/alice/reviews')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.review', 'Newer')
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.book.title', $new->book->title)
            ->assertJsonPath('data.0.book.authors', $new->book->authors->pluck('name')->all())
            ->assertJsonPath('data.1.review', 'Great.')
            ->assertJsonStructure(['data' => [['book' => ['title', 'authors', 'cover_url'], 'rating', 'review', 'reviewed_at']], 'links', 'meta']);
        expect($response->json('data.0'))->not->toHaveKeys(['status', 'current_page', 'review_visibility']);
        expect($old)->not->toBeNull();
    });

    it('is open to a guest', function () {
        reviewedBook($this->alice);

        $this->getJson('/api/users/alice/reviews')->assertOk()->assertJsonCount(1, 'data');
    });

    it('answers 404 for a private or missing profile', function () {
        reviewedBook($this->hidden);

        $this->getJson('/api/users/hidden/reviews')->assertNotFound();
        $this->getJson('/api/users/nobody/reviews')->assertNotFound();
    });

    it('is paginated by 20', function () {
        UserBook::factory()->count(21)->for($this->alice)->finished()
            ->create(['review' => 'Text', 'review_visibility' => 'public', 'reviewed_at' => now()]);

        $this->getJson('/api/users/alice/reviews')->assertJsonCount(20, 'data');
        $this->getJson('/api/users/alice/reviews?page=2')->assertJsonCount(1, 'data');
    });

    it('does not run a query per review', function () {
        UserBook::factory()->count(10)->for($this->alice)->finished()
            ->create(['review' => 'Text', 'review_visibility' => 'public', 'reviewed_at' => now()]);

        DB::enableQueryLog();
        $this->getJson('/api/users/alice/reviews')->assertOk();

        expect(count(DB::getQueryLog()))->toBeLessThan(10);
    });

    it('counts only public reviews in the profile summary', function () {
        reviewedBook($this->alice);
        reviewedBook($this->alice, ['review_visibility' => 'private']);
        reviewedBook($this->alice, ['review' => null]);

        $this->getJson('/api/users/alice')->assertOk()->assertJsonPath('data.reviews_count', 1);
    });
});
