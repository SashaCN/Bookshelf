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

        DB::transaction(fn () => $user->delete());

        // Files that are already missing are fine.
        Storage::disk('local')->delete($paths);
    }
}
