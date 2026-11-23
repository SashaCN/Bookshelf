<?php
/**
 * Laboratory work 7: measures the Chidamber and Kemerer metrics (WMC, DIT, NOC, CBO, RFC, LCOM) of the classes of the
 * Bookshelf backend (backend/app). Run from the repository root:
 *
 *     php docs/lab7/measure.php            # writes docs/lab7/results.json
 *
 * It only reads the source files of backend/app and the classes of backend/vendor (through reflection, to know the
 * parents of the framework classes). It makes no network calls and does not read .env files.
 *
 * Counting rules (the same rules are described in the report):
 *  - Unit: a class, an abstract class, a final class or an enum declared in backend/app. Traits are measured and listed
 *    but are not counted in the statistics (they are not instantiated; their methods are mixed into other classes).
 *  - WMC: the number of the methods declared in the class body (weight 1 for each method, constructors, static, private and
 *    abstract methods included). Inherited methods and the methods of the used traits are NOT counted.
 *    WMC_cc is the variant with the cyclomatic complexity as the weight of a method (1 + number of decisions).
 *  - DIT: the length of the path of "extends" from the class to the root, through the framework classes too (found by
 *    reflection). DIT_app counts only the ancestors declared in backend/app. Interfaces and traits are not parents.
 *  - NOC: the number of classes in backend/app that extend the class directly.
 *  - CBO: the number of distinct other classes that the class refers to (extends, implements, used traits, types of
 *    properties, parameters and results, new, static calls and constants, instanceof, catch, attributes, X::class).
 *    The class itself, self/static/parent and the built-in scalar types are not counted. CBO_app counts only the classes
 *    declared in backend/app.
 *  - RFC: own methods + distinct methods of other classes that the class calls. A call is identified by (class of the
 *    receiver)::method. The class of the receiver is known for $this, typed properties and parameters, new and static
 *    calls; otherwise it is "?" (the call is identified by the name of the method only).
 *  - LCOM: Chidamber and Kemerer 1994: P - Q (not below 0), P = pairs of methods without a common property, Q = pairs with
 *    a common property. Property = $this->name or self::$name. The constructors and the abstract methods are not
 *    taken as methods for LCOM. LCOM4 (Hitz and Montazeri) = the number of the connected groups of methods, where a link is
 *    a common property or a call $this->method().
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$app = $root . '/backend/app';
require $root . '/backend/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$finder = new NodeFinder();

// ------------------------------------------------------------------------------------------------ parse every file
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $files[] = $f->getPathname();
    }
}
sort($files);

/** @var array<string,array> $units */
$units = [];
foreach ($files as $path) {
    $code = file_get_contents($path);
    $stmts = $parser->parse($code);
    $traverser = new NodeTraverser(new NameResolver());
    $stmts = $traverser->traverse($stmts);
    $decls = $finder->find($stmts, fn(Node $n) => $n instanceof Node\Stmt\ClassLike && $n->name !== null);
    foreach ($decls as $decl) {
        $kind = match (true) {
            $decl instanceof Node\Stmt\Trait_ => 'trait',
            $decl instanceof Node\Stmt\Enum_ => 'enum',
            $decl instanceof Node\Stmt\Interface_ => 'interface',
            default => 'class',
        };
        $fqcn = $decl->namespacedName->toString();
        $rel = substr($path, strlen($app) + 1);
        $units[$fqcn] = [
            'node' => $decl, 'fqcn' => $fqcn, 'short' => $decl->name->toString(), 'file' => 'app/' . $rel,
            'kind' => $kind, 'abstract' => $decl instanceof Node\Stmt\Class_ && $decl->isAbstract(),
            'final' => $decl instanceof Node\Stmt\Class_ && $decl->isFinal(),
        ];
    }
}

function category(string $file): string
{
    $parts = explode('/', $file); // app/<dir>/...
    $dir = $parts[1];
    if ($dir === 'Http') {
        return $parts[2]; // Controllers, Requests, Resources, Middleware, Responses
    }
    return $dir;
}

// ------------------------------------------------------------------------------------------------ helpers
const SPECIAL = ['self', 'static', 'parent'];

function typeNames(?Node $type): array
{
    if ($type === null) {
        return [];
    }
    $out = [];
    $stack = [$type];
    while ($stack) {
        $t = array_pop($stack);
        if ($t instanceof Node\NullableType) {
            $stack[] = $t->type;
        } elseif ($t instanceof Node\UnionType || $t instanceof Node\IntersectionType) {
            foreach ($t->types as $x) {
                $stack[] = $x;
            }
        } elseif ($t instanceof Node\Name) {
            $out[] = $t->toString();
        }
    }
    return $out;
}

