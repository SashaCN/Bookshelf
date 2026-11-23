<?php

namespace App\Services\Epub;

use App\Enums\ReaderError;
use DOMDocument;
use DOMElement;

/**
 * Finds out which documents of an EPUB make up the book, in which order, and what the chapters are called:
 * META-INF/container.xml points to the package (content.opf), whose manifest lists the files and whose spine
 * puts them in order; the table of contents is an EPUB 3 "nav" document or an EPUB 2 NCX.
 */
final class PackageReader
{
    private const EPUB_NAMESPACE = 'http://www.idpf.org/2007/ops';

    private const DOCUMENT_TYPES = ['application/xhtml+xml', 'text/html', 'application/xml', 'text/xml', 'text/x-oeb1-document'];

    private const TITLE_LENGTH = 200;

    public function __construct(private readonly EpubLimits $limits) {}

    /**
     * @throws EpubImportException when the book has no package or it is unusable
     */
    public function read(EpubArchive $archive): EpubPackage
    {
        $packagePath = $this->findPackage($archive);
        $package = $this->load($archive, $packagePath);
        $directory = EpubPaths::directory($packagePath);

        $manifest = $this->manifest($package, $directory);
        $spine = $this->spine($package, $manifest, $archive);

        return new EpubPackage($spine, $this->titles($package, $manifest, $archive));
    }

    /**
     * @throws EpubImportException
     */
    private function findPackage(EpubArchive $archive): string
    {
        $container = $this->load($archive, 'META-INF/container.xml');

        foreach ($container->getElementsByTagNameNS('*', 'rootfile') as $rootfile) {
            $path = EpubPaths::resolve('', $rootfile->getAttribute('full-path'));

            if ($path !== null && $archive->has($path)) {
                return $path;
            }
        }

        throw new EpubImportException(ReaderError::Invalid, 'no package document');
    }

    /**
     * @throws EpubImportException
     */
    private function load(EpubArchive $archive, string $path): DOMDocument
    {
        $xml = $archive->read($path, $this->limits->maxDocumentBytes, ReaderError::Invalid);

        if ($xml === null) {
            throw new EpubImportException(ReaderError::Invalid, "{$path} is missing");
        }

        return SafeXml::parse($xml) ?? throw new EpubImportException(ReaderError::Invalid, "{$path} is not XML");
    }

    /**
     * @return array<string, array{path: ?string, type: string, properties: list<string>}> by the id of the item
     *
     * @throws EpubImportException
     */
    private function manifest(DOMDocument $package, string $directory): array
    {
        $manifest = $package->getElementsByTagNameNS('*', 'manifest')->item(0);

        if (! $manifest instanceof DOMElement) {
            throw new EpubImportException(ReaderError::Invalid, 'no manifest');
        }

        $items = [];
        $count = 0;

        foreach ($manifest->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->localName !== 'item') {
                continue;
            }

            if (++$count > $this->limits->maxManifestItems) {
                throw new EpubImportException(ReaderError::Invalid, 'too many manifest items');
            }

            $id = $node->getAttribute('id');

            if ($id === '') {
                continue;
            }

            $items[$id] ??= [
                'path' => EpubPaths::resolve($directory, $node->getAttribute('href')),
                'type' => strtolower(trim($node->getAttribute('media-type'))),
                'properties' => preg_split('/\s+/', trim($node->getAttribute('properties')), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            ];
        }

        return $items;
    }

    /**
     * The documents of the spine that exist in the archive and are documents (not images), each once.
     *
     * @param  array<string, array{path: ?string, type: string, properties: list<string>}>  $manifest
     * @return list<string>
     *
     * @throws EpubImportException
     */
    private function spine(DOMDocument $package, array $manifest, EpubArchive $archive): array
    {
        $spine = $package->getElementsByTagNameNS('*', 'spine')->item(0);

        if (! $spine instanceof DOMElement) {
            throw new EpubImportException(ReaderError::Invalid, 'no spine');
        }

        $paths = [];
        $seen = [];
        $count = 0;

        foreach ($spine->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->localName !== 'itemref') {
                continue;
            }

            if (++$count > $this->limits->maxManifestItems) {
                throw new EpubImportException(ReaderError::Invalid, 'too many spine items');
            }

            $item = $manifest[$node->getAttribute('idref')] ?? null;

            if ($item === null || $item['path'] === null || ! $this->isDocument($item['type'], $item['path'])) {
                continue;
            }

            // A document is one chapter however it is spelled: the entry that is found is what counts (a link that
            // differs in case, or two items that point at one file, must not make it appear again).
            $entry = $archive->entryName($item['path']);

            if ($entry !== null && ! isset($seen[$entry])) {
                $seen[$entry] = true;
                $paths[] = $entry;
            }
        }

