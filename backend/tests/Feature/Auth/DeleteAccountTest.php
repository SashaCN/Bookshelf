<?php

use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Quote;
use App\Models\ReadingGoal;
use App\Models\ReadingLog;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
});

/**
 * Something of every kind that belongs to the reader.
 */
function populate(User $user): UserBook
{
    $userBook = UserBook::factory()->reading()->for($user)->create();
    Quote::factory()->create(['user_book_id' => $userBook->id]);
    ReadingLog::factory()->create(['user_book_id' => $userBook->id]);
    ReadingGoal::factory()->for($user)->create(['year' => 2026]);
    $text = BookText::factory()->create(['user_book_id' => $userBook->id]);
    BookChapter::factory()->create(['book_text_id' => $text->id]);

    return $userBook;
}

it('removes the account with all its data', function () {
    populate($this->user);
    $this->other->following()->attach($this->user->id);
    $this->user->following()->attach($this->other->id);
    $this->user->blockedReaders()->attach($this->other->id);
    $this->other->blockedReaders()->attach($this->user->id);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(User::find($this->user->id))->toBeNull();
    foreach (['user_books', 'quotes', 'reading_logs', 'reading_goals', 'book_texts', 'book_chapters', 'follows', 'blocks'] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }
});

it('leaves other readers data untouched', function () {
    populate($this->user);
    populate($this->other);
    $this->other->following()->attach($this->user->id);
    $this->user->following()->attach($this->other->id);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect($this->other->userBooks()->count())->toBe(1)
        ->and($this->other->quotes()->count())->toBe(1)
        ->and($this->other->readingGoals()->count())->toBe(1)
        ->and(BookText::query()->where('user_id', $this->other->id)->count())->toBe(1)
        ->and(BookChapter::query()->count())->toBe(1)
        ->and($this->other->following()->count())->toBe(0)
        ->and($this->other->followers()->count())->toBe(0);
});

it('removes the posts, with the comments, likes and reports under them', function () {
    $mine = Post::factory()->create(['user_id' => $this->user->id, 'likes_count' => 1, 'comments_count' => 1]);
    DB::table('likes')->insert(['user_id' => $this->other->id, 'post_id' => $mine->id, 'created_at' => now()]);
    $underMine = Comment::factory()->create(['post_id' => $mine->id, 'user_id' => $this->other->id]);
    Report::factory()->about($mine)->create(['reporter_id' => $this->other->id]);
    Report::factory()->about($underMine)->create(['reporter_id' => $this->other->id]);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    foreach (['posts', 'comments', 'likes', 'reports'] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }
    expect(User::find($this->other->id))->not->toBeNull();
});

it('keeps the counters of other readers posts right', function () {
    $theirs = Post::factory()->create(['user_id' => $this->other->id]);
    $stranger = User::factory()->create();
    // The counters are kept by the application: two likes (mine and a stranger's), three visible comments (two mine), one hidden.
    DB::table('likes')->insert([
        ['user_id' => $this->user->id, 'post_id' => $theirs->id, 'created_at' => now()],
        ['user_id' => $stranger->id, 'post_id' => $theirs->id, 'created_at' => now()],
    ]);
    Comment::factory()->count(2)->create(['post_id' => $theirs->id, 'user_id' => $this->user->id]);
    Comment::factory()->create(['post_id' => $theirs->id, 'user_id' => $stranger->id]);
    Comment::factory()->hidden()->create(['post_id' => $theirs->id, 'user_id' => $this->user->id]);
    Comment::factory()->hidden()->create(['post_id' => $theirs->id, 'user_id' => $stranger->id]);
    $theirs->forceFill(['likes_count' => 2, 'comments_count' => 3])->save();
    $untouched = Post::factory()->create(['user_id' => $stranger->id, 'likes_count' => 5, 'comments_count' => 4]);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect($theirs->fresh()->likes_count)->toBe(1)
        ->and($theirs->fresh()->comments_count)->toBe(1)
        ->and(Comment::query()->where('post_id', $theirs->id)->count())->toBe(2)
        ->and($untouched->fresh()->likes_count)->toBe(5)
        ->and($untouched->fresh()->comments_count)->toBe(4);
});

it('removes the reports about the comments of the account', function () {
    $theirs = Post::factory()->create(['user_id' => $this->other->id]);
    $mine = Comment::factory()->create(['post_id' => $theirs->id, 'user_id' => $this->user->id]);
    $kept = Comment::factory()->create(['post_id' => $theirs->id, 'user_id' => $this->other->id]);
    Report::factory()->about($mine)->create(['reporter_id' => $this->other->id]);
    Report::factory()->about($kept)->create(['reporter_id' => $this->user->id]);
    Report::factory()->about($theirs)->create(['reporter_id' => $this->user->id]);
    $stays = Report::factory()->about($theirs)->create(['reporter_id' => $this->other->id]);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    // Neither the report about the removed comment nor the reports the reader filed are left.
    expect(Report::query()->pluck('id')->all())->toBe([$stays->id]);
});

it('rejects a wrong password and deletes nothing', function () {
    populate($this->user);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->actingAs($this->user)->deleteJson('/api/me')->assertUnprocessable()->assertJsonValidationErrors('password');

    expect(User::find($this->user->id))->not->toBeNull()
        ->and(UserBook::query()->count())->toBe(1);
});

it('answers 401 to a guest', function () {
    $this->deleteJson('/api/me', ['password' => 'password'])->assertUnauthorized();
});

it('cannot log in after deletion', function () {
    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    $this->actAsGuest();

    $this->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'password'])
        ->assertUnprocessable();
    $this->assertGuest();
});

it('deletes the account of a session signed in with a password', function () {
    // A real session login, unlike actingAs().
    $this->withHeader('Referer', config('app.url'));
    $this->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertSuccessful();

    $this->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(User::find($this->user->id))->toBeNull();
    $this->actAsGuest();
    $this->getJson('/api/me')->assertUnauthorized();
});

it('throttles wrong passwords and keeps the account', function () {
    foreach (range(1, 5) as $attempt) {
        $this->actAs($this->user)->deleteJson('/api/me', ['password' => 'wrong'])->assertUnprocessable();
    }

    $this->actAs($this->user)->deleteJson('/api/me', ['password' => 'wrong'])->assertTooManyRequests();
    $this->actAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertTooManyRequests();
    expect(User::find($this->user->id))->not->toBeNull();
});

it('also removes other sessions and reset tokens', function () {
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 'stranger', 'user_id' => $this->other->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert([
        ['email' => $this->user->email, 'token' => 'x', 'created_at' => now()],
        ['email' => $this->other->email, 'token' => 'y', 'created_at' => now()],
    ]);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['stranger'])
        ->and(DB::table('password_reset_tokens')->pluck('email')->all())->toBe([$this->other->email]);
});
