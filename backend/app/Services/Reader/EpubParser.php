<?php

namespace App\Services\Reader;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Reads an EPUB straight out of the zip, without extracting anything to disk.
 * The file is untrusted: limits are checked before an entry is read, and no read goes past its limit.
 */
class EpubParser
{
    public const MAX_ENTRIES = 2000;

    public const MAX_TOTAL_SIZE = 200 * 1024 * 1024;

    public const MAX_ENTRY_SIZE = 50 * 1024 * 1024;

    public const MAX_RATIO = 100;

    private const RATIO_CHECKED_FROM = 1024 * 1024;

    private const MAX_XML_SIZE = 2 * 1024 * 1024;

    public const MAX_CHAPTER_SIZE = 5 * 1024 * 1024;

    public const MAX_IMAGE_SIZE = 2 * 1024 * 1024;

    public const MAX_CHAPTER_IMAGES = 8 * 1024 * 1024;

    private const IMAGE_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    private const CHAPTER_TYPES = ['application/xhtml+xml', 'text/html'];

    private const REMOVED_ELEMENTS = ['script', 'iframe', 'object', 'embed', 'link', 'style', 'base', 'meta', 'form',
        'svg', 'math', 'template', 'frame', 'frameset', 'applet'];

    private const URL_ATTRIBUTES = ['href', 'src', 'xlink:href', 'action', 'formaction', 'poster', 'background', 'data', 'srcset', 'cite'];

    private const UNSAFE_SCHEMES = ['javascript:', 'vbscript:', 'data:'];

