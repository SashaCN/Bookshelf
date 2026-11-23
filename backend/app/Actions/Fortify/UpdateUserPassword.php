<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password, signing out their other sessions.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [...$this->passwordRules(), 'confirmed'],
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password' => Hash::make($input['password'])])->save();

        // Rehashes for the session guard: this session stays valid, the others stop matching.
        $guard = Auth::guard('web');
        $guard->logoutOtherDevices($input['password']);

        // Fortify's route runs outside the api group, so Sanctum's AuthenticateSession never refreshes the stored
        // hash here; without this the next request of this very session would look stale and be signed out.
        if (request()->hasSession() && $guard instanceof SessionGuard) {
            request()->session()->put('password_hash_web', $guard->hashPasswordForCookie($user->fresh()?->getAuthPassword() ?? ''));
        }
    }
}
