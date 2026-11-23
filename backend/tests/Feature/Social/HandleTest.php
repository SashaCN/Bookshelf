<?php

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Social\GenerateHandle;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function register(array $overrides = []): void
{
    test()->postJson('/api/auth/register', $overrides + [
        'name' => 'Olena',
        'email' => 'olena@example.com',
        'password' => 'secret-password',
    ])->assertCreated();
}

describe('assigned at registration', function () {
    it('makes the handle out of the name', function () {
        register(['name' => 'Olena Kovalenko']);

        expect(User::query()->sole()->handle)->toBe('olena_kovalenko');
    });

    it('turns a Ukrainian name into Latin letters', function () {
        register(['name' => 'Олена Шевченко']);

        expect(User::query()->sole()->handle)->toBe('olena_shevchenko');
    });

    it('adds a random number when the handle is taken', function () {
        User::factory()->create(['handle' => 'olena']);

        register(['name' => 'Olena']);

        $handle = User::query()->where('email', 'olena@example.com')->sole()->handle;
        expect($handle)->toMatch('/^olena\d{4}$/');
    });

    it('does not take a handle that is taken, whatever number it tries', function () {
        User::factory()->create(['handle' => 'olena']);
        $generator = app(GenerateHandle::class);

        $handles = collect(range(1, 40))->map(function () use ($generator) {
            $handle = $generator->handle('Olena');
            User::factory()->create(['handle' => $handle]);

            return $handle;
        });

        expect($handles->unique())->toHaveCount(40)->and($handles)->each->toMatch('/^olena\d{4,8}$/');
    });

    it('looks the handle up a few times at most, however many readers have the same name', function () {
        foreach (range(1, 30) as $number) {
            User::factory()->create(['handle' => $number === 1 ? 'olena' : "olena{$number}"]);
        }

        DB::enableQueryLog();
        app(GenerateHandle::class)->handle('Olena');

        // One for the name itself and one for its first random number (a second only on a very unlucky draw).
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
    });

    it('falls back to a plain word when nothing usable is left of the name', function (string $name) {
        register(['name' => $name]);

        expect(User::query()->sole()->handle)->toBe('reader');
    })->with(['an emoji' => ['🙂🙂'], 'one letter' => ['Я'], 'punctuation' => ['!!!']]);

    it('numbers the fallback too', function () {
        User::factory()->create(['handle' => 'reader']);

        register(['name' => '🙂']);

        expect(User::query()->where('email', 'olena@example.com')->sole()->handle)->toMatch('/^reader\d{4}$/');
    });

    it('always makes a valid handle, however strange the name', function (string $name) {
        register(['name' => $name]);

        expect(User::query()->sole()->handle)->toMatch('/^[a-z0-9_]{3,30}$/');
    })->with([
        'very long' => [str_repeat('Oleksandrivna ', 7)],
        'with symbols' => ['Ol@na_k!'],
        'with accents' => ['Zoë Çelik'],
        'only digits' => ['12345'],
        'Chinese' => ['山田太郎'],
        'underscores' => ['___a___'],
    ]);

    it('keeps the number inside the length limit, shortening the name where it must', function () {
        $long = str_repeat('a', 40);
        User::factory()->create(['handle' => substr($long, 0, 22)]);

        foreach (range(1, 20) as $unused) {
            $handle = app(GenerateHandle::class)->handle($long);

            expect(strlen($handle))->toBeLessThanOrEqual(30)
                ->and($handle)->toMatch('/^[a-z0-9_]{3,30}$/')
                ->and($handle)->toStartWith(str_repeat('a', 22));
        }
    });

    it('gives the same name different handles when two readers sign up', function () {
        register(['name' => 'Olena', 'email' => 'one@example.com']);

        app(CreateNewUser::class)->create(['name' => 'Olena', 'email' => 'two@example.com', 'password' => 'secret-password']);

        $handles = User::query()->orderBy('id')->pluck('handle')->all();
        expect($handles[0])->toBe('olena')
            ->and($handles[1])->toMatch('/^olena\d{4}$/');
    });

    it('tries again with the next handle when another registration took it a moment ago', function () {
        $handles = Mockery::mock(GenerateHandle::class);
        $handles->shouldReceive('handle')->andReturn('olena', 'olena', 'olena2');
        User::factory()->create(['handle' => 'olena']);

        $user = (new CreateNewUser($handles))->create(['name' => 'Olena', 'email' => 'two@example.com', 'password' => 'secret-password']);

        expect($user->handle)->toBe('olena2');
    });

    it('does not let a reader choose the handle at registration', function () {
        register(['name' => 'Olena', 'handle' => 'chosen_one']);

        expect(User::query()->sole()->handle)->toBe('olena');
    });

    it('does not make a bio or a moderator of the new reader', function () {
        register(['name' => 'Olena', 'bio' => 'hello', 'is_admin' => true]);

        expect(User::query()->sole())->bio->toBeNull()->is_admin->toBeFalse();
    });

    it('tells the reader the handle in /api/me', function () {
        register(['name' => 'Olena']);

        // A fresh model: the one that registered still knows it was just created, which would make the answer a 201.
        $this->actingAs(User::query()->sole())->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.handle', 'olena')
            ->assertJsonPath('data.bio', null);
    });
});

