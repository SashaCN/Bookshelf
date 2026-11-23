<?php

/**
 * Tests of laboratory work 8. A tiny runner, no PHPUnit.
 *   php docs/lab8/tests.php          prints one line per check and a summary (exit code 1 if something failed)
 *   php docs/lab8/tests.php json     prints all checks as JSON (used by the report)
 */

declare(strict_types=1);

use Lab8\ChainTable;
use Lab8\Hash;
use Lab8\LabelTable;
use Lab8\Scanner;
use Lab8\Tables;

require __DIR__ . '/src/bootstrap.php';

/** @var array[] every check: table, case, expected, actual, ok */
$checks = [];
$group = '';

function group(string $name): void
{
    global $group;
    $group = $name;
}

function show(mixed $v): string
{
    return is_string($v) ? "'$v'" : (is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) : var_export($v, true));
}

/** One check: the expected result against the actual one. */
function eq(string $case, mixed $expected, mixed $actual): void
{
    global $checks, $group;
    $checks[] = ['table' => $group, 'case' => $case, 'expected' => show($expected), 'actual' => show($actual), 'ok' => $expected === $actual];
}

/** A check that the call throws an exception of the class (the message is shown). */
function throws(string $case, callable $fn, string $class = LogicException::class): void
{
    try {
        $fn();
        eq($case, $class, 'no exception');
    } catch (Throwable $e) {
        eq($case, $class, $e instanceof $class ? $class : get_class($e));
    }
}

// ------------------------------------------------------------------------------------------------------------ Hash
group('Hash functions');
eq("sum('ab') = 97 + 98", 195, Hash::sum('ab'));
eq("poly31('ab') = 97 * 31 + 98", 3105, Hash::poly31('ab'));
eq("pjw('a') = 97", 97, Hash::pjw('a'));
eq("pjw('ab') = (97 << 4) + 98", 1650, Hash::pjw('ab'));
eq("sum gives the same value for the anagrams abc and cba", true, Hash::sum('abc') === Hash::sum('cba'));
eq("poly31 gives different values for abc and cba", true, Hash::poly31('abc') !== Hash::poly31('cba'));
$inRange = true;
foreach (Hash::NAMES as $fn) {
    foreach (['$user', 'App\\Models\\Book', str_repeat('x', 300), ''] as $key) {
        $inRange = $inRange && Hash::bucket($fn, $key, 61) >= 0 && Hash::bucket($fn, $key, 61) < 61;
    }
}
eq('the bucket is always in 0..M-1 (3 functions, 4 keys, long and empty keys too)', true, $inRange);
throws('an unknown hash function is refused', fn () => Hash::bucket('md5', 'a', 61), InvalidArgumentException::class);

// ---------------------------------------------------------------------------------------------------- ChainTable
group('Chain table (the core)');
$c = new ChainTable('pjw', 7);
$a = $c->add('alpha', ['v' => 1]);
$b = $c->add('beta', ['v' => 2]);
eq('find returns the id of an added key', $a, $c->find('alpha'));
eq('find of a missing key is null', null, $c->find('gamma'));
$a2 = $c->add('alpha', ['v' => 3]);
eq('the newest record with the same key is found first', $a2, $c->find('alpha'));
$c->delete($a2);
eq('after deleting the newest record the older one is found again', $a, $c->find('alpha'));
eq('has() of a deleted id is false', false, $c->has($a2));
$c->write($b, 'v', 20);
eq('write and read of an attribute', 20, $c->read($b, 'v'));
$big = new ChainTable('pjw', 4);
$ids = [];
for ($i = 0; $i < 100; $i++) {
    $ids["key$i"] = $big->add("key$i", ['n' => $i]);
}
$before = $big->stats()['avg_probes'];
$big->reorganize(61);
$after = $big->stats()['avg_probes'];
eq('reorganize: 100 records in 4 buckets, then in 61: the average chain is shorter', true, $after < $before);
eq('reorganize does not change the ids', $ids['key57'], $big->find('key57'));
eq('reorganize keeps all records', 100, $big->count());
eq('delete from the middle of a chain keeps the other records reachable', true, (function () {
    $t = new ChainTable('sum', 1);        // one bucket: all in one chain
    [$x, $y, $z] = [$t->add('x', []), $t->add('y', []), $t->add('z', [])];
    $t->delete($y);
    return $t->find('x') === $x && $t->find('z') === $z && $t->find('y') === null;
})());
throws('delete of an unknown id is refused', fn () => $c->delete(99), OutOfBoundsException::class);
eq('nextPrime(60) = 61', 61, ChainTable::nextPrime(60));

