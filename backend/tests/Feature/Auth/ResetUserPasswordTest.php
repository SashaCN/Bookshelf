<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('replaces the password with the new hash', function () {
    $user = User::factory()->create();

    app(ResetUserPassword::class)->reset($user, ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password']);

    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeFalse();
});

it('keeps the old password when the new one is too short', function () {
    $user = User::factory()->create();

    expect(fn () => app(ResetUserPassword::class)->reset($user, ['password' => 'short', 'password_confirmation' => 'short']))
        ->toThrow(ValidationException::class);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
