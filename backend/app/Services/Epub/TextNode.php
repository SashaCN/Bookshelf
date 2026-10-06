<?php

namespace App\Services\Epub;

/**
 * A run of text of a chapter that is being cleaned up.
 */
final class TextNode
{
    public function __construct(public string $value) {}
}
