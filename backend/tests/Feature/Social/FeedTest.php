<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->friend = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->user->following()->attach($this->friend->id);
});

function feedIds(string $query = ''): array
{
    return collect(test()->getJson("/api/feed{$query}")->assertOk()->json('data'))->pluck('id')->all();
}

it('requires authentication', function () {
    $this->getJson('/api/feed')->assertUnauthorized();
});

describe('the "following" scope', function () {
    it('shows my posts and those of the readers I follow, newest first', function () {
        $mine = Post::factory()->create(['user_id' => $this->user->id]);
        $friends = Post::factory()->create(['user_id' => $this->friend->id]);
        Post::factory()->create(['user_id' => $this->stranger->id]);
        $newest = Post::factory()->create(['user_id' => $this->friend->id]);

        $this->actingAs($this->user);

        expect(feedIds('?scope=following'))->toBe([$newest->id, $friends->id, $mine->id]);
    });

    it('is the default scope', function () {
        $friends = Post::factory()->create(['user_id' => $this->friend->id]);
        Post::factory()->create(['user_id' => $this->stranger->id]);

        $this->actingAs($this->user);

        expect(feedIds())->toBe([$friends->id]);
    });

    it('is empty for a reader who follows nobody and has not written anything', function () {
        Post::factory()->count(2)->create(['user_id' => $this->stranger->id]);
        $loner = User::factory()->create();

        $this->actingAs($loner)->getJson('/api/feed?scope=following')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('links.next', null);
    });

    it('does not show the posts of somebody I have unfollowed', function () {
        Post::factory()->create(['user_id' => $this->friend->id]);
        $this->user->following()->detach($this->friend->id);

        $this->actingAs($this->user);

        expect(feedIds())->toBe([]);
    });
});

describe('the "all" scope', function () {
    it('shows every visible post, newest first', function () {
        $mine = Post::factory()->create(['user_id' => $this->user->id]);
        $friends = Post::factory()->create(['user_id' => $this->friend->id]);
        $strangers = Post::factory()->review()->create(['user_id' => $this->stranger->id]);

        $this->actingAs($this->user);

        expect(feedIds('?scope=all'))->toBe([$strangers->id, $friends->id, $mine->id]);
    });
});

it('rejects an unknown scope', function () {
    $this->actingAs($this->user)->getJson('/api/feed?scope=popular')->assertUnprocessable()->assertJsonValidationErrors('scope');
});

describe('paging', function () {
    it('gives 20 posts a page and a link to the next one', function () {
        $posts = Post::factory()->count(25)->create(['user_id' => $this->friend->id]);

        $first = $this->actingAs($this->user)->getJson('/api/feed')->assertOk()->assertJsonCount(20, 'data');
        expect($first->json('links.next'))->not->toBeNull();

        $second = $this->actingAs($this->user)->getJson('/api/feed?page=2')->assertOk()->assertJsonCount(5, 'data');
        expect($second->json('links.next'))->toBeNull();

        $all = array_merge($first->json('data.*.id'), $second->json('data.*.id'));
        expect($all)->toBe($posts->pluck('id')->reverse()->values()->all());
    });

    it('works the same in the "all" scope', function () {
        Post::factory()->count(21)->create(['user_id' => $this->stranger->id]);

        $this->actingAs($this->user)->getJson('/api/feed?scope=all')->assertJsonCount(20, 'data');
        $this->actingAs($this->user)->getJson('/api/feed?scope=all&page=2')->assertJsonCount(1, 'data');
    });

    it('does not count what the reader cannot see towards a page', function () {
        Post::factory()->count(20)->create(['user_id' => $this->friend->id]);
        Post::factory()->count(5)->hidden()->create(['user_id' => $this->friend->id]);

        $this->actingAs($this->user)->getJson('/api/feed')
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('links.next', null);
    });
});

