<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create(['handle' => 'me_reader']);
    $this->olena = User::factory()->create(['handle' => 'olena_k', 'name' => 'Olena', 'email' => 'olena.private@example.com']);
});

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/blocks'],
    ['PUT', '/api/blocks/olena_k'],
    ['DELETE', '/api/blocks/olena_k'],
]);

describe('blocking', function () {
    it('blocks a reader and answers with their profile', function () {
        $this->actingAs($this->user)->putJson('/api/blocks/olena_k')
            ->assertOk()
            ->assertJsonPath('data.handle', 'olena_k')
            ->assertJsonPath('data.is_blocked', true);

        expect(DB::table('blocks')->where(['user_id' => $this->user->id, 'blocked_id' => $this->olena->id])->count())->toBe(1);
    });

    it('is idempotent', function () {
        $this->actingAs($this->user)->putJson('/api/blocks/olena_k')->assertOk();
        $this->actingAs($this->user)->putJson('/api/blocks/olena_k')->assertOk();

        expect(DB::table('blocks')->count())->toBe(1);
    });

    it('does not let a reader block themselves', function () {
        $this->actingAs($this->user)->putJson('/api/blocks/me_reader')
            ->assertUnprocessable()
            ->assertJsonPath('errors.handle', ['social.cannot_block_self']);

        expect(DB::table('blocks')->count())->toBe(0);
    });

    it('has the database refuse a block of oneself as well', function () {
        expect(fn () => DB::table('blocks')->insert(['user_id' => $this->user->id, 'blocked_id' => $this->user->id]))
            ->toThrow(QueryException::class);
    });

    it('answers 404 for a handle nobody has', function () {
        $this->actingAs($this->user)->putJson('/api/blocks/nobody_here')->assertNotFound();
        $this->actingAs($this->user)->deleteJson('/api/blocks/nobody_here')->assertNotFound();
    });

    it('cuts the follows between the two readers, in both directions', function () {
        $this->user->following()->attach($this->olena->id);
        $this->olena->following()->attach($this->user->id);
        $bystander = User::factory()->create();
        $bystander->following()->attach($this->olena->id);

        $this->actingAs($this->user)->putJson('/api/blocks/olena_k')->assertOk();

        expect(DB::table('follows')->count())->toBe(1)
            ->and(DB::table('follows')->value('follower_id'))->toBe($bystander->id);
    });
});

describe('what blocking does', function () {
    beforeEach(function () {
        $this->post = Post::factory()->create(['user_id' => $this->olena->id]);
        $this->mine = Post::factory()->create(['user_id' => $this->user->id]);
        $this->comment = Comment::factory()->for($this->mine)->create(['user_id' => $this->olena->id]);
        $this->actingAs($this->user)->putJson('/api/blocks/olena_k')->assertOk();
    });

    it('hides the blocked reader from the one who blocked, everywhere', function () {
        $this->actingAs($this->user)->getJson('/api/feed?scope=all')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->mine->id);
        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}")->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->user)->getJson("/api/posts/{$this->mine->id}/comments")->assertJsonCount(0, 'data');
        $this->actingAs($this->user)->getJson("/api/posts/{$this->mine->id}")->assertJsonPath('data.comments_count', 0);
        expect($this->actingAs($this->user)->getJson('/api/profiles?q=olena')->json('data'))->toBe([]);
    });

    it('hides the one who blocked from the blocked reader, everywhere: mutual', function () {
        $this->actingAs($this->olena)->getJson('/api/feed?scope=all')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->post->id);
        $this->actingAs($this->olena)->getJson("/api/posts/{$this->mine->id}")->assertNotFound();
        $this->actingAs($this->olena)->getJson('/api/profiles/me_reader')->assertNotFound();
        $this->actingAs($this->olena)->getJson('/api/profiles/me_reader/posts')->assertNotFound();
        $this->actingAs($this->olena)->getJson("/api/posts/{$this->mine->id}/comments")->assertNotFound();
        expect($this->actingAs($this->olena)->getJson('/api/profiles?q=me_reader')->json('data'))->toBe([]);
    });

    it('does not let the blocked reader follow, like or comment on the blocker', function () {
        $this->actingAs($this->olena)->putJson('/api/profiles/me_reader/follow')->assertNotFound();
        $this->actingAs($this->olena)->putJson("/api/posts/{$this->mine->id}/like")->assertNotFound();
        $this->actingAs($this->olena)->postJson("/api/posts/{$this->mine->id}/comments", ['content' => 'Hello'])->assertNotFound();

        expect(DB::table('follows')->count())->toBe(0)
            ->and(DB::table('likes')->count())->toBe(0)
            ->and(Comment::query()->count())->toBe(1);
    });

    it('does not let the blocker follow, like or comment on the blocked reader either', function () {
        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')->assertUnprocessable();
        $this->actingAs($this->user)->putJson("/api/posts/{$this->post->id}/like")->assertNotFound();
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/comments", ['content' => 'Hello'])->assertNotFound();

        expect(DB::table('follows')->count())->toBe(0)->and(DB::table('likes')->count())->toBe(0);
    });

    it('does not touch what the blocked reader has already written', function () {
        expect(Post::query()->count())->toBe(2)->and(Comment::query()->count())->toBe(1);
    });

    it('leaves other readers unaffected', function () {
        $other = User::factory()->create();

        $this->actingAs($other)->getJson('/api/feed?scope=all')->assertJsonCount(2, 'data');
        $this->actingAs($other)->getJson("/api/posts/{$this->mine->id}/comments")->assertJsonCount(1, 'data');
    });

    it('brings everything back when the block is lifted', function () {
        $this->actingAs($this->user)->deleteJson('/api/blocks/olena_k')->assertNoContent();

        $this->actingAs($this->user)->getJson('/api/feed?scope=all')->assertJsonCount(2, 'data');
        $this->actingAs($this->olena)->getJson('/api/profiles/me_reader')->assertOk();
        // The follows that blocking cut do not come back by themselves.
        expect(DB::table('follows')->count())->toBe(0);
    });
});

