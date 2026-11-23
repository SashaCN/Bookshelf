<?php

namespace App\Services\Epub;

/**
 * Paths inside an EPUB archive: always relative, with "/" as the separator, never leading outside of the archive.
 */
final class EpubPaths
{
    /**
     * Whether the name of an archive entry may be trusted: not empty, not absolute (not even a drive letter) and
     * without a ".." segment. Nothing is ever extracted to disk here, but a file that tries is not a book.
     */
    public static function isSafeEntryName(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0")) {
            return false;
        }

        $name = str_replace('\\', '/', $name);

        if ($name[0] === '/' || preg_match('#^[A-Za-z]:#', $name) === 1) {
            return false;
        }

        return ! in_array('..', explode('/', $name), true);
    }

    /**
     * The directory part of an archive path ("" for the top level).
     */
    public static function directory(string $path): string
    {
        $position = strrpos($path, '/');

        return $position === false ? '' : substr($path, 0, $position);
    }

    /**
     * Where a reference ("../text/ch1.xhtml#start") found in the file in `$baseDirectory` points to inside the
     * archive, or null for something that is not a file of the archive: a URL, an absolute path, an empty reference,
     * or one that climbs out of the archive.
     */
    public static function resolve(string $baseDirectory, string $reference): ?string
    {
        $reference = preg_replace('/[#?].*$/s', '', trim($reference)) ?? '';

        if ($reference === '' || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*:#', $reference) === 1) {
            return null;
        }

        $reference = str_replace('\\', '/', rawurldecode($reference));

        if ($reference[0] === '/' || str_contains($reference, "\0")) {
            return null;
        }

        $segments = $baseDirectory === '' ? [] : explode('/', $baseDirectory);

        foreach (explode('/', $reference) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $part;
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}