// ----------------------------------------------------------------------------------------------------- TypeTable
group('Type table');
$tb = new Tables();
$ty = $tb->types;
eq('18 built-in types', 18, $ty->count());
eq('find int', true, $ty->find('int') !== null);
eq('find is not case-sensitive: INT', $ty->find('int'), $ty->find('INT'));
eq('find of an unknown type is null', null, $ty->find('Decimal'));
eq('size of int is 8 bytes', 8, $ty->read($ty->find('int'), 'size'));
eq('size of bool is 1 byte', 1, $ty->read($ty->find('bool'), 'size'));
eq('string has no fixed size', null, $ty->read($ty->find('string'), 'size'));
eq('resource cannot be written in a declaration', false, $ty->read($ty->find('resource'), 'declarable'));
eq('kind of array is compound', 'compound', $ty->read($ty->find('array'), 'kind'));
$ty->resetCounters();
foreach ($ty->names() as $name) {
    $ty->find($name);
}
eq('binary search: at most 5 probes per name for 18 types', true, $ty->probes / $ty->searches <= 5);
eq('names() are sorted', true, ($n = $ty->names()) === (function () use ($n) { sort($n); return $n; })());
$shape = $ty->addClass('Shape');
$circle = $ty->addClass('Circle', $shape);
eq('a new class gets the next id (after 18 built-in types and Shape)', 19, $circle);
eq('the new class is found', $circle, $ty->find('circle'));
eq('the parent of Circle is Shape', $shape, $ty->read($circle, 'parent'));
throws('the same type declared twice is refused', fn () => $ty->addClass('SHAPE'));
eq('int is accepted where float is required', true, $ty->accepts($ty->find('float'), $ty->find('int')));
eq('float is not accepted where int is required', false, $ty->accepts($ty->find('int'), $ty->find('float')));
eq('anything is accepted where mixed is required', true, $ty->accepts($ty->find('mixed'), $ty->find('string')));
eq('a class is accepted where its parent is required', true, $ty->accepts($shape, $circle));
eq('a parent is not accepted where its child is required', false, $ty->accepts($circle, $shape));
eq('array is accepted where iterable is required', true, $ty->accepts($ty->find('iterable'), $ty->find('array')));
eq('a class is accepted where object is required', true, $ty->accepts($ty->find('object'), $circle));
eq('acceptsAny for the union string|false', true, $ty->acceptsAny([$ty->find('string'), $ty->find('false')], $ty->find('false')));
throws('a built-in type cannot be deleted', fn () => $ty->delete($ty->find('int')));
$ty->delete($circle);
eq('a deleted user type is not found', null, $ty->find('Circle'));
eq('other types are still found after the delete', true, $ty->find('Shape') === $shape && $ty->find('float') !== null);
$ty->reorganize();
eq('reorganize keeps the search working', $shape, $ty->find('Shape'));

// ------------------------------------------------------------------------------------------------- ConstantTable
group('Constant table');
$tb = new Tables();
$k = $tb->constants;
$ty = $tb->types;
eq('17 predefined constants', 17, count($k->all()));
eq('PHP_INT_MAX has the real value', PHP_INT_MAX, $k->read($k->find('PHP_INT_MAX'), 'value'));
eq('E_ALL has the real value', E_ALL, $k->read($k->find('E_ALL'), 'value'));
eq('type of M_PI is float', $ty->find('float'), $k->read($k->find('M_PI'), 'type'));
eq('type of PHP_EOL is string', $ty->find('string'), $k->read($k->find('PHP_EOL'), 'type'));
eq('TRUE is found (true, false, null are not case-sensitive)', true, $k->read($k->find('TRUE'), 'value'));
eq('php_int_max in the wrong case is not found', null, $k->find('php_int_max'));
$l1 = $k->addLiteral(42);
eq('the same literal is stored once', $l1, $k->addLiteral(42));
eq('the string "42" is another literal than the number 42', true, $k->addLiteral('42') !== $l1);
eq('type of the literal 1.5 is float', $ty->find('float'), $k->read($k->addLiteral(1.5), 'type'));
eq('a literal is not found as a named constant', null, $k->find('int:42'));
$limit = $k->addUser('LIMIT', 10);
eq('a user constant is found and has the value', 10, $k->read($k->find('LIMIT'), 'value'));
throws('a constant defined twice is refused', fn () => $k->addUser('LIMIT', 11));
throws('a predefined constant is read-only', fn () => $k->write($k->find('M_PI'), 'value', 3));
throws('a predefined constant cannot be deleted', fn () => $k->delete($k->find('M_PI')));
$k->delete($limit);
eq('a deleted user constant is not found', null, $k->find('LIMIT'));
$before = $k->find('E_ALL');
$k->reorganize(7);
eq('reorganize keeps the ids', $before, $k->find('E_ALL'));

