<?php

namespace App\Services\Epub;

/**
 * A chapter that has been cleaned up: HTML that is safe to put into a page, the length of its text in UTF-16
 * code units (see Text) and the text of its first heading.
 */
final class SanitizedChapter
{
    public function __construct(
        public readonly string $html,
        public readonly int $characters,
        public readonly ?string $heading,
    ) {}
}
