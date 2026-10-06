<?php

namespace App\Services\Epub;

/**
 * What the package of a book (its content.opf and table of contents) says about reading it.
 */
final class EpubPackage
{
    /**
     * @param  list<string>  $spine  archive paths of the documents to read, in reading order, each once
     * @param  array<string, string>  $titles  chapter titles from the table of contents, by archive path
     */
    public function __construct(
        public readonly array $spine,
        public readonly array $titles,
    ) {}
}