// ----------------------------------------------------------------------------------------------- OperationTable
group('Operation table');
$tb = new Tables();
$o = $tb->operations;
$ty = $tb->types;
eq('70 operations', 70, $o->count());
eq('** is right-associative', 'right', $o->attr('**', 'assoc'));
eq('+ is left-associative', 'left', $o->attr('+', 'assoc'));
eq('< is non-associative', 'none', $o->attr('<', 'assoc'));
eq('?? is right-associative', 'right', $o->attr('??', 'assoc'));
eq('- has two forms: binary has arity 2', 2, $o->attr('-', 'arity', 'infix'));
eq('- has two forms: unary has arity 1', 1, $o->attr('-', 'arity', 'prefix'));
eq('++ prefix and postfix are different records', true, $o->find('++', 'prefix') !== $o->find('++', 'postfix'));
eq('the ternary operator has arity 3', 3, $o->attr('? :', 'arity', 'ternary'));
eq('an unknown operator is not found', null, $o->find('<<<'));
eq('a lexeme with a wrong form is not found: * as prefix', null, $o->find('*', 'prefix'));
eq('code generator of + is gen_add', 'gen_add', $o->attr('+', 'gen'));
eq('result type of < is bool', $ty->find('bool'), $o->attr('<', 'result'));
eq('result type of + depends on the operands', null, $o->attr('+', 'result'));
$order = ['**', '*', '+', '<<', '.', '<', '==', '&', '^', '|', '&&', '||', '??', '=', 'and', 'xor', 'or'];
$decreasing = true;
for ($i = 1; $i < count($order); $i++) {
    $decreasing = $decreasing && $o->attr($order[$i - 1], 'prec') > $o->attr($order[$i], 'prec');
}
eq('the precedence follows the PHP manual: ** * + << . < == & ^ | && || ?? = and xor or', true, $decreasing);
eq('* binds tighter than +', true, $o->bindsTighter('*', '+'));
eq('+ does not bind tighter than *', false, $o->bindsTighter('+', '*'));
eq('+ binds tighter than . (PHP 8)', true, $o->bindsTighter('+', '.'));
eq('a - b - c: the left - binds first', true, $o->bindsTighter('-', '-'));
eq('a = b = c: the left = does not bind first', false, $o->bindsTighter('=', '='));
eq('! binds tighter than *', true, $o->bindsTighter('!', '*', 'prefix', 'infix'));
eq('= binds tighter than and', true, $o->bindsTighter('=', 'and'));
eq('+ accepts (int, float)', true, $o->allows('+', 'infix', [$ty->find('int'), $ty->find('float')]));
eq('+ does not accept (string, int)', false, $o->allows('+', 'infix', [$ty->find('string'), $ty->find('int')]));
eq('. accepts (string, int)', true, $o->allows('.', 'infix', [$ty->find('string'), $ty->find('int')]));
eq('% does not accept a float', false, $o->allows('%', 'infix', [$ty->find('float'), $ty->find('int')]));
eq('! accepts an array', true, $o->allows('!', 'prefix', [$ty->find('array')]));
eq('an operator with a wrong number of operands is refused', false, $o->allows('+', 'infix', [$ty->find('int')]));
$o->resetCounters();
foreach ($o->all() as $r) {
    $o->find($r['lexeme'], $r['fix']);
}
eq('the average search takes less than 2 probes', true, $o->probes / $o->searches < 2);
eq('the table is less than half full', true, $o->load() < 0.5);

