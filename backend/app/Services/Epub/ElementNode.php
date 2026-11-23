<?php

namespace App\Services\Epub;

/**
 * An element of a chapter that is being cleaned up. It has a name and children and nothing else: no attributes,
 * which is what makes the result safe.
 */
final class ElementNode
{
    /** @var list<ElementNode|TextNode> */
    public array $children = [];

    /** Whether it holds text (or is a rule): an element that does not is dropped. Set when the chapter is finished. */
    public bool $meaningful = false;

    public function __construct(public readonly string $tag) {}
}
