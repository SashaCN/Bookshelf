<?php

use App\Actions\Social\FollowUser;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->alice = User::factory()->public()->create(['username' => 'alice', 'name' => 'Alice']);
    $this->hidden = User::factory()->create(['username' => 'hidden', 'is_public' => false]);
});

function quoteOf(User $user, bool $public = true, array $attributes = []): Quote
{
    $userBook = UserBook::factory()->for($user)->reading(10)->create();
    $factory = Quote::factory()->for($userBook);

    return ($public ? $factory->public() : $factory)->create($attributes);
}

it('requires authentication for every endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/feed'],
    ['GET', '/api/users?q=al'],
    ['GET', '/api/users/alice/followers'],
    ['GET', '/api/users/alice/following'],
    ['POST', '/api/users/alice/follow'],
    ['DELETE', '/api/users/alice/follow'],
]);

describe('profile settings', function () {
    it('returns the new fields from /api/me', function () {
        $this->actingAs($this->alice)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'alice')
            ->assertJsonPath('data.is_public', true);

        $this->actingAs($this->me)->getJson('/api/me')
            ->assertJsonPath('data.username', null)
            ->assertJsonPath('data.is_public', false);
    });

    it('sets a username and goes public, lowercasing the handle', function () {
        $this->actingAs($this->me)->patchJson('/api/me', ['username' => 'Bob_42', 'is_public' => true])
            ->assertOk()
            ->assertJsonPath('data.username', 'bob_42')
            ->assertJsonPath('data.is_public', true);

        expect($this->me->refresh()->username)->toBe('bob_42');
    });

    it('can go public when the username was set earlier', function () {
        $this->actingAs($this->hidden)->patchJson('/api/me', ['is_public' => true])->assertOk();
        expect($this->hidden->refresh()->is_public)->toBeTrue();
    });

    it('keeps the own username when it is sent again', function () {
        $this->actingAs($this->alice)->patchJson('/api/me', ['username' => 'alice'])->assertOk();
    });

    it('rejects a public profile without a username', function () {
        $this->actingAs($this->me)->patchJson('/api/me', ['is_public' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_public.0', 'profile.username_required');

        expect($this->me->refresh()->is_public)->toBeFalse();
    });

    it('rejects removing the username of a public profile', function () {
        $this->actingAs($this->alice)->patchJson('/api/me', ['username' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_public.0', 'profile.username_required');
    });

    it('rejects a malformed username', function (string $username) {
        $this->actingAs($this->me)->patchJson('/api/me', ['username' => $username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    })->with(['ab', 'has space', 'dash-ed', 'кирилиця', str_repeat('a', 31)]);

    it('rejects a taken username in any case', function () {
        $this->actingAs($this->me)->patchJson('/api/me', ['username' => 'ALICE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    });

    it('rejects a non-boolean is_public', function () {
        $this->actingAs($this->me)->patchJson('/api/me', ['is_public' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_public');
    });
});

describe('quote visibility', function () {
    beforeEach(function () {
        $this->userBook = UserBook::factory()->for($this->me)->reading(10)->create();
    });

    it('is private by default', function () {
        $this->actingAs($this->me)->postJson("/api/library/{$this->userBook->id}/quotes", ['content' => 'Hello'])
            ->assertCreated()
            ->assertJsonPath('data.visibility', 'private');
    });

    it('can be public from the start and changed later', function () {
        $id = $this->actingAs($this->me)->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'Hello',
            'visibility' => 'public',
        ])->assertCreated()->assertJsonPath('data.visibility', 'public')->json('data.id');

        $this->actingAs($this->me)->patchJson("/api/quotes/{$id}", ['visibility' => 'private'])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private');
    });

    it('rejects an unknown visibility', function () {
        $this->actingAs($this->me)->postJson("/api/library/{$this->userBook->id}/quotes", [
            'content' => 'Hello',
            'visibility' => 'friends',
        ])->assertUnprocessable()->assertJsonValidationErrors('visibility');

        $quote = quoteOf($this->me);
        $this->actingAs($this->me)->patchJson("/api/quotes/{$quote->id}", ['visibility' => 'friends'])
            ->assertUnprocessable()->assertJsonValidationErrors('visibility');
    });
});

describe('profile', function () {
    it('shows a public profile', function () {
        $fan = User::factory()->public()->create();
        $fan->following()->attach($this->alice->id);
        $this->alice->following()->attach($fan->id);
        $this->me->following()->attach($this->alice->id);
        UserBook::factory()->for($this->alice)->finished()->create();
        UserBook::factory()->for($this->alice)->finished()->create(['finished_at' => now()->subYears(2)]);
        UserBook::factory()->for($this->alice)->reading()->create();

        $this->actingAs($this->me)->getJson('/api/users/Alice')
            ->assertOk()
            ->assertExactJson(['data' => [
                'username' => 'alice',
                'name' => 'Alice',
                'books_finished_this_year' => 1,
                'followers_count' => 1,
                'following_count' => 1,
                'is_following' => true,
                'is_me' => false,
            ]]);
    });

    it('lets the owner see their own private profile', function () {
        $this->actingAs($this->hidden)->getJson('/api/users/hidden')
            ->assertOk()
            ->assertJsonPath('data.is_me', true)
            ->assertJsonPath('data.is_following', false);
    });

    it('answers 404 for private and missing profiles on every endpoint', function (string $method, string $suffix) {
        $this->actingAs($this->me)->json($method, "/api/users/hidden{$suffix}")->assertNotFound();
        $this->actingAs($this->me)->json($method, "/api/users/nobody{$suffix}")->assertNotFound();
    })->with([
        ['GET', ''],
        ['GET', '/quotes'],
        ['GET', '/followers'],
        ['GET', '/following'],
        ['POST', '/follow'],
        ['DELETE', '/follow'],
    ]);
});

describe('profile quotes', function () {
    it('lists only public quotes, newest first, with the book', function () {
        $old = quoteOf($this->alice);
        quoteOf($this->alice, false);
        $new = quoteOf($this->alice);
        quoteOf($this->me);

        $this->actingAs($this->me)->getJson('/api/users/alice/quotes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonPath('data.0.visibility', 'public')
            ->assertJsonPath('data.0.book.title', $new->userBook->book->title);
    });

    it('is paginated like the own quotes', function () {
        foreach (range(1, 21) as $i) {
            quoteOf($this->alice);
        }

        $this->actingAs($this->me)->getJson('/api/users/alice/quotes')
            ->assertJsonCount(20, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
        $this->actingAs($this->me)->getJson('/api/users/alice/quotes?page=2')->assertJsonCount(1, 'data');
    });
});

describe('guest access', function () {
    it('shows a public profile and its public quotes to a guest', function () {
        quoteOf($this->alice, true, ['content' => 'Open words', 'note' => 'my secret note', 'is_favorite' => true]);
        quoteOf($this->alice, false, ['content' => 'Secret words']);

        $this->getJson('/api/users/alice')->assertOk()
            ->assertJsonPath('data.username', 'alice')
            ->assertJsonPath('data.is_following', false)
            ->assertJsonPath('data.is_me', false);

        $response = $this->getJson('/api/users/alice/quotes')->assertOk()->assertJsonCount(1, 'data');
        expect($response->json('data.0.content'))->toBe('Open words');
    });

    it('answers 404 for a private or missing profile', function (string $suffix) {
        $this->getJson("/api/users/hidden{$suffix}")->assertNotFound();
        $this->getJson("/api/users/nobody{$suffix}")->assertNotFound();
    })->with(['', '/quotes']);

    it('keeps followers, following and following actions for signed-in readers', function (string $method, string $suffix) {
        $this->json($method, "/api/users/alice{$suffix}")->assertUnauthorized();
    })->with([['GET', '/followers'], ['GET', '/following'], ['POST', '/follow'], ['DELETE', '/follow']]);

    it('still knows a signed-in reader on the public routes', function () {
        app(FollowUser::class)->follow($this->me, $this->alice);

        $this->actingAs($this->me)->getJson('/api/users/alice')
            ->assertJsonPath('data.is_following', true)->assertJsonPath('data.is_me', false);
    });

    it('still lets a signed-in reader open their own private profile', function () {
        $this->actingAs($this->hidden)->getJson('/api/users/hidden')->assertOk()->assertJsonPath('data.is_me', true);
    });

    it('hides the notes and favorite flag of a quote from a guest', function () {
        quoteOf($this->alice, true, ['note' => 'my secret note', 'is_favorite' => true]);

        $this->getJson('/api/users/alice/quotes')->assertJsonMissingPath('data.0.note')->assertJsonMissingPath('data.0.is_favorite');
    });

    it('hides the notes and favorite flag of a quote from another reader', function () {
        quoteOf($this->alice, true, ['note' => 'my secret note', 'is_favorite' => true]);

        $this->actingAs($this->me)->getJson('/api/users/alice/quotes')
            ->assertJsonMissingPath('data.0.note')->assertJsonMissingPath('data.0.is_favorite');
    });

    it('keeps the notes and favorite flag for the owner', function () {
        quoteOf($this->alice, true, ['note' => 'my secret note', 'is_favorite' => true]);

        $this->actingAs($this->alice)->getJson('/api/users/alice/quotes')
            ->assertJsonPath('data.0.note', 'my secret note')->assertJsonPath('data.0.is_favorite', true);
    });

    it('throttles guests by address', function () {
        foreach (range(1, 60) as $ignored) {
            $this->getJson('/api/users/alice')->assertOk();
        }

        $this->getJson('/api/users/alice')->assertStatus(429);
    });
});

describe('following', function () {
    it('follows and unfollows idempotently', function () {
        $this->actingAs($this->me)->postJson('/api/users/alice/follow')->assertNoContent();
        $this->actingAs($this->me)->postJson('/api/users/alice/follow')->assertNoContent();
        expect($this->me->following()->count())->toBe(1);

        $this->actingAs($this->me)->deleteJson('/api/users/alice/follow')->assertNoContent();
        $this->actingAs($this->me)->deleteJson('/api/users/alice/follow')->assertNoContent();
        expect($this->me->following()->count())->toBe(0);
    });

    it('can unfollow a reader who went private since', function () {
        $this->me->following()->attach($this->hidden->id);

        $this->actingAs($this->me)->deleteJson('/api/users/hidden/follow')->assertNoContent();
        expect($this->me->following()->count())->toBe(0);
    });

    it('answers 404 when unfollowing a private or missing reader that is not followed', function () {
        $this->actingAs($this->me)->deleteJson('/api/users/hidden/follow')->assertNotFound();
        $this->actingAs($this->me)->deleteJson('/api/users/nobody/follow')->assertNotFound();
    });

    it('still answers 404 when following a private reader', function () {
        $this->actingAs($this->me)->postJson('/api/users/hidden/follow')->assertNotFound();
    });

    it('does not fail when the follow is made at the same moment from two requests', function () {
        // Two requests that both found "not following yet": the second insert meets the first one's row.
        $action = new FollowUser;
        $action->follow($this->me, $this->alice);
        $action->follow(User::query()->findOrFail($this->me->id), User::query()->findOrFail($this->alice->id));

        expect(DB::table('follows')->count())->toBe(1);
    });

    it('cannot follow yourself', function () {
        $this->actingAs($this->alice)->postJson('/api/users/alice/follow')
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'follow.self');
    });

    it('cannot follow yourself even with a private profile', function () {
        $this->actingAs($this->hidden)->postJson('/api/users/hidden/follow')
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'follow.self');
    });

    it('lists followers and following, public readers only', function () {
        $bob = User::factory()->public()->create(['username' => 'bob', 'name' => 'Bob']);
        $bob->following()->attach($this->alice->id);
        $this->hidden->following()->attach($this->alice->id);
        $this->alice->following()->attach([$bob->id, $this->hidden->id]);

        $this->actingAs($this->me)->getJson('/api/users/alice/followers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', ['username' => 'bob', 'name' => 'Bob']);

        $this->actingAs($this->me)->getJson('/api/users/alice/following')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'bob');
    });
});

describe('feed', function () {
    it('shows public quotes of followed readers with their author', function () {
        $bob = User::factory()->public()->create(['username' => 'bob', 'name' => 'Bob']);
        $this->me->following()->attach([$this->alice->id, $bob->id]);

        $first = quoteOf($this->alice);
        $second = quoteOf($bob);

        $this->actingAs($this->me)->getJson('/api/feed')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.author', ['username' => 'bob', 'name' => 'Bob'])
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.1.author.username', 'alice')
            ->assertJsonPath('data.1.book.title', $first->userBook->book->title);
    });

    it('leaves out private quotes, readers not followed and readers who went private', function () {
        $stranger = User::factory()->public()->create();
        $gone = User::factory()->public()->create();
        $this->me->following()->attach([$this->alice->id, $gone->id]);

        $shown = quoteOf($this->alice);
        quoteOf($this->alice, false);
        quoteOf($stranger);
        quoteOf($gone);
        $gone->update(['is_public' => false]);
        quoteOf($this->me);

        $this->actingAs($this->me)->getJson('/api/feed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shown->id);
    });

    it('is empty without follows and paginated otherwise', function () {
        $this->actingAs($this->me)->getJson('/api/feed')->assertOk()->assertJsonCount(0, 'data');

        $this->me->following()->attach($this->alice->id);
        foreach (range(1, 21) as $i) {
            quoteOf($this->alice);
        }
        $this->actingAs($this->me)->getJson('/api/feed')->assertJsonCount(20, 'data');
        $this->actingAs($this->me)->getJson('/api/feed?page=2')->assertJsonCount(1, 'data');
    });

    it('does not run a query per quote', function () {
        $this->me->following()->attach($this->alice->id);
        foreach (range(1, 10) as $i) {
            quoteOf($this->alice);
        }

        $this->actingAs($this->me);
        DB::enableQueryLog();
        $this->getJson('/api/feed')->assertOk();

        expect(count(DB::getQueryLog()))->toBeLessThan(12);
    });
});

describe('people search', function () {
    function publicUser(string $username, string $name = 'Someone'): User
    {
        return User::factory()->public()->create(['username' => $username, 'name' => $name]);
    }

    function searchUsernames($test, string $q): array
    {
        return collect($test->actingAs($test->me)->getJson('/api/users?'.http_build_query(['q' => $q]))
            ->assertOk()->json('data'))->pluck('username')->all();
    }

    it('requires authentication', function () {
        $this->getJson('/api/users?q=al')->assertUnauthorized();
    });

    it('validates q', function (array $query) {
        $this->actingAs($this->me)->getJson('/api/users?'.http_build_query($query))
            ->assertUnprocessable()->assertJsonValidationErrors('q');
    })->with([
        'missing' => [[]],
        'one char' => [['q' => 'a']],
        'blank' => [['q' => '   ']],
        'too long' => [['q' => str_repeat('a', 51)]],
    ]);

    it('matches by username prefix and by name substring, case-insensitively', function () {
        publicUser('zed', 'Marcus Aurelius');
        publicUser('bobby', 'Bob');

        expect(searchUsernames($this, 'ALI'))->toBe(['alice']);
        expect(searchUsernames($this, 'aurel'))->toBe(['zed']);
        expect(searchUsernames($this, 'obb'))->toBe([]);
    });

    it('strips a leading @', function () {
        expect(searchUsernames($this, '@alice'))->toBe(['alice']);
    });

    it('leaves out private readers, readers without a username and the viewer', function () {
        User::factory()->create(['username' => 'alpha', 'name' => 'Alpha', 'is_public' => false]);
        User::factory()->create(['username' => null, 'name' => 'Alice Nameless', 'is_public' => true]);
        $this->me->update(['username' => 'alicia', 'is_public' => true]);

        expect(searchUsernames($this, 'al'))->toBe(['alice']);
    });

    it('treats wildcards literally', function () {
        publicUser('a_b', 'Under');
        publicUser('axb', 'Plain');

        expect(searchUsernames($this, '__'))->toBe([]);
        expect(searchUsernames($this, '%%'))->toBe([]);
        expect(searchUsernames($this, 'a_'))->toBe(['a_b']);
        expect(searchUsernames($this, '\\\\'))->toBe([]);
    });

    it('orders exact handle, then prefix, then name matches', function () {
        publicUser('zzz', 'Carl Bobson');
        publicUser('bobby', 'Zed');
        publicUser('bob', 'Yan');
        publicUser('bobcat', 'Adam');

        expect(searchUsernames($this, 'bob'))->toBe(['bob', 'bobcat', 'bobby', 'zzz']);
    });

    it('returns at most 20 readers', function () {
        foreach (range(1, 25) as $i) {
            publicUser(sprintf('many%02d', $i));
        }

        expect(searchUsernames($this, 'many'))->toHaveCount(20);
    });

    it('flags the readers the viewer follows', function () {
        $bob = publicUser('alan', 'Alan');
        app(FollowUser::class)->follow($this->me, $bob);

        $this->actingAs($this->me)->getJson('/api/users?q=al')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['username' => 'alan', 'name' => 'Alan', 'is_following' => true],
                ['username' => 'alice', 'name' => 'Alice', 'is_following' => false],
            ]]);
    });
});
