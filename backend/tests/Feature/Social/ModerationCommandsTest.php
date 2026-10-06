<?php

use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->author = User::factory()->create();
    $this->post = Post::factory()->create(['user_id' => $this->author->id, 'content' => 'A post worth a look']);
    $this->comment = Comment::factory()->for($this->post)->create(['content' => 'A rude comment']);
    $this->post->forceFill(['comments_count' => 1])->save();
});

describe('moderation:reports', function () {
    it('says so when nobody has reported anything', function () {
        $this->artisan('moderation:reports')->expectsOutputToContain('There are no open reports.')->assertSuccessful();
    });

    it('lists the open reports with what they are about', function () {
        $reporter = User::factory()->create(['handle' => 'angry_reader']);
        $report = Report::factory()->about($this->post)->create(['reporter_id' => $reporter->id, 'reason' => 'abuse', 'note' => 'Insulting']);
        Report::factory()->about($this->comment)->create();

        expect(Artisan::call('moderation:reports'))->toBe(0);

        expect(Artisan::output())
            ->toContain("post {$this->post->id}")
            ->toContain("comment {$this->comment->id}")
            ->toContain('abuse')
            ->toContain('@angry_reader')
            ->toContain('Insulting')
            ->toContain('A post worth a look')
            ->toContain('A rude comment')
            ->toContain('2 open reports');

        expect($report->fresh()->status)->toBe(ReportStatus::Open);
    });

    it('leaves out the reports that have been resolved', function () {
        Report::factory()->about($this->post)->resolved()->create(['note' => 'already handled']);

        $this->artisan('moderation:reports')->expectsOutputToContain('There are no open reports.')->assertSuccessful();
    });

    it('prints what readers wrote safely: no escape sequences, one line, cut short, no console markup', function () {
        $note = "\e[31mRED\e[0m\e]0;pwned\x07 line one\nline two <error>tag</error> \u{202E}reversed ".str_repeat('x', 300);
        $post = Post::factory()->create(['user_id' => $this->author->id, 'content' => "\e[2Jclear\r\n<info>markup</info> ".str_repeat('y', 300)]);
        Report::factory()->about($post)->create(['note' => $note]);

        Artisan::call('moderation:reports');
        $output = Artisan::output();

        expect($output)
            ->not->toContain("\e")
            ->not->toContain("\x07")
            ->not->toContain("\u{202E}")
            ->not->toContain('pwned')
            ->not->toContain(str_repeat('x', 100))
            ->not->toContain(str_repeat('y', 100))
            ->toContain('RED')
            ->toContain('line one line two')
            ->toContain('<error>tag')
            ->toContain('<info>markup');
    });

    it('shows whether the object is hidden, or gone', function () {
        $hidden = Post::factory()->hidden()->create();
        Report::factory()->about($hidden)->create();
        Report::factory()->create(['reportable_id' => 987654]);

        Artisan::call('moderation:reports');

        expect(Artisan::output())->toContain('hidden')->toContain('deleted');
    });
});

describe('moderation:hide', function () {
    it('hides a post and closes the reports about it', function () {
        Report::factory()->about($this->post)->count(2)->create();
        $other = Report::factory()->create();

        $this->artisan('moderation:hide', ['type' => 'post', 'id' => $this->post->id])
            ->expectsOutputToContain('Hidden. 2 open reports closed.')
            ->assertSuccessful();

        expect($this->post->fresh()->hidden_at)->not->toBeNull()
            ->and(Report::query()->where('status', ReportStatus::Open)->pluck('id')->all())->toBe([$other->id]);
    });

    it('hides a comment and uncounts it', function () {
        $this->artisan('moderation:hide', ['type' => 'comment', 'id' => $this->comment->id])->expectsOutputToContain('Hidden. 0 open reports closed.')->assertSuccessful();

        expect($this->comment->fresh()->hidden_at)->not->toBeNull()->and($this->post->fresh()->comments_count)->toBe(0);
    });

    it('can be repeated without harm', function () {
        $this->artisan('moderation:hide', ['type' => 'comment', 'id' => $this->comment->id])->assertSuccessful();
        $this->artisan('moderation:hide', ['type' => 'comment', 'id' => $this->comment->id])
            ->expectsOutputToContain('It was hidden already. 0 open reports closed.')
            ->assertSuccessful();

        expect($this->post->fresh()->comments_count)->toBe(0);
    });

    it('hides the post from the readers', function () {
        $this->artisan('moderation:hide', ['type' => 'post', 'id' => $this->post->id])->assertSuccessful();

        $this->actingAs(User::factory()->create())->getJson("/api/posts/{$this->post->id}")->assertNotFound();
        $this->actingAs($this->author)->getJson("/api/posts/{$this->post->id}")->assertOk()->assertJsonPath('data.is_hidden', true);
    });

    it('refuses an unknown type or an object that is not there', function (string $type, string $id) {
        $this->artisan('moderation:hide', ['type' => $type, 'id' => $id])->assertFailed();

        expect(Post::query()->whereNotNull('hidden_at')->count())->toBe(0);
    })->with([
        'unknown type' => ['user', '1'],
        'no such post' => ['post', '999999'],
        'no such comment' => ['comment', '999999'],
    ]);
});

