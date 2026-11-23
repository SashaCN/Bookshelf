<?php

use App\Models\Quote;
use App\Models\ReaderBookmark;
use App\Models\ReaderDocument;
use App\Models\ReadingGoal;
use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['is_public' => true, 'username' => 'olena']);
    $this->other = User::factory()->create(['is_public' => true, 'username' => 'taras']);
});

function populate(User $user): array
{
    $userBook = UserBook::factory()->reading()->for($user)->create();
    $quote = Quote::factory()->public()->create(['user_book_id' => $userBook->id]);
    ReadingLog::factory()->create(['user_book_id' => $userBook->id]);
    ReadingGoal::factory()->for($user)->create(['year' => 2026]);
    $document = ReaderDocument::factory()->for($user)->create();
    ReaderBookmark::factory()->create(['reader_document_id' => $document->id]);
    DB::table('user_book_reads')->insert(['user_book_id' => $userBook->id, 'started_at' => now()->subDays(9), 'finished_at' => now()->subDays(2)]);
    Storage::disk('local')->put($document->path, 'epub');

    return [$quote, $document];
}

it('removes the account with all its data and files', function () {
    [$quote, $document] = populate($this->user);
    $this->other->following()->attach($this->user->id);
    $this->user->following()->attach($this->other->id);
    DB::table('quote_likes')->insert(['user_id' => $this->other->id, 'quote_id' => $quote->id, 'created_at' => now()]);
    DB::table('quote_likes')->insert(['user_id' => $this->user->id, 'quote_id' => Quote::factory()->public()->create()->id, 'created_at' => now()]);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(User::find($this->user->id))->toBeNull();
    // The one book and quote left belong to the stranger whose quote the user liked.
    foreach (['user_books' => 1, 'quotes' => 1, 'reading_logs' => 0, 'reading_goals' => 0, 'reader_documents' => 0, 'reader_bookmarks' => 0, 'user_book_reads' => 0, 'follows' => 0] as $table => $left) {
        expect(DB::table($table)->count())->toBe($left, $table);
    }
    expect(DB::table('quote_likes')->count())->toBe(0);
    Storage::disk('local')->assertMissing($document->path);
});

it('leaves other readers data untouched and updates their counts', function () {
    populate($this->user);
    [$theirQuote, $theirDocument] = populate($this->other);
    $this->other->following()->attach($this->user->id);
    $this->user->following()->attach($this->other->id);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect($this->other->userBooks()->count())->toBe(1)
        ->and($this->other->quotes()->count())->toBe(1)
        ->and($this->other->readerDocuments()->count())->toBe(1)
        ->and($this->other->readingGoals()->count())->toBe(1)
        ->and($this->other->following()->count())->toBe(0)
        ->and($this->other->followers()->count())->toBe(0);
    Storage::disk('local')->assertExists($theirDocument->path);
});

it('does not mind files that are already missing', function () {
    ReaderDocument::factory()->for($this->user)->create();

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(User::find($this->user->id))->toBeNull();
});

it('rejects a wrong password and deletes nothing', function () {
    [, $document] = populate($this->user);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->actingAs($this->user)->deleteJson('/api/me')->assertUnprocessable()->assertJsonValidationErrors('password');

    expect(User::find($this->user->id))->not->toBeNull();
    Storage::disk('local')->assertExists($document->path);
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

it('throttles wrong passwords and keeps the account', function () {
    foreach (range(1, 5) as $attempt) {
        $this->actAs($this->user)->deleteJson('/api/me', ['password' => 'wrong'])->assertUnprocessable();
    }

    $this->actAs($this->user)->deleteJson('/api/me', ['password' => 'wrong'])->assertTooManyRequests();
    $this->actAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertTooManyRequests();
    expect(User::find($this->user->id))->not->toBeNull();
});

it('also removes comments, blocks, chapter reads, other sessions and reset tokens', function () {
    [$quote, $document] = populate($this->user);
    $theirs = Quote::factory()->public()->create();
    DB::table('quote_comments')->insert([
        ['quote_id' => $quote->id, 'user_id' => $this->other->id, 'body' => 'on mine', 'created_at' => now(), 'updated_at' => now()],
        ['quote_id' => $theirs->id, 'user_id' => $this->user->id, 'body' => 'by me', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('user_blocks')->insert([
        ['blocker_id' => $this->user->id, 'blocked_id' => $this->other->id, 'created_at' => now()],
        ['blocker_id' => $this->other->id, 'blocked_id' => $this->user->id, 'created_at' => now()],
    ]);
    DB::table('reader_chapter_reads')->insert(['reader_document_id' => $document->id, 'chapter' => 0, 'read_on' => '2026-11-01', 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 'stranger', 'user_id' => $this->other->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert([
        ['email' => $this->user->email, 'token' => 'x', 'created_at' => now()],
        ['email' => $this->other->email, 'token' => 'y', 'created_at' => now()],
    ]);

    $this->actingAs($this->user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    foreach (['quote_comments', 'user_blocks', 'reader_chapter_reads'] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }
    expect(DB::table('sessions')->pluck('id')->all())->toBe(['stranger']);
    expect(DB::table('password_reset_tokens')->pluck('email')->all())->toBe([$this->other->email]);
});