        return $paths;
    }

    private function isDocument(string $type, string $path): bool
    {
        if ($type === '') {
            return preg_match('/\.(x?html?|xml)$/i', $path) === 1;
        }

        return in_array($type, self::DOCUMENT_TYPES, true);
    }

    /**
     * Chapter titles from the table of contents, by the path of the document they lead to (the first title wins when
     * several lead to the same one). A book without a usable table of contents simply has none.
     *
     * @param  array<string, array{path: ?string, type: string, properties: list<string>}>  $manifest
     * @return array<string, string>
     */
    private function titles(DOMDocument $package, array $manifest, EpubArchive $archive): array
    {
        $titles = [];
        $read = [];

        foreach ($this->tableOfContents($package, $manifest) as $item) {
            // One file is read once, however many items of the manifest (or spellings) lead to it.
            $entry = $archive->entryName($item['path']);

            if ($entry === null || isset($read[$entry])) {
                continue;
            }

            $read[$entry] = true;
            $xml = $archive->read($entry, $this->limits->maxDocumentBytes, ReaderError::Invalid);
            $document = $xml === null ? null : SafeXml::parse($xml);

            if ($document === null) {
                continue;
            }

            $directory = EpubPaths::directory($entry);
            $entries = $item['nav'] ? $this->navEntries($document) : $this->ncxEntries($document);

            foreach ($entries as [$reference, $label]) {
                $path = EpubPaths::resolve($directory, $reference);
                $label = mb_substr(Text::collapse($label), 0, self::TITLE_LENGTH);

                // Under the name the entry has, which is what the spine uses.
                $path = $path === null ? null : ($archive->entryName($path) ?? $path);

                if ($path !== null && $label !== '') {
                    $titles[$path] ??= $label;
                }
            }

            if ($titles !== []) {
                break;
            }
        }

        return $titles;
    }

    /**
     * The documents that may hold the table of contents, the better one first: the EPUB 3 navigation document,
     * then the NCX the spine names, then any NCX.
     *
     * @param  array<string, array{path: ?string, type: string, properties: list<string>}>  $manifest
     * @return list<array{path: string, nav: bool}>
     */
    private function tableOfContents(DOMDocument $package, array $manifest): array
    {
        $found = [];

        foreach ($manifest as $item) {
            if ($item['path'] !== null && in_array('nav', $item['properties'], true)) {
                $found[] = ['path' => $item['path'], 'nav' => true];
            }
        }

        $spine = $package->getElementsByTagNameNS('*', 'spine')->item(0);
        $ncxId = $spine instanceof DOMElement ? $spine->getAttribute('toc') : '';
        $named = $manifest[$ncxId] ?? null;

        if ($named !== null && $named['path'] !== null) {
            $found[] = ['path' => $named['path'], 'nav' => false];
        }

        foreach ($manifest as $item) {
            if ($item['path'] !== null && $item['type'] === 'application/x-dtbncx+xml') {
                $found[] = ['path' => $item['path'], 'nav' => false];
            }
        }

        return $found;
    }

    /**
     * The links of the "toc" navigation of an EPUB 3 navigation document (the first navigation when none says it is).
     *
     * @return list<array{string, string}> the target and the label of each link
     */
    private function navEntries(DOMDocument $document): array
    {
        $navigation = null;

        foreach ($document->getElementsByTagNameNS('*', 'nav') as $nav) {
            $type = $nav->getAttributeNS(self::EPUB_NAMESPACE, 'type') ?: $nav->getAttribute('epub:type');
            $navigation ??= $nav;

            if (in_array('toc', preg_split('/\s+/', $type, -1, PREG_SPLIT_NO_EMPTY) ?: [], true)) {
                $navigation = $nav;
                break;
            }
        }

        $entries = [];

        if ($navigation instanceof DOMElement) {
            foreach ($navigation->getElementsByTagNameNS('*', 'a') as $link) {
                $this->assertRoomForEntry($entries);
                $entries[] = [$link->getAttribute('href'), $link->textContent];
            }
        }

        return $entries;
    }

    /**
     * The points of an EPUB 2 NCX: a label and the document it leads to.
     *
     * @return list<array{string, string}>
     */
    private function ncxEntries(DOMDocument $document): array
    {
        $entries = [];

        foreach ($document->getElementsByTagNameNS('*', 'navPoint') as $point) {
            $label = null;
            $target = null;

            foreach ($point->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }

                if ($child->localName === 'navLabel') {
                    $label ??= $child->textContent;
                } elseif ($child->localName === 'content') {
                    $target ??= $child->getAttribute('src');
                }
            }

            if ($label !== null && $target !== null) {
                $this->assertRoomForEntry($entries);
                $entries[] = [$target, $label];
            }
        }

        return $entries;
    }

    /**
     * A table of contents has a line for each chapter; one with thousands is not a table of contents of a book.
     *
     * @param  list<array{string, string}>  $entries
     *
     * @throws EpubImportException
     */
    private function assertRoomForEntry(array $entries): void
    {
        if (count($entries) >= $this->limits->maxTocEntries) {
            throw new EpubImportException(ReaderError::TooManyChapters, 'table of contents');
        }
    }
}
