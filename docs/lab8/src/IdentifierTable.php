<?php

declare(strict_types=1);

namespace Lab8;

/**
 * The identifier table: a hash table with overflow chains (ChainTable) plus a stack of scopes.
 *
 * Scopes. In PHP the unit of visibility is the function, not the block: `if` and `foreach` do not open a scope.
 * So the scopes here are: global, function (method, closure) and class. A variable is visible only in its own scope
 * (a closure sees only what is listed in `use`); functions, classes and constants are global.
 *
 * Record: name, kind (keyword|variable|parameter|constant|function|class|property|method|class_constant),
 * scope (id), type (ids in the type table), ref (id in another table: a procedure for a function or method,
 * a constant for a constant, a type for a class, a global variable for `global $x`),
 * addr (the slot in the frame of the scope), flags (static, readonly, global, captured, public, ...), initialized.
 */
final class IdentifierTable
{
    /** Kinds that live in the global scope and are visible everywhere. */
    private const GLOBAL_KINDS = ['keyword', 'function', 'class', 'constant'];

    /** The 25 keywords that fill the table before the translation: word, group. */
    public const KEYWORDS = [
        ['class', 'declaration'], ['function', 'declaration'], ['const', 'declaration'], ['var', 'declaration'],
        ['public', 'modifier'], ['private', 'modifier'], ['protected', 'modifier'], ['static', 'modifier'],
        ['global', 'declaration'], ['interface', 'declaration'], ['trait', 'declaration'], ['enum', 'declaration'],
        ['readonly', 'modifier'], ['abstract', 'modifier'], ['final', 'modifier'], ['namespace', 'declaration'],
        ['use', 'declaration'], ['extends', 'inheritance'], ['implements', 'inheritance'], ['new', 'expression'],
        ['return', 'control'], ['if', 'control'], ['else', 'control'], ['foreach', 'control'], ['goto', 'control'],
    ];

    private ChainTable $table;
    /** @var array<int,array{id:int,kind:string,owned:int[],slots:int}> the stack of open scopes */
    private array $scopes;
    private int $nextScope = 0;

    public function __construct(string $hash = 'pjw', int $buckets = 61)
    {
        $this->table = new ChainTable($hash, $buckets);
        $this->scopes = [['id' => $this->nextScope++, 'kind' => 'global', 'owned' => [], 'slots' => 0]];
        foreach (self::KEYWORDS as [$word, $group]) {
            $this->table->add($word, ['name' => $word, 'kind' => 'keyword', 'scope' => 0, 'type' => [], 'ref' => $group,
                'addr' => null, 'flags' => [], 'initialized' => true]);
        }
    }

    // ---- scopes -------------------------------------------------------------------------------------------

    public function enterScope(string $kind): int
    {
        $this->scopes[] = ['id' => $this->nextScope++, 'kind' => $kind, 'owned' => [], 'slots' => 0];
        return $this->scopes[array_key_last($this->scopes)]['id'];
    }

    /** Deletion of a block: every record of the scope is removed. Returns the names that were removed. */
    public function exitScope(): array
    {
        count($this->scopes) > 1 || throw new \LogicException('The global scope cannot be closed');
        $scope = array_pop($this->scopes);
        $names = [];
        foreach (array_reverse($scope['owned']) as $id) {
            $names[] = $this->table->read($id, 'name');
            $this->table->delete($id);
        }
        return array_reverse($names);
    }

    public function depth(): int
    {
        return count($this->scopes) - 1;
    }

    public function scopeKind(): string
    {
        return $this->scopes[array_key_last($this->scopes)]['kind'];
    }

    // ---- operations ---------------------------------------------------------------------------------------

    /** Search: the record that is visible in the current scope, or null. */
    public function find(string $name): ?int
    {
        $current = $this->scopes[array_key_last($this->scopes)]['id'];
        return $this->table->find($name, fn (array $r) => $r['scope'] === $current
            || ($r['scope'] === 0 && in_array($r['kind'], self::GLOBAL_KINDS, true)));
    }

    /** The variable of the global scope with this name (for the `global $x;` statement). */
    public function findGlobalVariable(string $name): ?int
    {
        return $this->table->find($name, fn (array $r) => $r['scope'] === 0 && $r['kind'] === 'variable');
    }

    /** Is the word a reserved keyword? Keywords are case-insensitive, so the word is made lower case. */
    public function isKeyword(string $word): bool
    {
        $id = $this->table->find(strtolower($word), fn (array $r) => $r['kind'] === 'keyword');
        return $id !== null;
    }

    /**
     * Adds a declaration to the current scope (or to the global scope when `$global` is true, for example a function
     * declared inside another function). A second declaration of a name in the same scope is an error.
     * @param int[] $type ids in the type table
     */
    public function add(string $name, string $kind, array $type = [], array $flags = [], mixed $ref = null,
                        bool $initialized = false, bool $global = false): int
    {
        $scope = &$this->scopes[$global ? 0 : array_key_last($this->scopes)];
        $existing = $this->table->find($name, fn (array $r) => $r['scope'] === $scope['id']);
        $existing === null || throw new \LogicException("Identifier $name is already declared in this scope");
        in_array($kind, self::GLOBAL_KINDS, true) && $kind !== 'keyword' && $this->isKeyword($name) && throw new \LogicException("$name is a reserved word");
        $addr = in_array($kind, ['variable', 'parameter'], true) ? $scope['slots']++ : null;
        $id = $this->table->add($name, ['name' => $name, 'kind' => $kind, 'scope' => $scope['id'], 'type' => $type,
            'ref' => $ref, 'addr' => $addr, 'flags' => $flags, 'initialized' => $initialized]);
        $scope['owned'][] = $id;
        return $id;
    }

    public function read(int $id, ?string $attr = null): mixed
    {
        return $this->table->read($id, $attr);
    }

    public function write(int $id, string $attr, mixed $value): void
    {
        $this->table->read($id, 'kind') !== 'keyword' || throw new \LogicException('Keywords are read-only');
        $this->table->write($id, $attr, $value);
    }

    /** Reorganization: the bucket array is made bigger (or smaller) and the chains are built again. */
    public function reorganize(?int $buckets = null): void
    {
        $this->table->reorganize($buckets);
    }

    /** @return array[] the live records, the oldest first */
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
}
