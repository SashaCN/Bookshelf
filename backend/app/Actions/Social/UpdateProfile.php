<?php

namespace App\Actions\Social;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateProfile
{
    /**
     * @param  array{name?: string, timezone?: string, username?: string|null, is_public?: bool}  $data
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $data): User
    {
        $user->fill($data);

        // A public profile is found by its handle, so it cannot exist without one.
        if ($user->is_public && $user->username === null) {
            throw ValidationException::withMessages(['is_public' => ['profile.username_required']]);
        }

        $user->save();

        return $user;
    }
}
