<?php

namespace App\Services\Epub;

/**
 * The one definition of "a character" for the text of a chapter.
 *
 * The client finds a selection by the index into a JavaScript string, which counts UTF-16 code units: a letter is
 * one, an emoji is two. Every offset and length in the reader (`characters` of a chapter, `start_offset` and
 * `end_offset` of a quote) is counted in the same unit, so the server and the browser always agree.
 *
 * The text of a chapter is its sanitized HTML with the tags taken out (what `textContent` is in a browser).
 */
final class Text
{
    /**
     * The length of a string in UTF-16 code units.
     */
    public static function length(string $utf8): int
    {
        return $utf8 === '' ? 0 : intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * `length` UTF-16 code units from the unit `start`; null when that is not a range of the text: outside of it,
     * empty, or cutting an emoji (a pair of surrogates) in two.
     */
    public static function slice(string $utf8, int $start, int $length): ?string
    {
        if ($start < 0 || $length < 1) {
            return null;
        }

        $units = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');

        if (($start + $length) * 2 > strlen($units)) {
            return null;
        }

        $part = substr($units, $start * 2, $length * 2);
        $first = unpack('v', $part)[1] ?? 0;
        $last = unpack('v', substr($part, -2))[1] ?? 0;

        // A low surrogate first or a high surrogate last: the range begins or ends in the middle of a character.
        if (($first >= 0xDC00 && $first <= 0xDFFF) || ($last >= 0xD800 && $last <= 0xDBFF)) {
            return null;
        }

        return mb_convert_encoding($part, 'UTF-8', 'UTF-16LE');
    }

    /**
     * The text of a chapter: its HTML without the tags. Only the markup this app writes is expected here (it escapes
     * "&", "<" and ">" in text and has no attributes), so this is the exact inverse of how a chapter is written.
     */
    public static function ofHtml(string $html): string
    {
        return strtr(strip_tags($html), ['&lt;' => '<', '&gt;' => '>', '&amp;' => '&']);
    }

    /**
     * Runs of white space become one space and the ends are trimmed. For titles and other text that is read.
     */
    public static function collapse(string $text): string
    {
        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $text) ?? '');
    }

    /**
     * The text without any white space at all, for telling whether two selections of the same passage are the same:
     * a browser puts a line break where the text of a chapter has none, between two paragraphs for one.
     */
    public static function skeleton(string $text): string
    {
        return preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', '', $text) ?? '';
    }
}