describe('changing the handle and the bio', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['handle' => 'olena']);
    });

    it('requires authentication', function () {
        $this->patchJson('/api/me', ['handle' => 'hacker'])->assertUnauthorized();
    });

    it('changes the handle and the bio', function () {
        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => 'book_worm', 'bio' => 'I read on trains.'])
            ->assertOk()
            ->assertJsonPath('data.handle', 'book_worm')
            ->assertJsonPath('data.bio', 'I read on trains.');

        expect($this->user->fresh())->handle->toBe('book_worm')->bio->toBe('I read on trains.');
    });

    it('accepts the handle the reader has already', function () {
        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => 'olena', 'name' => 'Olena K'])->assertOk();
    });

    it('lower-cases the handle', function () {
        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => '  Book_Worm '])
            ->assertOk()
            ->assertJsonPath('data.handle', 'book_worm');
    });

    it('rejects a handle that is taken, saying so with a code', function () {
        User::factory()->create(['handle' => 'taken_one']);

        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => 'taken_one'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.handle', ['social.handle_taken']);

        expect($this->user->fresh()->handle)->toBe('olena');
    });

    it('takes "Taken_One" for taken too', function () {
        User::factory()->create(['handle' => 'taken_one']);

        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => 'Taken_One'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.handle', ['social.handle_taken']);
    });

    it('rejects a handle of the wrong shape, saying so with a code', function (string $handle) {
        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => $handle])
            ->assertUnprocessable()
            ->assertJsonPath('errors.handle', ['social.handle_invalid']);
    })->with([
        'too short' => ['ab'],
        'too long' => [str_repeat('a', 31)],
        'a space' => ['two words'],
        'a dash' => ['book-worm'],
        'a dot' => ['book.worm'],
        'an at sign' => ['@olena'],
        'Cyrillic' => ['олена'],
    ]);

    it('accepts the shortest and the longest handle', function (string $handle) {
        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => $handle])->assertOk();
    })->with(['shortest' => ['abc'], 'longest' => [str_repeat('a', 30)], 'digits and underscores' => ['_1_2_']]);

    it('rejects an empty handle and a handle that is not text', function (mixed $handle) {
        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => $handle])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('handle');
    })->with(['empty' => [''], 'number list' => [[1, 2]]]);

    it('rejects a bio over 280 characters and accepts 280', function () {
        $this->actingAs($this->user)->patchJson('/api/me', ['bio' => str_repeat('a', 281)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bio');

        $this->actingAs($this->user)->patchJson('/api/me', ['bio' => str_repeat('a', 280)])->assertOk();
    });

    it('clears the bio', function () {
        $this->user->update(['bio' => 'Something']);

        $this->actingAs($this->user)->patchJson('/api/me', ['bio' => ''])->assertOk()->assertJsonPath('data.bio', null);

        expect($this->user->fresh()->bio)->toBeNull();
    });

    it('answers like for a taken handle when another reader takes it between the check and the update', function () {
        User::updating(fn () => throw new UniqueConstraintViolationException(
            'mysql',
            'update `users` set `handle` = ?',
            [],
            new Exception("Duplicate entry 'book_worm' for key 'users.users_handle_unique'"),
        ));

        $this->actingAs($this->user)->patchJson('/api/me', ['handle' => 'book_worm'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.handle', ['social.handle_taken']);

        expect($this->user->fresh()->handle)->toBe('olena');
    });

    it('does not take any other unique violation for a taken handle', function () {
        User::updating(fn () => throw new UniqueConstraintViolationException('mysql', 'update `users`', [], new Exception('Duplicate entry for key email')));
        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs($this->user)->patchJson('/api/me', ['name' => 'New']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('does not let a reader make themselves a moderator', function () {
        $this->actingAs($this->user)->patchJson('/api/me', ['is_admin' => true])->assertOk();

        expect($this->user->fresh()->is_admin)->toBeFalse();
    });
});
