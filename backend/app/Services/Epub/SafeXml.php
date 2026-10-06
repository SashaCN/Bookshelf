<?php

namespace App\Services\Epub;

use App\Enums\ReaderError;
use DOMDocument;

/**
 * Reads an XML document that came out of somebody else's file, without trusting it.
 *
 * - The text is first turned into plain UTF-8 here, so that nothing is hidden from the checks below by an exotic
 *   encoding (UTF-16, UTF-32, EBCDIC); only a few single-byte encodings are accepted besides it.
 * - A document that declares an entity is refused: that is how files are read and servers are made to wait (XXE, "billion laughs"),
 *   and a book has no use for it. A DOCTYPE without an internal subset is simply cut off.
 * - Named entities of HTML ("&nbsp;") that a book may use without declaring them become numeric references.
 * - libxml gets no network (LIBXML_NONET), no entity substitution (no LIBXML_NOENT), no DTD, no LIBXML_PARSEHUGE
 *   (so its limits on depth and size stay on), and a loader of external entities that always says no.
 */
final class SafeXml
{
    /** The encodings a document may declare besides UTF-8 and UTF-16; the key is lower case, the value is for mbstring. */
    private const SINGLE_BYTE_ENCODINGS = [
        'us-ascii' => 'ASCII',
        'ascii' => 'ASCII',
        'iso-8859-1' => 'ISO-8859-1',
        'latin1' => 'ISO-8859-1',
        'iso-8859-2' => 'ISO-8859-2',
        'iso-8859-5' => 'ISO-8859-5',
        'iso-8859-15' => 'ISO-8859-15',
        'windows-1250' => 'Windows-1250',
        'windows-1251' => 'Windows-1251',
        'cp1251' => 'Windows-1251',
        'windows-1252' => 'Windows-1252',
        'cp1252' => 'Windows-1252',
        'koi8-r' => 'KOI8-R',
        'koi8-u' => 'KOI8-U',
    ];

    private const XML_PREDEFINED = 'amp|lt|gt|quot|apos';

    /**
     * The document, or null when nothing of it could be read (it is broken beyond what libxml can recover).
     *
     * @throws EpubImportException when the document is something no book needs: it declares entities, or is in an
     *                             encoding that is not accepted
     */
    public static function parse(string $xml): ?DOMDocument
    {
        $xml = self::prepare($xml);

        if (trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        // Whatever asks for an external resource, the answer is no.
        libxml_set_external_entity_loader(static fn () => null);

        try {
            $document = new DOMDocument;
            $document->resolveExternals = false;
            $document->substituteEntities = false;
            $document->validateOnParse = false;

            // RECOVER: books in the wild are often not quite well-formed; whatever can be read is read, and what is read
            // is only ever copied into a new tree (see HtmlSanitizer).
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_RECOVER);

            return $loaded && $document->documentElement !== null ? $document : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            libxml_set_external_entity_loader(null);
        }
    }

    /**
     * Plain UTF-8 text without an XML declaration, a DOCTYPE or an unknown entity.
     *
     * @throws EpubImportException
     */
    public static function prepare(string $xml): string
    {
        $xml = self::toUtf8($xml);

        // XML has no NUL characters. Finding one means UTF-32 or something else that hides the markup from the checks.
        if (str_contains($xml, "\0")) {
            throw new EpubImportException(ReaderError::Invalid, 'binary data in XML');
        }

        if (stripos($xml, '<!ENTITY') !== false) {
            throw new EpubImportException(ReaderError::Invalid, 'entity declaration');
        }

        if (preg_match('/<!DOCTYPE\b[^>\[]*([>\[])/i', $xml, $doctype) === 1) {
            if ($doctype[1] === '[') {
                throw new EpubImportException(ReaderError::Invalid, 'DOCTYPE with an internal subset');
            }

            // Not needed to read a book, and what it points at must never be fetched.
            $xml = preg_replace('/<!DOCTYPE\b[^>\[]*>/i', '', $xml, 1) ?? $xml;
        }

        return self::replaceNamedEntities($xml);
    }

    /**
     * @throws EpubImportException
     */
    private static function toUtf8(string $xml): string
    {
        if (str_starts_with($xml, "\xEF\xBB\xBF")) {
            $xml = substr($xml, 3);
        } elseif (str_starts_with($xml, "\xFF\xFE") || str_starts_with($xml, "\xFE\xFF")) {
            $little = str_starts_with($xml, "\xFF\xFE");
            $xml = mb_convert_encoding(substr($xml, 2), 'UTF-8', $little ? 'UTF-16LE' : 'UTF-16BE');

            return self::withoutDeclaration($xml);
        } elseif (str_starts_with($xml, "<\0?\0")) {
            return self::withoutDeclaration(mb_convert_encoding($xml, 'UTF-8', 'UTF-16LE'));
        } elseif (str_starts_with($xml, "\0<\0?")) {
            return self::withoutDeclaration(mb_convert_encoding($xml, 'UTF-8', 'UTF-16BE'));
        }

        // "<?xm" in EBCDIC, which libxml would recognize and read although none of the checks here can.
        if (str_starts_with($xml, "\x4C\x6F\xA7\x94")) {
            throw new EpubImportException(ReaderError::Invalid, 'EBCDIC');
        }

        $encoding = 'utf-8';

        if (preg_match('/^\s*<\?xml\b[^?]*\bencoding\s*=\s*["\']([A-Za-z0-9._-]+)["\']/', $xml, $declaration) === 1) {
            $encoding = strtolower($declaration[1]);
        }

        if (isset(self::SINGLE_BYTE_ENCODINGS[$encoding])) {
            $xml = mb_convert_encoding($xml, 'UTF-8', self::SINGLE_BYTE_ENCODINGS[$encoding]);
        } elseif (! in_array($encoding, ['utf-8', 'utf8', 'utf-16', 'utf-16le', 'utf-16be'], true)) {
            // UTF-16 here means a declaration that does not match the bytes (they have no NULs, or they would have
            // been converted above): the bytes are taken as what they are.
            throw new EpubImportException(ReaderError::Invalid, "encoding {$encoding}");
        }

        return self::withoutDeclaration(mb_scrub($xml, 'UTF-8'));
    }

    /**
     * The text is UTF-8 by now, whatever the declaration said, so the declaration goes.
     */
    private static function withoutDeclaration(string $xml): string
    {
        return preg_replace('/^\s*<\?xml\b[^?]*\?>/', '', $xml, 1) ?? $xml;
    }

    /**
     * Without a DTD XML knows five entities. A book written as XHTML may use the named ones of HTML as well;
     * they become numeric references, and a name nobody knows is dropped.
     */
    private static function replaceNamedEntities(string $xml): string
    {
        if (! str_contains($xml, '&')) {
            return $xml;
        }

        return preg_replace_callback(
            '/&(?!(?:'.self::XML_PREDEFINED.');)[A-Za-z][A-Za-z0-9]{1,31};/',
            static function (array $match): string {
                $decoded = html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($decoded === $match[0]) {
                    return '';
                }

                $reference = '';

                foreach (mb_str_split($decoded) as $character) {
                    $reference .= '&#'.mb_ord($character).';';
                }

                return $reference;
            },
            $xml,
        ) ?? $xml;
    }
}
