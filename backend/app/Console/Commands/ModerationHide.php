<?php

namespace App\Console\Commands;

use App\Actions\Social\ModerateContent;
use App\Console\Commands\Concerns\FindsReportedContent;
use Illuminate\Console\Command;

class ModerationHide extends Command
{
    use FindsReportedContent;

    protected $signature = 'moderation:hide {type : "post" or "comment"} {id : The id of the post or the comment}';

    protected $description = 'Hide a post or a comment from everybody but its author, and close the reports about it';

    public function handle(ModerateContent $moderation): int
    {
        $content = $this->findContent($this->argument('type'), $this->argument('id'));

        if ($content === null) {
            return self::FAILURE;
        }

        $changed = $moderation->hide($content);
        $resolved = $moderation->resolveReports($content);

        $this->components->info(($changed ? 'Hidden.' : 'It was hidden already.')." {$resolved} open reports closed.");

        return self::SUCCESS;
    }
}
