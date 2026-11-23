<?php

namespace App\Services\Epub;

use App\Enums\ReaderError;
use Throwable;

/**
 * Opens an EPUB file that somebody uploaded. The file is untrusted: see EpubArchive (the archive), SafeXml
 * (every XML document) and HtmlSanitizer (every chapter) for what is done about that.
 */
class EpubImporter
{
    /** The algorithms of "encryption" that only scramble the fonts of a book; they are no DRM. */
    private const FONT_OBFUSCATION = [
        'http://www.idpf.org/2008/embedding',
        'http://ns.adobe.com/pdf/enc#RC',
    ];

    public function __construct(private readonly EpubLimits $limits = new EpubLimits) {}

    /**
     * Checks the archive and reads the package, which is cheap and needs no database; the chapters are read later,
     * by EpubBook::chapters(). The caller closes the book.
     *
     * @throws EpubImportException
     */
    public function open(string $path): EpubBook
    {
        $archive = EpubArchive::open($path, $this->limits);

        try {
            $this->assertNotEncrypted($archive);
            $package = (new PackageReader($this->limits))->read($archive);
        } catch (Throwable $exception) {
            $archive->close();

            throw $exception;
        }

        return new EpubBook($archive, $package, new HtmlSanitizer($this->limits), $this->limits);
    }

    /**
     * @throws EpubImportException
     */
    private function assertNotEncrypted(EpubArchive $archive): void
    {
        $xml = $archive->read('META-INF/encryption.xml', $this->limits->maxDocumentBytes, ReaderError::Invalid);

        if ($xml === null) {
            return;
        }

        $document = SafeXml::parse($xml) ?? throw new EpubImportException(ReaderError::Invalid, 'encryption.xml is not XML');

        foreach ($document->getElementsByTagNameNS('*', 'EncryptionMethod') as $method) {
            if (! in_array($method->getAttribute('Algorithm'), self::FONT_OBFUSCATION, true)) {
                throw new EpubImportException(ReaderError::Drm);
            }
        }
    }
}
