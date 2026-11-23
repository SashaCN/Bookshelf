<?php

declare(strict_types=1);

namespace Lab8;

/**
 * The type table. Records are stored in the order of adding (their id never changes); a separate index
 * of ids is kept sorted by the lower-case name and searched with the binary search.
 *
 * Record: id, name, kind (scalar|compound|special|pseudo|resource|class), size (bytes or null),
 * declarable (may be written in a declaration), builtin, parent (id of the parent class), members.
 */
final class TypeTable
{
    /** name, kind, size in bytes (null = depends on the value), declarable */
    private const BUILTIN = [
        ['bool', 'scalar', 1, true], ['int', 'scalar', 8, true], ['float', 'scalar', 8, true],
        ['string', 'scalar', null, true], ['array', 'compound', null, true], ['object', 'compound', null, true],
        ['callable', 'compound', null, true], ['iterable', 'compound', null, true],
        ['null', 'special', 0, true], ['void', 'special', 0, true], ['never', 'special', 0, true],
        ['mixed', 'special', null, true], ['false', 'special', 1, true], ['true', 'special', 1, true],
        ['self', 'pseudo', null, true], ['parent', 'pseudo', null, true], ['static', 'pseudo', null, true],
        ['resource', 'resource', null, false],
    ];

    /** @var array<int,array> */
    private array $records = [];
    /** @var int[] ids sorted by the lower-case name */
    private array $index = [];
    private int $nextId = 0;
    public int $searches = 0;
    public int $probes = 0;

    public function __construct()
    {
        foreach (self::BUILTIN as [$name, $kind, $size, $declarable]) {
            $this->insert($name, $kind, $size, $declarable, true, null);
        }
    }

    /** Binary search by name (case-insensitive, like type names in PHP). Returns the id or null. */
    public function find(string $name): ?int
    {
        $this->searches++;
        $key = strtolower($name);
        $low = 0;
        $high = count($this->index) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $this->probes++;
            $cmp = strcmp($key, $this->records[$this->index[$mid]]['key']);
            if ($cmp === 0) {
                return $this->index[$mid];
            }
            $cmp < 0 ? $high = $mid - 1 : $low = $mid + 1;
        }
        return null;
    }

    /** Adds a user class, interface, trait or enum. The id of the new type is returned. */
    public function addClass(string $name, ?int $parent = null): int
    {
        $this->find($name) === null || throw new \LogicException("Type $name is already declared");
        $parent === null || $this->has($parent) || throw new \OutOfBoundsException("No type $parent");
        return $this->insert($name, 'class', null, true, false, $parent);
    }

    public function has(int $id): bool
    {
        return isset($this->records[$id]);
    }

    public function read(int $id, ?string $attr = null): mixed
    {
        $record = $this->records[$id] ?? throw new \OutOfBoundsException("No type $id");
        return $attr === null ? $record : ($record[$attr] ?? null);
    }

    public function write(int $id, string $attr, mixed $value): void
    {
        $this->has($id) || throw new \OutOfBoundsException("No type $id");
        $this->records[$id][$attr] = $value;
    }

    /** Deletes a user type (for example a class declared inside a block that is closed). Built-in types stay. */
    public function delete(int $id): void
    {
        $this->read($id, 'builtin') === false || throw new \LogicException('A built-in type cannot be deleted');
        unset($this->records[$id]);
        $this->index = array_values(array_filter($this->index, fn (int $i) => $i !== $id));
    }

    /** Reorganization: the index is sorted again (after adding many types it can be built once, at the end). */
    public function reorganize(): void
    {
        $this->index = array_keys($this->records);
        usort($this->index, fn (int $a, int $b) => strcmp($this->records[$a]['key'], $this->records[$b]['key']));
    }

    /** May a value of type $from be passed where type $to is required? (PHP rules without strict_types) */
    public function accepts(int $to, int $from): bool
    {
        $t = $this->records[$to]['key'];
        $f = $this->records[$from]['key'];
        if ($t === $f || $t === 'mixed' || $f === 'never') {
            return true;
        }
        $rule = match ($t) {
            'float' => $f === 'int',
            'bool' => $f === 'true' || $f === 'false',
            'iterable' => $f === 'array',
            'object' => $this->records[$from]['kind'] === 'class',
            default => false,
        };
        // a class accepts its descendants
        for ($p = $this->records[$from]['parent']; !$rule && $p !== null; $p = $this->records[$p]['parent']) {
            $rule = $p === $to;
        }
        return $rule;
    }

    /** The same for a union: $to is a list of ids. */
    public function acceptsAny(array $to, int $from): bool
    {
        foreach ($to as $id) {
            if ($this->accepts($id, $from)) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] names in the sorted order */
    public function names(): array
    {
        return array_map(fn (int $id) => $this->records[$id]['name'], $this->index);
    }

    /** @return array[] all records in the order of ids */
    public function all(): array
    {
        return array_values($this->records);
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function resetCounters(): void
    {
        $this->searches = $this->probes = 0;
    }

    private function insert(string $name, string $kind, ?int $size, bool $declarable, bool $builtin, ?int $parent): int
    {
        $id = $this->nextId++;
        $key = strtolower($name);
        $this->records[$id] = ['id' => $id, 'name' => $name, 'key' => $key, 'kind' => $kind, 'size' => $size,
            'declarable' => $declarable, 'builtin' => $builtin, 'parent' => $parent, 'members' => 0];
        // the place in the sorted index: the first id whose key is bigger (a linear walk is fine for an insert)
        $at = 0;
        while ($at < count($this->index) && strcmp($this->records[$this->index[$at]]['key'], $key) < 0) {
            $at++;
        }
        array_splice($this->index, $at, 0, [$id]);
        return $id;
    }
}
