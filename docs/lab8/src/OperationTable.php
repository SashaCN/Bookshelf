<?php

declare(strict_types=1);

namespace Lab8;

/**
 * The operation table: a static table of all PHP operators (the PHP manual, "Operator Precedence").
 * It is built once and never changes, so only search and reading of attributes exist.
 *
 * Organization: open addressing with linear probing, the key is "<lexeme>|<fix>". The table is less than a third full,
 * so a search takes only a little more than one probe and never walks through the whole table.
 *
 * Record: lexeme, name, fix (prefix|postfix|infix|ternary), arity, prec (bigger binds tighter),
 * assoc (left|right|none), operands (ids of the types that the operands may have, a link to the type table),
 * result (id of the result type, or null when it depends on the operands), gen (the code generation routine).
 */
final class OperationTable
{
    private const SIZE = 257;

    // The groups of operand types.
    private const NUM = ['int', 'float'];
    private const ANY = ['mixed'];

    /** lexeme, name, fix, precedence, assoc, operands (group or names), result type or null, generator */
    private const ROWS = [
        ['->', 'property or method access', 'infix', 25, 'left', ['object', 'mixed'], null, 'gen_member'],
        ['?->', 'null-safe access', 'infix', 25, 'left', ['object', 'null'], null, 'gen_member'],
        ['::', 'scope resolution', 'infix', 25, 'left', ['string', 'object'], null, 'gen_static'],
        ['new', 'object creation', 'prefix', 24, 'none', ['string', 'object'], 'object', 'gen_new'],
        ['clone', 'object copy', 'prefix', 24, 'none', ['object'], 'object', 'gen_clone'],
        ['**', 'exponentiation', 'infix', 23, 'right', self::NUM, null, 'gen_pow'],
        ['++', 'increment', 'prefix', 22, 'none', ['int', 'float', 'string', 'null'], null, 'gen_inc'],
        ['--', 'decrement', 'prefix', 22, 'none', ['int', 'float', 'string', 'null'], null, 'gen_dec'],
        ['++', 'increment', 'postfix', 22, 'none', ['int', 'float', 'string', 'null'], null, 'gen_inc'],
        ['--', 'decrement', 'postfix', 22, 'none', ['int', 'float', 'string', 'null'], null, 'gen_dec'],
        ['~', 'bitwise not', 'prefix', 22, 'none', ['int', 'float', 'string'], null, 'gen_bitnot'],
        ['+', 'unary plus', 'prefix', 22, 'none', self::NUM, null, 'gen_uplus'],
        ['-', 'unary minus', 'prefix', 22, 'none', self::NUM, null, 'gen_neg'],
        ['(int)', 'cast to int', 'prefix', 22, 'none', self::ANY, 'int', 'gen_cast'],
        ['(float)', 'cast to float', 'prefix', 22, 'none', self::ANY, 'float', 'gen_cast'],
        ['(string)', 'cast to string', 'prefix', 22, 'none', self::ANY, 'string', 'gen_cast'],
        ['(bool)', 'cast to bool', 'prefix', 22, 'none', self::ANY, 'bool', 'gen_cast'],
        ['(array)', 'cast to array', 'prefix', 22, 'none', self::ANY, 'array', 'gen_cast'],
        ['(object)', 'cast to object', 'prefix', 22, 'none', self::ANY, 'object', 'gen_cast'],
        ['@', 'error suppression', 'prefix', 22, 'none', self::ANY, null, 'gen_silence'],
        ['instanceof', 'type check', 'infix', 21, 'left', ['object', 'string'], 'bool', 'gen_instanceof'],
        ['!', 'logical not', 'prefix', 20, 'none', self::ANY, 'bool', 'gen_not'],
        ['*', 'multiplication', 'infix', 19, 'left', self::NUM, null, 'gen_mul'],
        ['/', 'division', 'infix', 19, 'left', self::NUM, null, 'gen_div'],
        ['%', 'remainder', 'infix', 19, 'left', ['int'], 'int', 'gen_mod'],
        ['+', 'addition', 'infix', 18, 'left', ['int', 'float', 'array'], null, 'gen_add'],
        ['-', 'subtraction', 'infix', 18, 'left', self::NUM, null, 'gen_sub'],
        ['<<', 'shift left', 'infix', 17, 'left', ['int'], 'int', 'gen_shl'],
        ['>>', 'shift right', 'infix', 17, 'left', ['int'], 'int', 'gen_shr'],
        ['.', 'string concatenation', 'infix', 16, 'left', ['string', 'int', 'float', 'bool', 'null'], 'string', 'gen_concat'],
        ['<', 'less than', 'infix', 15, 'none', self::ANY, 'bool', 'gen_cmp'],
        ['<=', 'less or equal', 'infix', 15, 'none', self::ANY, 'bool', 'gen_cmp'],
        ['>', 'greater than', 'infix', 15, 'none', self::ANY, 'bool', 'gen_cmp'],
        ['>=', 'greater or equal', 'infix', 15, 'none', self::ANY, 'bool', 'gen_cmp'],
        ['==', 'equal', 'infix', 14, 'none', self::ANY, 'bool', 'gen_eq'],
        ['!=', 'not equal', 'infix', 14, 'none', self::ANY, 'bool', 'gen_eq'],
        ['<>', 'not equal', 'infix', 14, 'none', self::ANY, 'bool', 'gen_eq'],
        ['===', 'identical', 'infix', 14, 'none', self::ANY, 'bool', 'gen_identical'],
        ['!==', 'not identical', 'infix', 14, 'none', self::ANY, 'bool', 'gen_identical'],
        ['<=>', 'spaceship', 'infix', 14, 'none', self::ANY, 'int', 'gen_spaceship'],
        ['&', 'bitwise and', 'infix', 13, 'left', ['int', 'string'], null, 'gen_bitand'],
        ['^', 'bitwise xor', 'infix', 12, 'left', ['int', 'string'], null, 'gen_bitxor'],
        ['|', 'bitwise or', 'infix', 11, 'left', ['int', 'string'], null, 'gen_bitor'],
        ['&&', 'logical and', 'infix', 10, 'left', self::ANY, 'bool', 'gen_and'],
        ['||', 'logical or', 'infix', 9, 'left', self::ANY, 'bool', 'gen_or'],
        ['??', 'null coalescing', 'infix', 8, 'right', self::ANY, null, 'gen_coalesce'],
        ['? :', 'conditional', 'ternary', 7, 'none', self::ANY, null, 'gen_ternary'],
        ['?:', 'short conditional', 'infix', 7, 'none', self::ANY, null, 'gen_elvis'],
        ['=', 'assignment', 'infix', 6, 'right', self::ANY, null, 'gen_assign'],
        ['=&', 'assignment by reference', 'infix', 6, 'right', self::ANY, null, 'gen_assign_ref'],
        ['+=', 'add and assign', 'infix', 6, 'right', self::NUM, null, 'gen_assign_op'],
        ['-=', 'subtract and assign', 'infix', 6, 'right', self::NUM, null, 'gen_assign_op'],
        ['*=', 'multiply and assign', 'infix', 6, 'right', self::NUM, null, 'gen_assign_op'],
        ['/=', 'divide and assign', 'infix', 6, 'right', self::NUM, null, 'gen_assign_op'],
        ['%=', 'remainder and assign', 'infix', 6, 'right', ['int'], 'int', 'gen_assign_op'],
        ['**=', 'power and assign', 'infix', 6, 'right', self::NUM, null, 'gen_assign_op'],
        ['.=', 'concatenate and assign', 'infix', 6, 'right', ['string', 'int', 'float', 'bool', 'null'], 'string', 'gen_assign_op'],
        ['&=', 'and and assign', 'infix', 6, 'right', ['int', 'string'], null, 'gen_assign_op'],
        ['|=', 'or and assign', 'infix', 6, 'right', ['int', 'string'], null, 'gen_assign_op'],
        ['^=', 'xor and assign', 'infix', 6, 'right', ['int', 'string'], null, 'gen_assign_op'],
        ['<<=', 'shift left and assign', 'infix', 6, 'right', ['int'], 'int', 'gen_assign_op'],
        ['>>=', 'shift right and assign', 'infix', 6, 'right', ['int'], 'int', 'gen_assign_op'],
        ['??=', 'coalesce and assign', 'infix', 6, 'right', self::ANY, null, 'gen_assign_op'],
        ['yield from', 'delegating yield', 'prefix', 5, 'none', ['iterable'], 'mixed', 'gen_yield_from'],
        ['yield', 'yield', 'prefix', 5, 'none', self::ANY, 'mixed', 'gen_yield'],
        ['print', 'print', 'prefix', 5, 'none', self::ANY, 'int', 'gen_print'],
        ['and', 'low precedence and', 'infix', 4, 'left', self::ANY, 'bool', 'gen_and'],
        ['xor', 'logical xor', 'infix', 3, 'left', self::ANY, 'bool', 'gen_xor'],
        ['or', 'low precedence or', 'infix', 2, 'left', self::ANY, 'bool', 'gen_or'],
        ['throw', 'throw', 'prefix', 1, 'none', ['object'], 'never', 'gen_throw'],
    ];

