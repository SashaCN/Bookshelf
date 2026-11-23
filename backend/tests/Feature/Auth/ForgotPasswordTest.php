<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    // The SPA's requests: Sanctum starts the session only for them.
    $this->withHeader('Referer', config('app.url'));
});

it('mails a link that points to the SPA reset screen', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'olena@example.com']);

    $this->postJson('/api/auth/forgot-password', ['email' => 'olena@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return $url === config('app.url').'/reset-password/'.$notification->token.'?email=olena%40example.com';
    });
});

it('answers an unknown email exactly like a known one', function () {
    Notification::fake();
    User::factory()->create(['email' => 'olena@example.com']);

    $known = $this->postJson('/api/auth/forgot-password', ['email' => 'olena@example.com']);
    $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

    $unknown->assertOk();
    expect($unknown->json())->toBe($known->json());
    Notification::assertCount(1);
});

it('still validates the shape of the email', function () {
    $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('throttles repeated requests', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();
    }

    $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertTooManyRequests();
});

it('resets the password with a real token, then only the new password logs in', function () {
    $user = User::factory()->create(['email' => 'olena@example.com']);
    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'olena@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertOk();

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();

    $this->postJson('/api/auth/login', ['email' => 'olena@example.com', 'password' => 'password'])
        ->assertUnprocessable();
    $this->assertGuest();

    $this->postJson('/api/auth/login', ['email' => 'olena@example.com', 'password' => 'a-brand-new-password'])
        ->assertOk();
    $this->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'olena@example.com');
});

it('signs the account out of sessions opened before the reset', function () {
    $user = User::factory()->create(['email' => 'olena@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'olena@example.com', 'password' => 'password'])->assertOk();
    $stale = session('password_hash_web');
    expect($stale)->not->toBeNull();

    // A reset is requested by a guest, from another browser.
    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->postJson('/api/auth/reset-password', [
        'token' => Password::createToken($user),
        'email' => 'olena@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertOk();

    $this->app['auth']->forgetGuards();
    $this->actingAs($user->fresh())->withSession(['password_hash_web' => $stale])
        ->getJson('/api/me')->assertUnauthorized();
});

it('rejects an invalid token', function () {
    User::factory()->create(['email' => 'olena@example.com']);

    $this->postJson('/api/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'olena@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('rejects an expired token', function () {
    $user = User::factory()->create(['email' => 'olena@example.com']);
    $token = Password::createToken($user);

    $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'olena@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('keeps the old password when the new one is too short', function () {
    $user = User::factory()->create(['email' => 'olena@example.com']);

    $this->postJson('/api/auth/reset-password', [
        'token' => Password::createToken($user),
        'email' => 'olena@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});