function singleType(?Node $type): ?string
{
    $n = typeNames($type);
    return count($n) === 1 && !in_array(strtolower($n[0]), SPECIAL, true) ? $n[0] : null;
}

/** Cyclomatic complexity of a statement list: 1 + decisions. */
function cyclomatic(array $stmts): int
{
    $cc = 1;
    $f = new NodeFinder();
    foreach ($f->find($stmts, fn() => true) as $n) {
        if ($n instanceof Node\Stmt\If_ || $n instanceof Node\Stmt\ElseIf_ || $n instanceof Node\Stmt\For_
            || $n instanceof Node\Stmt\Foreach_ || $n instanceof Node\Stmt\While_ || $n instanceof Node\Stmt\Do_
            || $n instanceof Node\Stmt\Catch_ || $n instanceof Node\Expr\Ternary
            || $n instanceof Node\Expr\BinaryOp\BooleanAnd || $n instanceof Node\Expr\BinaryOp\BooleanOr
            || $n instanceof Node\Expr\BinaryOp\LogicalAnd || $n instanceof Node\Expr\BinaryOp\LogicalOr
            || $n instanceof Node\Expr\BinaryOp\Coalesce) {
            $cc++;
        } elseif ($n instanceof Node\Stmt\Case_ && $n->cond !== null) {
            $cc++;
        } elseif ($n instanceof Node\MatchArm && $n->conds !== null) {
            $cc++;
        }
    }
    return $cc;
}

// ------------------------------------------------------------------------------------------------ inheritance
function ancestors(string $fqcn, array $units): array
{
    $chain = [];
    $current = $fqcn;
    $guard = 0;
    while ($guard++ < 30) {
        if (isset($units[$current])) {
            $decl = $units[$current]['node'];
            $parent = ($decl instanceof Node\Stmt\Class_ && $decl->extends) ? $decl->extends->toString() : null;
        } elseif (class_exists($current)) {
            $p = (new ReflectionClass($current))->getParentClass();
            $parent = $p ? $p->getName() : null;
        } else {
            $parent = null;
        }
        if ($parent === null) {
            break;
        }
        $chain[] = $parent;
        $current = $parent;
    }
    return $chain;
}

$children = [];
foreach ($units as $u) {
    $decl = $u['node'];
    if ($decl instanceof Node\Stmt\Class_ && $decl->extends) {
        $children[$decl->extends->toString()][] = $u['fqcn'];
    }
}

// ------------------------------------------------------------------------------------------------ per class metrics
$builtin = ['int', 'float', 'string', 'bool', 'array', 'mixed', 'void', 'null', 'never', 'object', 'callable', 'iterable', 'false', 'true'];

