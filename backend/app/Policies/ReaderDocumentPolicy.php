<?php

namespace App\Policies;

use App\Models\ReaderDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * An uploaded book belongs to one reader. Everybody else gets "not found".
 */
class ReaderDocumentPolicy
{
    public function view(User $user, ReaderDocument $document): Response
    {
        return $this->owns($user, $document);
    }

    public function update(User $user, ReaderDocument $document): Response
    {
        return $this->owns($user, $document);
    }

    public function delete(User $user, ReaderDocument $document): Response
    {
        return $this->owns($user, $document);
    }

    private function owns(User $user, ReaderDocument $document): Response
    {
        return $user->id === $document->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
