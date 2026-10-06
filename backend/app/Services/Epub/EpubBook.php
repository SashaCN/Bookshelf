<?php

namespace App\Services\Epub;

use App\Enums\ReaderError;
use Generator;

/**
 * An opened EPUB: its chapters can be read one after another, each one parsed, cleaned up and handed over before
 * the next is touched, so that a book is never in memory as a whole.
 */
final class EpubBook
{
    public function __construct(
        private readonly EpubArchive $archive,
        private readonly EpubPackage $package,
        private readonly HtmlSanitizer $sanitizer,
        private readonly EpubLimits $limits,
    ) {}

    /**
     * The chapters that have text, in reading order. A document of the spine that is missing, cannot be read or holds
     * no text (a cover, a page with only pictures) is skipped.
     *
     * @return Generator<int, ParsedChapter>
     *
     * @throws EpubImportException when a document is unacceptable, or there are too many chapters, or one is too big, or the text is
     */
    public function chapters(): Generator
    {
        $position = 0;
        $characters = 0;

        foreach ($this->package->spine as $path) {
            $xml = $this->archive->read($path, $this->limits->maxDocumentBytes, ReaderError::ChapterTooLarge);
            $document = $xml === null ? null : SafeXml::parse($xml);
            unset($xml);

            if ($document === null) {
                continue;
            }

            $chapter = $this->sanitizer->sanitize($document);
            unset($document);

            if ($chapter->characters === 0) {
                continue;
            }

            if ($position >= $this->limits->maxChapters) {
                throw new EpubImportException(ReaderError::TooManyChapters);
            }

            $characters += $chapter->characters;

            if ($characters > $this->limits->maxTextCharacters) {
                throw new EpubImportException(ReaderError::TextTooLarge);
            }

            yield new ParsedChapter($position, $this->package->titles[$path] ?? $chapter->heading, $chapter->html, $chapter->characters);

            $position++;
        }
    }

    public function close(): void
    {
        $this->archive->close();
    }
}
