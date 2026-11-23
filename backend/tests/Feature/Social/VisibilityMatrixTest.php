<?php

use App\Models\Quote;
use App\Models\QuoteComment;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

/*
 * Who may see what, per viewer. Subject "sub" is public with one public quote that carries a like and a comment from
 * "fan" and a like and a comment from "foe". Viewers differ in their relation to those two and to the subject.
 */
beforeEach(function () {
    $this->sub = User::factory()->public()->create(['username' => 'sub']);
    $this->fan = User::factory()->public()->create(['username' => 'fan']);
    $this->foe = User::factory()->public()->create(['username' => 'foe']);
    $this->viewer = User::factory()->public()->create(['username' => 'viewer']);

    $book = UserBook::factory()->for($this->sub)->reading(10)->create(['review' => 'Great', 'review_visibility' => 'public']);
    $this->quote = Quote::factory()->for($book)->public()->create(['note' => 'secret', 'is_favorite' => true]);
    foreach ([$this->fan, $this->foe] as $reader) {
        DB::table('quote_likes')->insert(['user_id' => $reader->id, 'quote_id' => $this->quote->id, 'created_at' => now()]);
        QuoteComment::factory()->create(['quote_id' => $this->quote->id, 'user_id' => $reader->id]);
        DB::table('follows')->insert(['follower_id' => $reader->id, 'followed_id' => $this->sub->id, 'created_at' => now()]);
    }
    // The viewer blocked "foe" (after foe's activity, which stays), so foe's likes, comments and follow are invisible to them.
    DB::table('user_blocks')->insert(['blocker_id' => $this->viewer->id, 'blocked_id' => $this->foe->id, 'created_at' => now()]);
});

function actAsViewer(string $who): void
{
    $test = test();
    match ($who) {
        'guest' => $test->actAsGuest(),
        'owner' => $test->actAs($test->sub),
        'viewer' => $test->actAs($test->viewer),
        'foe' => $test->actAs($test->foe),
    };
}

it('counts only what the viewer can see on a public quote', function (string $who, int $likes, int $comments, int $followers) {
    actAsViewer($who);

    $profile = $this->getJson('/api/users/sub');
    $quote = $this->getJson('/api/users/sub/quotes');

    $profile->assertOk()->assertJsonPath('data.followers_count', $followers)->assertJsonPath('data.reviews_count', 1);
    $quote->assertOk()->assertJsonPath('data.0.likes_count', $likes)->assertJsonPath('data.0.comments_count', $comments);
    $this->getJson("/api/quotes/{$this->quote->id}/comments")->assertOk()->assertJsonCount($comments, 'data');
})->with([
    'guest sees all' => ['guest', 2, 2, 2],
    'owner sees all' => ['owner', 2, 2, 2],
    'blocking viewer does not count the blocked reader' => ['viewer', 1, 1, 1],
]);

it('hides everything of the subject from a reader the subject blocked or who is blocked', function (string $uri) {
    DB::table('user_blocks')->insert(['blocker_id' => $this->sub->id, 'blocked_id' => $this->viewer->id, 'created_at' => now()]);
    $this->actAs($this->viewer)->getJson($uri)->assertNotFound();
})->with(['/api/users/sub', '/api/users/sub/quotes', '/api/users/sub/reviews', '/api/users/sub/followers', '/api/users/sub/following']);

it('leaks no owner-only fields to anyone but the owner', function (string $who, bool $owner) {
    actAsViewer($who);

    $row = $this->getJson('/api/users/sub/quotes')->assertOk()->json('data.0');

    expect(array_key_exists('note', $row))->toBe($owner)
        ->and(array_key_exists('is_favorite', $row))->toBe($owner);
    expect($this->getJson('/api/users/sub/reviews')->json('data.0'))->not->toHaveKeys(['status', 'user_id', 'finished_at']);
})->with([['guest', false], ['viewer', false], ['owner', true]]);

it('answers hidden and missing targets the same way', function (string $method, string $uri) {
    $private = User::factory()->create(['username' => 'shy']);
    $hiddenQuote = Quote::factory()->for(UserBook::factory()->for($this->sub)->reading(10)->create())->create();
    $this->actAs($this->viewer);

    $missing = str_replace(['{u}', '{q}'], ['nobody', '99999999'], $uri);
    $hidden = str_replace(['{u}', '{q}'], [$private->username, (string) $hiddenQuote->id], $uri);

    expect($this->json($method, $hidden, ['body' => 'x'])->getStatusCode())
        ->toBe($this->json($method, $missing, ['body' => 'x'])->getStatusCode())
        ->toBe(404);
})->with([
    ['POST', '/api/users/{u}/follow'],
    ['GET', '/api/users/{u}/followers'],
    ['GET', '/api/users/{u}'],
    ['POST', '/api/quotes/{q}/like'],
    ['DELETE', '/api/quotes/{q}/like'],
    ['GET', '/api/quotes/{q}/comments'],
    ['POST', '/api/quotes/{q}/comments'],
]);

it('answers blocking a private reader and a missing one alike', function () {
    $private = User::factory()->create(['username' => 'shy']);
    $this->actAs($this->viewer);

    expect($this->postJson('/api/users/shy/block')->getStatusCode())->toBe($this->postJson('/api/users/nobody/block')->getStatusCode());
});

it('keeps blocked readers out of search, feed and the new-followers badge', function () {
    $this->actAs($this->viewer);
    DB::table('follows')->insert(['follower_id' => $this->viewer->id, 'followed_id' => $this->foe->id, 'created_at' => now()]);

    $this->getJson('/api/users?q=foe')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/feed')->assertOk()->assertJsonCount(0, 'data');

    $this->actAs($this->sub);
    expect($this->getJson('/api/me')->json('data.new_followers_count'))->toBe(2);
    $this->actAs($this->viewer);
    DB::table('follows')->insert(['follower_id' => $this->foe->id, 'followed_id' => $this->viewer->id, 'created_at' => now()]);
    expect($this->getJson('/api/me')->json('data.new_followers_count'))->toBe(0);
});

it('throttles follow, like and block', function (string $method, string $uri) {
    $this->actAs($this->viewer);
    foreach (range(1, 60) as $_) {
        $this->json($method, $uri);
    }
    $this->json($method, $uri)->assertStatus(429);
})->with([
    ['POST', '/api/users/fan/follow'],
    ['POST', '/api/quotes/1/like'],
    ['DELETE', '/api/users/fan/block'],
]);

it('runs the public profile endpoints in a constant number of queries', function () {
    foreach (range(1, 5) as $i) {
        $u = User::factory()->public()->create();
        QuoteComment::factory()->create(['quote_id' => $this->quote->id, 'user_id' => $u->id]);
        Quote::factory()->for(UserBook::factory()->for($this->sub)->reading(10)->create())->public()->create();
    }
    $this->actAs($this->viewer);

    DB::enableQueryLog();
    $this->getJson('/api/users/sub/quotes')->assertOk();
    $this->getJson("/api/quotes/{$this->quote->id}/comments")->assertOk();
    $this->getJson('/api/users/sub/followers')->assertOk();
    expect(count(DB::getQueryLog()))->toBeLessThan(25);
});
