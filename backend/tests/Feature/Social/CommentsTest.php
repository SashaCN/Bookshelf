<?php

use App\Actions\Social\PostComments;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->author = User::factory()->create();
    $this->post = Post::factory()->create(['user_id' => $this->author->id]);
});

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/posts/1/comments'],
    ['POST', '/api/posts/1/comments'],
    ['DELETE', '/api/comments/1'],
]);

describe('listing', function () {
    it('lists the comments of a post, newest first, with their authors', function () {
        $first = Comment::factory()->for($this->post)->create(['content' => 'First']);
        $second = Comment::factory()->for($this->post)->create(['content' => 'Second']);
        Comment::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")->assertOk();

        expect($response->json('data.*.id'))->toBe([$second->id, $first->id])
            ->and($response->json('data.0'))->toMatchArray([
                'content' => 'Second',
                'post_id' => $this->post->id,
                'is_mine' => false,
                'is_hidden' => false,
                'author' => ['handle' => $second->user->handle, 'name' => $second->user->name],
            ]);
    });

    it('never gives away the e-mail or the time zone of a commenter', function () {
        $comment = Comment::factory()->for($this->post)->create();

        $body = $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")->getContent();

        expect($body)->not->toContain($comment->user->email)->not->toContain('timezone');
    });

    it('pages by 20', function () {
        Comment::factory()->count(23)->for($this->post)->create();

        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('links.next', fn ($next) => $next !== null);
        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments?page=2")
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('links.next', null);
    });

    it('is empty for a post without comments', function () {
        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")->assertOk()->assertJsonCount(0, 'data');
    });

    it('says who may delete a comment', function () {
        $mine = Comment::factory()->for($this->post)->create(['user_id' => $this->user->id]);
        $theirs = Comment::factory()->for($this->post)->create();

        $asCommenter = $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")->json('data');
        expect(collect($asCommenter)->pluck('can_delete', 'id')->all())->toBe([$theirs->id => false, $mine->id => true])
            ->and(collect($asCommenter)->pluck('is_mine', 'id')->all())->toBe([$theirs->id => false, $mine->id => true]);

        $asPostAuthor = $this->actingAs($this->author)->getJson("/api/posts/{$this->post->id}/comments")->json('data');
        expect(collect($asPostAuthor)->pluck('can_delete')->unique()->all())->toBe([true]);
    });

    it('leaves out hidden comments, except for their own author, who sees them flagged', function () {
        $visible = Comment::factory()->for($this->post)->create();
        $hidden = Comment::factory()->hidden()->for($this->post)->create(['user_id' => $this->user->id]);

        $asAuthor = $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")->json('data');
        expect(collect($asAuthor)->pluck('is_hidden', 'id')->all())->toBe([$hidden->id => true, $visible->id => false]);

        $asOther = $this->actingAs($this->author)->getJson("/api/posts/{$this->post->id}/comments")->json('data.*.id');
        expect($asOther)->toBe([$visible->id]);
    });

    it('leaves out the comments of readers who have a block with me, in either direction', function () {
        $fine = Comment::factory()->for($this->post)->create();
        $blockedByMe = Comment::factory()->for($this->post)->create();
        $blockingMe = Comment::factory()->for($this->post)->create();
        $this->user->blockedReaders()->attach($blockedByMe->user_id);
        $blockingMe->user->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fine->id);
    });

    it('answers 404 for a post the reader cannot see', function () {
        $hidden = Post::factory()->hidden()->create();

        $this->actingAs($this->user)->getJson("/api/posts/{$hidden->id}/comments")->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/posts/999999/comments')->assertNotFound();
    });

    it('answers 404 for the post of a blocked reader', function () {
        $this->author->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}/comments")->assertNotFound();
    });
});