    /** @var array<int,?array> slot => record */
    private array $slots;
    public int $searches = 0;
    public int $probes = 0;

    public function __construct(private TypeTable $types, private string $hash = 'pjw')
    {
        $this->slots = array_fill(0, self::SIZE, null);
        foreach (self::ROWS as [$lexeme, $name, $fix, $prec, $assoc, $operands, $result, $gen]) {
            $record = ['lexeme' => $lexeme, 'name' => $name, 'fix' => $fix, 'prec' => $prec, 'assoc' => $assoc,
                'arity' => match ($fix) { 'infix' => 2, 'ternary' => 3, default => 1 },
                'operands' => array_map(fn (string $t) => $this->types->find($t), $operands),
                'result' => $result === null ? null : $this->types->find($result), 'gen' => $gen];
            $slot = Hash::bucket($this->hash, "$lexeme|$fix", self::SIZE);
            while ($this->slots[$slot] !== null) {
                $slot = ($slot + 1) % self::SIZE;
            }
            $this->slots[$slot] = $record;
        }
    }

    /** Search by lexeme and kind of the operator. Returns the record or null. */
    public function find(string $lexeme, string $fix = 'infix'): ?array
    {
        $this->searches++;
        $slot = Hash::bucket($this->hash, "$lexeme|$fix", self::SIZE);
        while ($this->slots[$slot] !== null) {
            $this->probes++;
            if ($this->slots[$slot]['lexeme'] === $lexeme && $this->slots[$slot]['fix'] === $fix) {
                return $this->slots[$slot];
            }
            $slot = ($slot + 1) % self::SIZE;
        }
        $this->probes++;
        return null;
    }

