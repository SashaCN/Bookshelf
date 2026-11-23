<?php

use App\Models\Quote;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);
});

function createTagged($test, mixed $tags, array $extra = [])
{
    return $test->postJson("/api/library/{$test->userBook->id}/quotes", array_merge(['content' => 'Text', 'tags' => $tags], $extra));
}

function tagNames(User $user): array
{
    return Tag::query()->whereBelongsTo($user)->orderBy('name')->pluck('name')->all();
}

describe('saving tags', function () {
    it('creates the quote with normalized tags, sorted by name', function () {
        $this->actingAs($this->user);

        createTagged($this, ['  Стоїцизм ', 'ЗВИЧКИ', 'мудрі   думки'])
            ->assertCreated()
            ->assertJsonPath('data.tags', ['звички', 'мудрі думки', 'стоїцизм']);

        expect(tagNames($this->user))->toBe(['звички', 'мудрі думки', 'стоїцизм']);
    });

    it('treats names that normalize to the same tag as one', function () {
        $this->actingAs($this->user);

        createTagged($this, ['Привіт', ' привіт', 'ПРИВІТ'])->assertCreated()->assertJsonPath('data.tags', ['привіт']);

        expect(Tag::count())->toBe(1);
    });

    it('answers an empty list when no tags were sent', function () {
        $this->actingAs($this->user)->postJson("/api/library/{$this->userBook->id}/quotes", ['content' => 'Text'])
            ->assertCreated()->assertJsonPath('data.tags', []);
    });

    it('reuses the existing tag of the reader', function () {
        $this->actingAs($this->user);

        createTagged($this, ['a'])->assertCreated();
        createTagged($this, ['A', 'b'])->assertCreated();

        expect(tagNames($this->user))->toBe(['a', 'b'])->and(Tag::count())->toBe(2);
    });

    it('gives every reader their own tag of the same name', function () {
        $other = UserBook::factory()->for($this->stranger)->create();

        $this->actingAs($this->user);
        createTagged($this, ['shared'])->assertCreated();
        $this->actingAs($this->stranger)->postJson("/api/library/{$other->id}/quotes", ['content' => 'X', 'tags' => ['shared']])->assertCreated();

        expect(Tag::where('name', 'shared')->count())->toBe(2);
    });

    it('replaces the tags on update, leaves them alone when omitted and clears them with an empty list', function () {
        $quote = Quote::factory()->for($this->userBook)->create();
        $this->actingAs($this->user);

        $this->patchJson("/api/quotes/{$quote->id}", ['tags' => ['a', 'b']])->assertOk()->assertJsonPath('data.tags', ['a', 'b']);
        $this->patchJson("/api/quotes/{$quote->id}", ['is_favorite' => true])->assertOk()->assertJsonPath('data.tags', ['a', 'b']);
        $this->patchJson("/api/quotes/{$quote->id}", ['tags' => ['b', 'c']])->assertOk()->assertJsonPath('data.tags', ['b', 'c']);
        $this->patchJson("/api/quotes/{$quote->id}", ['tags' => []])->assertOk()->assertJsonPath('data.tags', []);

        expect(Tag::count())->toBe(0);
    });

    it('deletes tags that no quote of the reader uses any more, but only the reader\'s own', function () {
        $first = Quote::factory()->for($this->userBook)->create();
        $second = Quote::factory()->for($this->userBook)->create();
        $foreign = Tag::create(['user_id' => $this->stranger->id, 'name' => 'orphan-of-someone-else']);
        $this->actingAs($this->user);

        $this->patchJson("/api/quotes/{$first->id}", ['tags' => ['kept', 'dropped']])->assertOk();
        $this->patchJson("/api/quotes/{$second->id}", ['tags' => ['kept']])->assertOk();
        $this->patchJson("/api/quotes/{$first->id}", ['tags' => []])->assertOk();

        expect(tagNames($this->user))->toBe(['kept'])->and(Tag::whereKey($foreign->id)->exists())->toBeTrue();
    });

    it('deletes orphan tags when the quote is deleted', function () {
        $this->actingAs($this->user);
        $id = createTagged($this, ['solo'])->json('data.id');

        $this->deleteJson("/api/quotes/{$id}")->assertNoContent();

        expect(Tag::count())->toBe(0);
    });

    it('rolls the tags back with the quote when saving fails', function () {
        $this->actingAs($this->user);

        createTagged($this, ['a'], ['page' => 999])->assertUnprocessable();

        expect(Tag::count())->toBe(0)->and(Quote::count())->toBe(0);
    });

    it('does not touch the tags of a foreign quote', function () {
        $quote = Quote::factory()->for(UserBook::factory()->for($this->stranger)->create())->create();

        $this->actingAs($this->user)->patchJson("/api/quotes/{$quote->id}", ['tags' => ['x']])->assertNotFound();

        expect(Tag::count())->toBe(0);
    });
});

