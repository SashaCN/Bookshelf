<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Sends the request `$allowed` times, which all get through, and once more, which is turned away.
 */
function expectLimitAfter(int $allowed, Closure $send): void
{
    foreach (range(1, $allowed) as $attempt) {
        $send($attempt)->assertSuccessful();
    }

    $send($allowed + 1)->assertTooManyRequests();
}

it('lets a reader publish 20 posts an hour', function () {
    $quote = Quote::factory()->for(UserBook::factory()->for($this->user))->create();

    expectLimitAfter(20, fn () => $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id]));

    expect(Post::query()->count())->toBe(20);
});

it('lets a reader write 60 comments an hour', function () {
    $post = Post::factory()->create();

    expectLimitAfter(60, fn () => $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/comments", ['content' => 'Hi']));

    expect(Comment::query()->count())->toBe(60);
});

it('lets a reader like 120 times a minute, taking likes back included', function () {
    $post = Post::factory()->create();

    expectLimitAfter(120, fn (int $attempt) => $attempt % 2 === 1
        ? $this->actingAs($this->user)->putJson("/api/posts/{$post->id}/like")
        : $this->actingAs($this->user)->deleteJson("/api/posts/{$post->id}/like"));
});

it('lets a reader follow 120 times a minute, unfollowing included', function () {
    $other = User::factory()->create(['handle' => 'olena_k']);

    expectLimitAfter(120, fn (int $attempt) => $attempt % 2 === 1
        ? $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')
        : $this->actingAs($this->user)->deleteJson('/api/profiles/olena_k/follow'));

    expect($other->exists)->toBeTrue();
});

it('lets a reader send 20 reports an hour', function () {
    $posts = Post::factory()->count(21)->create();

    expectLimitAfter(20, fn (int $attempt) => $this->actingAs($this->user)->postJson("/api/posts/{$posts[$attempt - 1]->id}/report", ['reason' => 'spam']));
});

it('lets a reader change and delete 60 times a minute, all the changes without a limit of their own together', function () {
    $post = Post::factory()->create(['user_id' => $this->user->id]);
    $theirs = Comment::factory()->for(Post::factory()->create())->create(['user_id' => $this->user->id]);
    User::factory()->create(['handle' => 'olena_k']);

    // 20 edits of a post, 20 blocks and 20 unblocks: 60 changes of three different kinds.
    expectLimitAfter(60, fn (int $attempt) => match ($attempt % 3) {
        1 => $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['comment' => "Edit {$attempt}"]),
        2 => $this->actingAs($this->user)->putJson('/api/blocks/olena_k'),
        default => $this->actingAs($this->user)->deleteJson('/api/blocks/olena_k'),
    });

    // The limit is shared: no other change goes through either.
    $this->actingAs($this->user)->deleteJson("/api/comments/{$theirs->id}")->assertTooManyRequests();
    $this->actingAs($this->user)->deleteJson("/api/posts/{$post->id}")->assertTooManyRequests();
    $this->actingAs($this->user)->putJson('/api/blocks/olena_k')->assertTooManyRequests();
    expect(Comment::query()->find($theirs->id))->not->toBeNull()->and(Post::query()->find($post->id))->not->toBeNull();
});

it('counts the changes of each reader on their own', function () {
    $post = Post::factory()->create(['user_id' => $this->user->id]);
    foreach (range(1, 60) as $unused) {
        $this->actingAs($this->user)->patchJson("/api/posts/{$post->id}", ['comment' => 'x'])->assertOk();
    }

    $other = User::factory()->create();
    $mine = Post::factory()->create(['user_id' => $other->id]);

    $this->actingAs($other)->deleteJson("/api/posts/{$mine->id}")->assertNoContent();
});

it('keeps the limits apart: one used up does not stop the others', function () {
    $post = Post::factory()->create();
    $quote = Quote::factory()->for(UserBook::factory()->for($this->user))->create();
    foreach (range(1, 20) as $unused) {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id])->assertCreated();
    }
    $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id])->assertTooManyRequests();

    $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/comments", ['content' => 'Still fine'])->assertCreated();
    $this->actingAs($this->user)->putJson("/api/posts/{$post->id}/like")->assertOk();
    $this->actingAs($this->user)->getJson('/api/feed')->assertOk();
});

it('counts each reader on their own', function () {
    $quote = Quote::factory()->for(UserBook::factory()->for($this->user))->create();
    foreach (range(1, 20) as $unused) {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id])->assertCreated();
    }

    $other = User::factory()->create();
    $theirs = Quote::factory()->for(UserBook::factory()->for($other))->create();

    $this->actingAs($other)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $theirs->id])->assertCreated();
});

it('opens the limit again after the time has passed', function () {
    $quote = Quote::factory()->for(UserBook::factory()->for($this->user))->create();
    foreach (range(1, 20) as $unused) {
        $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id])->assertCreated();
    }
    $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id])->assertTooManyRequests();

    $this->travel(61)->minutes();

    $this->actingAs($this->user)->postJson('/api/posts', ['type' => 'quote', 'quote_id' => $quote->id])->assertCreated();
});

it('tells the client how long to wait', function () {
    $post = Post::factory()->create();
    foreach (range(1, 120) as $unused) {
        $this->actingAs($this->user)->putJson("/api/posts/{$post->id}/like");
    }

    $this->actingAs($this->user)->putJson("/api/posts/{$post->id}/like")
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});
