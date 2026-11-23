<?php

use App\Models\Quote;
use App\Models\QuoteComment;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::factory()->public()->create(['username' => 'owner', 'name' => 'Owner']);
    $this->me = User::factory()->public()->create(['username' => 'me', 'name' => 'Me']);
    $this->other = User::factory()->public()->create(['username' => 'other', 'name' => 'Other']);
    $this->quote = commentQuote($this->owner);
});

function commentQuote(User $user, bool $public = true): Quote
{
    $factory = Quote::factory()->for(UserBook::factory()->for($user)->reading(10)->create());

    return ($public ? $factory->public() : $factory)->create();
}

function commentOn(Quote $quote, User $user, string $body = 'Nice'): QuoteComment
{
    return QuoteComment::factory()->create(['quote_id' => $quote->id, 'user_id' => $user->id, 'body' => $body]);
}

describe('reading', function () {
    it('lists comments oldest first with the author and can_delete', function () {
        commentOn($this->quote, $this->other, 'first');
        commentOn($this->quote, $this->me, 'second');

        $this->actAs($this->me)->getJson("/api/quotes/{$this->quote->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'first')
            ->assertJsonPath('data.0.author', ['username' => 'other', 'name' => 'Other'])
            ->assertJsonPath('data.0.can_delete', false)
            ->assertJsonPath('data.1.body', 'second')
            ->assertJsonPath('data.1.can_delete', true);
    });

    it('lets a guest read, without delete rights', function () {
        commentOn($this->quote, $this->other);

        $this->actAsGuest()->getJson("/api/quotes/{$this->quote->id}/comments")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_delete', false);
    });

    it('lets the quote owner delete any comment', function () {
        commentOn($this->quote, $this->other);

        $this->actAs($this->owner)->getJson("/api/quotes/{$this->quote->id}/comments")->assertJsonPath('data.0.can_delete', true);
    });

    it('shows an author without a public profile or handle as anonymous', function () {
        commentOn($this->quote, User::factory()->create(['username' => 'shy', 'name' => 'Shy', 'is_public' => false]));
        commentOn($this->quote, User::factory()->create(['username' => null, 'name' => 'NoHandle', 'is_public' => true]));

        $this->actAsGuest()->getJson("/api/quotes/{$this->quote->id}/comments")
            ->assertJsonPath('data.0.author', ['username' => null, 'name' => null])
            ->assertJsonPath('data.1.author', ['username' => null, 'name' => null]);
    });

    it('paginates', function () {
        QuoteComment::factory()->count(21)->create(['quote_id' => $this->quote->id, 'user_id' => $this->other->id]);

        $this->getJson("/api/quotes/{$this->quote->id}/comments")->assertJsonCount(20, 'data')->assertJsonPath('meta.current_page', 1);
        $this->getJson("/api/quotes/{$this->quote->id}/comments?page=2")->assertJsonCount(1, 'data');
    });

    it('hides the comments of readers blocked in either direction', function (string $direction) {
        commentOn($this->quote, $this->other, 'from other');
        commentOn($this->quote, $this->me, 'from me');
        $pair = $direction === 'viewer blocks' ? ['blocker_id' => $this->me->id, 'blocked_id' => $this->other->id]
            : ['blocker_id' => $this->other->id, 'blocked_id' => $this->me->id];
        DB::table('user_blocks')->insert($pair + ['created_at' => now()]);

        $response = $this->actAs($this->me)->getJson("/api/quotes/{$this->quote->id}/comments")->assertOk();
        expect(collect($response->json('data'))->pluck('body')->all())->toBe(['from me']);
    })->with(['viewer blocks', 'viewer is blocked']);

    it('answers 404 for a quote that is not visible', function (string $case) {
        $quote = match ($case) {
            'private quote' => commentQuote($this->owner, false),
            'private profile' => commentQuote(User::factory()->create(['is_public' => false])),
            'missing' => new Quote(['id' => 999999]),
            'blocked owner' => $this->quote,
            'blocking owner' => $this->quote,
        };
        if ($case === 'blocked owner') {
            DB::table('user_blocks')->insert(['blocker_id' => $this->me->id, 'blocked_id' => $this->owner->id, 'created_at' => now()]);
        }
        if ($case === 'blocking owner') {
            DB::table('user_blocks')->insert(['blocker_id' => $this->owner->id, 'blocked_id' => $this->me->id, 'created_at' => now()]);
        }
        $id = $quote->id ?? 999999;

        $this->actAs($this->me)->getJson("/api/quotes/{$id}/comments")->assertNotFound();
        $this->actAs($this->me)->postJson("/api/quotes/{$id}/comments", ['body' => 'hi'])->assertNotFound();
        $this->actAs($this->me)->deleteJson("/api/quotes/{$id}/comments/1")->assertNotFound();
    })->with(['private quote', 'private profile', 'missing', 'blocked owner', 'blocking owner']);

    it('answers 404 to a guest for a private quote', function () {
        $quote = commentQuote($this->owner, false);

        $this->actAsGuest()->getJson("/api/quotes/{$quote->id}/comments")->assertNotFound();
    });

    it('counts only visible comments in the feed and profile lists', function () {
        $this->owner->followers()->attach($this->me->id);
        $second = commentQuote($this->owner);
        commentOn($this->quote, $this->other);
        commentOn($this->quote, $this->me);
        commentOn($second, $this->other);
        DB::table('user_blocks')->insert(['blocker_id' => $this->me->id, 'blocked_id' => $this->other->id, 'created_at' => now()]);

        $expected = [$second->id => 0, $this->quote->id => 1];
        $feed = $this->actAs($this->me)->getJson('/api/feed')->assertOk();
        expect(collect($feed->json('data'))->pluck('comments_count', 'id')->all())->toBe($expected);

        $profile = $this->getJson('/api/users/owner/quotes')->assertOk();
        expect(collect($profile->json('data'))->pluck('comments_count', 'id')->all())->toBe($expected);
    });

    it('counts comments in the lists without a query per quote', function () {
        $this->owner->followers()->attach($this->me->id);
        foreach (range(1, 6) as $ignored) {
            commentOn(commentQuote($this->owner), $this->other);
        }

        $this->actAs($this->me);
        DB::enableQueryLog();
        $this->getJson('/api/feed')->assertOk()->assertJsonCount(7, 'data');
        expect(count(DB::getQueryLog()))->toBeLessThan(10);

        DB::flushQueryLog();
        $this->getJson('/api/users/owner/quotes')->assertOk()->assertJsonCount(7, 'data');
        expect(count(DB::getQueryLog()))->toBeLessThan(10);
    });

    it('does not run a query per comment', function () {
        foreach (range(1, 8) as $i) {
            commentOn($this->quote, User::factory()->public()->create(['username' => "reader{$i}"]));
        }

        $this->actAs($this->me);
        DB::enableQueryLog();
        $this->getJson("/api/quotes/{$this->quote->id}/comments")->assertOk()->assertJsonCount(8, 'data');
        expect(count(DB::getQueryLog()))->toBeLessThan(8);
    });
});

describe('posting', function () {
    it('creates a comment', function () {
        $this->actAs($this->me)->postJson("/api/quotes/{$this->quote->id}/comments", ['body' => "  Great\nquote  "])
            ->assertCreated()
            ->assertJsonPath('data.body', "Great\nquote")
            ->assertJsonPath('data.author.username', 'me')
            ->assertJsonPath('data.can_delete', true);

        expect(QuoteComment::query()->where('user_id', $this->me->id)->count())->toBe(1);
    });

    it('requires authentication', function () {
        $this->actAsGuest()->postJson("/api/quotes/{$this->quote->id}/comments", ['body' => 'hi'])->assertUnauthorized();
        $this->actAsGuest()->deleteJson("/api/quotes/{$this->quote->id}/comments/1")->assertUnauthorized();
    });

    it('validates the length', function (mixed $body) {
        $this->actAs($this->me)->postJson("/api/quotes/{$this->quote->id}/comments", ['body' => $body])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
    })->with([[null], [''], ['   '], [str_repeat('a', 1001)], [['x']]]);

    it('accepts exactly 1000 characters', function () {
        $this->actAs($this->me)->postJson("/api/quotes/{$this->quote->id}/comments", ['body' => str_repeat('я', 1000)])->assertCreated();
    });

    it('throttles posting per reader', function () {
        $this->actAs($this->me);

        foreach (range(1, 10) as $ignored) {
            $this->postJson("/api/quotes/{$this->quote->id}/comments", ['body' => 'hi'])->assertCreated();
        }

        $this->postJson("/api/quotes/{$this->quote->id}/comments", ['body' => 'hi'])->assertTooManyRequests();
        $this->actAs($this->other)->postJson("/api/quotes/{$this->quote->id}/comments", ['body' => 'hi'])->assertCreated();
    });
});

describe('deleting', function () {
    it('lets the author delete', function () {
        $comment = commentOn($this->quote, $this->me);

        $this->actAs($this->me)->deleteJson("/api/quotes/{$this->quote->id}/comments/{$comment->id}")->assertNoContent();
        expect(QuoteComment::query()->count())->toBe(0);
    });

    it('lets the quote owner delete', function () {
        $comment = commentOn($this->quote, $this->other);

        $this->actAs($this->owner)->deleteJson("/api/quotes/{$this->quote->id}/comments/{$comment->id}")->assertNoContent();
        expect(QuoteComment::query()->count())->toBe(0);
    });

    it('answers 404 to anybody else and keeps the comment', function () {
        $comment = commentOn($this->quote, $this->other);

        $this->actAs($this->me)->deleteJson("/api/quotes/{$this->quote->id}/comments/{$comment->id}")->assertNotFound();
        expect(QuoteComment::query()->count())->toBe(1);
    });

    it('answers 404 for a comment of another quote', function () {
        $comment = commentOn(commentQuote($this->owner), $this->me);

        $this->actAs($this->me)->deleteJson("/api/quotes/{$this->quote->id}/comments/{$comment->id}")->assertNotFound();
        expect(QuoteComment::query()->count())->toBe(1);
    });
});

describe('cascades', function () {
    it('deletes comments with the quote and with their author', function () {
        commentOn($this->quote, $this->me);
        $other = commentQuote($this->owner);
        commentOn($other, $this->me);
        commentOn($other, $this->other);

        $this->quote->delete();
        expect(QuoteComment::query()->count())->toBe(2);

        $this->me->delete();
        expect(QuoteComment::query()->pluck('user_id')->all())->toBe([$this->other->id]);
    });

    it('deletes the comments between two readers when one blocks the other, and only those', function () {
        $mine = commentQuote($this->me);
        commentOn($this->quote, $this->me);
        commentOn($mine, $this->owner);
        $kept = commentOn($this->quote, $this->other);
        $keptOnOther = commentOn(commentQuote($this->other), $this->me);

        $this->actAs($this->me)->postJson('/api/users/owner/block')->assertNoContent();

        expect(QuoteComment::query()->pluck('id')->sort()->values()->all())->toBe(collect([$kept->id, $keptOnOther->id])->sort()->values()->all());
    });

    it('deletes comments when the account is deleted', function () {
        commentOn($this->quote, $this->me);
        $this->me->delete();

        expect(QuoteComment::query()->count())->toBe(0);
    });
});
