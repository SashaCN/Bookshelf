<?php

namespace App\Actions\Social;

use App\Enums\ReportableType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PostComments
{
    /**
     * `comments_count` of the post is the number of comments that are not hidden; it changes together with the comment.
     */
    public function add(Post $post, User $author, string $content): Comment
    {
        return DB::transaction(function () use ($post, $author, $content): Comment {
            $comment = $post->comments()->create(['user_id' => $author->id, 'content' => $content]);

            DB::table('posts')->where('id', $post->id)->increment('comments_count');

            return $comment->refresh()->load('user');
        });
    }

    /**
     * The counter follows what really happened to the row: it goes down only when this call is the one that deleted
     * the comment, and only if the comment was counted, that is not hidden at this very moment. So two deletes at once
     * (or one with an out of date model) take one off, and a comment hidden meanwhile, which hiding has taken off
     * already, takes nothing off again.
     */
    public function remove(Comment $comment): void
    {
        DB::transaction(function () use ($comment): void {
            // The row as it is now, locked, so a hide or another delete waits for this one.
            $current = Comment::query()->whereKey($comment->getKey())->lockForUpdate()->first();

            if ($current === null) {
                return;
            }

            Report::query()
                ->where('reportable_type', ReportableType::Comment->value)
                ->where('reportable_id', $current->id)
                ->delete();

            $deleted = Comment::query()->whereKey($current->getKey())->delete();

            if ($deleted > 0 && ! $current->isHidden()) {
                DB::table('posts')->where('id', $current->post_id)->where('comments_count', '>', 0)->decrement('comments_count');
            }
        });
    }
}
