<?php

namespace App\Services\Epub;

/**
 * A chapter of an imported book, ready to be stored.
 */
final class ParsedChapter
{
    public function __construct(
        public readonly int $position,
        public readonly ?string $title,
        public readonly string $html,
        public readonly int $characters,
    ) {}
}