$rows = [];
foreach ($units as $fqcn => $u) {
    /** @var Node\Stmt\ClassLike $decl */
    $decl = $u['node'];
    $methods = $decl->getMethods();
    $methodNames = [];
    foreach ($methods as $m) {
        $methodNames[strtolower($m->name->toString())] = true;
    }

    // --- WMC
    $wmc = count($methods);
    $wmcCc = 0;
    $maxCc = 0;
    foreach ($methods as $m) {
        $cc = $m->stmts === null ? 1 : cyclomatic($m->stmts);
        $wmcCc += $cc;
        $maxCc = max($maxCc, $cc);
    }

    // --- DIT, NOC
    $chain = ancestors($fqcn, $units);
    $dit = count($chain);
    $ditApp = 0;
    foreach ($chain as $a) {
        if (isset($units[$a])) {
            $ditApp++;
        }
    }
    $noc = count($children[$fqcn] ?? []);

    // --- CBO: all class names referenced in the body (but not function and constant names)
    $skip = [];
    $names = [];
    $visitor = new class($skip, $names) extends NodeVisitorAbstract {
        public function __construct(public array &$skip, public array &$names) {}

        public function enterNode(Node $n)
        {
            if ($n instanceof Node\Expr\FuncCall && $n->name instanceof Node\Name) {
                $this->skip[spl_object_id($n->name)] = true;
            } elseif ($n instanceof Node\Expr\ConstFetch) {
                $this->skip[spl_object_id($n->name)] = true;
            } elseif ($n instanceof Node\Stmt\Class_ || $n instanceof Node\Stmt\Enum_) {
                // names of the "extends" and "implements" are references; the class name itself is an Identifier
            }
            if ($n instanceof Node\Name && !isset($this->skip[spl_object_id($n)])) {
                $this->names[] = $n->toString();
            }
            return null;
        }
    };
    (new NodeTraverser($visitor))->traverse([$decl]);
    $coupled = [];
    foreach ($names as $name) {
        if (in_array(strtolower($name), SPECIAL, true) || in_array(strtolower($name), $builtin, true) || $name === $fqcn) {
            continue;
        }
        $coupled[$name] = true;
    }
    $coupled = array_keys($coupled);
    sort($coupled);
    $cboApp = count(array_filter($coupled, fn($c) => isset($units[$c])));

    // --- typed properties (and promoted constructor parameters) of the class: used to find the class of $this->prop
    $propType = [];
    $declaredProps = [];
    foreach ($decl->stmts as $s) {
        if ($s instanceof Node\Stmt\Property) {
            foreach ($s->props as $p) {
                $declaredProps[$p->name->toString()] = true;
                if (($t = singleType($s->type)) !== null && !in_array(strtolower($t), $builtin, true)) {
                    $propType[$p->name->toString()] = $t;
                }
            }
        }
    }
    foreach ($methods as $m) {
        if (strtolower($m->name->toString()) === '__construct') {
            foreach ($m->params as $p) {
                if ($p->flags !== 0 && $p->var instanceof Node\Expr\Variable && is_string($p->var->name)) {
                    $declaredProps[$p->var->name] = true;
                    if (($t = singleType($p->type)) !== null && !in_array(strtolower($t), $builtin, true)) {
                        $propType[$p->var->name] = $t;
                    }
                }
            }
        }
    }

    // --- RFC and LCOM: walk through every method
    $external = [];
    $propsOf = [];   // method => set of properties
    $callsOf = [];   // method => set of own methods called by $this->m()
    $lcomMethods = [];
    foreach ($methods as $m) {
        $mname = $m->name->toString();
        $vars = [];
        foreach ($m->params as $p) {
            if ($p->var instanceof Node\Expr\Variable && is_string($p->var->name) && ($t = singleType($p->type)) !== null
                && !in_array(strtolower($t), $builtin, true)) {
                $vars[$p->var->name] = $t;
            }
        }
        $props = [];
        $calls = [];
        if ($m->stmts !== null) {
            foreach ($finder->find($m->stmts, fn() => true) as $n) {
                // closure parameters with a type
                if ($n instanceof Node\Expr\Closure || $n instanceof Node\Expr\ArrowFunction) {
                    foreach ($n->params as $p) {
                        if ($p->var instanceof Node\Expr\Variable && is_string($p->var->name) && ($t = singleType($p->type)) !== null
                            && !in_array(strtolower($t), $builtin, true)) {
                            $vars[$p->var->name] = $t;
                        }
                    }
                }
                if ($n instanceof Node\Expr\Assign && $n->var instanceof Node\Expr\Variable && is_string($n->var->name)
                    && $n->expr instanceof Node\Expr\New_ && $n->expr->class instanceof Node\Name) {
                    $vars[$n->var->name] = $n->expr->class->toString();
                }
            }
            foreach ($finder->find($m->stmts, fn() => true) as $n) {
                if (($n instanceof Node\Expr\PropertyFetch || $n instanceof Node\Expr\NullsafePropertyFetch)
                    && $n->var instanceof Node\Expr\Variable && $n->var->name === 'this' && $n->name instanceof Node\Identifier) {
                    $props[$n->name->toString()] = true;
                } elseif ($n instanceof Node\Expr\StaticPropertyFetch && $n->class instanceof Node\Name
                    && in_array(strtolower($n->class->toString()), ['self', 'static'], true) && $n->name instanceof Node\VarLikeIdentifier) {
                    $props['::' . $n->name->toString()] = true;
                }
                if (($n instanceof Node\Expr\MethodCall || $n instanceof Node\Expr\NullsafeMethodCall) && $n->name instanceof Node\Identifier) {
                    $called = $n->name->toString();
                    $recv = $n->var;
                    if ($recv instanceof Node\Expr\Variable && $recv->name === 'this') {
                        if (isset($methodNames[strtolower($called)])) {
                            $calls[strtolower($called)] = true;
                        } else {
                            $external['(inherited)::' . strtolower($called)] = true;
                        }
                        continue;
                    }
                    $cls = '?';
                    if ($recv instanceof Node\Expr\Variable && is_string($recv->name) && isset($vars[$recv->name])) {
                        $cls = $vars[$recv->name];
                    } elseif (($recv instanceof Node\Expr\PropertyFetch || $recv instanceof Node\Expr\NullsafePropertyFetch)
                        && $recv->var instanceof Node\Expr\Variable && $recv->var->name === 'this'
                        && $recv->name instanceof Node\Identifier && isset($propType[$recv->name->toString()])) {
                        $cls = $propType[$recv->name->toString()];
                    }
                    $external[$cls . '::' . strtolower($called)] = true;
                } elseif ($n instanceof Node\Expr\StaticCall && $n->name instanceof Node\Identifier) {
                    $called = strtolower($n->name->toString());
                    if ($n->class instanceof Node\Name) {
                        $c = strtolower($n->class->toString());
                        if ($c === 'self' || $c === 'static') {
                            if (isset($methodNames[$called])) {
                                $calls[$called] = true;
                            } else {
                                $external['(inherited)::' . $called] = true;
                            }
                        } elseif ($c === 'parent') {
                            $external['(parent)::' . $called] = true;
                        } else {
                            $external[$n->class->toString() . '::' . $called] = true;
                        }
                    } else {
                        $external['?::' . $called] = true;
                    }
                } elseif ($n instanceof Node\Expr\New_ && $n->class instanceof Node\Name) {
                    $c = strtolower($n->class->toString());
                    if (!in_array($c, SPECIAL, true)) {
                        $external[$n->class->toString() . '::__construct'] = true;
                    }
                }
            }
        }
        $propsOf[$mname] = $props;
        $callsOf[$mname] = $calls;
        if (strtolower($mname) !== '__construct' && $m->stmts !== null) {
            $lcomMethods[] = $mname;
        }
    }
    $rfc = count($methods) + count($external);

    // --- LCOM (Chidamber and Kemerer) and LCOM4
    $P = 0;
    $Q = 0;
    $k = count($lcomMethods);
    for ($i = 0; $i < $k; $i++) {
        for ($j = $i + 1; $j < $k; $j++) {
            $common = array_intersect_key($propsOf[$lcomMethods[$i]], $propsOf[$lcomMethods[$j]]);
            if ($common) {
                $Q++;
            } else {
                $P++;
            }
        }
    }
    $lcom = max($P - $Q, 0);
    // LCOM4: connected components
    $parent = [];
    foreach ($lcomMethods as $mname) {
        $parent[strtolower($mname)] = strtolower($mname);
    }
    $find = function (string $x) use (&$parent, &$find) {
        while ($parent[$x] !== $x) {
            $parent[$x] = $parent[$parent[$x]];
            $x = $parent[$x];
        }
        return $x;
    };
    $union = function (string $a, string $b) use (&$parent, $find) {
        $ra = $find($a);
        $rb = $find($b);
        if ($ra !== $rb) {
            $parent[$ra] = $rb;
        }
    };
    for ($i = 0; $i < $k; $i++) {
        $a = $lcomMethods[$i];
        for ($j = $i + 1; $j < $k; $j++) {
            if (array_intersect_key($propsOf[$a], $propsOf[$lcomMethods[$j]])) {
                $union(strtolower($a), strtolower($lcomMethods[$j]));
            }
        }
        foreach (array_keys($callsOf[$a]) as $called) {
            if (isset($parent[$called])) {
                $union(strtolower($a), $called);
            }
        }
    }
    $components = [];
    foreach (array_keys($parent) as $x) {
        $components[$find($x)] = true;
    }
    $lcom4 = $k === 0 ? 0 : count($components);

    $rows[] = [
        'fqcn' => $fqcn, 'name' => $u['short'], 'file' => $u['file'], 'category' => category($u['file']),
        'kind' => $u['kind'], 'abstract' => $u['abstract'], 'final' => $u['final'],
        'parent' => $chain[0] ?? null,
        'loc' => $decl->getEndLine() - $decl->getStartLine() + 1,
        'properties' => count($declaredProps),
        'wmc' => $wmc, 'wmc_cc' => $wmcCc, 'max_cc' => $maxCc,
        'dit' => $dit, 'dit_app' => $ditApp, 'noc' => $noc,
        'cbo' => count($coupled), 'cbo_app' => $cboApp,
        'rfc' => $rfc, 'rfc_external' => count($external),
        'lcom' => $lcom, 'lcom4' => $lcom4, 'lcom_pairs_p' => $P, 'lcom_pairs_q' => $Q,
        'chain' => $chain, 'coupled' => $coupled,
    ];
}

