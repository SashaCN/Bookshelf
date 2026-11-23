<?php

namespace App\Actions\Reader;

use App\Models\ReaderBookmark;

class DeleteBookmark
{
    public function handle(ReaderBookmark $bookmark): void
    {
        $bookmark->delete();
    }
}
