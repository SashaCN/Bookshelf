<?php

use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->alice = User::factory()->public()->create(['username' => 'alice', 'name' => 'Alice']);
    $this->bob = User::factory()->public()->create(['username' => 'bob', 'name' => 'Bob']);
    $this->carol = User::factory()->public()->create(['username' => 'carol', 'name' => 'Carol']);
});

function blockQuote(User $user, bool $public = true): Quote
{
    $factory = Quote::factory()->for(UserBook::factory()->for($user)->reading(10)->create());

    return ($public ? $factory->public() : $factory)->create();
}

function blockUsernames($response): array
{
    return collect($response->json('data'))->pluck('username')->all();
}

it('requires authentication for the block endpoints', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['POST', '/api/users/alice/block'],
    ['DELETE', '/api/users/alice/block'],
    ['GET', '/api/me/blocks'],
]);

describe('blocking', function () {
    it('blocks and unblocks idempotently', function () {
        $this->actAs($this->alice);

        $this->postJson('/api/users/bob/block')->assertNoContent();
        $this->postJson('/api/users/bob/block')->assertNoContent();
        expect(DB::table('user_blocks')->count())->toBe(1);

        $this->deleteJson('/api/users/bob/block')->assertNoContent();
        $this->deleteJson('/api/users/bob/block')->assertNoContent();
        expect(DB::table('user_blocks')->count())->toBe(0);
    });

    it('answers 404 for an unknown handle', function (string $method) {
        $this->actAs($this->alice)->json($method, '/api/users/nobody/block')->assertNotFound();
    })->with(['POST', 'DELETE']);

    it('is case-insensitive about the handle', function () {
        $this->actAs($this->alice)->postJson('/api/users/BOB/block')->assertNoContent();
        expect(DB::table('user_blocks')->where('blocked_id', $this->bob->id)->exists())->toBeTrue();
    });

    it('can block a private profile', function () {
        $hidden = User::factory()->create(['username' => 'hidden', 'is_public' => false]);

        $this->actAs($this->alice)->postJson('/api/users/hidden/block')->assertNoContent();
        expect(DB::table('user_blocks')->where('blocked_id', $hidden->id)->exists())->toBeTrue();
    });

    it('refuses to block yourself', function () {
        $this->actAs($this->alice)->postJson('/api/users/alice/block')
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'block.self');

        expect(DB::table('user_blocks')->count())->toBe(0);
    });

    it('lists the blocked readers, newest block first, paginated', function () {
        $this->actAs($this->alice);
        $this->postJson('/api/users/bob/block');
        $this->travel(1)->minute();
        $this->postJson('/api/users/carol/block');

        $this->getJson('/api/me/blocks')
            ->assertOk()
            ->assertJsonPath('data', [
                ['username' => 'carol', 'name' => 'Carol'],
                ['username' => 'bob', 'name' => 'Bob'],
            ]);

        foreach (range(1, 20) as $i) {
            DB::table('user_blocks')->insert([
                'blocker_id' => $this->alice->id,
                'blocked_id' => User::factory()->public()->create()->id,
                'created_at' => now()->subDay(),
            ]);
        }
        $this->getJson('/api/me/blocks')->assertJsonCount(20, 'data');
        $this->getJson('/api/me/blocks?page=2')->assertJsonCount(2, 'data');
    });

    it('does not list who blocked you', function () {
        $this->actAs($this->alice)->postJson('/api/users/bob/block');

        $this->actAs($this->bob)->getJson('/api/me/blocks')->assertOk()->assertJsonCount(0, 'data');
    });

    it('removes follows in both directions and likes in both directions, in one go', function () {
        $aliceQuote = blockQuote($this->alice);
        $bobQuote = blockQuote($this->bob);
        $carolQuote = blockQuote($this->carol);

        $this->alice->following()->attach([$this->bob->id, $this->carol->id]);
        $this->bob->following()->attach([$this->alice->id, $this->carol->id]);
        foreach ([[$this->alice, $bobQuote], [$this->bob, $aliceQuote], [$this->alice, $carolQuote], [$this->bob, $carolQuote]] as [$user, $quote]) {
            DB::table('quote_likes')->insert(['user_id' => $user->id, 'quote_id' => $quote->id, 'created_at' => now()]);
        }

        $this->actAs($this->alice)->postJson('/api/users/bob/block')->assertNoContent();

        expect(DB::table('follows')->get()->map(fn ($f) => [$f->follower_id, $f->followed_id])->all())
            ->toEqualCanonicalizing([[$this->alice->id, $this->carol->id], [$this->bob->id, $this->carol->id]]);
        expect(DB::table('quote_likes')->get()->map(fn ($l) => [$l->user_id, $l->quote_id])->all())
            ->toEqualCanonicalizing([[$this->alice->id, $carolQuote->id], [$this->bob->id, $carolQuote->id]]);
    });

    it('keeps the counts of third parties consistent', function () {
        $this->carol->following()->attach([$this->alice->id, $this->bob->id]);
        $this->alice->following()->attach($this->bob->id);
        $this->actAs($this->carol)->getJson('/api/users/bob')->assertJsonPath('data.followers_count', 2);

        $this->actAs($this->alice)->postJson('/api/users/bob/block');

        $this->actAs($this->carol)->getJson('/api/users/bob')->assertJsonPath('data.followers_count', 1);
        $this->getJson('/api/users/bob/followers')->assertJsonCount(1, 'data');
    });

});