describe('the id of an object', function () {
    it('has to be a whole number: "1abc" is not the post 1', function (string $command, string $id) {
        $this->artisan($command, ['type' => 'post', 'id' => $id])
            ->expectsOutputToContain('The id must be a whole number')
            ->assertFailed();

        expect(Post::query()->whereNotNull('hidden_at')->count())->toBe(0);
    })->with([
        'hide' => ['moderation:hide', '1abc'],
        'restore' => ['moderation:restore', '1abc'],
    ]);

    it('is refused when it is not made of digits only', function (string $id) {
        $this->post->forceFill(['hidden_at' => now()])->save();

        $this->artisan('moderation:restore', ['type' => 'post', 'id' => $id])->assertFailed();

        expect($this->post->fresh()->hidden_at)->not->toBeNull();
    })->with(['a trailing letter' => ['1abc'], 'a minus' => ['-1'], 'a fraction' => ['1.5'], 'a sign' => ['+1'], 'an exponent' => ['1e3'], 'empty' => ['']]);

    it('is refused for a report as well', function () {
        $report = Report::factory()->about($this->post)->create();

        $this->artisan('moderation:resolve', ['report' => $report->id.'abc'])
            ->expectsOutputToContain('The id must be a whole number')
            ->assertFailed();

        expect($report->fresh()->status)->toBe(ReportStatus::Open);
    });

    it('is printed back without escape sequences', function () {
        Artisan::call('moderation:hide', ['type' => 'post', 'id' => "1\e[31mabc"]);

        expect(Artisan::output())->not->toContain("\e");
    });
});

describe('moderation:restore', function () {
    it('shows a hidden post again and closes the reports about it', function () {
        $hidden = Post::factory()->hidden()->create();
        Report::factory()->about($hidden)->count(3)->create();

        $this->artisan('moderation:restore', ['type' => 'post', 'id' => $hidden->id])
            ->expectsOutputToContain('Restored. 3 open reports closed.')
            ->assertSuccessful();

        expect($hidden->fresh()->hidden_at)->toBeNull()
            ->and(Report::query()->where('status', ReportStatus::Open)->count())->toBe(0);
    });

    it('is not hidden again by the very next report', function () {
        $hidden = Post::factory()->hidden()->create();
        Report::factory()->about($hidden)->count(3)->create();
        $this->artisan('moderation:restore', ['type' => 'post', 'id' => $hidden->id])->assertSuccessful();

        $this->actingAs(User::factory()->create())->postJson("/api/posts/{$hidden->id}/report", ['reason' => 'spam'])->assertCreated();

        expect($hidden->fresh()->hidden_at)->toBeNull();
    });

    it('shows a hidden comment again and counts it', function () {
        $this->artisan('moderation:hide', ['type' => 'comment', 'id' => $this->comment->id])->assertSuccessful();

        $this->artisan('moderation:restore', ['type' => 'comment', 'id' => $this->comment->id])->expectsOutputToContain('Restored. 0 open reports closed.')->assertSuccessful();

        expect($this->comment->fresh()->hidden_at)->toBeNull()->and($this->post->fresh()->comments_count)->toBe(1);
    });

    it('says so for something that was not hidden, and counts nothing twice', function () {
        $this->artisan('moderation:restore', ['type' => 'comment', 'id' => $this->comment->id])
            ->expectsOutputToContain('It was not hidden. 0 open reports closed.')
            ->assertSuccessful();

        expect($this->post->fresh()->comments_count)->toBe(1);
    });

    it('refuses an unknown type or an object that is not there', function (string $type, string $id) {
        $this->artisan('moderation:restore', ['type' => $type, 'id' => $id])->assertFailed();
    })->with([
        'unknown type' => ['user', '1'],
        'no such post' => ['post', '999999'],
        'no such comment' => ['comment', '999999'],
    ]);
});