describe('writing a comment', function () {
    it('adds a comment and counts it', function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Nice one!'])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Nice one!')
            ->assertJsonPath('data.post_id', $this->post->id)
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.author.handle', $this->user->handle);

        expect($this->post->fresh()->comments_count)->toBe(1)
            ->and(Comment::query()->sole()->user_id)->toBe($this->user->id);
    });

    it('keeps the counter equal to the number of comments', function () {
        $readers = User::factory()->count(3)->create();

        foreach ($readers as $reader) {
            $this->actingAs($reader)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Hello'])->assertCreated();
        }

        expect($this->post->fresh()->comments_count)->toBe(3)->and($this->post->comments()->count())->toBe(3);
    });

    it('shows up in the feed count and in the post', function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Hi'])->assertCreated();

        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}")->assertJsonPath('data.comments_count', 1);
        $this->actingAs($this->user)->getJson('/api/feed?scope=all')->assertJsonPath('data.0.comments_count', 1);
    });

    it('does not leave a comment behind when the counter cannot be updated', function () {
        DB::beforeExecuting(function (string $query): void {
            if (str_contains($query, 'update `posts` set `comments_count`')) {
                throw new RuntimeException('The counter is out of reach.');
            }
        });
        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Hi']))
            ->toThrow(RuntimeException::class);

        expect(Comment::query()->count())->toBe(0)->and($this->post->fresh()->comments_count)->toBe(0);
    });

    it('lets the author comment on their own post', function () {
        $this->actingAs($this->author)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Thanks'])->assertCreated();
    });

    it('validates the content', function (mixed $content) {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => $content])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        expect(Comment::query()->count())->toBe(0)->and($this->post->fresh()->comments_count)->toBe(0);
    })->with([
        'missing' => [null],
        'empty' => [''],
        'only spaces' => ['    '],
        'too long' => [str_repeat('a', 1001)],
        'not text' => [[1, 2]],
    ]);

    it('accepts 1 and 1000 characters', function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'a'])->assertCreated();
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => str_repeat('a', 1000)])->assertCreated();
    });

    it('answers "not found" for a post the reader cannot see, whatever is sent', function () {
        $hidden = Post::factory()->hidden()->create();

        $this->actingAs($this->user)->postJson("/api/posts/{$hidden->id}/comments", ['content' => 'Hi'])->assertNotFound();
        $this->actingAs($this->user)->postJson("/api/posts/{$hidden->id}/comments", [])->assertNotFound();
        $this->actingAs($this->user)->postJson('/api/posts/999999/comments', ['content' => 'Hi'])->assertNotFound();

        expect(Comment::query()->count())->toBe(0);
    });

    it('does not let a reader the author has blocked comment', function () {
        $this->author->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Hi'])->assertNotFound();
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", [])->assertNotFound();

        expect(Comment::query()->count())->toBe(0)->and($this->post->fresh()->comments_count)->toBe(0);
    });

    it('does not let a reader comment on the post of somebody they have blocked', function () {
        $this->user->blockedReaders()->attach($this->author->id);

        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Hi'])->assertNotFound();

        expect(Comment::query()->count())->toBe(0);
    });
});

