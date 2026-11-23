<?php

namespace App\Http\Controllers;

use App\Actions\Account\DeleteAccount;
use App\Actions\Account\ExportUserData;
use App\Actions\Social\UpdateProfile;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Update the authenticated user's profile (name and time zone).
     */
    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): UserResource
    {
        return new UserResource($updateProfile->handle($request->user(), $request->validated()));
    }

    /**
     * Mark the followers list as seen, so the new-follower badge resets.
     */
    public function followersSeen(Request $request): Response
    {
        $request->user()->forceFill(['followers_seen_at' => now()])->save();

        return response()->noContent();
    }

    /**
     * Everything the reader owns as a JSON file for download (without the EPUB files).
     */
    public function export(Request $request, ExportUserData $export): StreamedResponse
    {
        $user = $request->user();
        $filename = 'bookshelf-export-'.now($user->timezone)->toDateString().'.json';

        return response()->streamDownload(function () use ($export, $user) {
            foreach ($export->handle($user) as $piece) {
                echo $piece;
            }
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * Delete the account with all its data and files, after confirming the password.
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