describe('what the reader never sees', function () {
    it('leaves out posts hidden by moderation', function () {
        $visible = Post::factory()->create(['user_id' => $this->friend->id]);
        Post::factory()->hidden()->create(['user_id' => $this->friend->id]);

        $this->actingAs($this->user);

        expect(feedIds())->toBe([$visible->id])->and(feedIds('?scope=all'))->toBe([$visible->id]);
    });

    it('shows the author their own hidden post, flagged', function () {
        $hidden = Post::factory()->hidden()->create(['user_id' => $this->user->id]);
        $normal = Post::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/feed')->assertOk();

        expect($response->json('data.*.id'))->toBe([$normal->id, $hidden->id])
            ->and($response->json('data.*.is_hidden'))->toBe([false, true]);
    });

    it('leaves out the posts of readers I have blocked', function () {
        $friends = Post::factory()->create(['user_id' => $this->friend->id]);
        Post::factory()->create(['user_id' => $this->stranger->id]);
        $this->user->blockedReaders()->attach($this->stranger->id);

        $this->actingAs($this->user);

        expect(feedIds('?scope=all'))->toBe([$friends->id]);
    });

    it('leaves out the posts of readers who have blocked me', function () {
        $friends = Post::factory()->create(['user_id' => $this->friend->id]);
        Post::factory()->create(['user_id' => $this->stranger->id]);
        $this->stranger->blockedReaders()->attach($this->user->id);

        $this->actingAs($this->user);

        expect(feedIds('?scope=all'))->toBe([$friends->id]);
    });

    it('leaves out a blocked reader even when a follow is still there', function () {
        Post::factory()->create(['user_id' => $this->friend->id]);
        $this->user->blockedReaders()->attach($this->friend->id);

        $this->actingAs($this->user);

        expect(feedIds())->toBe([])->and(feedIds('?scope=all'))->toBe([]);
    });

    it('does not let a block of two other readers change anything for me', function () {
        $post = Post::factory()->create(['user_id' => $this->stranger->id]);
        $this->friend->blockedReaders()->attach($this->stranger->id);

        $this->actingAs($this->user);

        expect(feedIds('?scope=all'))->toBe([$post->id]);
    });
});

describe('each post', function () {
    it('tells whether I have liked it and how many likes and comments it has', function () {
        $liked = Post::factory()->create(['user_id' => $this->friend->id, 'likes_count' => 2]);
        $liked->likedBy()->attach([$this->user->id, $this->stranger->id]);
        Comment::factory()->count(3)->for($liked)->create();
        $plain = Post::factory()->create(['user_id' => $this->friend->id]);

        $data = $this->actingAs($this->user)->getJson('/api/feed')->assertOk()->json('data');

        expect($data[0])->toMatchArray(['id' => $plain->id, 'liked_by_me' => false, 'likes_count' => 0, 'comments_count' => 0])
            ->and($data[1])->toMatchArray(['id' => $liked->id, 'liked_by_me' => true, 'likes_count' => 2, 'comments_count' => 3]);
    });

    it('counts only the comments the reader can see', function () {
        $post = Post::factory()->create(['user_id' => $this->friend->id, 'comments_count' => 4]);
        Comment::factory()->count(2)->for($post)->create();
        Comment::factory()->hidden()->for($post)->create();
        $blocked = User::factory()->create();
        Comment::factory()->for($post)->create(['user_id' => $blocked->id]);
        $this->user->blockedReaders()->attach($blocked->id);

        $this->actingAs($this->user)->getJson('/api/feed')->assertJsonPath('data.0.comments_count', 2);
    });

    it('counts my own hidden comment, which I can see', function () {
        $post = Post::factory()->create(['user_id' => $this->friend->id]);
        Comment::factory()->hidden()->for($post)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/feed')->assertJsonPath('data.0.comments_count', 1);
    });

    it('gives the name and handle of the author, and never an e-mail or a time zone', function () {
        Post::factory()->create(['user_id' => $this->friend->id]);

        $response = $this->actingAs($this->user)->getJson('/api/feed')->assertOk();

        expect($response->json('data.0.author'))->toBe(['handle' => $this->friend->handle, 'name' => $this->friend->name])
            ->and($response->getContent())->not->toContain($this->friend->email)->not->toContain('timezone');
    });

    it('is loaded with a fixed number of queries, however many posts there are', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->user)->getJson('/api/feed?scope=all')->assertOk();

            return count(DB::getQueryLog());
        };

        Post::factory()->count(2)->create();
        $few = $count();
        Post::factory()->count(15)->create();

        expect($count())->toBe($few);
    });
});
