<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create();
    // The SPA's requests: Sanctum starts the session only for them.
    $this->withHeader('Referer', config('app.url'));
});

it('changes the password', function () {
    $this->actingAs($this->user)->putJson('/api/auth/user/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSuccessful();

    expect(Hash::check('a-brand-new-password', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $this->actingAs($this->user)->putJson('/api/auth/user/password', [
        'current_password' => 'not-my-password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a weak or unconfirmed new password', function (string $password, string $confirmation) {
    $this->actingAs($this->user)->putJson('/api/auth/user/password', [
        'current_password' => 'password',
        'password' => $password,
        'password_confirmation' => $confirmation,
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
})->with([
    'too short' => ['short', 'short'],
    'not confirmed' => ['a-brand-new-password', 'something-else'],
]);

it('answers 401 to a guest', function () {
    $this->putJson('/api/auth/user/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertUnauthorized();
});

it('drops sessions that know the old password', function () {
    $stale = Auth::guard('web')->hashPasswordForCookie($this->user->password);
    $this->user->forceFill(['password' => Hash::make('a-brand-new-password')])->save();

    $this->actingAs($this->user)->withSession(['password_hash_web' => $stale])
        ->getJson('/api/me')->assertUnauthorized();
});

it('keeps the session that changed the password signed in', function () {
    $this->actingAs($this->user)
        ->withSession(['password_hash_web' => Auth::guard('web')->hashPasswordForCookie($this->user->password)])
        ->putJson('/api/auth/user/password', [
            'current_password' => 'password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSuccessful();

    expect(Hash::check('a-brand-new-password', $this->user->fresh()->password))->toBeTrue();
});

it('serves api requests of a session signed in with a password', function () {
    // A real session login, unlike actingAs(): this is the path the session-check middleware inspects.
    $this->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertSuccessful();

    $this->getJson('/api/me')->assertOk();
});

it('keeps the current session signed in after changing the password', function () {
    $this->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertSuccessful();
    $this->getJson('/api/me')->assertOk();

    $this->putJson('/api/auth/user/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSuccessful();

    $this->getJson('/api/me')->assertOk();
});
