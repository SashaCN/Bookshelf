<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAccount
{
    /**
     * Delete the user and everything that belongs to them. Rows go in one transaction (foreign keys cascade);
     * the EPUB files go only after it committed, so a failure never leaves a half-deleted account.
     */
    public function handle(User $user): void
    {
        $paths = $user->readerDocuments()->pluck('path')->all();

        DB::transaction(function () use ($user): void {
            // Neither table has a foreign key: other devices must not keep a session, nor an old reset link a live token.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();
        });

        // Files that are already missing are fine.
        Storage::disk('local')->delete($paths);
    }
}
