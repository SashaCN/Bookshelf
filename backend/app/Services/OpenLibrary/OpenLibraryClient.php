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

    /**
     * @return list<CatalogEntry>
     *
     * @throws CatalogUnavailableException
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->entries($this->cachedSearch(Str::lower(Str::squish($query)), $limit));
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
        $cacheKey = 'openlibrary:search:'.sha1($query.'|'.$limit);

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
                ->timeout((int) config('services.openlibrary.timeout'))
                ->retry(2, 150, throw: false)
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
        $title = isset($doc['title']) && is_string($doc['title']) ? trim($doc['title']) : '';

        // Without a stable key or a title the work cannot be added to a library.
        if (! is_string($key) || ! str_starts_with($key, '/works/') || $title === '') {
            return null;
        }

        $pageCount = $doc['number_of_pages_median'] ?? null;
        $year = $doc['first_publish_year'] ?? null;
        $coverId = $doc['cover_i'] ?? null;
        $subtitle = isset($doc['subtitle']) && is_string($doc['subtitle']) ? trim($doc['subtitle']) : null;

        return new CatalogEntry(
            workKey: $key,
            title: $title,
            subtitle: $subtitle !== '' ? $subtitle : null,
            authors: $this->strings($doc['author_name'] ?? []),
            coverUrl: is_int($coverId) && $coverId > 0
                ? rtrim((string) config('services.openlibrary.covers_url'), '/')."/b/id/{$coverId}-M.jpg"
                : null,
            pageCount: is_int($pageCount) && $pageCount > 0 ? $pageCount : null,
            publishedYear: is_int($year) && $year > 0 ? $year : null,
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
            if (is_string($item) && trim($item) !== '') {
                $strings[] = trim($item);
            }
        }

        return array_values(array_unique($strings));
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
