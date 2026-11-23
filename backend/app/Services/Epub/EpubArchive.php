<?php

namespace App\Services\Epub;

use App\Enums\ReaderError;
use ZipArchive;

/**
 * An EPUB file opened for reading, with every limit and check of the archive in one place.
 *
 * Nothing is extracted: an entry is read into memory when it is asked for, never more than the caller allows,
 * and never more than the limits of the whole archive allow.
 */
final class EpubArchive
{
    private const MIMETYPE = 'application/epub+zip';

    /** @var array<string, int> the index of every entry by its name */
    private array $index = [];

    /** @var array<string, string>|null the names by their lower case, for books whose links differ from their files in case */
    private ?array $folded = null;

    /** What has been read so far, counted as it comes out and not as the archive claims it will. */
    private int $bytesRead = 0;

    private function __construct(
        private readonly ZipArchive $zip,
        private readonly EpubLimits $limits,
    ) {}

    /**
     * @throws EpubImportException when the file is not an EPUB, is not safe to open or is over a limit
     */
    public static function open(string $path, EpubLimits $limits): self
    {
        $size = is_file($path) ? filesize($path) : false;

        if ($size === false || $size < 22) {
            throw new EpubImportException(ReaderError::NotEpub, 'too short to be an archive');
        }

        if ($size > $limits->maxFileBytes) {
            throw new EpubImportException(ReaderError::TooLarge, 'file');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new EpubImportException(ReaderError::NotEpub, 'not a ZIP archive');
        }

        $archive = new self($zip, $limits);

        try {
            $archive->inspect();
            $archive->assertMimetype();
        } catch (EpubImportException $exception) {
            $zip->close();

            throw $exception;
        }

        return $archive;
    }

    public function has(string $name): bool
    {
        return $this->locate($name) !== null;
    }

    /**
     * The name an entry really has in the archive for a name that may be spelled differently (a link that differs in
     * case); null when there is no such entry. Two spellings of one file give one name.
     */
    public function entryName(string $name): ?string
    {
        return $this->locate($name);
    }

    /**
     * The content of an entry, or null when the archive has no such entry.
     *
     * @param  ReaderError  $tooBig  what to report when the entry is bigger than `$maxBytes`
     *
     * @throws EpubImportException
     */
    public function read(string $name, int $maxBytes, ReaderError $tooBig = ReaderError::TooLarge): ?string
    {
        $name = $this->locate($name);

        if ($name === null) {
            return null;
        }

        $index = $this->index[$name];
        $declared = $this->zip->statIndex($index)['size'] ?? 0;

        if ($declared > $maxBytes) {
            throw new EpubImportException($tooBig, $name);
        }

        $stream = $this->zip->getStreamIndex($index);

        if ($stream === false) {
            throw new EpubImportException(ReaderError::Invalid, "cannot read {$name}");
        }

        $data = '';

        try {
            while (! feof($stream)) {
                $chunk = $this->readChunk($stream);

                if ($chunk === false) {
                    throw new EpubImportException(ReaderError::Invalid, "cannot read {$name}");
                }

                if ($chunk === '') {
                    break;
                }

                $data .= $chunk;
                $this->bytesRead += strlen($chunk);

                // The sizes in the headers of an archive are only what its maker says. What really comes out is counted.
                if (strlen($data) > $maxBytes) {
                    throw new EpubImportException($tooBig, $name);
                }

                if (strlen($data) > $declared) {
                    throw new EpubImportException(ReaderError::Invalid, "{$name} is bigger than it says");
                }

                if ($this->bytesRead > $this->limits->maxUnpackedBytes) {
                    throw new EpubImportException(ReaderError::TooLarge, 'unpacked');
                }
            }
        } finally {
            fclose($stream);
        }

        return $data;
    }

    /**
     * One piece of an entry, or false when it cannot be read. A damaged compressed entry makes fread() raise a warning
     * ("Zlib error: data error") that the framework would turn into an exception and an answer of 500: it is the file
     * that is bad, so the warning is taken here and reported as false.
     *
     * @param  resource  $stream
     */
    private function readChunk($stream): string|false
    {
        set_error_handler(static fn (): bool => true);

        try {
            return fread($stream, 65536);
        } finally {
            restore_error_handler();
        }
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * Looks at every entry once: how many there are, whether their names are safe, how big they claim to be.
     *
     * @throws EpubImportException
     */
    private function inspect(): void
    {
        if ($this->zip->numFiles > $this->limits->maxEntries) {
            throw new EpubImportException(ReaderError::TooManyEntries);
        }

        $total = 0;

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);

            if ($stat === false) {
                throw new EpubImportException(ReaderError::Invalid, 'unreadable entry');
            }

            if (! EpubPaths::isSafeEntryName($stat['name'])) {
                throw new EpubImportException(ReaderError::UnsafeArchive, 'entry name');
            }

            // A password-protected archive cannot be read, and is no EPUB (the encryption of EPUB is another file).
            if ($stat['encryption_method'] !== ZipArchive::EM_NONE) {
                throw new EpubImportException(ReaderError::Drm, 'encrypted archive');
            }

            $total += $stat['size'];

            if ($total > $this->limits->maxUnpackedBytes) {
                throw new EpubImportException(ReaderError::TooLarge, 'unpacked');
            }

            $this->index[str_replace('\\', '/', $stat['name'])] ??= $i;
        }
    }

    /**
     * A file is an EPUB when it says so, in an entry called "mimetype".
     *
     * @throws EpubImportException
     */
    private function assertMimetype(): void
    {
        $mimetype = $this->read('mimetype', 128, ReaderError::NotEpub);

        if ($mimetype === null || trim($mimetype) !== self::MIMETYPE) {
            throw new EpubImportException(ReaderError::NotEpub, 'no mimetype');
        }
    }

    private function locate(string $name): ?string
    {
        if (isset($this->index[$name])) {
            return $name;
        }

        if ($this->folded === null) {
            $this->folded = [];

            foreach (array_keys($this->index) as $entry) {
                $this->folded[strtolower($entry)] ??= $entry;
            }
        }

        return $this->folded[strtolower($name)] ?? null;
    }
}
