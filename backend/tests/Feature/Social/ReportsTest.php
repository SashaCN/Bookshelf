<?php

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->author = User::factory()->create();
    $this->post = Post::factory()->create(['user_id' => $this->author->id]);
    $this->comment = Comment::factory()->for($this->post)->create(['user_id' => $this->author->id]);
});

/**
 * Report the post from `$count` more readers, returning them.
 */
function reportersOf(Post|Comment $target, int $count): Collection
{
    $kind = $target instanceof Post ? 'post' : 'comment';

    return User::factory()->count($count)->create()->each(
        fn (User $reporter) => test()->actingAs($reporter)->postJson("/api/{$kind}s/{$target->id}/report", ['reason' => 'spam'])->assertCreated(),
    );
}

it('requires authentication', function (string $uri) {
    $this->postJson($uri, ['reason' => 'spam'])->assertUnauthorized();
})->with(['/api/posts/1/report', '/api/comments/1/report']);

describe('reporting a post', function () {
    it('stores the report', function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'abuse', 'note' => 'Insults other readers.'])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'abuse')
            ->assertJsonPath('data.status', 'open');

        $report = Report::query()->sole();
        expect($report)
            ->reporter_id->toBe($this->user->id)
            ->reportable_type->toBe('post')
            ->reportable_id->toBe($this->post->id)
            ->reason->toBe(ReportReason::Abuse)
            ->note->toBe('Insults other readers.')
            ->status->toBe(ReportStatus::Open);
    });

    it('does not need a note', function (string $reason) {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => $reason])
            ->assertCreated()
            ->assertJsonPath('data.reason', $reason);

        expect(Report::query()->sole()->note)->toBeNull();
    })->with(['spam', 'abuse', 'other']);

    it('takes one report per reader per post, whatever the reason', function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertCreated();

        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'abuse'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason', ['social.already_reported']);

        expect(Report::query()->count())->toBe(1);
    });

    it('takes a report even after the first one was resolved: still one per reader', function () {
        Report::factory()->about($this->post)->resolved()->create(['reporter_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason', ['social.already_reported']);
    });

    it('lets a reader report several posts, and several readers report one post', function () {
        $other = Post::factory()->create();

        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->actingAs($this->user)->postJson("/api/posts/{$other->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->actingAs(User::factory()->create())->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertCreated();

        expect(Report::query()->count())->toBe(3);
    });

    it('does not let an author report their own post', function () {
        $this->actingAs($this->author)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason', ['social.cannot_report_own']);

        expect(Report::query()->count())->toBe(0);
    });

    it('validates the body', function (array $body, string $field) {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'no reason' => [[], 'reason'],
        'unknown reason' => [['reason' => 'boring'], 'reason'],
        'note too long' => [['reason' => 'spam', 'note' => str_repeat('a', 501)], 'note'],
    ]);

    it('accepts a note of 500 characters', function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam', 'note' => str_repeat('a', 500)])
            ->assertCreated();
    });

    it('answers "not found" for a post the reader cannot see, whatever is sent', function () {
        $hidden = Post::factory()->hidden()->create();
        $blocked = Post::factory()->create();
        $this->user->blockedReaders()->attach($blocked->user_id);

        foreach ([$hidden, $blocked] as $post) {
            $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/report", ['reason' => 'spam'])->assertNotFound();
            $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/report", [])->assertNotFound();
        }
        $this->actingAs($this->user)->postJson('/api/posts/999999/report', ['reason' => 'spam'])->assertNotFound();

        expect(Report::query()->count())->toBe(0);
    });
});

