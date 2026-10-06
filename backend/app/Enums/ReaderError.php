<?php

namespace App\Enums;

/**
 * The stable codes of the business-rule errors of the reader. The API answers 422 with the code as the message of
 * the field it is about, and the client translates it (see frontend/src/i18n/reader.uk.ts).
 */
enum ReaderError: string
{
    /** The file is not an EPUB: not a ZIP archive, or no "mimetype" entry that says application/epub+zip. */
    case NotEpub = 'reader.not_epub';

    /** The file (or what it unpacks to) is bigger than the limits allow. */
    case TooLarge = 'reader.too_large';

    /** More entries in the archive than the limit. */
    case TooManyEntries = 'reader.too_many_entries';

    /** More chapters than the limit. */
    case TooManyChapters = 'reader.too_many_chapters';

    /** One chapter is bigger than the limit once it has been cleaned up. */
    case ChapterTooLarge = 'reader.chapter_too_large';

    /** An entry of the archive is named by an absolute path or one with "..": it points outside of the archive. */
    case UnsafeArchive = 'reader.unsafe_archive';

    /** All the chapters of the text together are more than one text may be. */
    case TextTooLarge = 'reader.text_too_large';

    /** The reader would have more texts, or more text, than the quota allows. */
    case QuotaExceeded = 'reader.quota_exceeded';

    /** The server is busy importing another book (only one is read at a time). */
    case Busy = 'reader.busy';

    /** The book is protected by DRM (or the archive has a password) and cannot be read. */
    case Drm = 'reader.drm';

    /** Nothing readable is left in the book. */
    case Empty = 'reader.empty';

    /** The book is damaged, or its XML is not acceptable (entities, unknown encodings, no package). */
    case Invalid = 'reader.invalid';

    /** No file came with the request, or it did not arrive whole. */
    case NoFile = 'reader.no_file';

    /** A quote was anchored to a text, but the book has none. */
    case NoText = 'reader.no_text';

    /** The text at the anchor is not the text of the quote. */
    case AnchorMismatch = 'reader.anchor_mismatch';

    /** The anchor points outside of the chapter, or at a chapter that does not exist. */
    case AnchorOutOfRange = 'reader.anchor_out_of_range';

    /** The reading position names a chapter the text does not have. */
    case ChapterOutOfRange = 'reader.chapter_out_of_range';
}
