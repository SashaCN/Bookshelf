<?php

declare(strict_types=1);

namespace Lab8;

/**
 * A small front end: reads a PHP fragment with token_get_all() and fills the tables from its declarations.
 * It is not a full parser. It understands: namespace, const, class/interface/trait/enum, properties, functions,
 * methods and closures (parameters, types, `use`), variables, `global`, `static`, labels and `goto`.
 */
final class Scanner
{
    private const SKIP = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG];
    private const MODIFIERS = [T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_READONLY, T_VAR, T_ABSTRACT, T_FINAL];
    private const TYPE_TOKENS = [T_STRING, T_ARRAY, T_CALLABLE, T_STATIC, T_NAME_QUALIFIED];
    private const ASSIGN = ['=', '+=', '-=', '*=', '/=', '.=', '%=', '**=', '??=', '&=', '|=', '^=', '<<=', '>>='];

    /** @var array[] tokens as [id, text, line] without spaces and comments */
    private array $t = [];
    private int $i = 0;
    private int $depth = 0;
    private int $pc = 0;
    private int $closures = 0;
    private string $namespace = '';
    /** @var array[] open scopes: depth of the closing brace, kind, name, procedure id */
    private array $open = [];

    /** @var string[] messages of the translator: declarations, fix-ups and scope exits */
    public array $log = [];
    /** @var string[] semantic errors found */
    public array $errors = [];
    /** @var array<int,array> the jump instructions that were generated: pc => [op, label, target] */
    public array $code = [];
    /** @var array<string,int> how many times each operator was met */
    public array $operators = [];
    /** @var array<string,int> how many times each named constant was met */
    public array $constantUses = [];
    /** @var array[] what was deleted at the exit of each scope: ['scope' => ..., 'removed' => [...]] */
    public array $exits = [];

    public function __construct(private Tables $tb)
    {
    }

    public function scan(string $source): void
    {
        foreach (token_get_all($source) as $token) {
            $token = is_array($token) ? $token : [-1, $token, $this->t[array_key_last($this->t)][2] ?? 1];
            in_array($token[0], self::SKIP, true) || $this->t[] = $token;
        }
        $count = count($this->t);
        for ($this->i = 0; $this->i < $count; $this->i++) {
            $this->step();
        }
        $undefined = $this->tb->labels->closeFunction();
        foreach ($undefined as $name) {
            $this->errors[] = "label $name is used but never defined";
        }
    }

    // ---- one token ----------------------------------------------------------------------------------------

    private function step(): void
    {
        [$id, $text, $line] = $this->t[$this->i];
        $kind = $this->tb->identifiers->scopeKind();
        switch (true) {
            case $id === T_NAMESPACE:
                $this->namespace = $this->t[++$this->i][1];
                $this->declare($this->namespace, 'namespace', [], [], null, true, true);
                break;
            case $id === T_CONST:
                $this->constant($kind);
                break;
            case in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $this->tokenIs(1, T_STRING)
                && !$this->prevIs(T_NEW, T_DOUBLE_COLON):
                $this->classHeader($id);
                break;
            case $id === T_FUNCTION:
                $this->functionHeader($kind);
                break;
            case $id === T_GLOBAL:
                $this->globalStatement();
                break;
            case $id === T_STATIC && $this->tokenIs(1, T_VARIABLE) && in_array($kind, ['function', 'closure'], true):
                $this->i++;
                $this->declareVariable($this->t[$this->i][1], ['static'], true);
                break;
            case $id === T_GOTO:
                $name = $this->t[++$this->i][1];
                $this->code[$this->pc] = ['op' => 'goto', 'label' => $name, 'target' => $this->tb->labels->reference($name, $this->pc)];
                break;
            case $id === T_STRING && $this->tokenIs(1, ':') && $this->prevIs(';', '{', '}', null) && $kind !== 'class':
                $this->label($text, $line);
                break;
            case $id === T_VARIABLE:
                $this->variable($text, $kind);
                break;
            case $id === T_LNUMBER || $id === T_DNUMBER || $id === T_CONSTANT_ENCAPSED_STRING:
                $this->tb->constants->addLiteral($this->literal($this->t[$this->i]));
                break;
            case $id === T_STRING:
                $this->name($text);
                break;
            case $text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES:
                $this->depth++;
                break;
            case $text === '}':
                $this->closeBrace();
                break;
            case $text === ';':
                $this->pc++;
                break;
            default:
                $this->operator();
        }
    }

    // ---- declarations -------------------------------------------------------------------------------------

    private function constant(string $kind): void
    {
        $name = $this->t[++$this->i][1];
        $value = $this->tokenIs(1, '=') ? $this->literal($this->t[$this->i + 2]) : null;
        $full = $kind === 'class' ? end($this->open)['name'] . "::$name" : $name;
        try {
            $ref = $this->tb->constants->addUser($full, $value);
        } catch (\LogicException $e) {
            $this->errors[] = $e->getMessage() . " (line {$this->t[$this->i][2]})";
            return;
        }
        $this->declare($name, $kind === 'class' ? 'class_constant' : 'constant', [], [], $ref, true, $kind !== 'class');
    }

    private function classHeader(int $token): void
    {
        $name = $this->t[++$this->i][1];
        $parent = null;
        $flags = [token_name($token) === 'T_CLASS' ? 'class' : strtolower(substr(token_name($token), 2))];
        for ($k = $this->i - 2; $k >= 0 && in_array($this->t[$k][0], [T_ABSTRACT, T_FINAL, T_READONLY], true); $k--) {
            $flags[] = strtolower($this->t[$k][1]);
        }
        while (++$this->i < count($this->t) && $this->t[$this->i][1] !== '{') {
            if ($this->t[$this->i][0] === T_EXTENDS && $parent === null) {
                $parent = $this->typeId($this->t[$this->i + 1][1]);
            }
        }
        try {
            $typeId = $this->tb->types->addClass($name, $parent);
        } catch (\LogicException $e) {
            $this->errors[] = $e->getMessage() . " (line {$this->t[$this->i][2]})";
            $typeId = $this->tb->types->find($name);
        }
        $this->declare($name, 'class', [$typeId], $flags, $typeId, true, true);
        $this->log[] = "class $name" . ($parent === null ? '' : ' extends ' . $this->tb->types->read($parent, 'name')) . ' -> type #' . $typeId;
        $this->tb->identifiers->enterScope('class');
        $this->open[] = ['depth' => ++$this->depth, 'kind' => 'class', 'name' => $name, 'proc' => null, 'type' => $typeId];
    }

    private function functionHeader(string $outer): void
    {
        $start = $this->i;
        $k = $start + 1;
        $k += $this->t[$k][1] === '&' ? 1 : 0;
        $name = $this->t[$k][0] === T_STRING ? $this->t[$k++][1] : null;
        $closure = $name === null;
        $params = $this->parameters($k);                      // moves $k after ')'
        $captured = [];
        if ($closure && $this->t[$k][0] === T_USE) {
            for ($k += 2; $this->t[$k][1] !== ')'; $k++) {
                $this->t[$k][0] === T_VARIABLE && $captured[] = $this->t[$k][1];
            }
            $k++;
        }
        $returns = [];
        if ($this->t[$k][1] === ':') {
            for ($k++; !in_array($this->t[$k][1], ['{', ';'], true); $k++) {
                $returns = array_merge($returns, $this->typeList([$this->t[$k]]));
            }
        }
        $returns = $returns ?: [$this->tb->types->find('mixed')];
        $method = $outer === 'class';
        $owner = $method ? end($this->open)['name'] : null;
        $display = $closure ? '{closure}#' . ++$this->closures : ($method ? "$owner::$name" : $name);
        $hasBody = $this->t[$k][1] === '{';
        try {
            $proc = $this->tb->procedures->addUser($display, $params, $returns, $hasBody ? $this->pc : null);
        } catch (\LogicException $e) {
            $this->errors[] = $e->getMessage() . " (line {$this->t[$start][2]})";
            $proc = $this->tb->procedures->find($display);
        }
        if (!$closure) {
            $ident = $this->declare($name, $method ? 'method' : 'function', $returns, $this->modifiersBefore($start), $proc, true, !$method);
            $this->tb->procedures->write($proc, 'ident', $ident);
        }
        $this->log[] = ($closure ? 'closure' : ($method ? 'method' : 'function')) . " $display(" . count($params) . ' params) -> proc #' . $proc;
        $this->i = $k - 1;                                    // the main loop moves to the '{' or ';'
        if (!$hasBody) {
            return;
        }
        $this->i++;
        $this->tb->identifiers->enterScope($closure ? 'closure' : 'function');
        $this->tb->labels->openFunction();
        $this->open[] = ['depth' => ++$this->depth, 'kind' => $closure ? 'closure' : 'function', 'name' => $display,
            'proc' => $closure ? $proc : null];
        foreach ($params as $p) {
            $this->declare('$' . $p['name'], 'parameter', $p['type'], $p['variadic'] ? ['variadic'] : [], null, true);
        }
        foreach ($captured as $variable) {
            $this->declare($variable, 'variable', [], ['captured'], null, true);
        }
    }

    /** Reads `( type $name = default, ... )` from the token $k; leaves $k after the closing bracket. */
    private function parameters(int &$k): array
    {
        $params = [];
        $level = 0;
        $cur = null;
        for (; ; $k++) {
            [$id, $text] = $this->t[$k];
            if ($text === '(' && $level++ === 0) {
                $cur = ['type' => [], 'optional' => false, 'variadic' => false, 'byRef' => false, 'name' => ''];
                continue;
            }
            if ($text === ')' && --$level === 0 || $text === ',' && $level === 1) {
                $cur['name'] === '' || $params[] = $cur;
                $cur = ['type' => [], 'optional' => false, 'variadic' => false, 'byRef' => false, 'name' => ''];
                if ($text === ')') {
                    $k++;
                    return $params;
                }
                continue;
            }
            match (true) {
                $id === T_VARIABLE => $cur['name'] = ltrim($text, '$'),
                $text === '=' => $cur['optional'] = true,
                $text === '...' => $cur['variadic'] = true,
                $text === '&' => $cur['byRef'] = true,
                $cur['optional'] && $id === T_STRING => $this->name($text),
                !$cur['optional'] && (in_array($id, self::TYPE_TOKENS, true) || $text === '?') => $cur['type'] = array_merge($cur['type'], $this->typeList([$this->t[$k]])),
                default => null,
            };
        }
    }

    private function globalStatement(): void
    {
        while ($this->t[++$this->i][1] !== ';') {
            if ($this->t[$this->i][0] !== T_VARIABLE) {
                continue;
            }
            $name = $this->t[$this->i][1];
            $ids = $this->tb->identifiers;
            $global = $ids->findGlobalVariable($name) ?? $ids->add($name, 'variable', [], [], null, false, true);
            $this->declare($name, 'variable', [], ['global'], $global, true);
            $this->log[] = "global $name -> identifier #$global";
        }
        $this->pc++;
    }

    private function label(string $name, int $line): void
    {
        $fixups = $this->tb->labels->define($name, $this->pc, $line);
        LabelTable::patch($this->code, $fixups, $this->pc);
        $this->log[] = "label $name defined at address {$this->pc}" . ($fixups ? ', fix-up of goto at ' . implode(', ', $fixups) : '');
        $this->i++;                                           // the ':'
    }

    private function variable(string $name, string $kind): void
    {
        if ($name === '$this') {
            return;
        }
        if ($kind === 'class') {
            $flags = $this->modifiersBefore($this->i);
            $types = $this->typeBefore($this->i);
            $this->declare($name, 'property', $types, $flags, null, $this->tokenIs(1, '='));
            return;
        }
        $ids = $this->tb->identifiers;
        $id = $ids->find($name);
        $assigned = in_array($this->t[$this->i + 1][1] ?? '', self::ASSIGN, true) && !$this->prevIs(T_OBJECT_OPERATOR);
        if ($id === null) {
            $given = $assigned || $this->prevIs(T_AS, T_DOUBLE_ARROW);
            $this->declareVariable($name, [], $given);
            $given || $this->errors[] = "variable $name is used before it gets a value (line {$this->t[$this->i][2]})";
        } elseif ($assigned) {
            $ids->write($id, 'initialized', true);
        }
    }

    private function name(string $text): void
    {
        $prev = $this->t[$this->i - 1][0] ?? 0;
        if (in_array($prev, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_NEW, T_DOUBLE_COLON, T_CLASS, T_FUNCTION], true)) {
            return;
        }
        if ($this->tokenIs(1, '(')) {
            $this->call($text);
        } elseif (!$this->tokenIs(1, T_DOUBLE_COLON) && $this->tb->constants->find($text) !== null) {
            $this->constantUses[$text] = ($this->constantUses[$text] ?? 0) + 1;
        }
    }

    /** A call: the procedure table is searched and the arguments are checked. */
    private function call(string $name): void
    {
        $proc = $this->tb->procedures->find($name);
        if ($proc === null) {
            $this->log[] = "call of $name(): not in the procedure table (declared later or in another file)";
            return;
        }
        $args = [];
        $level = 0;
        $cur = [];
        for ($k = $this->i + 1; ; $k++) {
            $text = $this->t[$k][1];
            $level += in_array($text, ['(', '['], true) ? 1 : (in_array($text, [')', ']'], true) ? -1 : 0);
            if ($level === 0 || ($level === 1 && $text === ',')) {
                $cur === [] || $args[] = count($cur) === 1 ? $this->argType($cur[0]) : $this->tb->types->find('mixed');
                $cur = [];
                if ($level === 0) {
                    break;
                }
            } elseif ($k > $this->i + 1) {
                $cur[] = $this->t[$k];
            }
        }
        $errors = $this->tb->procedures->checkCall($proc, $args);
        foreach ($errors as $error) {
            $this->errors[] = $error . " (line {$this->t[$this->i][2]})";
        }
        $this->log[] = "call of $name() with " . count($args) . ' arguments: ' . ($errors ? 'ERROR' : 'ok');
    }

    // ---- helpers ------------------------------------------------------------------------------------------

    private function closeBrace(): void
    {
        $top = end($this->open);
        if ($top !== false && $top['depth'] === $this->depth) {
            array_pop($this->open);
            $removed = $this->tb->identifiers->exitScope();
            $top['kind'] === 'class' && $this->tb->types->write($top['type'], 'members', count($removed));
            $this->exits[] = ['scope' => $top['kind'] . ' ' . $top['name'], 'removed' => $removed];
            if ($top['kind'] !== 'class') {
                foreach ($this->tb->labels->closeFunction() as $name) {
                    $this->errors[] = "label $name is used but never defined (in {$top['name']})";
                }
            }
            if ($top['proc'] !== null) {
                $this->tb->procedures->delete($top['proc']);
            }
        }
        $this->depth--;
    }

    private function operator(): void
    {
        [$id, $text] = $this->t[$this->i];
        $text = in_array($id, [T_INT_CAST, T_DOUBLE_CAST, T_STRING_CAST, T_BOOL_CAST, T_ARRAY_CAST, T_OBJECT_CAST], true)
            ? str_replace(' ', '', $text) : $text;
        $text = ctype_alpha($text[0]) ? strtolower($text) : $text;
        $afterOperand = in_array($this->t[$this->i - 1][0] ?? 0, [T_VARIABLE, T_LNUMBER, T_DNUMBER, T_CONSTANT_ENCAPSED_STRING, T_STRING], true)
            || in_array($this->t[$this->i - 1][1] ?? '', [')', ']'], true);
        $fix = in_array($text, ['++', '--'], true) ? ($afterOperand ? 'postfix' : 'prefix')
            : ($afterOperand ? 'infix' : 'prefix');
        $ops = $this->tb->operations;
        if ($ops->find($text, $fix) !== null) {
            $this->operators["$text ($fix)"] = ($this->operators["$text ($fix)"] ?? 0) + 1;
        }
    }

    private function declare(string $name, string $kind, array $type, array $flags, mixed $ref, bool $init, bool $global = false): int
    {
        try {
            return $this->tb->identifiers->add($name, $kind, $type, $flags, $ref, $init, $global);
        } catch (\LogicException $e) {
            $this->errors[] = $e->getMessage() . " (line {$this->t[$this->i][2]})";
            return $this->tb->identifiers->find($name) ?? -1;
        }
    }

    private function declareVariable(string $name, array $flags, bool $init): void
    {
        $this->declare($name, 'variable', [], $flags, null, $init);
    }

    private function typeId(string $name): ?int
    {
        $id = $this->tb->types->find($name);
        $id !== null || $this->errors[] = "unknown type $name (line {$this->t[$this->i][2]})";
        return $id;
    }

    /** Type ids of the tokens: a name gives one id, `?` gives null. */
    private function typeList(array $tokens): array
    {
        $ids = [];
        foreach ($tokens as [$id, $text]) {
            if ($text === '?') {
                $ids[] = $this->tb->types->find('null');
            } elseif (in_array($id, self::TYPE_TOKENS, true)) {
                ($found = $this->typeId($text)) === null || $ids[] = $found;
            }
        }
        return $ids;
    }

    /** The type written before a property name, for example `private readonly float $r`. */
    private function typeBefore(int $at): array
    {
        $tokens = [];
        for ($k = $at - 1; $k >= 0 && !in_array($this->t[$k][1], [';', '{', '}'], true); $k--) {
            if (!in_array($this->t[$k][0], self::MODIFIERS, true)) {
                array_unshift($tokens, $this->t[$k]);
            }
        }
        return $this->typeList($tokens) ?: [$this->tb->types->find('mixed')];
    }

    private function modifiersBefore(int $at): array
    {
        $flags = [];
        for ($k = $at - 1; $k >= 0 && in_array($this->t[$k][0], self::MODIFIERS, true); $k--) {
            array_unshift($flags, strtolower($this->t[$k][1]));
        }
        return $flags;
    }

    private function argType(array $token): int
    {
        $types = $this->tb->types;
        return match ($token[0]) {
            T_LNUMBER => $types->find('int'),
            T_DNUMBER => $types->find('float'),
            T_CONSTANT_ENCAPSED_STRING => $types->find('string'),
            default => $types->find('mixed'),
        };
    }

    private function literal(array $token): mixed
    {
        return match ($token[0]) {
            T_LNUMBER => intval(str_replace('_', '', $token[1]), 0),
            T_DNUMBER => (float) str_replace('_', '', $token[1]),
            T_CONSTANT_ENCAPSED_STRING => substr($token[1], 1, -1),
            default => null,
        };
    }

    private function tokenIs(int $offset, int|string $what): bool
    {
        $token = $this->t[$this->i + $offset] ?? null;
        return $token !== null && (is_int($what) ? $token[0] === $what : $token[1] === $what);
    }

    private function prevIs(int|string|null ...$what): bool
    {
        $token = $this->t[$this->i - 1] ?? null;
        foreach ($what as $w) {
            if ($w === null ? $token === null : ($token !== null && (is_int($w) ? $token[0] === $w : $token[1] === $w))) {
                return true;
            }
        }
        return false;
    }
}
