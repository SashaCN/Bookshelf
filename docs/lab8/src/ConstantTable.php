<?php

declare(strict_types=1);

namespace Lab8;

/**
 * The constant table: a hash table with chains (see ChainTable). It holds three kinds of records:
 *   predefined  constants of the language (PHP_INT_MAX, M_PI, E_ALL, true, false, null), filled before the translation;
 *   user        constants from `const NAME = ...;` and `define()`;
 *   literal     numbers and strings met in the program text; the same literal is stored once.
 *
 * Record: name, kind, type (id in the type table), value.
 * The key of a literal is "<type>:<value>", for example "int:42" or 'string:"abc"'.
 */
final class ConstantTable
{
    private const PREDEFINED = ['PHP_INT_MAX', 'PHP_INT_MIN', 'PHP_INT_SIZE', 'PHP_FLOAT_EPSILON', 'PHP_EOL', 'E_ALL',
        'E_ERROR', 'E_WARNING', 'M_PI', 'M_E', 'SORT_ASC', 'COUNT_RECURSIVE', 'JSON_PRETTY_PRINT', 'DIRECTORY_SEPARATOR'];

    private ChainTable $table;

    public function __construct(private TypeTable $types, string $hash = 'pjw', int $buckets = 31)
    {
        $this->table = new ChainTable($hash, $buckets);
        foreach (self::PREDEFINED as $name) {
            $this->put($name, 'predefined', constant($name));
        }
        // true, false and null are not case-sensitive in PHP, so they are stored in lower case
        $this->put('true', 'predefined', true);
        $this->put('false', 'predefined', false);
        $this->put('null', 'predefined', null);
    }

    /** Search by name. The names are case-sensitive except true, false and null. */
    public function find(string $name): ?int
    {
        $found = $this->table->find($name, fn (array $r) => $r['kind'] !== 'literal');
        if ($found === null && in_array($lower = strtolower($name), ['true', 'false', 'null'], true)) {
            $found = $this->table->find($lower);
        }
        return $found;
    }

    /** Adds a user constant (const NAME = value). A second declaration of the same name is an error. */
    public function addUser(string $name, mixed $value): int
    {
        $this->find($name) === null || throw new \LogicException("Constant $name is already defined");
        return $this->put($name, 'user', $value);
    }

    /** Adds a literal, or returns the existing record of the same literal. */
    public function addLiteral(mixed $value): int
    {
        $type = $this->typeNameOf($value);
        $key = $type . ':' . var_export($value, true);
        return $this->table->find($key) ?? $this->table->add($key, [
            'name' => $key, 'kind' => 'literal', 'type' => $this->types->find($type), 'value' => $value]);
    }

    public function read(int $id, ?string $attr = null): mixed
    {
        return $this->table->read($id, $attr);
    }

    /** Only user constants and literals can be changed (for example, a literal whose value is folded later). */
    public function write(int $id, string $attr, mixed $value): void
    {
        $this->table->read($id, 'kind') !== 'predefined' || throw new \LogicException('A predefined constant is read-only');
        $this->table->write($id, $attr, $value);
    }

    public function delete(int $id): void
    {
        $this->table->read($id, 'kind') !== 'predefined' || throw new \LogicException('A predefined constant cannot be deleted');
        $this->table->delete($id);
    }

    public function reorganize(?int $buckets = null): void
    {
        $this->table->reorganize($buckets);
    }

    /** @return array[] all records, the oldest first */
    public function all(): array
    {
        return array_map(fn (int $id) => ['id' => $id] + $this->table->read($id), $this->table->ids());
    }

    public function stats(): array
    {
        return $this->table->stats();
    }

    private function put(string $name, string $kind, mixed $value): int
    {
        return $this->table->add($name, ['name' => $name, 'kind' => $kind,
            'type' => $this->types->find($this->typeNameOf($value)), 'value' => $value]);
    }

    private function typeNameOf(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => 'mixed',
        };
    }
}
