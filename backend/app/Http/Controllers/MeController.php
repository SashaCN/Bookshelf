<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
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
}
