<?php

/**
 * Demo of laboratory work 8: translates the declarations of sample.php into the six tables and prints them.
 *   php docs/lab8/demo.php            all tables
 *   php docs/lab8/demo.php json       the same as JSON
 */

declare(strict_types=1);

use Lab8\Scanner;
use Lab8\Tables;

require __DIR__ . '/src/bootstrap.php';

$tables = new Tables();
$scanner = new Scanner($tables);
$scanner->scan(file_get_contents(__DIR__ . '/sample.php'));

$t = $tables->types;
$names = fn (array $ids): string => implode('|', array_map(fn (int $id) => $t->read($id, 'name'), $ids)) ?: '-';
$flat = fn (mixed $v): string => is_bool($v) ? ($v ? 'yes' : 'no') : (is_array($v) ? (implode(',', $v) ?: '-') : (string) ($v ?? '-'));
$row = fn (array $cells): string => implode(' | ', array_map($flat, $cells));

$identifiers = array_map(fn (array $r) => [$r['id'], $r['name'], $r['kind'], $r['scope'], $names($r['type']), $r['ref'],
    $r['addr'], $r['flags'], $r['initialized']], array_filter($tables->identifiers->all(), fn (array $r) => $r['kind'] !== 'keyword'));
$typesUser = array_filter($t->all(), fn (array $r) => !$r['builtin']);
$constants = array_filter($tables->constants->all(), fn (array $r) => $r['kind'] !== 'predefined');
$procedures = array_filter($tables->procedures->all(), fn (array $r) => $r['origin'] === 'user');

if (($argv[1] ?? '') === 'json') {
    echo json_encode([
        'identifiers' => array_values($identifiers), 'user_types' => array_values($typesUser),
        'constants' => array_values($constants), 'procedures' => array_values($procedures),
        'jumps' => $scanner->code, 'errors' => $scanner->errors, 'operators' => $scanner->operators,
        'constant_uses' => $scanner->constantUses, 'scope_exits' => $scanner->exits,
        'stats' => ['identifiers' => $tables->identifiers->stats(), 'constants' => $tables->constants->stats(),
            'procedures' => $tables->procedures->stats(), 'operations_load' => $tables->operations->load()],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

$section = function (string $title, array $header, array $lines): void {
    echo "\n== $title ==\n", implode(' | ', $header), "\n";
    foreach ($lines as $line) {
        echo $line, "\n";
    }
};

echo "Translator tables for sample.php\n";
echo "\nLog of the translation\n";
foreach ($scanner->log as $line) {
    echo "  $line\n";
}
$section('Identifier table (keywords not shown; ' . count($tables->identifiers->all()) . ' records in all)',
    ['id', 'name', 'kind', 'scope', 'type', 'ref', 'addr', 'flags', 'init'], array_map($row, $identifiers));
$section('Scope exits (records deleted)', ['scope', 'removed'], array_map(fn (array $e) => $e['scope'] . ' | ' . implode(', ', $e['removed']), $scanner->exits));
$section('Type table: user types (' . $t->count() . ' types in all)', ['id', 'name', 'kind', 'parent', 'members'],
    array_map(fn (array $r) => $row([$r['id'], $r['name'], $r['kind'], $r['parent'] === null ? null : $t->read($r['parent'], 'name'), $r['members']]), $typesUser));
$section('Constant table: user constants and literals (' . count($tables->constants->all()) . ' records in all)', ['id', 'name', 'kind', 'type', 'value'],
    array_map(fn (array $r) => $row([$r['id'], $r['name'], $r['kind'], $names([$r['type']]), var_export($r['value'], true)]), $constants));
$section('Procedure table: user functions (' . $tables->procedures->count() . ' records in all, built-in ones included)',
    ['id', 'name', 'kind', 'params', 'min', 'max', 'returns', 'addr'],
    array_map(fn (array $r) => $row([$r['id'], $r['name'], $r['kind'],
        implode(', ', array_map(fn (array $p) => $names($p['type']) . ' $' . $p['name'] . ($p['optional'] ? '?' : ''), $r['params'])) ?: '-',
        $r['min'], $r['max'] ?? 'many', $names($r['returns']), $r['addr']]), $procedures));
$section('Label table (jumps and fix-ups)', ['pc', 'op', 'label', 'target'],
    array_map(fn (int $pc) => $row([$pc, $scanner->code[$pc]['op'], $scanner->code[$pc]['label'], $scanner->code[$pc]['target']]), array_keys($scanner->code)));
$section('Operators met (from the operation table)', ['operator', 'count', 'precedence', 'associativity'],
    array_map(function (string $key) use ($scanner, $tables, $row): string {
        preg_match('/^(.*) \((\w+)\)$/', $key, $m);
        $r = $tables->operations->find($m[1], $m[2]);
        return $row([$key, $scanner->operators[$key], $r['prec'], $r['assoc']]);
    }, array_keys($scanner->operators)));
echo "\nConstants of the language used: ", json_encode($scanner->constantUses), "\n";
echo "Errors found: ", $scanner->errors ? "\n  " . implode("\n  ", $scanner->errors) : 'none', "\n";
echo "\nStatistics of the hash tables (chains)\n";
foreach (['identifiers', 'constants', 'procedures'] as $name) {
    echo "  $name: ", json_encode($tables->$name->stats()), "\n";
}
echo "  operations: ", $tables->operations->count(), ' records, load ', $tables->operations->load(), "\n";