// -------------------------------------------------------------------------------------------- IdentifierTable
group('Identifier table');
$tb = new Tables();
$id = $tb->identifiers;
$ty = $tb->types;
eq('25 keywords after the initialization', 25, $id->count());
eq('class is a keyword', true, $id->isKeyword('class'));
eq('keywords are not case-sensitive: FUNCTION', true, $id->isKeyword('FUNCTION'));
eq('foo is not a keyword', false, $id->isKeyword('foo'));
eq('group of the keyword goto is control', 'control', $id->read($id->find('goto'), 'ref'));
$x = $id->add('$x', 'variable', [$ty->find('int')], [], null, true);
eq('a declared variable is found', $x, $id->find('$x'));
eq('the type attribute is a pointer to the type table', $ty->find('int'), $id->read($x, 'type')[0]);
eq('the slot of the first variable is 0', 0, $id->read($x, 'addr'));
eq('the slot of the second variable is 1', 1, $id->read($id->add('$y', 'variable'), 'addr'));
throws('a second declaration in the same scope is refused', fn () => $id->add('$x', 'variable'));
$id->write($x, 'initialized', false);
eq('write and read of an attribute', false, $id->read($x, 'initialized'));
throws('a keyword is read-only', fn () => $id->write($id->find('class'), 'kind', 'variable'));
throws('a class cannot be named like a keyword', fn () => $id->add('interface', 'class', [], [], null, true, true));
$id->enterScope('function');
eq('a variable of the outer scope is not visible in a function', null, $id->find('$x'));
eq('a keyword is visible in a function', true, $id->find('class') !== null);
$inner = $id->add('$x', 'variable');
eq('the same name in the function is a new record', true, $inner !== $x && $id->find('$x') === $inner);
eq('the slot of a variable in a new scope starts from 0', 0, $id->read($inner, 'addr'));
$f = $id->add('helper', 'function', [], [], null, true, true);
eq('a function declared inside a function goes to the global scope', true, $id->find('helper') === $f);
eq('depth of the scope is 1', 1, $id->depth());
$removed = $id->exitScope();
eq('exitScope returns the names that were deleted', ['$x'], $removed);
eq('after the exit the outer $x is visible again', $x, $id->find('$x'));
eq('the function declared inside survives the exit', $f, $id->find('helper'));
throws('the global scope cannot be closed', fn () => $id->exitScope());
$n = $id->count();
$id->enterScope('function');
$id->enterScope('closure');
$id->add('$a', 'variable');
$id->exitScope();
$id->add('$b', 'variable');
$id->exitScope();
eq('nested scopes: after both exits the table has the same size', $n, $id->count());
$big = new Lab8\IdentifierTable('pjw', 3);
for ($i = 0; $i < 300; $i++) {
    $big->add("\$v$i", 'variable', [], [], null, false, true);
}
$before = $big->stats()['avg_probes'];
$big->reorganize();
eq('reorganize: 300 records in 3 buckets, then in a bigger table: shorter chains', true, $big->stats()['avg_probes'] < $before);
eq('reorganize keeps the records reachable', true, $big->find('$v299') !== null && $big->find('$v0') !== null);

// ----------------------------------------------------------------------------------------------------- LabelTable
group('Label table');
$lt = new LabelTable();
eq('a forward goto: the label is unknown yet', null, $lt->reference('end', 5));
eq('a second forward goto to the same label', null, $lt->reference('end', 9));
eq('the label is created as undefined', false, $lt->read('end', 'defined'));
eq('define returns the instructions to fix up', [5, 9], $lt->define('end', 20, 7));
$code = [5 => ['op' => 'goto', 'target' => null], 9 => ['op' => 'goto', 'target' => null]];
LabelTable::patch($code, [5, 9], 20);
eq('patch puts the address into the saved instructions', [20, 20], [$code[5]['target'], $code[9]['target']]);
eq('a backward goto gets the address at once', 20, $lt->reference('end', 30));
eq('attribute addr of the label', 20, $lt->read('end', 'addr'));
eq('attribute line of the label', 7, $lt->read('end', 'line'));
throws('a label defined twice is refused', fn () => $lt->define('end', 40));
eq('a label that is not in the table is not found', null, $lt->find('other'));
$lt->reference('lost', 3);
$lt->define('alpha', 1);
$lt->reorganize();
eq('reorganize sorts the labels by name', ['alpha', 'end', 'lost'], array_column($lt->all(), 'name'));
$lt->openFunction();
eq('a closure has its own labels: the outer label is not visible', null, $lt->find('end'));
$lt->reference('inner', 1);
eq('closing the closure reports its undefined label', ['inner'], $lt->closeFunction());
eq('the outer labels are back after the closure', true, $lt->find('end') !== null);
eq('closing the function reports the undefined label of the function', ['lost'], $lt->closeFunction());
eq('after the close the table is empty', 0, $lt->count());
$lt->define('L1', 0, 0, 'generated');
$lt->write('L1', 'addr', 4);
eq('write of an attribute: the address of a generated label', 4, $lt->read('L1', 'addr'));
eq('kind of a generated label', 'generated', $lt->read('L1', 'kind'));

