<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * Assembles an EPUB for a test, in a temporary file, with ZipArchive. Nothing is downloaded and no real book is used.
 *
 *     EpubBuilder::make()->chapter('One', '<p>Hello</p>')->toFile();
 *
 * The defaults make a valid EPUB 3 with a "nav" table of contents; the other methods break it in one way or another.
 */
final class EpubBuilder
{
    /** @var list<string> */
    private static array $files = [];

    /** @var list<array{id: string, path: string, title: ?string, xml: string, inSpine: bool}> */
    private array $documents = [];

    /** @var list<array{string, string}> entries added as they are, in order */
    private array $entries = [];

    /** @var list<array{string, string}> entries that are read from a file when the archive is made: name and path */
    private array $fileEntries = [];

    /** @var list<string> */
    private array $danglingItemrefs = [];

    private ?string $mimetype = 'application/epub+zip';

    private string $toc = 'nav';

    private ?string $encryption = null;

    private ?string $opf = null;

    private ?string $container = null;

    private bool $withSpine = true;

    private static bool $cleanUpRegistered = false;

    public static function make(): self
    {
        if (! self::$cleanUpRegistered) {
            self::$cleanUpRegistered = true;
            register_shutdown_function(self::cleanUp(...));
        }

        return new self;
    }

    /**
     * A well-formed XHTML document around a body.
     */
    public static function xhtml(string $body, string $title = 'Chapter'): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<!DOCTYPE html>'
            .'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">'
            ."<head><title>{$title}</title></head><body>{$body}</body></html>";
    }

    /**
     * A chapter in the spine, with a well-formed document around the body, named in the table of contents.
     */
    public function chapter(?string $title, string $body): self
    {
        return $this->document($title, self::xhtml($body, $title ?? 'Chapter'));
    }

    /**
     * A document of the spine written out in full (for malformed, hostile and unusual ones).
     */
    public function document(?string $title, string $xml, bool $inSpine = true): self
    {
        $number = count($this->documents) + 1;
        $this->documents[] = ['id' => "ch{$number}", 'path' => "OEBPS/ch{$number}.xhtml", 'title' => $title, 'xml' => $xml, 'inSpine' => $inSpine];

        return $this;
    }

    /**
     * Any entry of the archive, with any name (for hostile ones).
     */
    public function entry(string $name, string $content): self
    {
        $this->entries[] = [$name, $content];

        return $this;
    }

    /**
     * An entry of NUL bytes that is as long as asked, without ever holding it in memory (a zip bomb compresses to
     * almost nothing and unpacks to this).
     */
    public function largeEntry(string $name, int $bytes): self
    {
        $path = tempnam(sys_get_temp_dir(), 'epubzero');

        if ($path === false) {
            throw new RuntimeException('Cannot make a temporary file.');
        }

        self::$files[] = $path;
        $handle = fopen($path, 'wb');
        ftruncate($handle, $bytes);
        fclose($handle);

        $this->fileEntries[] = [$name, $path];

        return $this;
    }

    public function withMimetype(?string $mimetype): self
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * 'nav' (EPUB 3, the default), 'ncx' (EPUB 2) or 'none'.
     */
    public function withToc(string $toc): self
    {
        $this->toc = $toc;

        return $this;
    }

    public function withEncryption(string $xml): self
    {
        $this->encryption = $xml;

        return $this;
    }

    public function withOpf(string $xml): self
    {
        $this->opf = $xml;

        return $this;
    }

    public function withContainer(string $xml): self
    {
        $this->container = $xml;

        return $this;
    }

    public function withoutSpine(): self
    {
        $this->withSpine = false;

        return $this;
    }

    /**
     * A spine entry that points at something the manifest does not have.
     */
    public function danglingItemref(string $idref = 'nowhere'): self
    {
        $this->danglingItemrefs[] = $idref;

        return $this;
    }

    /**
     * The archive as a file; it is deleted when the tests are done.
     */
    public function toFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'epub');

        if ($path === false) {
            throw new RuntimeException('Cannot make a temporary file.');
        }

        self::$files[] = $path;
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open the temporary archive.');
        }

        if ($this->mimetype !== null) {
            $zip->addFromString('mimetype', $this->mimetype);
            $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        }

        $zip->addFromString('META-INF/container.xml', $this->container ?? $this->containerXml());

        if ($this->encryption !== null) {
            $zip->addFromString('META-INF/encryption.xml', $this->encryption);
        }

        $zip->addFromString('OEBPS/content.opf', $this->opf ?? $this->opfXml());

        if ($this->toc === 'nav') {
            $zip->addFromString('OEBPS/nav.xhtml', $this->navXml());
        } elseif ($this->toc === 'ncx') {
            $zip->addFromString('OEBPS/toc.ncx', $this->ncxXml());
        }

        foreach ($this->documents as $document) {
            $zip->addFromString($document['path'], $document['xml']);
        }

        foreach ($this->entries as [$name, $content]) {
            $zip->addFromString($name, $content);
        }

        foreach ($this->fileEntries as [$name, $source]) {
            $zip->addFile($source, $name);
        }

        $zip->close();

        return $path;
    }

    public function toUpload(string $name = 'book.epub'): UploadedFile
    {
        return new UploadedFile($this->toFile(), $name, 'application/epub+zip', null, true);
    }

    /**
     * Removes every file the builders made.
     */
    public static function cleanUp(): void
    {
        foreach (self::$files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        self::$files = [];
    }

    private function containerXml(): string
    {
        return '<?xml version="1.0"?>'
            .'<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'
            .'<rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles>'
            .'</container>';
    }

    private function opfXml(): string
    {
        $manifest = '';
        $spine = '';

        foreach ($this->documents as $document) {
            $manifest .= "<item id=\"{$document['id']}\" href=\"".basename($document['path']).'" media-type="application/xhtml+xml"/>';

            if ($document['inSpine']) {
                $spine .= "<itemref idref=\"{$document['id']}\"/>";
            }
        }

        foreach ($this->danglingItemrefs as $idref) {
            $spine .= "<itemref idref=\"{$idref}\"/>";
        }

        $tocAttribute = '';

        if ($this->toc === 'nav') {
            $manifest .= '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>';
        } elseif ($this->toc === 'ncx') {
            $manifest .= '<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>';
            $tocAttribute = ' toc="ncx"';
        }

        $spineXml = $this->withSpine ? "<spine{$tocAttribute}>{$spine}</spine>" : '';

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="id">'
            .'<metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Test book</dc:title><dc:identifier id="id">test</dc:identifier><dc:language>uk</dc:language></metadata>'
            ."<manifest>{$manifest}</manifest>{$spineXml}</package>";
    }

    private function navXml(): string
    {
        $links = '';

        foreach ($this->documents as $document) {
            if ($document['title'] !== null) {
                $links .= '<li><a href="'.basename($document['path']).'">'.htmlspecialchars($document['title'], ENT_XML1).'</a></li>';
            }
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><head><title>Contents</title></head>'
            ."<body><nav epub:type=\"toc\"><ol>{$links}</ol></nav></body></html>";
    }

    private function ncxXml(): string
    {
        $points = '';

        foreach ($this->documents as $order => $document) {
            if ($document['title'] !== null) {
                $points .= '<navPoint id="p'.$order.'" playOrder="'.($order + 1).'"><navLabel><text>'
                    .htmlspecialchars($document['title'], ENT_XML1).'</text></navLabel><content src="'.basename($document['path']).'"/></navPoint>';
            }
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1"><head/><docTitle><text>Test</text></docTitle>'
            ."<navMap>{$points}</navMap></ncx>";
    }
}
