<?php

namespace App\Actions\Reader;

use App\Models\BookText;
use App\Models\UserBook;

/**
 * The outcome of saving where the reader got to: the text with its new position and the library entry as it is now
 * (its page may have moved on with the reader).
 */
final class PositionSaved
{
    public function __construct(
        public readonly BookText $text,
        public readonly UserBook $userBook,
    ) {}
}