describe('visibility when A blocks B', function () {
    beforeEach(function () {
        // alice blocks bob; carol is an uninvolved third party.
        $this->aliceQuote = blockQuote($this->alice);
        $this->bobQuote = blockQuote($this->bob);
        $this->carolQuote = blockQuote($this->carol);
        $this->actAs($this->alice)->postJson('/api/users/bob/block')->assertNoContent();
    });

    it('hides the profile and its tabs from both sides', function (string $suffix) {
        $this->actAs($this->bob)->getJson("/api/users/alice{$suffix}")->assertNotFound();
        $this->actAs($this->alice)->getJson("/api/users/bob{$suffix}")->assertNotFound();
        $this->actAs($this->carol)->getJson("/api/users/alice{$suffix}")->assertOk();
        $this->actAs($this->carol)->getJson("/api/users/bob{$suffix}")->assertOk();
    })->with(['', '/quotes', '/reviews', '/followers', '/following']);

    it('refuses follow and like in both directions', function () {
        $this->actAs($this->bob);
        $this->postJson('/api/users/alice/follow')->assertNotFound();
        $this->postJson("/api/quotes/{$this->aliceQuote->id}/like")->assertNotFound();

        $this->actAs($this->alice);
        $this->postJson('/api/users/bob/follow')->assertNotFound();
        $this->postJson("/api/quotes/{$this->bobQuote->id}/like")->assertNotFound();

        expect(DB::table('follows')->count())->toBe(0)->and(DB::table('quote_likes')->count())->toBe(0);
    });

    it('does not keep a like that is taken back after the block', function () {
        $this->actAs($this->bob)->deleteJson("/api/quotes/{$this->aliceQuote->id}/like")->assertNotFound();
    });

    it('leaves each other out of search', function () {
        expect(blockUsernames($this->actAs($this->bob)->getJson('/api/users?q=al')))->not->toContain('alice');
        expect(blockUsernames($this->actAs($this->bob)->getJson('/api/users?q=ca')))->toContain('carol');
        expect(blockUsernames($this->actAs($this->alice)->getJson('/api/users?q=bo')))->not->toContain('bob');
        expect(blockUsernames($this->actAs($this->carol)->getJson('/api/users?q=bo')))->toContain('bob');
    });

    it('leaves each other out of the feed', function () {
        // A follow that survived (for instance inserted around the action) must not leak either.
        DB::table('follows')->insert([
            ['follower_id' => $this->bob->id, 'followed_id' => $this->alice->id, 'created_at' => now()],
            ['follower_id' => $this->bob->id, 'followed_id' => $this->carol->id, 'created_at' => now()],
            ['follower_id' => $this->alice->id, 'followed_id' => $this->bob->id, 'created_at' => now()],
            ['follower_id' => $this->alice->id, 'followed_id' => $this->carol->id, 'created_at' => now()],
        ]);

        $this->actAs($this->bob)->getJson('/api/feed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->carolQuote->id);
        $this->actAs($this->alice)->getJson('/api/feed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->carolQuote->id);
    });

    it('leaves each other out of the lists of a third party', function () {
        DB::table('follows')->insert([
            ['follower_id' => $this->alice->id, 'followed_id' => $this->carol->id, 'created_at' => now()],
            ['follower_id' => $this->bob->id, 'followed_id' => $this->carol->id, 'created_at' => now()],
            ['follower_id' => $this->carol->id, 'followed_id' => $this->alice->id, 'created_at' => now()],
            ['follower_id' => $this->carol->id, 'followed_id' => $this->bob->id, 'created_at' => now()],
        ]);

        $this->actAs($this->bob);
        expect(blockUsernames($this->getJson('/api/users/carol/followers')))->toBe(['bob'])
            ->and(blockUsernames($this->getJson('/api/users/carol/following')))->toBe(['bob']);
        $this->getJson('/api/users/carol')->assertJsonPath('data.followers_count', 1)->assertJsonPath('data.following_count', 1);

        $this->actAs($this->alice);
        expect(blockUsernames($this->getJson('/api/users/carol/followers')))->toBe(['alice']);

    });

    it('does not count the blocked in the new-follower badge', function () {
        DB::table('follows')->insert(['follower_id' => $this->bob->id, 'followed_id' => $this->alice->id, 'created_at' => now()]);

        $this->actAs($this->alice)->getJson('/api/me')->assertJsonPath('data.new_followers_count', 0);
    });

    it('leaves guests unaffected', function () {
        $this->actAsGuest();

        $this->getJson('/api/users/alice')->assertOk();
        $this->getJson('/api/users/bob')->assertOk();
        $this->getJson('/api/users/alice/quotes')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/users/bob/quotes')->assertOk()->assertJsonCount(1, 'data');
    });

    it('restores visibility on unblock but not the deleted follows or likes', function () {
        $this->actAs($this->alice)->deleteJson('/api/users/bob/block')->assertNoContent();

        $this->actAs($this->bob)->getJson('/api/users/alice')->assertOk()->assertJsonPath('data.is_following', false);
        $this->actAs($this->alice)->getJson('/api/users/bob')->assertOk();
        expect(blockUsernames($this->getJson('/api/users?q=bob')))->toContain('bob');
        $this->actAs($this->bob)->postJson("/api/quotes/{$this->aliceQuote->id}/like")->assertNoContent();
        expect(DB::table('follows')->count())->toBe(0);
    });

    it('does not let the blocked reader undo a block by unblocking', function () {
        $this->actAs($this->bob)->deleteJson('/api/users/alice/block')->assertNoContent();

        $this->actAs($this->bob)->getJson('/api/users/alice')->assertNotFound();
    });

    it('does not run a query per quote in the feed', function () {
        $this->actAs($this->carol);
        $this->carol->following()->attach($this->alice->id);
        foreach (range(1, 10) as $i) {
            blockQuote($this->alice);
        }
        $this->carol->following()->attach($this->bob->id);

        DB::enableQueryLog();
        $this->getJson('/api/feed')->assertOk()->assertJsonCount(12, 'data');

        expect(count(DB::getQueryLog()))->toBeLessThan(12);
    });
});
