<?php

namespace App\Services\OpenLibrary;

use App\Exceptions\CatalogUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for the Open Library search API, the only part of it the app needs.
 * Responses are cached, and failures are never cached.
 */
class OpenLibraryClient
{
    private const FIELDS = 'key,title,subtitle,author_name,author_key,first_publish_year,cover_i,number_of_pages_median,subject,isbn';

    private const MAX_SUBJECTS = 12;

    /** Anthologies list hundreds of authors; a library entry keeps the first few (the pivot position is a tiny int). */
    private const MAX_AUTHORS = 10;

    /** The limits of our own columns and validation rules: whatever the catalog sends must fit them. */
    private const MAX_TEXT = 255;

    private const MAX_PAGES = 20000;

    private const MAX_YEAR = 9999;

    /**
     * @return list<CatalogEntry>
     *
     * @throws CatalogUnavailableException
     */
    public function search(string $query, int $limit = 20): array
    {
        // A valid ISBN (10 or 13, hyphens allowed) is looked up by that field instead of as free text.
        $isbn = Isbn::normalize($query);
        $q = $isbn !== null ? 'isbn:'.$isbn : Str::lower(Str::squish($query));

        return $this->entries($this->cachedSearch($q, $limit));
    }

    /**
     * Look a single work up by its key, e.g. "/works/OL17930368W".
     *
     * @throws CatalogUnavailableException
     */
    public function findByWorkKey(string $workKey): ?CatalogEntry
    {
        $entries = $this->entries($this->cachedSearch('key:'.$workKey, 1));

        foreach ($entries as $entry) {
            if ($entry->workKey === $workKey) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cachedSearch(string $query, int $limit): array
    {
        $cacheKey = 'openlibrary:search:'.hash('sha256', $query.'|'.$limit);

        /** @var list<array<string, mixed>> */
        return Cache::remember(
            $cacheKey,
            (int) config('services.openlibrary.cache_ttl'),
            fn () => $this->fetchDocs($query, $limit),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchDocs(string $query, int $limit): array
    {
        try {
            $response = Http::baseUrl((string) config('services.openlibrary.base_url'))
                ->withUserAgent((string) config('services.openlibrary.user_agent'))
                ->acceptJson()
                // One attempt only: the reader is waiting, and a retry would push the worst case beyond the 3 s of
                // the spec. A slow or failing catalog means "unavailable" and the client offers manual entry.
                ->timeout((int) config('services.openlibrary.timeout'))
                ->connectTimeout((int) config('services.openlibrary.timeout'))
                ->get('/search.json', ['q' => $query, 'limit' => $limit, 'fields' => self::FIELDS]);

            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new CatalogUnavailableException('Open Library is not reachable.', previous: $e);
        }

        $docs = $response->json('docs');

        if (! is_array($docs)) {
            throw new CatalogUnavailableException('Open Library returned an unexpected response.');
        }

        return array_values(array_filter($docs, is_array(...)));
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return list<CatalogEntry>
     */
    private function entries(array $docs): array
    {
        $entries = [];

        foreach ($docs as $doc) {
            $entry = $this->toEntry($doc);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    private function toEntry(array $doc): ?CatalogEntry
    {
        $key = $doc['key'] ?? null;
        $title = isset($doc['title']) && is_string($doc['title']) ? $this->clip($doc['title']) : '';

        // Without a stable key or a title the work cannot be added to a library.
        if (! is_string($key) || preg_match('#^/works/OL\d+W$#', $key) !== 1 || $title === '') {
            return null;
        }

        $pageCount = $doc['number_of_pages_median'] ?? null;
        $year = $doc['first_publish_year'] ?? null;
        $coverId = $doc['cover_i'] ?? null;
        $subtitle = isset($doc['subtitle']) && is_string($doc['subtitle']) ? $this->clip($doc['subtitle']) : null;

        return new CatalogEntry(
            workKey: $key,
            title: $title,
            subtitle: $subtitle !== '' ? $subtitle : null,
            authors: array_slice($this->strings($doc['author_name'] ?? []), 0, self::MAX_AUTHORS),
            coverUrl: is_int($coverId) && $coverId > 0
                ? rtrim((string) config('services.openlibrary.covers_url'), '/')."/b/id/{$coverId}-M.jpg"
                : null,
            pageCount: is_int($pageCount) && $pageCount > 0 && $pageCount <= self::MAX_PAGES ? $pageCount : null,
            publishedYear: is_int($year) && $year > 0 && $year <= self::MAX_YEAR ? $year : null,
            subjects: array_slice($this->strings($doc['subject'] ?? []), 0, self::MAX_SUBJECTS),
            isbn13: $this->isbn13($doc['isbn'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item) && $this->clip($item) !== '') {
                $strings[] = $this->clip($item);
            }
        }

        return array_values(array_unique($strings));
    }

    private function clip(string $text): string
    {
        return trim(mb_substr(trim($text), 0, self::MAX_TEXT));
    }

    private function isbn13(mixed $isbns): ?string
    {
        foreach ($this->strings($isbns) as $isbn) {
            $digits = str_replace('-', '', $isbn);

            if (preg_match('/^\d{13}$/', $digits) === 1) {
                return $digits;
            }
        }

        return null;
    }
}