    /** Reads one attribute of an operator, for example attr('**', 'assoc') is 'right'. */
    public function attr(string $lexeme, string $attr, string $fix = 'infix'): mixed
    {
        return ($this->find($lexeme, $fix) ?? throw new \OutOfBoundsException("No operator $lexeme ($fix)"))[$attr] ?? null;
    }

    /** Does operator $a bind tighter than $b? Equal levels follow the associativity of $a (true = bind left first). */
    public function bindsTighter(string $a, string $b, string $fixA = 'infix', string $fixB = 'infix'): bool
    {
        $ra = $this->find($a, $fixA) ?? throw new \OutOfBoundsException($a);
        $rb = $this->find($b, $fixB) ?? throw new \OutOfBoundsException($b);
        return $ra['prec'] > $rb['prec'] || ($ra['prec'] === $rb['prec'] && $ra['assoc'] === 'left');
    }

    /** May the operator get these operand types? (a link to the type table: accepts()) */
    public function allows(string $lexeme, string $fix, array $operandTypes): bool
    {
        $record = $this->find($lexeme, $fix);
        if ($record === null || count($operandTypes) !== $record['arity']) {
            return false;
        }
        foreach ($operandTypes as $type) {
            if (!$this->types->acceptsAny($record['operands'], $type)) {
                return false;
            }
        }
        return true;
    }

    /** @return array[] all records, ordered by the precedence (the tightest first) */
    public function all(): array
    {
        $records = array_values(array_filter($this->slots));
        usort($records, fn (array $a, array $b) => [$b['prec'], $a['lexeme']] <=> [$a['prec'], $b['lexeme']]);
        return $records;
    }

    public function count(): int
    {
        return count(array_filter($this->slots));
    }

    public function load(): float
    {
        return round($this->count() / self::SIZE, 3);
    }

    public function resetCounters(): void
    {
        $this->searches = $this->probes = 0;
    }
}
