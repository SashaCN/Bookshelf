<?php

declare(strict_types=1);

namespace Lab8;

/**
 * The procedure table: functions and methods. A hash table with chains (ChainTable) keyed by the lower-case name
 * (function names are not case-sensitive in PHP). It starts with 8 built-in functions, user functions are added.
 *
 * Record: name, kind (function|procedure: a procedure returns void), origin (builtin|user),
 * params (list of: name, type = ids in the type table, optional, variadic, byRef), min and max number of arguments
 * (max is null when the last parameter is variadic), returns (ids in the type table), addr (address of the first
 * instruction of the body; null for built-ins), ident (id in the identifier table).
 */
final class ProcedureTable
{
    /** name, params "type $name" ("?" = optional, "..." = variadic), return type */
    private const BUILTIN = [
        ['strlen', ['string $string'], 'int'],
        ['count', ['array $value', '?int $mode'], 'int'],
        ['array_map', ['callable|null $callback', 'array $array', '...array $arrays'], 'array'],
        ['str_contains', ['string $haystack', 'string $needle'], 'bool'],
        ['json_encode', ['mixed $value', '?int $flags', '?int $depth'], 'string|false'],
        ['intdiv', ['int $num1', 'int $num2'], 'int'],
        ['sprintf', ['string $format', '...mixed $values'], 'string'],
        ['in_array', ['mixed $needle', 'array $haystack', '?bool $strict'], 'bool'],
    ];

    private ChainTable $table;

    public function __construct(private TypeTable $types, string $hash = 'pjw', int $buckets = 31)
    {
        $this->table = new ChainTable($hash, $buckets);
        foreach (self::BUILTIN as [$name, $params, $returns]) {
            $this->put($name, 'function', 'builtin', array_map($this->parseParam(...), $params), $returns, null);
        }
    }

    /** Search by name (case-insensitive). Returns the id or null. */
    public function find(string $name): ?int
    {
        return $this->table->find(strtolower($name));
    }

    /**
     * Adds a user function or method. $params is a list of ['name' => ..., 'type' => [type ids], 'optional' => bool,
     * 'variadic' => bool, 'byRef' => bool]; $returns is a list of type ids.
     */
    public function addUser(string $name, array $params, array $returns, ?int $addr = null, ?int $ident = null): int
    {
        $this->find($name) === null || throw new \LogicException("Function $name is already declared");
        $void = $returns === [$this->types->find('void')];
        return $this->put($name, $void ? 'procedure' : 'function', 'user', $params, $returns, $addr, $ident);
    }

    public function read(int $id, ?string $attr = null): mixed
    {
        return $this->table->read($id, $attr);
    }

    public function write(int $id, string $attr, mixed $value): void
    {
        $this->table->read($id, 'origin') === 'user' || throw new \LogicException('A built-in function is read-only');
        $this->table->write($id, $attr, $value);
    }

    /** Deletion at the end of a block: for example an anonymous function after its body is translated. */
    public function delete(int $id): void
    {
        $this->table->read($id, 'origin') === 'user' || throw new \LogicException('A built-in function cannot be deleted');
        $this->table->delete($id);
    }

    public function reorganize(?int $buckets = null): void
    {
        $this->table->reorganize($buckets);
    }

    /**
     * The semantic check of a call: the number and the types of the actual arguments against the formal ones.
     * @param int[] $argTypes type ids of the arguments
     * @return string[] the list of errors (empty = the call is correct)
     */
    public function checkCall(int $id, array $argTypes): array
    {
        $p = $this->table->read($id);
        $errors = [];
        $count = count($argTypes);
        if ($count < $p['min']) {
            $errors[] = "{$p['name']}() needs at least {$p['min']} arguments, $count given";
        }
        if ($p['max'] !== null && $count > $p['max']) {
            $errors[] = "{$p['name']}() takes at most {$p['max']} arguments, $count given";
        }
        foreach ($argTypes as $i => $type) {
            $param = $p['params'][min($i, count($p['params']) - 1)] ?? null;
            // an argument of type mixed is a value whose type is not known to the translator: it is not checked
            $known = $this->types->read($type, 'key') !== 'mixed';
            if ($known && $param !== null && ($i < count($p['params']) || $param['variadic']) && !$this->types->acceptsAny($param['type'], $type)) {
                $errors[] = "argument " . ($i + 1) . " of {$p['name']}(): {$this->types->read($type, 'name')} is not accepted";
            }
        }
        return $errors;
    }

    /** @return array[] all records, the oldest first */
    public function all(): array
    {
        return array_map(fn (int $id) => ['id' => $id] + $this->table->read($id), $this->table->ids());
    }

    public function count(): int
    {
        return $this->table->count();
    }

    public function stats(): array
    {
        return $this->table->stats();
    }

    private function put(string $name, string $kind, string $origin, array $params, string|array $returns, ?int $addr, ?int $ident = null): int
    {
        $variadic = $params !== [] && $params[array_key_last($params)]['variadic'];
        $min = count(array_filter($params, fn (array $p) => !$p['optional'] && !$p['variadic']));
        return $this->table->add(strtolower($name), ['name' => $name, 'kind' => $kind, 'origin' => $origin,
            'params' => $params, 'min' => $min, 'max' => $variadic ? null : count($params),
            'returns' => is_string($returns) ? $this->typeIds($returns) : $returns, 'addr' => $addr, 'ident' => $ident]);
    }

    /** "?int $mode" or "...array $arrays" or "callable|null $callback" into a parameter record. */
    private function parseParam(string $text): array
    {
        [$type, $name] = explode(' $', $text);
        $optional = str_starts_with($type, '?');
        $variadic = str_starts_with($type, '...');
        return ['name' => $name, 'type' => $this->typeIds(ltrim($type, '?.')), 'optional' => $optional,
            'variadic' => $variadic, 'byRef' => false];
    }

    /** "string|false" into [id, id] */
    private function typeIds(string $union): array
    {
        return array_map(fn (string $t) => $this->types->find($t) ?? throw new \LogicException("Unknown type $t"), explode('|', $union));
    }
}