describe('deleting a comment', function () {
    beforeEach(function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Mine'])->assertCreated();
        $this->comment = Comment::query()->sole();
    });

    it('lets the author of the comment delete it, and uncounts it', function () {
        $this->actingAs($this->user)->deleteJson("/api/comments/{$this->comment->id}")->assertNoContent();

        expect(Comment::query()->count())->toBe(0)->and($this->post->fresh()->comments_count)->toBe(0);
    });

    it('lets the author of the post delete it', function () {
        $this->actingAs($this->author)->deleteJson("/api/comments/{$this->comment->id}")->assertNoContent();

        expect(Comment::query()->count())->toBe(0)->and($this->post->fresh()->comments_count)->toBe(0);
    });

    it('lets the author of the post delete the comment of a reader who has blocked them', function () {
        $this->user->blockedReaders()->attach($this->author->id);

        $this->actingAs($this->author)->deleteJson("/api/comments/{$this->comment->id}")->assertNoContent();
    });

    it('answers "not found" to anybody else, and keeps the comment', function () {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->deleteJson("/api/comments/{$this->comment->id}")->assertNotFound();

        expect(Comment::query()->count())->toBe(1)->and($this->post->fresh()->comments_count)->toBe(1);
    });

    it('answers 404 for a comment that does not exist or is already gone', function () {
        $this->actingAs($this->user)->deleteJson('/api/comments/999999')->assertNotFound();

        $this->actingAs($this->user)->deleteJson("/api/comments/{$this->comment->id}")->assertNoContent();
        $this->actingAs($this->user)->deleteJson("/api/comments/{$this->comment->id}")->assertNotFound();
    });

    it('keeps the comment when the counter cannot be updated', function () {
        DB::beforeExecuting(function (string $query): void {
            if (str_contains($query, 'update `posts` set `comments_count`')) {
                throw new RuntimeException('The counter is out of reach.');
            }
        });
        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs($this->user)->deleteJson("/api/comments/{$this->comment->id}"))->toThrow(RuntimeException::class);

        expect(Comment::query()->count())->toBe(1)->and($this->post->fresh()->comments_count)->toBe(1);
    });

    it('takes one off the counter, however often a stale copy of the comment is deleted', function () {
        $other = Comment::factory()->for($this->post)->create();
        $this->post->forceFill(['comments_count' => 2])->save();
        $stale = Comment::query()->find($this->comment->id);

        app(PostComments::class)->remove($stale);
        app(PostComments::class)->remove($stale);

        expect(Comment::query()->pluck('id')->all())->toBe([$other->id])
            ->and($this->post->fresh()->comments_count)->toBe(1);
    });

    it('takes nothing off the counter for a comment that was hidden since the copy was loaded', function () {
        Comment::factory()->for($this->post)->create();
        $this->post->forceFill(['comments_count' => 2])->save();
        $stale = Comment::query()->find($this->comment->id);

        // Hiding has already taken the comment off the counter.
        $this->artisan('moderation:hide', ['type' => 'comment', 'id' => $this->comment->id])->assertSuccessful();
        expect($this->post->fresh()->comments_count)->toBe(1);

        app(PostComments::class)->remove($stale);

        expect(Comment::query()->find($this->comment->id))->toBeNull()
            ->and($this->post->fresh()->comments_count)->toBe(1);
    });

    it('takes the counter off a comment that was restored since the copy was loaded', function () {
        $this->comment->forceFill(['hidden_at' => now()])->save();
        DB::table('posts')->where('id', $this->post->id)->update(['comments_count' => 0]);
        $stale = Comment::query()->find($this->comment->id);

        $this->artisan('moderation:restore', ['type' => 'comment', 'id' => $this->comment->id])->assertSuccessful();
        expect($this->post->fresh()->comments_count)->toBe(1);

        app(PostComments::class)->remove($stale);

        expect($this->post->fresh()->comments_count)->toBe(0);
    });

    it('locks the comment while it is deleted', function () {
        DB::enableQueryLog();
        $this->actingAs($this->user)->deleteJson("/api/comments/{$this->comment->id}")->assertNoContent();
        $queries = array_column(DB::getQueryLog(), 'query');

        $lock = array_find_key($queries, fn (string $query) => str_contains($query, 'from `comments`') && str_contains($query, 'for update'));
        $delete = array_find_key($queries, fn (string $query) => str_starts_with($query, 'delete from `comments`'));

        expect($lock)->not->toBeNull()->and($lock)->toBeLessThan($delete);
    });

    it('does not take the counter below the real number when a hidden comment goes', function () {
        $hidden = Comment::factory()->hidden()->for($this->post)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/comments/{$hidden->id}")->assertNoContent();

        // The hidden comment was never counted, so its removal leaves the other one counted.
        expect($this->post->fresh()->comments_count)->toBe(1);
    });

    it('removes the reports about the comment with it', function () {
        Report::factory()->about($this->comment)->create();
        $other = Report::factory()->create();

        $this->actingAs($this->user)->deleteJson("/api/comments/{$this->comment->id}")->assertNoContent();

        expect(Report::query()->pluck('id')->all())->toBe([$other->id]);
    });
});