describe('validating tags', function () {
    it('accepts the limits', function () {
        $this->actingAs($this->user);

        createTagged($this, [str_repeat('я', 40)])->assertCreated();
        createTagged($this, array_map(fn ($i) => "t$i", range(1, 10)))->assertCreated();
    });

    it('rejects bad tags with 422', function (mixed $tags) {
        $this->actingAs($this->user);

        $errors = createTagged($this, $tags)->assertUnprocessable()->json('errors');

        expect(array_keys($errors)[0])->toStartWith('tags');
    })->with([
        'not an array' => 'oops',
        'too many' => [array_map(fn ($i) => "t$i", range(1, 11))],
        'too long' => [[str_repeat('я', 41)]],
        'blank' => [['   ']],
        'empty' => [['']],
        'not a string' => [[['x']]],
    ]);

    it('applies the same limits on update', function () {
        $quote = Quote::factory()->for($this->userBook)->create();
        $this->actingAs($this->user);

        $this->patchJson("/api/quotes/{$quote->id}", ['tags' => array_map(fn ($i) => "t$i", range(1, 11))])->assertUnprocessable();
        $this->patchJson("/api/quotes/{$quote->id}", ['tags' => [str_repeat('x', 41)]])->assertUnprocessable();
        $this->patchJson("/api/quotes/{$quote->id}", ['tags' => null])->assertUnprocessable();
    });

    it('counts duplicates once against the limit', function () {
        $this->actingAs($this->user);

        createTagged($this, [...array_map(fn ($i) => "t$i", range(1, 10)), 'T1', ' t2'])->assertCreated();
    });
});

