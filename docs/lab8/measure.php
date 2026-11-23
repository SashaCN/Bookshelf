<?php

/**
 * Measurement for control question 5: the average number of probes (compared records) of a successful search
 * in the identifier table with 3 hash functions, against the linear search.
 *   php docs/lab8/measure.php          text   |   php docs/lab8/measure.php json
 *
 * The corpus is real PHP code: (A) the server part of Bookshelf (app, database, routes, config, tests) and
 * (B) the Illuminate Auth, Bus, Cache, Collections and Database packages of Laravel from the vendor folder.
 * Every variable and every name (T_STRING) is one search; the table is filled in the order of first appearance.
 */

declare(strict_types=1);

use Lab8\ChainTable;
use Lab8\Hash;

require __DIR__ . '/src/bootstrap.php';

$backend = dirname(__DIR__, 2) . '/backend';
$corpora = [
    'A: Bookshelf backend' => array_map(fn (string $d) => "$backend/$d", ['app', 'database', 'routes', 'config', 'tests']),
    'B: Laravel (5 packages)' => array_map(fn (string $d) => "$backend/vendor/laravel/framework/src/Illuminate/$d", ['Auth', 'Bus', 'Cache', 'Collections', 'Database']),
];

/** @return string[] all identifier occurrences of the .php files below the folders, in a fixed order */
function occurrences(array $folders): array
{
    $files = [];
    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
            $file->getExtension() === 'php' && $files[] = $file->getPathname();
        }
    }
    sort($files);
    $names = [];
    foreach ($files as $path) {
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_VARIABLE, T_STRING], true)) {
                $names[] = $token[1];
            }
        }
    }
    return $names;
}

$result = [];
foreach ($corpora as $title => $folders) {
    $all = occurrences($folders);
    if ($all === []) {
        continue;
    }
    $unique = array_values(array_unique($all));
    $position = array_flip($unique);
    // linear search in the order of first appearance: the probes of a name = its position + 1
    $linear = array_sum(array_map(fn (string $n) => $position[$n] + 1, $all)) / count($all);
    $row = ['occurrences' => count($all), 'unique' => count($unique), 'linear' => round($linear, 1), 'tables' => []];
    foreach ([61, 509, 4093, 4096] as $buckets) {
        foreach (Hash::NAMES as $fn) {
            $t = new ChainTable($fn, $buckets);
            foreach ($unique as $name) {
                $t->add($name, []);
            }
            foreach ($all as $name) {
                $t->find($name);
            }
            $s = $t->stats();
            $row['tables'][] = ['hash' => $fn, 'buckets' => $buckets, 'load' => round(count($unique) / $buckets, 2),
                'avg_probes' => round($t->probes / $t->searches, 3), 'avg_unique' => $s['avg_probes'],
                'longest' => $s['longest'], 'empty_pct' => round(100 * $s['empty'] / $buckets, 1)];
        }
    }
    $result[$title] = $row;
}

if (($argv[1] ?? '') === 'json') {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
foreach ($result as $title => $row) {
    printf("%s: %d occurrences, %d unique names\n", $title, $row['occurrences'], $row['unique']);
    printf("  linear search: %.1f probes on average\n", $row['linear']);
    printf("  %-8s %8s %6s %12s %12s %8s %8s\n", 'hash', 'buckets', 'load', 'probes/search', 'probes/name', 'longest', 'empty %');
    foreach ($row['tables'] as $t) {
        printf("  %-8s %8d %6.2f %12.3f %12.3f %8d %8.1f\n", $t['hash'], $t['buckets'], $t['load'], $t['avg_probes'], $t['avg_unique'], $t['longest'], $t['empty_pct']);
    }
    echo "\n";
}
