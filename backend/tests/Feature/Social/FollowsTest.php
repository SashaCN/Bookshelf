<?php

use App\Actions\Social\BlockReader;
use App\Actions\Social\FollowReader;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->olena = User::factory()->create(['handle' => 'olena_k']);
});

it('requires authentication', function (string $method) {
    $this->json($method, '/api/profiles/olena_k/follow')->assertUnauthorized();
})->with(['PUT', 'DELETE']);

describe('following', function () {
    it('follows a reader and answers with their profile', function () {
        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')
            ->assertOk()
            ->assertJsonPath('data.handle', 'olena_k')
            ->assertJsonPath('data.is_following', true)
            ->assertJsonPath('data.followers_count', 1);

        expect(DB::table('follows')->where(['follower_id' => $this->user->id, 'followed_id' => $this->olena->id])->count())->toBe(1);
    });

    it('is idempotent', function () {
        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')->assertOk();
        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')
            ->assertOk()
            ->assertJsonPath('data.followers_count', 1);

        expect(DB::table('follows')->count())->toBe(1);
    });

    it('is one way: the other reader does not follow back', function () {
        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')->assertOk();

        $this->actingAs($this->olena)->getJson("/api/profiles/{$this->user->handle}")
            ->assertJsonPath('data.is_following', false)
            ->assertJsonPath('data.following_count', 1)
            ->assertJsonPath('data.followers_count', 0);
    });

    it('does not let a reader follow themselves', function () {
        $this->actingAs($this->user)->putJson("/api/profiles/{$this->user->handle}/follow")
            ->assertUnprocessable()
            ->assertJsonPath('errors.handle', ['social.cannot_follow_self']);

        expect(DB::table('follows')->count())->toBe(0);
    });

    it('has the database refuse a follow of oneself as well', function () {
        expect(fn () => DB::table('follows')->insert(['follower_id' => $this->user->id, 'followed_id' => $this->user->id]))
            ->toThrow(QueryException::class);
    });

    it('answers 404 for a handle nobody has', function () {
        $this->actingAs($this->user)->putJson('/api/profiles/nobody_here/follow')->assertNotFound();
    });

    it('does not let a reader follow somebody who has blocked them, and says the reader is not there', function () {
        $this->olena->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')->assertNotFound();

        expect(DB::table('follows')->count())->toBe(0);
    });

    it('does not let a reader follow somebody they have blocked', function () {
        $this->user->blockedReaders()->attach($this->olena->id);

        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')
            ->assertUnprocessable()
            ->assertJsonPath('errors.handle', ['social.user_blocked']);

        expect(DB::table('follows')->count())->toBe(0);
    });

    it('puts the posts of the reader into my "following" feed', function () {
        $post = Post::factory()->create(['user_id' => $this->olena->id]);

        $this->actingAs($this->user)->getJson('/api/feed')->assertJsonCount(0, 'data');
        $this->actingAs($this->user)->putJson('/api/profiles/olena_k/follow')->assertOk();

        $this->actingAs($this->user)->getJson('/api/feed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $post->id);
    });
});

describe('unfollowing', function () {
    beforeEach(function () {
        $this->user->following()->attach($this->olena->id);
    });

    it('unfollows and answers with the profile', function () {
        $this->actingAs($this->user)->deleteJson('/api/profiles/olena_k/follow')
            ->assertOk()
            ->assertJsonPath('data.is_following', false)
            ->assertJsonPath('data.followers_count', 0);

        expect(DB::table('follows')->count())->toBe(0);
    });

    it('is idempotent', function () {
        $this->actingAs($this->user)->deleteJson('/api/profiles/olena_k/follow')->assertOk();
        $this->actingAs($this->user)->deleteJson('/api/profiles/olena_k/follow')->assertOk()->assertJsonPath('data.is_following', false);
    });

    it('takes back only my own follow', function () {
        $other = User::factory()->create();
        $other->following()->attach($this->olena->id);

        $this->actingAs($this->user)->deleteJson('/api/profiles/olena_k/follow')->assertOk()->assertJsonPath('data.followers_count', 1);

        expect(DB::table('follows')->where('follower_id', $other->id)->count())->toBe(1);
    });

    it('does nothing for a reader who was not followed', function () {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->deleteJson('/api/profiles/olena_k/follow')->assertOk();

        expect(DB::table('follows')->count())->toBe(1);
    });

    it('answers 404 for a handle nobody has', function () {
        $this->actingAs($this->user)->deleteJson('/api/profiles/nobody_here/follow')->assertNotFound();
    });
});

describe('two requests at the same moment', function () {
    it('do not fail when both found "not following yet"', function () {
        // Two requests that both passed the checks: the second insert meets the row of the first.
        $action = new FollowReader;
        $action->follow($this->user, $this->olena);
        $action->follow(User::query()->findOrFail($this->user->id), User::query()->findOrFail($this->olena->id));

        expect(DB::table('follows')->count())->toBe(1);
    });

    it('leave one follow and no error when the same request is sent twice in a row', function () {
        $this->actingAs($this->user);

        $this->putJson('/api/profiles/olena_k/follow')->assertOk();
        $this->putJson('/api/profiles/olena_k/follow')->assertOk();
        $this->deleteJson('/api/profiles/olena_k/follow')->assertOk();
        $this->deleteJson('/api/profiles/olena_k/follow')->assertOk();

        expect(DB::table('follows')->count())->toBe(0);
    });

    it('do not fail when two blocks arrive together', function () {
        $action = new BlockReader;
        $action->block($this->user, $this->olena);
        $action->block(User::query()->findOrFail($this->user->id), User::query()->findOrFail($this->olena->id));

        expect(DB::table('blocks')->count())->toBe(1);
    });
});