// ------------------------------------------------------------------------------------------------ aggregates
function stats(array $values): array
{
    sort($values);
    $n = count($values);
    if ($n === 0) {
        return ['n' => 0, 'mean' => 0, 'median' => 0, 'max' => 0, 'min' => 0, 'p90' => 0, 'sum' => 0];
    }
    $median = $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    return [
        'n' => $n, 'mean' => round(array_sum($values) / $n, 2), 'median' => $median, 'max' => max($values),
        'min' => min($values), 'p90' => $values[(int) ceil(0.9 * $n) - 1], 'sum' => array_sum($values),
    ];
}

$metrics = ['wmc', 'wmc_cc', 'dit', 'dit_app', 'noc', 'cbo', 'cbo_app', 'rfc', 'lcom', 'lcom4', 'loc'];
$measured = array_values(array_filter($rows, fn($r) => $r['kind'] !== 'trait' && $r['kind'] !== 'interface'));
$traits = array_values(array_filter($rows, fn($r) => $r['kind'] === 'trait'));

$system = [];
foreach ($metrics as $m) {
    $system[$m] = stats(array_column($measured, $m));
}

$cats = [];
foreach ($measured as $r) {
    $cats[$r['category']][] = $r;
}
ksort($cats);
$byCategory = [];
foreach ($cats as $name => $list) {
    $byCategory[$name] = ['n' => count($list)];
    foreach ($metrics as $m) {
        $byCategory[$name][$m] = stats(array_column($list, $m));
    }
}

