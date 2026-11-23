<?php

namespace App\Models;

use Database\Factories\QuoteCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['quote_id', 'user_id', 'body'])]
class QuoteComment extends Model
{
    /** @use HasFactory<QuoteCommentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Leaves out the comments of readers the viewer has blocked and of readers who have blocked the viewer. A guest has no blocks.
     *
     * @param  Builder<QuoteComment>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $viewer): void
    {
        if ($viewer === null) {
            return;
        }

        $query
            ->whereNotIn('quote_comments.user_id', DB::table('user_blocks')->where('blocker_id', $viewer->id)->select('blocked_id'))
            ->whereNotIn('quote_comments.user_id', DB::table('user_blocks')->where('blocked_id', $viewer->id)->select('blocker_id'));
    }
}