describe('hiding a post after three reports', function () {
    it('keeps the post visible after two reports', function () {
        reportersOf($this->post, 2);

        expect($this->post->fresh()->hidden_at)->toBeNull();
        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}")->assertOk();
    });

    it('hides the post with the third report from a different reader', function () {
        reportersOf($this->post, 3);

        expect($this->post->fresh()->hidden_at)->not->toBeNull();
    });

    it('hides it from everybody but the author, in the feed and on the post page', function () {
        reportersOf($this->post, 3);

        $this->actingAs($this->user)->getJson("/api/posts/{$this->post->id}")->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/feed?scope=all')->assertJsonCount(0, 'data');
        $this->actingAs($this->user)->getJson("/api/profiles/{$this->author->handle}/posts")->assertJsonCount(0, 'data');

        $this->actingAs($this->author)->getJson("/api/posts/{$this->post->id}")->assertOk()->assertJsonPath('data.is_hidden', true);
        $this->actingAs($this->author)->getJson('/api/feed?scope=all')->assertJsonPath('data.0.is_hidden', true);
    });

    it('does not count one reader twice', function () {
        $repeat = User::factory()->create();
        $this->actingAs($repeat)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->actingAs($repeat)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertUnprocessable();
        $this->actingAs($repeat)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'abuse'])->assertUnprocessable();
        reportersOf($this->post, 1);

        expect($this->post->fresh()->hidden_at)->toBeNull();
    });

    it('counts only open reports: a resolved one does not help', function () {
        Report::factory()->about($this->post)->resolved()->count(2)->create();
        reportersOf($this->post, 2);

        expect($this->post->fresh()->hidden_at)->toBeNull();

        reportersOf($this->post, 1);

        expect($this->post->fresh()->hidden_at)->not->toBeNull();
    });

    it('does not count the reports about something else', function () {
        $other = Post::factory()->create();
        reportersOf($other, 2);
        reportersOf($this->post, 1);
        reportersOf($this->comment, 2);

        expect($this->post->fresh()->hidden_at)->toBeNull()
            ->and($other->fresh()->hidden_at)->toBeNull()
            ->and($this->comment->fresh()->hidden_at)->toBeNull();
    });

    it('hides only the post that was reported', function () {
        $other = Post::factory()->create(['user_id' => $this->author->id]);
        reportersOf($this->post, 3);

        expect($other->fresh()->hidden_at)->toBeNull();
    });

    it('does not let anybody report a post that is hidden already', function () {
        reportersOf($this->post, 3);

        $this->actingAs(User::factory()->create())->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertNotFound();
    });

    it('leaves the reports open for a moderator', function () {
        reportersOf($this->post, 3);

        expect(Report::query()->where('status', ReportStatus::Open)->count())->toBe(3);
    });
});

describe('reports made at the same time', function () {
    /**
     * The statements one report makes, in order, as the database saw them.
     *
     * @return list<string>
     */
    function statementsOfReport(string $uri, User $reporter): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->actingAs($reporter)->postJson($uri, ['reason' => 'spam'])->assertCreated();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }

    it('lock the post before they count the open reports, so the third one always sees the first two', function () {
        $queries = statementsOfReport("/api/posts/{$this->post->id}/report", $this->user);

        $lock = array_find_key($queries, fn (string $query) => str_contains($query, 'from `posts`') && str_contains($query, 'for update'));
        $insert = array_find_key($queries, fn (string $query) => str_starts_with($query, 'insert into `reports`'));
        $count = array_find_key($queries, fn (string $query) => str_contains($query, 'count(*)') && str_contains($query, 'from `reports`'));

        expect($lock)->not->toBeNull()
            ->and($insert)->not->toBeNull()
            ->and($count)->not->toBeNull()
            ->and($lock)->toBeLessThan($insert)
            ->and($insert)->toBeLessThan($count);
    });

    it('lock the comment the same way', function () {
        $queries = statementsOfReport("/api/comments/{$this->comment->id}/report", $this->user);

        $lock = array_find_key($queries, fn (string $query) => str_contains($query, 'from `comments`') && str_contains($query, 'for update'));
        $insert = array_find_key($queries, fn (string $query) => str_starts_with($query, 'insert into `reports`'));

        expect($lock)->not->toBeNull()->and($lock)->toBeLessThan($insert);
    });

    it('count the reports made a moment before in the same flow: three in a row hide the post', function () {
        $queries = [];
        foreach (User::factory()->count(3)->create() as $reporter) {
            $queries[] = statementsOfReport("/api/posts/{$this->post->id}/report", $reporter);
        }

        expect($this->post->fresh()->hidden_at)->not->toBeNull();
        // Only the third report ends in hiding the post.
        expect(collect($queries)->map(fn (array $own) => collect($own)->contains(fn (string $query) => str_starts_with($query, 'update `posts` set `hidden_at`')))->all())
            ->toBe([false, false, true]);
    });

    it('answer "already reported" when the same reader slips in between the check and the insert', function () {
        Report::creating(fn () => throw new UniqueConstraintViolationException('mysql', 'insert into `reports`', [], new Exception('Duplicate entry')));

        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason', ['social.already_reported']);

        expect(Report::query()->count())->toBe(0);
    });
});

