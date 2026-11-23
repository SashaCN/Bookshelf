<?php

namespace App\Http\Controllers;

use App\Actions\Account\DeleteAccount;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Update the authenticated user's profile (name, time zone, handle and bio).
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        try {
            $user->update($request->validated());
        } catch (UniqueConstraintViolationException $e) {
            // Another reader took the handle between the check of the request and this update.
            if (! str_contains($e->getMessage(), 'handle')) {
                throw $e;
            }

            throw ValidationException::withMessages(['handle' => ['social.handle_taken']]);
        }

        return new UserResource($user);
    }

    /**
     * Delete the account with all its data, after confirming the password.
     */
    public function destroy(DeleteAccountRequest $request, DeleteAccount $deleteAccount): Response
    {
        $user = $request->user();
        $deleteAccount->handle($user);

        // Logging out would cycle the remember token and save the model, which would insert the deleted user again.
        $user->setRememberToken('');
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
