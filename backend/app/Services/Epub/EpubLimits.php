<?php

namespace App\Services\Epub;

/**
 * What an uploaded book may cost us (docs/mvp-plan.md, section 12.3). The defaults are the limits of the product;
 * tests build smaller ones where a real limit would need a huge file.
 */
final class EpubLimits
{
    public function __construct(
        /** The uploaded file. */
        public readonly int $maxFileBytes = 15 * 1024 * 1024,
        /** Everything the archive unpacks to, summed over its entries. */
        public readonly int $maxUnpackedBytes = 60 * 1024 * 1024,
        public readonly int $maxEntries = 2000,
        public readonly int $maxChapters = 500,
        /** The HTML of one chapter after it has been cleaned up. */
        public readonly int $maxChapterHtmlBytes = 2 * 1024 * 1024,
        /** One XML or XHTML document that is read into memory and parsed. */
        public readonly int $maxDocumentBytes = 10 * 1024 * 1024,
        /** All the chapters of one text together, in UTF-16 code units of their text. */
        public readonly int $maxTextCharacters = 8_000_000,
        /** Items of the manifest (and of the spine), and links of the table of contents: far more than any real book has. */
        public readonly int $maxManifestItems = 5000,
        public readonly int $maxTocEntries = 5000,
        /** What one reader may keep of all their texts together: characters (as above), and how many texts. */
        public readonly int $maxReaderCharacters = 40_000_000,
        public readonly int $maxReaderTexts = 30,
        /** How long an upload waits for the one import that may run at a time on the server, in seconds. */
        public readonly int $importLockWait = 5,
    ) {}

    public function maxFileKilobytes(): int
    {
        return intdiv($this->maxFileBytes, 1024);
    }
}