describe('unblocking', function () {
    it('is idempotent', function () {
        $this->user->blockedReaders()->attach($this->olena->id);

        $this->actingAs($this->user)->deleteJson('/api/blocks/olena_k')->assertNoContent();
        $this->actingAs($this->user)->deleteJson('/api/blocks/olena_k')->assertNoContent();

        expect(DB::table('blocks')->count())->toBe(0);
    });

    it('lifts only my own block', function () {
        $this->user->blockedReaders()->attach($this->olena->id);
        $this->olena->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->deleteJson('/api/blocks/olena_k')->assertNoContent();

        expect(DB::table('blocks')->where('user_id', $this->olena->id)->count())->toBe(1)
            ->and(DB::table('blocks')->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('works even when the other reader has blocked me too, who then stays hidden', function () {
        $this->user->blockedReaders()->attach($this->olena->id);
        $this->olena->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->deleteJson('/api/blocks/olena_k')->assertNoContent();

        expect(DB::table('blocks')->where('user_id', $this->user->id)->count())->toBe(0);
        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')->assertNotFound();
    });
});

describe('the list of blocked readers', function () {
    it('lists the readers I have blocked, the latest first, with public fields only', function () {
        $first = User::factory()->create(['handle' => 'first_one']);
        $second = User::factory()->create(['handle' => 'second_one']);
        DB::table('blocks')->insert([
            ['user_id' => $this->user->id, 'blocked_id' => $first->id, 'created_at' => now()->subHour()],
            ['user_id' => $this->user->id, 'blocked_id' => $second->id, 'created_at' => now()],
        ]);
        DB::table('blocks')->insert(['user_id' => $this->olena->id, 'blocked_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/blocks')->assertOk();

        expect($response->json('data.*.handle'))->toBe(['second_one', 'first_one'])
            ->and($response->json('data.0'))->toHaveKeys(['handle', 'name', 'bio', 'is_me', 'is_following'])
            ->and($response->getContent())->not->toContain($first->email)->not->toContain('timezone');
    });

    it('is empty when I have blocked nobody', function () {
        $this->actingAs($this->user)->getJson('/api/blocks')->assertOk()->assertJsonCount(0, 'data');
    });

    it('does not list the readers who blocked me', function () {
        $this->olena->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->getJson('/api/blocks')->assertJsonCount(0, 'data');
    });

    it('pages by 20', function () {
        $readers = User::factory()->count(22)->create();
        $this->user->blockedReaders()->attach($readers->pluck('id')->all());

        $this->actingAs($this->user)->getJson('/api/blocks')->assertJsonCount(20, 'data');
        $this->actingAs($this->user)->getJson('/api/blocks?page=2')->assertJsonCount(2, 'data');
    });
});

describe('what the answers give away', function () {
    beforeEach(function () {
        // The way a real server answers: with debug on, an answer carries the exception and its trace.
        config(['app.debug' => false]);
    });

    it('answers the same to a block of a reader who has blocked me as to a handle nobody has', function () {
        $this->olena->blockedReaders()->attach($this->user->id);

        $hidden = $this->actingAs($this->user)->putJson('/api/blocks/olena_k');
        $missing = $this->actingAs($this->user)->putJson('/api/blocks/nobody_here');

        expect($hidden->getStatusCode())->toBe(404)
            ->and($missing->getStatusCode())->toBe(404)
            ->and($hidden->json())->toBe($missing->json());
        expect(DB::table('blocks')->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('answers the same to a profile of a reader who has blocked me as to a handle nobody has', function () {
        $this->olena->blockedReaders()->attach($this->user->id);

        $hidden = $this->actingAs($this->user)->getJson('/api/profiles/olena_k');
        $missing = $this->actingAs($this->user)->getJson('/api/profiles/nobody_here');

        expect($hidden->getStatusCode())->toBe($missing->getStatusCode())
            ->and($hidden->json())->toBe($missing->json());
    });

    it('does not count the readers I am blocked with in the numbers of a profile', function () {
        $friend = User::factory()->create();
        $blockedByMe = User::factory()->create();
        $blockedMe = User::factory()->create();
        foreach ([$friend, $blockedByMe, $blockedMe] as $reader) {
            $reader->following()->attach($this->olena->id);
            $this->olena->following()->attach($reader->id);
            Post::factory()->create(['user_id' => $reader->id]);
        }
        $this->user->blockedReaders()->attach($blockedByMe->id);
        $blockedMe->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')
            ->assertOk()
            ->assertJsonPath('data.followers_count', 1)
            ->assertJsonPath('data.following_count', 1);

        // The reader with the block sees the same profile with all three.
        $this->actingAs($friend)->getJson('/api/profiles/olena_k')
            ->assertJsonPath('data.followers_count', 3)
            ->assertJsonPath('data.following_count', 3);
    });

    it('does not count the posts of a reader I am blocked with', function () {
        $blockedByMe = User::factory()->create();
        Post::factory()->count(2)->create(['user_id' => $this->olena->id]);
        $this->user->blockedReaders()->attach($blockedByMe->id);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')->assertJsonPath('data.posts_count', 2);
        $this->actingAs($this->user)->getJson('/api/profiles/'.$blockedByMe->handle)->assertOk()->assertJsonPath('data.posts_count', 0);
    });
});
