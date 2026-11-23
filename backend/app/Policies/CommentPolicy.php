<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A comment can be seen when its post can, unless moderation hid it (its author still sees it) or its author and the
 * viewer have blocked each other. The author of the comment and the author of the post may delete it; for everybody
 * else it is "not found".
 */
class CommentPolicy
{
    public function __construct(private readonly PostPolicy $posts) {}

    public function view(User $user, Comment $comment): Response
    {
        $visible = $user->id === $comment->user_id
            || (! $comment->isHidden() && ! $user->isBlockedWith($comment->user_id));

        return $visible && $this->posts->canSee($user, $comment->post)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, Comment $comment): Response
    {
        return $user->id === $comment->user_id || $user->id === $comment->post->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
