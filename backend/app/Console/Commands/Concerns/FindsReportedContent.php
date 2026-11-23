<?php

namespace App\Console\Commands\Concerns;

use App\Enums\ReportableType;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * For the moderation commands, which name a post or a comment by its type and id.
 *
 * @mixin Command
 */
trait FindsReportedContent
{
    use PrintsUserText;

    /**
     * Writes the reason to the console and returns null when there is no such type or no such object, or when the id
     * is not a whole number ("1abc" is not the post 1).
     */
    private function findContent(string $type, string $id): Post|Comment|null
    {
        $reportable = ReportableType::tryFrom($type);

        if ($reportable === null) {
            $this->components->error("Unknown type \"{$type}\": use \"post\" or \"comment\".");

            return null;
        }

        if (! ctype_digit($id)) {
            $this->components->error('The id must be a whole number, "'.$this->safe($id, 30).'" is not.');

            return null;
        }

        $content = match ($reportable) {
            ReportableType::Post => Post::query()->find($id),
            ReportableType::Comment => Comment::query()->find($id),
        };

        if ($content === null) {
            $this->components->error("There is no {$type} with id {$id}.");
        }

        return $content;
    }
}