$top = [];
foreach (['wmc', 'wmc_cc', 'dit', 'noc', 'cbo', 'cbo_app', 'rfc', 'lcom', 'lcom4'] as $m) {
    $sorted = $measured;
    usort($sorted, fn($a, $b) => [$b[$m], $a['name']] <=> [$a[$m], $b['name']]);
    $top[$m] = array_map(fn($r) => ['name' => $r['name'], 'category' => $r['category'], 'value' => $r[$m]], array_slice($sorted, 0, 10));
}

// distribution: value => number of classes
$distribution = [];
foreach (['wmc', 'dit', 'noc', 'cbo', 'rfc', 'lcom', 'lcom4'] as $m) {
    $d = [];
    foreach ($measured as $r) {
        $d[$r[$m]] = ($d[$r[$m]] ?? 0) + 1;
    }
    ksort($d);
    $distribution[$m] = (object) $d;
}

$thresholds = ['wmc' => 20, 'dit' => 5, 'noc' => 9, 'cbo' => 9, 'rfc' => 50, 'lcom4' => 1];
$exceed = [];
foreach ($thresholds as $m => $t) {
    $list = array_values(array_filter($measured, fn($r) => $r[$m] > $t));
    usort($list, fn($a, $b) => $b[$m] <=> $a[$m]);
    $exceed[$m] = ['threshold' => $t, 'count' => count($list), 'names' => array_map(fn($r) => $r['name'] . ' (' . $r[$m] . ')', $list)];
}

// roots of the inheritance trees inside the app
$roots = [];
foreach ($measured as $r) {
    if ($r['dit_app'] === 0 && ($children[$r['fqcn']] ?? []) !== []) {
        $roots[] = $r['name'] . ' (NOC ' . $r['noc'] . ')';
    }
}

$result = [
    'generated_by' => 'docs/lab7/measure.php',
    'php' => PHP_VERSION,
    'parser' => 'nikic/php-parser ' . (class_exists(PhpParser\Parser::class) ? '5.x' : ''),
    'files' => count($files),
    'declarations' => count($rows),
    'measured' => count($measured),
    'traits' => count($traits),
    'kinds' => array_count_values(array_column($measured, 'kind')),
    'abstract' => count(array_filter($measured, fn($r) => $r['abstract'])),
    'final' => count(array_filter($measured, fn($r) => $r['final'])),
    'system' => $system,
    'by_category' => $byCategory,
    'top' => $top,
    'distribution' => $distribution,
    'exceed' => $exceed,
    'roots' => $roots,
    'trait_names' => array_column($traits, 'name'),
    'classes' => $rows,
];
file_put_contents(__DIR__ . '/results.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
printf("files %d, declarations %d, measured %d, traits %d\n", count($files), count($rows), count($measured), count($traits));
foreach ($system as $m => $s) {
    printf("%-7s mean %-7s median %-5s max %-4s p90 %s\n", $m, $s['mean'], $s['median'], $s['max'], $s['p90']);
}
