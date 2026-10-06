<?php

use App\Enums\ReaderError;
use App\Services\Epub\EpubImportException;
use App\Services\Epub\HtmlSanitizer;
use App\Services\Epub\SafeXml;
use App\Services\Epub\Text;
use Tests\Support\EpubBuilder;

/**
 * What a chapter makes of an XML document after it has been through the whole pipeline.
 */
function chapterTextOf(string $xml): string
{
    $document = SafeXml::parse($xml);

    return $document === null ? '' : Text::ofHtml((new HtmlSanitizer)->sanitize($document)->html);
}

function xmlFailureOf(string $xml): ?ReaderError
{
    try {
        SafeXml::parse($xml);
    } catch (EpubImportException $exception) {
        return $exception->error;
    }

    return null;
}

describe('reading a document', function () {
    it('reads well-formed XHTML', function () {
        expect(chapterTextOf(EpubBuilder::xhtml('<p>Hello</p>')))->toBe('Hello');
    });

    it('understands the named entities of HTML that XML does not know', function () {
        $xml = EpubBuilder::xhtml('<p>a&nbsp;b &mdash; c&hellip; &laquo;d&raquo; &copy; &eacute;</p>');

        expect(chapterTextOf($xml))->toBe("a\u{00A0}b — c… «d» © é");
    });

    it('keeps the five entities of XML as they are', function () {
        expect(chapterTextOf(EpubBuilder::xhtml('<p>&lt;&gt;&amp;&quot;&apos; &#1055; &#x41F;</p>')))->toBe('<>&"\' П П');
    });

    it('drops an entity nobody knows instead of failing', function () {
        expect(chapterTextOf(EpubBuilder::xhtml('<p>a&nonsense;b</p>')))->toBe('ab');
    });

    it('reads what can be read of a document that is not well-formed', function () {
        $xml = EpubBuilder::xhtml('<p>one</p><p>two <em>three</p><p>four</p>');

        expect(chapterTextOf($xml))->toContain('one')->toContain('two');
    });

    it('reads nothing from something that is not XML', function () {
        expect(SafeXml::parse('this is not XML at all'))->toBeNull()
            ->and(SafeXml::parse(''))->toBeNull();
    });

    it('reads a document in UTF-16, with or without a byte order mark', function () {
        $xml = '<?xml version="1.0" encoding="utf-16"?>'.EpubBuilder::xhtml('<p>Привіт</p>');

        expect(chapterTextOf("\xFF\xFE".mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8')))->toBe('Привіт')
            ->and(chapterTextOf("\xFE\xFF".mb_convert_encoding($xml, 'UTF-16BE', 'UTF-8')))->toBe('Привіт')
            ->and(chapterTextOf(mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8')))->toBe('Привіт');
    });

    it('reads a document in UTF-8 with a byte order mark', function () {
        expect(chapterTextOf("\xEF\xBB\xBF".EpubBuilder::xhtml('<p>Привіт</p>')))->toBe('Привіт');
    });

    it('reads a document in a single-byte encoding that it declares', function () {
        $xml = '<?xml version="1.0" encoding="windows-1251"?>'.str_replace('<?xml version="1.0" encoding="utf-8"?>', '', EpubBuilder::xhtml('<p>Привіт, їжак</p>'));

        expect(chapterTextOf(mb_convert_encoding($xml, 'Windows-1251', 'UTF-8')))->toBe('Привіт, їжак');
    });

    it('does not break on bytes that are not UTF-8', function () {
        expect(chapterTextOf(EpubBuilder::xhtml("<p>ok \xC3\x28 ok</p>")))->toContain('ok');
    });
});

describe('a hostile document', function () {
    it('is refused when it declares an entity that reads a file', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>&xxe;</p></body></html>';

        expect(xmlFailureOf($xml))->toBe(ReaderError::Invalid);
    });

    it('is refused when it declares an entity that reads a URL', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE html [<!ENTITY xxe SYSTEM "http://127.0.0.1:1/secret">]>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>&xxe;</p></body></html>';

        expect(xmlFailureOf($xml))->toBe(ReaderError::Invalid);
    });

    it('is refused when it declares a parameter entity', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE html [<!ENTITY % remote SYSTEM "http://127.0.0.1:1/evil.dtd"> %remote;]>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>x</p></body></html>';

        expect(xmlFailureOf($xml))->toBe(ReaderError::Invalid);
    });

    it('is refused when it is a billion laughs', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">'
            .'<!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">]>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>&lol3;</p></body></html>';

        expect(xmlFailureOf($xml))->toBe(ReaderError::Invalid);
    });

    it('is refused when it has an internal subset of any kind', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE html [<!ELEMENT html ANY>]><html xmlns="http://www.w3.org/1999/xhtml"><body><p>x</p></body></html>';

        expect(xmlFailureOf($xml))->toBe(ReaderError::Invalid);
    });

    it('is refused when the entity is hidden in UTF-16', function () {
        $xml = '<?xml version="1.0" encoding="utf-16"?><!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>&xxe;</p></body></html>';

        expect(xmlFailureOf("\xFF\xFE".mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8')))->toBe(ReaderError::Invalid)
            ->and(xmlFailureOf(mb_convert_encoding($xml, 'UTF-16BE', 'UTF-8')))->toBe(ReaderError::Invalid);
    });

    it('is refused when the entity is hidden in UTF-32 or another encoding that has NUL bytes', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><html><body><p>&xxe;</p></body></html>';

        expect(xmlFailureOf(mb_convert_encoding($xml, 'UTF-32BE', 'UTF-8')))->toBe(ReaderError::Invalid)
            ->and(xmlFailureOf(mb_convert_encoding($xml, 'UTF-32LE', 'UTF-8')))->toBe(ReaderError::Invalid);
    });

    it('is refused when the entity is hidden in an encoding the checks cannot read', function () {
        $xml = '<?xml version="1.0" encoding="ibm037"?><!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><html><body><p>&xxe;</p></body></html>';

        $ebcdic = iconv('UTF-8', 'IBM037', $xml);

        expect($ebcdic)->not->toBeFalse()
            ->and(xmlFailureOf($ebcdic))->toBe(ReaderError::Invalid)
            ->and(xmlFailureOf('<?xml version="1.0" encoding="x-unknown"?><html/>'))->toBe(ReaderError::Invalid);
    });

    it('leaks nothing from the file system through a DOCTYPE that points at a file', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE html SYSTEM "file:///etc/passwd">'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>a&passwd;b&xxe;c</p></body></html>';

        $text = chapterTextOf($xml);

        expect($text)->toBe('abc')
            ->and($text)->not->toContain('root:');
    });

    it('does not go to the network for a DOCTYPE that points at a URL', function () {
        $xml = '<?xml version="1.0"?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://127.0.0.1:1/xhtml11.dtd">'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><body><p>fine&nbsp;text</p></body></html>';

        expect(chapterTextOf($xml))->toBe("fine\u{00A0}text");
    });

    it('does not follow an XInclude', function () {
        $xml = '<?xml version="1.0"?><html xmlns="http://www.w3.org/1999/xhtml" xmlns:xi="http://www.w3.org/2001/XInclude">'
            .'<body><p>a</p><xi:include href="file:///etc/passwd" parse="text"/></body></html>';

        expect(chapterTextOf($xml))->toBe('a');
    });

    it('does not follow an XML stylesheet or a schema location', function () {
        $xml = '<?xml version="1.0"?><?xml-stylesheet type="text/xsl" href="http://127.0.0.1:1/x.xsl"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.w3.org/1999/xhtml http://127.0.0.1:1/x.xsd">'
            .'<body><p>a</p></body></html>';

        expect(chapterTextOf($xml))->toBe('a');
    });
});
