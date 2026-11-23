<?php

use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['handle' => 'me_reader', 'timezone' => 'Asia/Tokyo']);
    $this->olena = User::factory()->create([
        'handle' => 'olena_k',
        'name' => 'Olena Kovalenko',
        'bio' => 'I read on trains.',
        'email' => 'olena.private@example.com',
        'timezone' => 'America/New_York',
    ]);
});

it('requires authentication for every endpoint', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with(['/api/profiles?q=a', '/api/profiles/olena_k', '/api/profiles/olena_k/posts']);

describe('a public profile', function () {
    it('shows the name, the handle, the bio and the numbers', function () {
        Post::factory()->count(2)->create(['user_id' => $this->olena->id]);
        $this->user->following()->attach($this->olena->id);
        $fan = User::factory()->create();
        $fan->following()->attach($this->olena->id);
        $this->olena->following()->attach($fan->id);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')
            ->assertOk()
            ->assertExactJson(['data' => [
                'handle' => 'olena_k',
                'name' => 'Olena Kovalenko',
                'bio' => 'I read on trains.',
                'posts_count' => 2,
                'followers_count' => 2,
                'following_count' => 1,
                'is_following' => true,
                'is_me' => false,
                'is_blocked' => false,
            ]]);
    });

    it('never gives away the e-mail address, the time zone or the id', function () {
        $body = $this->actingAs($this->user)->getJson('/api/profiles/olena_k')->assertOk()->getContent();

        expect($body)
            ->not->toContain('olena.private@example.com')
            ->not->toContain('America/New_York')
            ->not->toContain('email')
            ->not->toContain('timezone')
            ->not->toContain('"id"');
    });

    it('finds the reader whatever the case of the handle', function () {
        $this->actingAs($this->user)->getJson('/api/profiles/OLENA_K')->assertOk()->assertJsonPath('data.handle', 'olena_k');
    });

    it('answers 404 for a handle nobody has', function () {
        $this->actingAs($this->user)->getJson('/api/profiles/nobody_here')->assertNotFound();
    });

    it('knows my own profile', function () {
        $this->actingAs($this->user)->getJson('/api/profiles/me_reader')
            ->assertOk()
            ->assertJsonPath('data.is_me', true)
            ->assertJsonPath('data.is_following', false);
    });

    it('does not follow a reader who has not been followed', function () {
        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')->assertJsonPath('data.is_following', false);
    });

    it('counts only the posts that are visible to the viewer', function () {
        Post::factory()->count(2)->create(['user_id' => $this->olena->id]);
        Post::factory()->hidden()->create(['user_id' => $this->olena->id]);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')->assertJsonPath('data.posts_count', 2);
        // Her hidden post is still there for her.
        $this->actingAs($this->olena)->getJson('/api/profiles/olena_k')->assertJsonPath('data.posts_count', 3);
    });

    it('does not count followers and followed readers who have a block with the viewer', function () {
        $fine = User::factory()->create();
        $blockedByMe = User::factory()->create();
        $blockingMe = User::factory()->create();
        foreach ([$fine, $blockedByMe, $blockingMe] as $follower) {
            $follower->following()->attach($this->olena->id);
            $this->olena->following()->attach($follower->id);
        }
        $this->user->blockedReaders()->attach($blockedByMe->id);
        $blockingMe->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')
            ->assertJsonPath('data.followers_count', 1)
            ->assertJsonPath('data.following_count', 1);
        // For somebody else all of them count.
        $this->actingAs(User::factory()->create())->getJson('/api/profiles/olena_k')
            ->assertJsonPath('data.followers_count', 3)
            ->assertJsonPath('data.following_count', 3);
    });

    it('is "not found" when the reader has blocked me', function () {
        $this->olena->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts')->assertNotFound();
    });

    it('is shown to me, marked as blocked, when I have blocked the reader', function () {
        Post::factory()->create(['user_id' => $this->olena->id]);
        $this->user->blockedReaders()->attach($this->olena->id);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k')
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true)
            ->assertJsonPath('data.posts_count', 0);
        $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('the posts of a reader', function () {
    it('lists them newest first, and only theirs', function () {
        $first = Post::factory()->create(['user_id' => $this->olena->id]);
        $second = Post::factory()->review()->create(['user_id' => $this->olena->id]);
        Post::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts')->assertOk();

        expect($response->json('data.*.id'))->toBe([$second->id, $first->id])
            ->and($response->json('data.0.author.handle'))->toBe('olena_k');
    });

    it('is empty for a reader who has published nothing', function () {
        $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('links.next', null);
    });

    it('pages by 20', function () {
        Post::factory()->count(22)->create(['user_id' => $this->olena->id]);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts')->assertJsonCount(20, 'data');
        $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts?page=2')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('links.next', null);
    });

    it('leaves out hidden posts, except for the author', function () {
        $visible = Post::factory()->create(['user_id' => $this->olena->id]);
        $hidden = Post::factory()->hidden()->create(['user_id' => $this->olena->id]);

        $this->actingAs($this->user)->getJson('/api/profiles/olena_k/posts')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);

        $asAuthor = $this->actingAs($this->olena)->getJson('/api/profiles/olena_k/posts')->assertJsonCount(2, 'data');
        expect($asAuthor->json('data.*.id'))->toBe([$hidden->id, $visible->id]);
    });

    it('answers 404 for a handle nobody has', function () {
        $this->actingAs($this->user)->getJson('/api/profiles/nobody_here/posts')->assertNotFound();
    });
});

describe('searching for readers', function () {
    beforeEach(function () {
        User::factory()->create(['handle' => 'olenka', 'name' => 'Olenka Shevchenko']);
        User::factory()->create(['handle' => 'taras_book', 'name' => 'Taras Hryhorovych']);
        User::factory()->create(['handle' => 'reader_7', 'name' => 'Ivan Olenych']);
    });

    function search(string $q): array
    {
        return collect(test()->actingAs(test()->user)->getJson('/api/profiles?q='.urlencode($q))->assertOk()->json('data'))->pluck('handle')->all();
    }

    it('finds readers by the beginning of the handle, ordered by handle', function () {
        expect(search('olen'))->toBe(['olena_k', 'olenka', 'reader_7']);
    });

    it('finds readers by the beginning of the name, or of any word of it', function () {
        expect(search('Taras'))->toBe(['taras_book'])
            ->and(search('shevch'))->toBe(['olenka'])
            ->and(search('Hryh'))->toBe(['taras_book']);
    });

    it('does not find readers by the middle of a word', function () {
        expect(search('lena'))->toBe([])->and(search('book'))->toBe([]);
    });

    it('does not mind the case or a leading @', function () {
        expect(search('@TARAS'))->toBe(['taras_book'])->and(search('OLENA_K'))->toBe(['olena_k']);
    });

    it('takes "%" and "_" for what they are, not for wildcards', function () {
        expect(search('%%'))->toBe([])
            ->and(search('__'))->toBe([])
            ->and(search('olena_'))->toBe(['olena_k'])
            ->and(search('olen_'))->toBe([]);
    });

    it('does not find readers who have a block with me, in either direction', function () {
        $this->user->blockedReaders()->attach(User::query()->firstWhere('handle', 'olenka')->id);
        User::query()->firstWhere('handle', 'reader_7')->blockedReaders()->attach($this->user->id);

        expect(search('olen'))->toBe(['olena_k']);
    });

    it('shows a reader as followed or not, and marks me', function () {
        $this->user->following()->attach($this->olena->id);

        $data = collect($this->actingAs($this->user)->getJson('/api/profiles?q=olen')->json('data'))->keyBy('handle');

        expect($data['olena_k']['is_following'])->toBeTrue()
            ->and($data['olenka']['is_following'])->toBeFalse()
            ->and($this->actingAs($this->user)->getJson('/api/profiles?q=me_')->json('data.0.is_me'))->toBeTrue();
    });

    it('gives only the public fields of a reader', function () {
        $response = $this->actingAs($this->user)->getJson('/api/profiles?q=olena_k')->assertOk();

        expect($response->json('data.0'))->toBe([
            'handle' => 'olena_k',
            'name' => 'Olena Kovalenko',
            'bio' => 'I read on trains.',
            'is_me' => false,
            'is_following' => false,
        ]);
        expect($response->getContent())->not->toContain('olena.private@example.com')->not->toContain('timezone');
    });

    it('pages by 20', function () {
        User::factory()->count(22)->sequence(fn ($sequence) => ['handle' => 'zz_reader_'.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();

        $this->actingAs($this->user)->getJson('/api/profiles?q=zz_')->assertJsonCount(20, 'data');
        $this->actingAs($this->user)->getJson('/api/profiles?q=zz_&page=2')->assertJsonCount(2, 'data');
    });

    it('needs something to search for', function (string $uri) {
        $this->actingAs($this->user)->getJson($uri)->assertUnprocessable()->assertJsonValidationErrors('q');
    })->with([
        'no query' => ['/api/profiles'],
        'empty' => ['/api/profiles?q='],
        'only an @' => ['/api/profiles?q=@'],
        'only @s' => ['/api/profiles?q=@@@'],
        'a single letter' => ['/api/profiles?q=a'],
        'a single letter after the @' => ['/api/profiles?q=@a'],
        'a single percent sign' => ['/api/profiles?q=%25'],
        'a single underscore' => ['/api/profiles?q=_'],
        'spaces' => ['/api/profiles?q=%20%20%20'],
        'too long' => ['/api/profiles?q='.'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
    ]);

    it('never lists readers for a query that is too short', function () {
        $this->actingAs($this->user)->getJson('/api/profiles?q=@')->assertUnprocessable()->assertJsonMissingPath('data');
        $this->actingAs($this->user)->getJson('/api/profiles?q=o')->assertUnprocessable()->assertJsonMissingPath('data');
    });

    it('searches for two characters, with or without the @', function () {
        expect(search('ol'))->toBe(['olena_k', 'olenka', 'reader_7'])->and(search('@ta'))->toBe(['taras_book']);
    });

    it('lets a reader search 30 times a minute', function () {
        foreach (range(1, 30) as $unused) {
            $this->actingAs($this->user)->getJson('/api/profiles?q=olen')->assertOk();
        }

        $this->actingAs($this->user)->getJson('/api/profiles?q=olen')->assertTooManyRequests();
        $this->actingAs(User::factory()->create())->getJson('/api/profiles?q=olen')->assertOk();
    });
});
