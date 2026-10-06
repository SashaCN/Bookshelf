<?php

namespace App\Actions\Social;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LikePost
{
    /**
     * Idempotent. The counter moves only when a row was really added, in the same transaction, so it stays exact
     * however often (and however simultaneously) a reader taps.
     */
    public function like(Post $post, User $reader): void
    {
        DB::transaction(function () use ($post, $reader): void {
            $added = DB::table('likes')->insertOrIgnore(['user_id' => $reader->id, 'post_id' => $post->id]);

            if ($added > 0) {
                DB::table('posts')->where('id', $post->id)->increment('likes_count');
            }
        });
    }

    public function unlike(Post $post, User $reader): void
    {
        DB::transaction(function () use ($post, $reader): void {
            $removed = DB::table('likes')->where('user_id', $reader->id)->where('post_id', $post->id)->delete();

            if ($removed > 0) {
                DB::table('posts')->where('id', $post->id)->where('likes_count', '>', 0)->decrement('likes_count');
            }
        });
    }
}