// -------------------------------------------------------------------------------------------- ProcedureTable
group('Procedure table');
$tb = new Tables();
$p = $tb->procedures;
$ty = $tb->types;
$T = fn (string ...$names): array => array_map(fn (string $n) => $ty->find($n), $names);
eq('8 built-in functions', 8, $p->count());
eq('find is not case-sensitive: STRLEN', $p->find('strlen'), $p->find('STRLEN'));
eq('find of an unknown function is null', null, $p->find('nothing'));
$strlen = $p->find('strlen');
eq('strlen has 1 parameter', 1, count($p->read($strlen, 'params')));
eq('strlen returns int', $T('int'), $p->read($strlen, 'returns'));
eq('origin of strlen is builtin', 'builtin', $p->read($strlen, 'origin'));
eq('count takes 1 to 2 arguments', [1, 2], [$p->read($p->find('count'), 'min'), $p->read($p->find('count'), 'max')]);
eq('sprintf has no upper limit (variadic)', null, $p->read($p->find('sprintf'), 'max'));
eq('array_map needs at least 2 arguments', 2, $p->read($p->find('array_map'), 'min'));
eq('json_encode returns string|false (two types)', $T('string', 'false'), $p->read($p->find('json_encode'), 'returns'));
eq('call strlen(string) is correct', [], $p->checkCall($strlen, $T('string')));
eq('call strlen(int): the type is wrong', ['argument 1 of strlen(): int is not accepted'], $p->checkCall($strlen, $T('int')));
eq('call strlen(): too few arguments', ['strlen() needs at least 1 arguments, 0 given'], $p->checkCall($strlen, []));
eq('call strlen(string, string): too many arguments', ['strlen() takes at most 1 arguments, 2 given'], $p->checkCall($strlen, $T('string', 'string')));
eq('call sprintf(string, int, float, bool) is correct (variadic mixed)', [], $p->checkCall($p->find('sprintf'), $T('string', 'int', 'float', 'bool')));
eq('call intdiv(int, int) is correct', [], $p->checkCall($p->find('intdiv'), $T('int', 'int')));
eq('call intdiv(float, int): the first type is wrong', ['argument 1 of intdiv(): float is not accepted'], $p->checkCall($p->find('intdiv'), $T('float', 'int')));
eq('call array_map(null, array) is correct (callable|null)', [], $p->checkCall($p->find('array_map'), $T('null', 'array')));
eq('an argument of an unknown type (mixed) is not checked', [], $p->checkCall($strlen, $T('mixed')));
$total = $p->addUser('total', [['name' => 'items', 'type' => $T('array'), 'optional' => false, 'variadic' => false, 'byRef' => false]], $T('int'), 10, 7);
eq('a user function is found and has the address', 10, $p->read($p->find('Total'), 'addr'));
eq('a function with a return type is a function', 'function', $p->read($total, 'kind'));
eq('the link to the identifier table is kept', 7, $p->read($total, 'ident'));
$log = $p->addUser('log', [], $T('void'));
eq('a function that returns void is a procedure', 'procedure', $p->read($log, 'kind'));
throws('a function declared twice is refused', fn () => $p->addUser('TOTAL', [], $T('int')));
throws('a built-in function is read-only', fn () => $p->write($strlen, 'addr', 1));
$p->write($log, 'addr', 55);
eq('write of the address of a user function', 55, $p->read($log, 'addr'));
$p->delete($log);
eq('a deleted function is not found', null, $p->find('log'));
throws('a built-in function cannot be deleted', fn () => $p->delete($strlen));
$p->reorganize(7);
eq('reorganize keeps the search working', $total, $p->find('total'));

