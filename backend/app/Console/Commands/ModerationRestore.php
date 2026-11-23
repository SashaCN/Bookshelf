<?php

namespace App\Console\Commands;

use App\Actions\Social\ModerateContent;
use App\Console\Commands\Concerns\FindsReportedContent;
use Illuminate\Console\Command;

class ModerationRestore extends Command
{
    use FindsReportedContent;

    protected $signature = 'moderation:restore {type : "post" or "comment"} {id : The id of the post or the comment}';

    protected $description = 'Show a hidden post or comment to everybody again, and close the reports about it';

    public function handle(ModerateContent $moderation): int
    {
        $content = $this->findContent($this->argument('type'), $this->argument('id'));

        if ($content === null) {
            return self::FAILURE;
        }

        $changed = $moderation->restore($content);
        // The reports have been looked at: left open, the next single report would hide it again at once.
        $resolved = $moderation->resolveReports($content);

        $this->components->info(($changed ? 'Restored.' : 'It was not hidden.')." {$resolved} open reports closed.");

        return self::SUCCESS;
    }
}