describe('reporting a comment', function () {
    it('stores the report', function () {
        $this->actingAs($this->user)->postJson("/api/comments/{$this->comment->id}/report", ['reason' => 'spam', 'note' => 'Advertising.'])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'spam');

        expect(Report::query()->sole())
            ->reportable_type->toBe('comment')
            ->reportable_id->toBe($this->comment->id);
    });

    it('takes one report per reader per comment', function () {
        $this->actingAs($this->user)->postJson("/api/comments/{$this->comment->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->actingAs($this->user)->postJson("/api/comments/{$this->comment->id}/report", ['reason' => 'spam'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason', ['social.already_reported']);
    });

    it('lets a reader report a post and its comment', function () {
        $this->actingAs($this->user)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->actingAs($this->user)->postJson("/api/comments/{$this->comment->id}/report", ['reason' => 'spam'])->assertCreated();

        expect(Report::query()->count())->toBe(2);
    });

    it('does not let an author report their own comment', function () {
        $this->actingAs($this->author)->postJson("/api/comments/{$this->comment->id}/report", ['reason' => 'spam'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason', ['social.cannot_report_own']);
    });

    it('answers "not found" for a comment the reader cannot see, whatever is sent', function () {
        $hidden = Comment::factory()->hidden()->for($this->post)->create();
        $ofHiddenPost = Comment::factory()->for(Post::factory()->hidden())->create();
        $blocked = Comment::factory()->for($this->post)->create();
        $this->user->blockedReaders()->attach($blocked->user_id);

        foreach ([$hidden, $ofHiddenPost, $blocked] as $comment) {
            $this->actingAs($this->user)->postJson("/api/comments/{$comment->id}/report", ['reason' => 'spam'])->assertNotFound();
            $this->actingAs($this->user)->postJson("/api/comments/{$comment->id}/report", [])->assertNotFound();
        }
        $this->actingAs($this->user)->postJson('/api/comments/999999/report', ['reason' => 'spam'])->assertNotFound();
    });

    it('hides the comment with the third report, and uncounts it', function () {
        $this->post->forceFill(['comments_count' => 1])->save();

        reportersOf($this->comment, 2);
        expect($this->comment->fresh()->hidden_at)->toBeNull()->and($this->post->fresh()->comments_count)->toBe(1);

        reportersOf($this->comment, 1);

        expect($this->comment->fresh()->hidden_at)->not->toBeNull()->and($this->post->fresh()->comments_count)->toBe(0);
    });

    it('hides the comment from everybody but its author, in the list and in the count', function () {
        $viewer = User::factory()->create();
        reportersOf($this->comment, 3);

        $this->actingAs($viewer)->getJson("/api/posts/{$this->post->id}/comments")->assertJsonCount(0, 'data');
        $this->actingAs($viewer)->getJson("/api/posts/{$this->post->id}")->assertJsonPath('data.comments_count', 0);

        $this->actingAs($this->author)->getJson("/api/posts/{$this->post->id}/comments")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_hidden', true);
    });

    it('leaves the post visible', function () {
        reportersOf($this->comment, 3);

        expect($this->post->fresh()->hidden_at)->toBeNull();
    });
});