describe('moderation:resolve', function () {
    it('marks one report as looked at', function () {
        $report = Report::factory()->about($this->post)->create();
        $other = Report::factory()->about($this->post)->create();

        $this->artisan('moderation:resolve', ['report' => $report->id])->expectsOutputToContain('Resolved.')->assertSuccessful();

        expect($report->fresh()->status)->toBe(ReportStatus::Resolved)
            ->and($other->fresh()->status)->toBe(ReportStatus::Open);
    });

    it('does not change whether the object is hidden', function () {
        $hidden = Post::factory()->hidden()->create();
        $report = Report::factory()->about($hidden)->create();

        $this->artisan('moderation:resolve', ['report' => $report->id])->assertSuccessful();

        expect($hidden->fresh()->hidden_at)->not->toBeNull();
    });

    it('can be repeated', function () {
        $report = Report::factory()->resolved()->create();

        $this->artisan('moderation:resolve', ['report' => $report->id])->expectsOutputToContain('It was resolved already.')->assertSuccessful();
    });

    it('refuses a report that is not there', function () {
        $this->artisan('moderation:resolve', ['report' => 999999])->assertFailed();
    });

    describe('when the last open report of a hidden object is resolved', function () {
        it('warns that the object stays hidden and says how to bring it back', function () {
            $hidden = Post::factory()->hidden()->create();
            $report = Report::factory()->about($hidden)->create();

            $this->artisan('moderation:resolve', ['report' => $report->id])
                ->expectsOutputToContain('Resolved.')
                ->expectsOutputToContain("That was the last open report about post {$hidden->id}, which is still hidden.")
                ->assertSuccessful();

            expect($hidden->fresh()->hidden_at)->not->toBeNull();
        });

        it('names the command that restores it', function () {
            $hidden = Comment::factory()->hidden()->for($this->post)->create();
            $report = Report::factory()->about($hidden)->create();

            Artisan::call('moderation:resolve', ['report' => $report->id]);

            expect(Artisan::output())->toContain("moderation:restore comment {$hidden->id}");
        });

        it('says nothing of the kind while other reports are still open', function () {
            $hidden = Post::factory()->hidden()->create();
            $reports = Report::factory()->about($hidden)->count(2)->create();

            Artisan::call('moderation:resolve', ['report' => $reports[0]->id]);

            expect(Artisan::output())->not->toContain('still hidden');
        });

        it('says nothing of the kind for an object that is visible', function () {
            $report = Report::factory()->about($this->post)->create();

            Artisan::call('moderation:resolve', ['report' => $report->id]);

            expect(Artisan::output())->not->toContain('still hidden');
        });

        it('says nothing of the kind for an object that is gone', function () {
            $report = Report::factory()->create(['reportable_id' => 987654]);

            Artisan::call('moderation:resolve', ['report' => $report->id]);

            expect(Artisan::output())->toContain('Resolved.')->not->toContain('still hidden');
        });

        it('warns again each time a hidden object loses its last report, not for one resolved already', function () {
            $hidden = Post::factory()->hidden()->create();
            $report = Report::factory()->about($hidden)->resolved()->create();

            Artisan::call('moderation:resolve', ['report' => $report->id]);

            expect(Artisan::output())->toContain('It was resolved already.')->not->toContain('still hidden');
        });
    });
});

it('takes a post from three reports to a decision of a moderator', function () {
    foreach (User::factory()->count(3)->create() as $reporter) {
        $this->actingAs($reporter)->postJson("/api/posts/{$this->post->id}/report", ['reason' => 'spam'])->assertCreated();
    }
    expect($this->post->fresh()->hidden_at)->not->toBeNull();

    $this->artisan('moderation:reports')->expectsOutputToContain('3 open reports')->assertSuccessful();

    // The moderator finds the post fine and puts it back.
    $this->artisan('moderation:restore', ['type' => 'post', 'id' => $this->post->id])->assertSuccessful();

    expect($this->post->fresh()->hidden_at)->toBeNull();
    $this->artisan('moderation:reports')->expectsOutputToContain('There are no open reports.')->assertSuccessful();
});