describe('filtering by tag', function () {
    beforeEach(function () {
        $this->otherBook = UserBook::factory()->for($this->user)->create();
        $this->a = Quote::factory()->for($this->userBook)->create();
        $this->b = Quote::factory()->for($this->otherBook)->create();
        $this->c = Quote::factory()->for($this->userBook)->favorite()->create();
        $this->actingAs($this->user);
        $this->patchJson("/api/quotes/{$this->a->id}", ['tags' => ['читання']]);
        $this->patchJson("/api/quotes/{$this->b->id}", ['tags' => ['читання', 'інше']]);
        $this->patchJson("/api/quotes/{$this->c->id}", ['tags' => ['інше']]);
    });

    it('lists only the quotes with the tag, normalizing the name', function () {
        $ids = fn (string $tag) => collect($this->getJson('/api/quotes?tag='.urlencode($tag))->assertOk()->json('data'))->pluck('id')->all();

        expect($ids('читання'))->toBe([$this->b->id, $this->a->id])
            ->and($ids('  ЧИТАННЯ '))->toBe([$this->b->id, $this->a->id])
            ->and($ids('немає'))->toBe([]);
    });

    it('combines with the book and favorite filters', function () {
        $ids = fn (string $query) => collect($this->getJson("/api/quotes?$query")->assertOk()->json('data'))->pluck('id')->all();

        expect($ids("tag=читання&book={$this->userBook->id}"))->toBe([$this->a->id])
            ->and($ids('tag=інше&favorite=1'))->toBe([$this->c->id]);
    });

    it('narrows the export too', function () {
        $body = $this->get('/api/quotes/export?tag='.urlencode('читання'))->assertOk()->streamedContent();

        expect($body)->toContain($this->a->content)->toContain($this->b->content)->not->toContain($this->c->content);
    });

    it('never matches the tags of another reader', function () {
        $this->actingAs($this->stranger)->getJson('/api/quotes?tag='.urlencode('читання'))->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('listing tags', function () {
    it('requires authentication', function () {
        $this->getJson('/api/tags')->assertUnauthorized();
    });

    it('lists the reader\'s tags with quote counts, ordered by name', function () {
        $one = Quote::factory()->for($this->userBook)->create();
        $two = Quote::factory()->for($this->userBook)->create();
        $this->actingAs($this->user);
        $this->patchJson("/api/quotes/{$one->id}", ['tags' => ['бета', 'альфа']]);
        $this->patchJson("/api/quotes/{$two->id}", ['tags' => ['бета']]);

        $foreignQuote = Quote::factory()->for(UserBook::factory()->for($this->stranger)->create())->create();
        $this->actingAs($this->stranger)->patchJson("/api/quotes/{$foreignQuote->id}", ['tags' => ['чужий']]);

        $this->actingAs($this->user)->getJson('/api/tags')->assertOk()->assertExactJson(['data' => [
            ['name' => 'альфа', 'quotes_count' => 1],
            ['name' => 'бета', 'quotes_count' => 2],
        ]]);
    });

    it('does not list tags left without quotes', function () {
        Tag::create(['user_id' => $this->user->id, 'name' => 'orphan']);

        $this->actingAs($this->user)->getJson('/api/tags')->assertOk()->assertExactJson(['data' => []]);
    });
});

describe('exposure', function () {
    it('shows tags to the owner in the list, one quote and the quote of the day', function () {
        $this->actingAs($this->user);
        $id = createTagged($this, ['мої'], ['visibility' => 'public'])->json('data.id');

        $this->getJson('/api/quotes')->assertJsonPath('data.0.tags', ['мої']);
        $this->getJson("/api/quotes/{$id}")->assertJsonPath('data.tags', ['мої']);
        $this->getJson('/api/quotes/daily')->assertJsonPath('data.tags', ['мої']);
    });

    it('hides tags from guests and other readers on public profiles and in the feed', function () {
        $this->user->update(['username' => 'owner', 'is_public' => true]);
        $this->actingAs($this->user);
        createTagged($this, ['secret'], ['visibility' => 'public'])->assertCreated();

        $this->stranger->following()->attach($this->user->id);

        $this->actAsGuest();
        $guest = $this->getJson('/api/users/owner/quotes')->assertOk();
        expect($guest->getContent())->not->toContain('secret')->and($guest->json('data.0'))->not->toHaveKey('tags');

        $this->actingAs($this->stranger);
        $profile = $this->getJson('/api/users/owner/quotes')->assertOk();
        $feed = $this->getJson('/api/feed')->assertOk();
        expect($profile->json('data'))->toHaveCount(1)->and($profile->getContent())->not->toContain('secret')
            ->and($feed->json('data'))->toHaveCount(1)->and($feed->getContent())->not->toContain('secret');

        // Not even to the owner on their own public profile: those are shared resources.
        $this->actingAs($this->user);
        expect($this->getJson('/api/users/owner/quotes')->assertOk()->getContent())->not->toContain('secret');
    });
});

it('lists quotes with their tags in a constant number of queries', function () {
    $this->actingAs($this->user);
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/quotes?tag=x')->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    foreach (range(1, 2) as $i) {
        createTagged($this, ['x', "y$i"])->assertCreated();
    }
    $few = $count();

    foreach (range(3, 12) as $i) {
        createTagged($this, ['x', "y$i", "z$i"])->assertCreated();
    }

    expect($count())->toBe($few);
});

it('writes the tags of a quote into the Markdown export, escaped', function () {
    $this->actingAs($this->user);
    createTagged($this, ['стоїцизм', 'a *b*', '# head'], ['content' => 'Line'])->assertCreated();
    Quote::factory()->for($this->userBook)->create(['content' => 'Plain']);

    $body = $this->get('/api/quotes/export')->assertOk()->streamedContent();

    expect($body)->toContain("> Line\n\n#\\#\u{00A0}head #a\u{00A0}\\*b\\* #стоїцизм")
        ->and($body)->toContain("> Plain\n");
    expect(substr_count($body, '#стоїцизм'))->toBe(1);
});

it('removes the tags with the account', function () {
    $this->actingAs($this->user);
    createTagged($this, ['gone'])->assertCreated();

    $this->user->delete();

    expect(Tag::count())->toBe(0)->and(DB::table('quote_tag')->count())->toBe(0);
});
