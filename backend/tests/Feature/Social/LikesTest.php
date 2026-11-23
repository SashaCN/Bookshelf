<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->author = User::factory()->create();
    $this->post = Post::factory()->create(['user_id' => $this->author->id]);
});

/**
 * The counter on the post and the likes in the table must always agree.
 */
function expectExactLikes(Post $post, int $likes): void
{
    expect($post->fresh()->likes_count)->toBe($likes)
        ->and(DB::table('likes')->where('post_id', $post->id)->count())->toBe($likes);
}

it('requires authentication', function (string $method) {
    $this->json($method, '/api/posts/1/like')->assertUnauthorized();
})->with(['PUT', 'DELETE']);

describe('liking', function () {
    it('likes a post and answers with the post and its new counter', function () {
        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.id', $this->post->id)
            ->assertJsonPath('data.liked_by_me', true)
            ->assertJsonPath('data.likes_count', 1);

        expectExactLikes($this->post, 1);
    });

    it('is idempotent: liking twice counts once', function () {
        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertOk();
        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.likes_count', 1)
            ->assertJsonPath('data.liked_by_me', true);

        expectExactLikes($this->post, 1);
    });

    it('lets the author like their own post', function () {
        $this->actingAs($this->author)->putJson("/api/posts/{$this->post->id}/like")->assertOk()->assertJsonPath('data.likes_count', 1);
    });

    it('counts the likes of different readers', function () {
        $readers = User::factory()->count(3)->create();

        foreach ($readers as $reader) {
            $this->actingAs($reader)->putJson("/api/posts/{$this->post->id}/like")->assertOk();
        }

        expectExactLikes($this->post, 3);
    });

    it('does not count a like that is already there once more', function () {
        $this->post->likedBy()->attach($this->user->id);
        $this->post->forceFill(['likes_count' => 1])->save();

        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertJsonPath('data.likes_count', 1);

        expectExactLikes($this->post, 1);
    });

    it('answers 404 for a post that does not exist', function () {
        $this->actingAs($this->user)->putJson('/api/posts/999999/like')->assertNotFound();
    });

    it('answers 404 for a post hidden by moderation, and likes nothing', function () {
        $this->post->update(['hidden_at' => now()]);

        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertNotFound();

        expectExactLikes($this->post, 0);
    });

    it('lets the author like their post that moderation hid', function () {
        $this->post->update(['hidden_at' => now()]);

        $this->actingAs($this->author)->putJson("/api/posts/{$this->post->id}/like")->assertOk();
    });

    it('does not let a reader the author has blocked like the post', function () {
        $this->author->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertNotFound();

        expectExactLikes($this->post, 0);
    });

    it('does not let a reader like the post of somebody they have blocked', function () {
        $this->user->blockedReaders()->attach($this->author->id);

        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertNotFound();

        expectExactLikes($this->post, 0);
    });
});

describe('taking a like back', function () {
    beforeEach(function () {
        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertOk();
    });

    it('takes the like back and answers with the new counter', function () {
        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.liked_by_me', false)
            ->assertJsonPath('data.likes_count', 0);

        expectExactLikes($this->post, 0);
    });

    it('is idempotent: taking it back twice does not go below zero', function () {
        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk();
        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk()->assertJsonPath('data.likes_count', 0);

        expectExactLikes($this->post, 0);
    });

    it('takes back only my own like', function () {
        $other = User::factory()->create();
        $this->actingAs($other)->putJson("/api/posts/{$this->post->id}/like")->assertOk();

        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk()->assertJsonPath('data.likes_count', 1);

        expect(DB::table('likes')->where('post_id', $this->post->id)->pluck('user_id')->all())->toBe([$other->id]);
        expectExactLikes($this->post, 1);
    });

    it('does nothing for a reader who never liked the post', function () {
        $other = User::factory()->create();

        $this->actingAs($other)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk()->assertJsonPath('data.likes_count', 1);

        expectExactLikes($this->post, 1);
    });

    it('lets the reader take the like back from a post that moderation hid since, without showing the post', function () {
        $this->post->update(['hidden_at' => now()]);

        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")
            ->assertOk()
            ->assertExactJson(['data' => null]);

        expectExactLikes($this->post, 0);
    });

    it('lets a reader whom the author has blocked take their like back, without showing the post', function () {
        $this->author->blockedReaders()->attach($this->user->id);

        $response = $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk()->assertExactJson(['data' => null]);

        expect($response->getContent())->not->toContain($this->post->content);
        expectExactLikes($this->post, 0);
    });

    it('lets the author blocked by the reader take theirs back as well', function () {
        $this->user->blockedReaders()->attach($this->author->id);

        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk()->assertExactJson(['data' => null]);

        expectExactLikes($this->post, 0);
    });

    it('is idempotent for a reader who is blocked', function () {
        $this->author->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk();
        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk();

        expectExactLikes($this->post, 0);
    });

    it('takes back only the like of the blocked reader, not the likes of others', function () {
        $other = User::factory()->create();
        $this->actingAs($other)->putJson("/api/posts/{$this->post->id}/like")->assertOk();
        $this->author->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")->assertOk();

        expect(DB::table('likes')->where('post_id', $this->post->id)->pluck('user_id')->all())->toBe([$other->id]);
        expectExactLikes($this->post, 1);
    });

    it('still gives the post back to a reader who may see it', function () {
        $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.id', $this->post->id);
    });

    it('answers 404 for a post that does not exist', function () {
        $this->actingAs($this->user)->deleteJson('/api/posts/999999/like')->assertNotFound();
    });

    it('stays exact through many likes and unlikes in a row', function () {
        $readers = User::factory()->count(5)->create();

        foreach ($readers as $reader) {
            $this->actingAs($reader)->putJson("/api/posts/{$this->post->id}/like");
        }
        foreach ($readers->take(2) as $reader) {
            $this->actingAs($reader)->deleteJson("/api/posts/{$this->post->id}/like");
        }
        $this->actingAs($readers[4])->putJson("/api/posts/{$this->post->id}/like");

        expectExactLikes($this->post, 4);
    });
});

it('does not leave a like behind when the counter cannot be updated', function () {
    DB::beforeExecuting(function (string $query): void {
        if (str_contains($query, 'update `posts` set `likes_count`')) {
            throw new RuntimeException('The counter is out of reach.');
        }
    });
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like"))->toThrow(RuntimeException::class);

    expect(DB::table('likes')->count())->toBe(0)->and($this->post->fresh()->likes_count)->toBe(0);
});

it('does not take a like away when the counter cannot be updated', function () {
    $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertOk();
    DB::beforeExecuting(function (string $query): void {
        if (str_contains($query, 'update `posts` set `likes_count`')) {
            throw new RuntimeException('The counter is out of reach.');
        }
    });
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->user)->deleteJson("/api/posts/{$this->post->id}/like"))->toThrow(RuntimeException::class);

    expectExactLikes($this->post, 1);
});

it('shows my like on the post and in the feed', function () {
    $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertOk();

    $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}")->assertJsonPath('data.liked_by_me', true);
    $this->actingAs($this->user)->getJson('/api/feed?scope=all')->assertJsonPath('data.0.liked_by_me', true);
    $this->actingAs($this->author)->getJson('/api/feed?scope=all')->assertJsonPath('data.0.liked_by_me', false);
});
