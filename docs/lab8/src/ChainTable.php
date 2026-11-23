<?php

declare(strict_types=1);

namespace Lab8;

/**
 * A hash table with overflow chains. It is the core of the identifier, constant and procedure tables.
 *
 * A record has a number (id) that never changes while the record lives, so other tables can keep it as a pointer.
 * The bucket array holds the id of the first record of each chain; a new record goes to the head of its chain,
 * so a search finds the newest record with the key first (this is what makes shadowing in scopes work).
 */
final class ChainTable
{
    /** @var array<int,int> bucket => id of the head record, or -1 for an empty chain */
    private array $heads;
    /** @var array<int,array{key:string,next:int,data:array}> */
    private array $records = [];
    private int $nextId = 0;
    public int $searches = 0;
    public int $probes = 0;

    public function __construct(private string $hash = 'pjw', private int $buckets = 61)
    {
        $this->heads = array_fill(0, $buckets, -1);
    }

    /** Search: returns the id of the newest record with the key, or null. Counts the compared records. */
    public function find(string $key, ?callable $accept = null): ?int
    {
        $this->searches++;
        for ($id = $this->heads[Hash::bucket($this->hash, $key, $this->buckets)]; $id !== -1; $id = $this->records[$id]['next']) {
            $this->probes++;
            if ($this->records[$id]['key'] === $key && ($accept === null || $accept($this->records[$id]['data']))) {
                return $id;
            }
        }
        return null;
    }

    /** Adds a record to the head of its chain and returns its id. */
    public function add(string $key, array $data): int
    {
        $bucket = Hash::bucket($this->hash, $key, $this->buckets);
        $id = $this->nextId++;
        $this->records[$id] = ['key' => $key, 'next' => $this->heads[$bucket], 'data' => $data];
        $this->heads[$bucket] = $id;
        return $id;
    }

    public function has(int $id): bool
    {
        return isset($this->records[$id]);
    }

    public function key(int $id): string
    {
        return $this->records[$id]['key'];
    }

    /** Reads the whole record (or one attribute when `$attr` is given). */
    public function read(int $id, ?string $attr = null): mixed
    {
        $data = $this->records[$id]['data'] ?? throw new \OutOfBoundsException("No record $id");
        return $attr === null ? $data : ($data[$attr] ?? null);
    }

    public function write(int $id, string $attr, mixed $value): void
    {
        $this->has($id) || throw new \OutOfBoundsException("No record $id");
        $this->records[$id]['data'][$attr] = $value;
    }

    /** Removes a record from its chain and frees it. The ids of the other records do not change. */
    public function delete(int $id): void
    {
        $this->has($id) || throw new \OutOfBoundsException("No record $id");
        $bucket = Hash::bucket($this->hash, $this->records[$id]['key'], $this->buckets);
        $prev = -1;
        for ($cur = $this->heads[$bucket]; $cur !== $id; $cur = $this->records[$cur]['next']) {
            $prev = $cur;
        }
        if ($prev === -1) {
            $this->heads[$bucket] = $this->records[$id]['next'];
        } else {
            $this->records[$prev]['next'] = $this->records[$id]['next'];
        }
        unset($this->records[$id]);
    }

    /** Reorganization: a new number of buckets and all chains rebuilt (the oldest record first, so the order is kept). */
    public function reorganize(?int $buckets = null, ?string $hash = null): void
    {
        $this->buckets = $buckets ?? self::nextPrime(max(31, intdiv(count($this->records), 2)));
        $this->hash = $hash ?? $this->hash;
        $this->heads = array_fill(0, $this->buckets, -1);
        ksort($this->records);
        foreach ($this->records as $id => $record) {
            $bucket = Hash::bucket($this->hash, $record['key'], $this->buckets);
            $this->records[$id]['next'] = $this->heads[$bucket];
            $this->heads[$bucket] = $id;
        }
    }

    /** The smallest prime that is not less than $n. A prime number of buckets spreads the remainders of the hash better. */
    public static function nextPrime(int $n): int
    {
        for (;; $n++) {
            for ($d = 2; $d * $d <= $n; $d++) {
                if ($n % $d === 0) {
                    continue 2;
                }
            }
            if ($n > 1) {
                return $n;
            }
        }
    }

    /** @return array<int,int> ids of all live records, from the oldest */
    public function ids(): array
    {
        return array_keys($this->records);
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function bucketCount(): int
    {
        return $this->buckets;
    }

    /** Statistics of the chains: the longest chain, empty buckets and the average probes of a successful search. */
    public function stats(): array
    {
        $longest = $empty = $sum = 0;
        foreach ($this->heads as $id) {
            $length = 0;
            for (; $id !== -1; $id = $this->records[$id]['next']) {
                $length++;
                $sum += $length;
            }
            $longest = max($longest, $length);
            $empty += $length === 0 ? 1 : 0;
        }
        $n = count($this->records);
        return ['records' => $n, 'buckets' => $this->buckets, 'longest' => $longest, 'empty' => $empty,
            'avg_probes' => $n ? round($sum / $n, 3) : 0.0];
    }

    public function resetCounters(): void
    {
        $this->searches = $this->probes = 0;
    }
}