    /**
     * @return array{title: string, author: string|null, chapters: list<string>}
     *
     * @throws ValidationException
     */
    public function parse(string $path, string $fallbackTitle): array
    {
        $zip = $this->open($path);

        try {
            $this->assertNotABomb($zip);

            $mimetype = $this->read($zip, 'mimetype', 256);
            if ($mimetype === null || trim($mimetype) !== 'application/epub+zip') {
                $this->fail('reader.not_epub');
            }

            $container = $this->loadXml($this->read($zip, 'META-INF/container.xml', self::MAX_XML_SIZE));
            $rootfile = (new DOMXPath($container))->query('//*[local-name()="rootfile"]/@full-path')->item(0)?->nodeValue;
            if ($rootfile === null || trim($rootfile) === '') {
                $this->fail('reader.invalid_epub');
            }
            $opfPath = $this->normalize(trim($rootfile));

            $opf = $this->loadXml($this->read($zip, $opfPath, self::MAX_XML_SIZE));
            $xpath = new DOMXPath($opf);

            $title = $this->text($xpath, '//*[local-name()="metadata"]/*[local-name()="title"]');
            $author = $this->text($xpath, '//*[local-name()="metadata"]/*[local-name()="creator"]');

            $chapters = $this->spine($zip, $xpath, dirname($opfPath));
            if ($chapters === []) {
                $this->fail('reader.invalid_epub');
            }

            return [
                'title' => mb_substr($title ?? $fallbackTitle, 0, 255),
                'author' => $author !== null ? mb_substr($author, 0, 255) : null,
                'chapters' => $chapters,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * The HTML of one chapter: the inside of its body, with active content removed.
     * Null when the chapter is not in the file. The client still treats the result as untrusted.
     *
     * @return array{title: string|null, html: string}|null
     */
    public function chapter(string $path, string $entry): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }

        try {
            $raw = $this->read($zip, $entry, self::MAX_CHAPTER_SIZE);

            return $raw === null ? null : $this->sanitize($raw, $zip, $entry);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{title: string|null, html: string}
     */
    private function sanitize(string $raw, ZipArchive $zip, string $entry): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$this->toUtf8($raw), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        $heading = $xpath->query('//h1|//h2')->item(0)?->textContent;
        $heading ??= $xpath->query('//title')->item(0)?->textContent;
        $title = $heading !== null ? trim((string) preg_replace('/\s+/u', ' ', $heading)) : null;

        foreach (self::REMOVED_ELEMENTS as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $element) {
                $element->parentNode?->removeChild($element);
            }
        }

        $this->embedImages($dom, $zip, $entry);

        foreach ($xpath->query('//*') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = strtolower(preg_replace('/[\x00-\x20]+/', '', $attribute->nodeValue ?? '') ?? '');
                if (str_starts_with($name, 'on') || $name === 'srcdoc' || ($this->isUrlAttribute($name) && $this->hasUnsafeScheme($value))) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $html = '';
        if ($body !== null) {
            foreach ($body->childNodes as $child) {
                $html .= $dom->saveHTML($child);
            }
        }

        return ['title' => $title === '' ? null : $title, 'html' => $html];
    }

    /**
     * Replaces the src of every <img> with a data: URI of the image from the archive, or removes the image.
     * Nothing is written to disk.
     */
    private function embedImages(DOMDocument $dom, ZipArchive $zip, string $chapterEntry): void
    {
        $budget = self::MAX_CHAPTER_IMAGES;

        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $img) {
            $img->removeAttribute('srcset');
            $img->removeAttribute('style');

            $uri = $this->imageDataUri($zip, $chapterEntry, $img->getAttribute('src'), $budget);
            if ($uri !== null) {
                $img->setAttribute('src', $uri);

                continue;
            }

            $alt = trim($img->getAttribute('alt'));
            if ($alt !== '') {
                $img->parentNode?->replaceChild($dom->createTextNode($alt), $img);
            } else {
                $img->parentNode?->removeChild($img);
            }
        }
    }

    private function imageDataUri(ZipArchive $zip, string $chapterEntry, string $src, int &$budget): ?string
    {
        $src = trim($src);
        // Absolute paths, protocol-relative and scheme URLs (http:, data:, ...) are never fetched.
        if ($src === '' || str_starts_with($src, '/') || str_starts_with($src, '\\')
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $src) === 1) {
            return null;
        }

        $target = rawurldecode(explode('#', explode('?', $src)[0])[0]);
        if ($target === '' || str_contains($target, "\0")) {
            return null;
        }

        $parts = [];
        foreach (explode('/', dirname($chapterEntry) === '.' ? $target : dirname($chapterEntry).'/'.$target) as $part) {
            if ($part === '..') {
                // Going above the root of the archive.
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }
        $name = implode('/', $parts);

        $mime = self::IMAGE_TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? null;
        $stat = $mime !== null ? $zip->statName($name) : false;
        if ($stat === false || $stat['size'] > self::MAX_IMAGE_SIZE || $stat['size'] > $budget) {
            return null;
        }

        $data = $this->read($zip, $name, self::MAX_IMAGE_SIZE + 1);
        if ($data === null || strlen($data) > self::MAX_IMAGE_SIZE || strlen($data) > $budget || ! $this->matchesType($data, $mime)) {
            return null;
        }

        $budget -= strlen($data);

        return 'data:'.$mime.';base64,'.base64_encode($data);
    }

    private function matchesType(string $data, string $mime): bool
    {
        return match ($mime) {
            'image/png' => str_starts_with($data, "\x89PNG\r\n\x1a\n"),
            'image/jpeg' => str_starts_with($data, "\xFF\xD8\xFF"),
            'image/gif' => str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a'),
            'image/webp' => str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP',
            default => false,
        };
    }

    /**
     * Chapters may declare another encoding (windows-1251 is common for Cyrillic books), or have a byte order mark.
     * The result is always valid UTF-8.
     */
    private function toUtf8(string $raw): string
    {
        $encoding = 'UTF-8';
        if (str_starts_with($raw, "\xFF\xFE")) {
            $encoding = 'UTF-16LE';
        } elseif (str_starts_with($raw, "\xFE\xFF")) {
            $encoding = 'UTF-16BE';
        } elseif (preg_match('/^\s*<\?xml[^>]*encoding\s*=\s*["\']([A-Za-z0-9._-]+)["\']/i', $raw, $matches) === 1
            && in_array(strtoupper($matches[1]), array_map('strtoupper', mb_list_encodings()), true)) {
            $encoding = $matches[1];
        }

        return ltrim(mb_convert_encoding($raw, 'UTF-8', $encoding), "\u{FEFF}");
    }

    private function isUrlAttribute(string $name): bool
    {
        return in_array($name, self::URL_ATTRIBUTES, true);
    }

    /**
     * The value is already lowercase and free of whitespace and control characters, which browsers ignore in schemes.
     * Embedded images of a book are the only data URL that is harmless.
     */
    private function hasUnsafeScheme(string $value): bool
    {
        if (preg_match('#^data:image/(png|jpe?g|gif|webp);#', $value) === 1) {
            return false;
        }

        foreach (self::UNSAFE_SCHEMES as $scheme) {
            if (str_starts_with($value, $scheme)) {
                return true;
            }
        }

        return false;
    }

    private function open(string $path): ZipArchive
    {
        $handle = @fopen($path, 'rb');
        $magic = $handle ? fread($handle, 4) : false;
        if ($handle) {
            fclose($handle);
        }

        $zip = new ZipArchive;
        if ($magic !== "PK\x03\x04" || $zip->open($path, ZipArchive::RDONLY) !== true) {
            $this->fail('reader.not_epub');
        }

        return $zip;
    }

    /**
     * Judged from the sizes the archive declares; reads are capped on top of that.
     */
    private function assertNotABomb(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $this->fail('reader.zip_bomb');
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $this->fail('reader.not_epub');
            }

            $size = $stat['size'];
            $total += $size;

            $suspiciousRatio = $size > self::RATIO_CHECKED_FROM
                && ($stat['comp_size'] <= 0 || $size / $stat['comp_size'] > self::MAX_RATIO);

            if ($size > self::MAX_ENTRY_SIZE || $total > self::MAX_TOTAL_SIZE || $suspiciousRatio) {
                $this->fail('reader.zip_bomb');
            }
        }
    }

    /**
     * Never reads more than $limit bytes of an entry. Null when there is no such entry.
     */
    private function read(ZipArchive $zip, string $name, int $limit): ?string
    {
        $stream = $zip->getStream($name);
        if ($stream === false) {
            return null;
        }

        $content = stream_get_contents($stream, $limit);
        fclose($stream);

        return $content === false ? null : $content;
    }

    private function loadXml(?string $xml): DOMDocument
    {
        // Metadata has no use for entities, and declaring them is how XML bombs are built.
        if ($xml === null || trim($xml) === '' || stripos($xml, '<!ENTITY') !== false) {
            $this->fail('reader.invalid_epub');
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // No LIBXML_NOENT (entities stay unexpanded) and no network: nothing external is ever fetched.
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $this->fail('reader.invalid_epub');
        }

        return $dom;
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $value = $xpath->query($query)->item(0)?->textContent;
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function spine(ZipArchive $zip, DOMXPath $xpath, string $baseDir): array
    {
        $items = [];
        foreach ($xpath->query('//*[local-name()="manifest"]/*[local-name()="item"]') ?: [] as $item) {
            if ($item instanceof DOMElement) {
                $items[$item->getAttribute('id')] = $item;
            }
        }

        $chapters = [];
        foreach ($xpath->query('//*[local-name()="spine"]/*[local-name()="itemref"]') ?: [] as $itemref) {
            $item = $itemref instanceof DOMElement ? ($items[$itemref->getAttribute('idref')] ?? null) : null;
            $href = $item?->getAttribute('href');

            if ($item === null || $href === null || $href === ''
                || ! in_array(strtolower($item->getAttribute('media-type')), self::CHAPTER_TYPES, true)) {
                continue;
            }

            // A reference to a part of a file (#fragment) or a query is still that file; "/x" is relative to the archive root.
            $target = rawurldecode(explode('#', explode('?', $href)[0])[0]);
            if ($target === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $target) === 1) {
                continue;
            }

            $entry = $this->normalize(str_starts_with($target, '/') || $baseDir === '.' ? $target : $baseDir.'/'.$target);

            // A book may list a file twice, and a manifest may name a file the archive does not have.
            if (! in_array($entry, $chapters, true) && $zip->locateName($entry) !== false) {
                $chapters[] = $entry;
            }
        }

        return $chapters;
    }

    /**
     * A zip-internal path without "." and ".." parts.
     */
    private function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $code): never
    {
        throw ValidationException::withMessages(['file' => [$code]]);
    }
}