// --------------------------------------------------------------------------------------------------- Scanner
group('Scanner (all tables together)');
$tb = new Tables();
$sc = new Scanner($tb);
$sc->scan((string) file_get_contents(__DIR__ . '/sample.php'));
eq('the sample has no errors', [], $sc->errors);
eq('3 user types are added', 21, $tb->types->count());
eq('Circle extends Base', $tb->types->find('Base'), $tb->types->read($tb->types->find('Circle'), 'parent'));
eq('Circle has 4 members (property, constructor and 2 methods)', 4, $tb->types->read($tb->types->find('Circle'), 'members'));
eq('LIMIT is a constant of type int with the value 10', [10, $tb->types->find('int')], [
    $tb->constants->read($tb->constants->find('LIMIT'), 'value'), $tb->constants->read($tb->constants->find('LIMIT'), 'type')]);
eq('the literal 0 is stored once although it is written 3 times', 1, count(array_filter($tb->constants->all(), fn (array $r) => $r['name'] === 'int:0')));
eq('function total: 1 to 2 arguments, returns int', [1, 2, [$tb->types->find('int')]], (function () use ($tb) {
    $r = $tb->procedures->read($tb->procedures->find('total'));
    return [$r['min'], $r['max'], $r['returns']];
})());
eq('the closure is deleted from the procedure table at the end of its body', null, $tb->procedures->find('{closure}#1'));
eq('the method Circle::area is in the procedure table', true, $tb->procedures->find('Circle::area') !== null);
eq('the identifier of total points to its procedure and back', $tb->procedures->find('total'),
    $tb->identifiers->read($tb->identifiers->find('total'), 'ref'));
eq('the local variables of total are deleted', null, $tb->identifiers->find('$sum'));
eq('a global variable of the top-level code is kept', true, $tb->identifiers->find('$count') !== null);
eq('forward goto: the jump at address 13 is fixed up to the label at 17', 17, $sc->code[13]['target']);
eq('the label table is empty at the end', 0, $tb->labels->count());
eq('the first exit is the interface Shape with its method', ['class Shape', ['area']], [$sc->exits[0]['scope'], $sc->exits[0]['removed']]);
eq('the operator ** (right, level 23) was met once', 1, $sc->operators['** (infix)'] ?? 0);
eq('the language constant M_PI was used once', 1, $sc->constantUses['M_PI'] ?? 0);

$bad = function (string $code) use ($tb): array {
    $s = new Scanner(new Tables());
    $s->scan($code);
    return $s->errors;
};
eq('error: goto to a label that does not exist', ['label missing is used but never defined (in f)'], $bad('<?php function f(): void { goto missing; }'));
eq('error: a variable that has no value yet', ['variable $x is used before it gets a value (line 1)'], $bad('<?php function f(): void { echo $x; }'));
eq('error: a function declared twice', ['Function F is already declared (line 1)'], $bad('<?php function f(): void {} function F(): void {}'));
eq('error: a call with a wrong argument type', ['argument 1 of strlen(): int is not accepted (line 1)'], $bad('<?php $n = strlen(5);'));
eq('error: a call with too many arguments', ['intdiv() takes at most 2 arguments, 3 given (line 1)'], $bad('<?php $n = intdiv(1, 2, 3);'));
eq('error: an unknown type', ['unknown type Decimal (line 1)'], $bad('<?php function f(Decimal $d): void {}'));
eq('error: a constant defined twice', ['Constant A is already defined (line 1)'], $bad('<?php const A = 1; const A = 2;'));
eq('a label that is defined before the goto (backward jump) has no error', [], $bad('<?php function f(): void { top: $i = 1; goto top; }'));

// ---------------------------------------------------------------------------------------------------- result
$failed = array_filter($checks, fn (array $c) => !$c['ok']);
if (($argv[1] ?? '') === 'json') {
    echo json_encode(['total' => count($checks), 'failed' => count($failed), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit($failed ? 1 : 0);
}
foreach ($checks as $n => $c) {
    printf("%s %3d  %-26s %s\n", $c['ok'] ? 'ok  ' : 'FAIL', $n + 1, $c['table'], $c['case']);
    $c['ok'] || printf("          expected %s\n          actual   %s\n", $c['expected'], $c['actual']);
}
$byGroup = [];
foreach ($checks as $c) {
    $byGroup[$c['table']][] = $c['ok'];
}
echo "\n";
foreach ($byGroup as $name => $oks) {
    printf("%-30s %2d checks, %2d passed\n", $name, count($oks), count(array_filter($oks)));
}
printf("\nTotal: %d checks, %d passed, %d failed\n", count($checks), count($checks) - count($failed), count($failed));
exit($failed ? 1 : 0);
