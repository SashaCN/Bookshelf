"""The report on laboratory work 8: tables of a translator for PHP and the operations on them."""

from __future__ import annotations

import json
from pathlib import Path

from listing import line_count, lines_of

HERE = Path(__file__).resolve().parent
RESULTS = HERE / 'results'

# The files of the appendix: (path, first line, last line, note). Scanner.php, the tests and the demo are in the repository.
LISTING = [
    ('src/Hash.php', 1, None, 'The three hash functions.'),
    ('src/ChainTable.php', 1, None, 'The hash table with overflow chains: the core of three tables.'),
    ('src/IdentifierTable.php', 1, None, 'The identifier table with the stack of scopes and the 25 keywords.'),
    ('src/TypeTable.php', 1, None, 'The type table: records, a sorted index and the binary search.'),
    ('src/OperationTable.php', 1, 34, 'The operation table, the start: the description of the records and the groups of operand types.'),
    ('src/OperationTable.php', 98, None, 'The operation table, the end: the open addressing, the search and the checks. The 70 rows of the data (lines 27 to 98) are in the file.'),
    ('src/LabelTable.php', 1, None, 'The label table with the fix-ups of forward references.'),
]


def data() -> dict:
    def load(name: str) -> dict:
        return json.loads((RESULTS / name).read_text(encoding='utf-8'))
    return {'tests': load('tests.json'), 'demo': load('demo.json'), 'hash': load('hash_comparison.json')}


def flat(value) -> str:
    if isinstance(value, bool):
        return 'yes' if value else 'no'
    if isinstance(value, list):
        return ', '.join(flat(v) for v in value) or '-'
    return '-' if value is None else str(value)


# Checks shown in the table of expected and actual results: the beginning of the case name.
SELECTED = [
    "sum('ab')", "pjw('ab')", 'the newest record with the same key', 'reorganize does not change the ids',
    'find is not case-sensitive: INT', 'binary search: at most 5', 'a class is accepted where its parent',
    'TRUE is found', 'the same literal is stored once', '** is right-associative', 'the precedence follows the PHP manual',
    '+ binds tighter than . (PHP 8)', '+ does not accept (string, int)', 'the average search takes less than 2 probes',
    'a variable of the outer scope is not visible', 'exitScope returns the names', 'the function declared inside survives',
    'a forward goto: the label is unknown', 'define returns the instructions to fix up', 'a label defined twice',
    'call strlen(int)', 'call strlen(): too few', 'a function that returns void is a procedure',
    'forward goto: the jump at address 13', 'error: goto to a label that does not exist',
]


def write(d, ctx, r: dict) -> None:
    tests, demo, hashes = r['tests'], r['demo'], r['hash']

    d.h1('Objective', page_break=True)
    d.p('To get practical skills in the design and the implementation of the tables of a translator and of the operations '
        'on them.')

    d.h1('Task')
    d.p('The language is PHP (the server part of Bookshelf is written in PHP 8.4, so the tables are made for the language '
        'of the project). The tool language is PHP too. The task is to implement the six tables of the variant: the '
        'identifier table with the initialization by the keywords of the language, the type table with the built-in '
        'types, the constant table, the operation table with all the operators, the label table and the procedure table '
        'with the initialization by built-in functions. For every table the following operations are made as '
        'separate methods: search, adding, reading and writing of an attribute, deleting and reorganization. The report '
        'must give the constructs of the language that define and use the objects of the tables, the record structure '
        'and the links between the tables, the method of the organization with a justification, the program, and the '
        'test data with the expected results.')

    d.h1('Theoretical background')
    d.p('A table is a set of records; every record has named fields, and one of them is the key by which the record is '
        'searched and added. The tables are the most used data structure of a translator. The declarations fill them, '
        'and the executable part of the program is translated with the help of the information from them: the semantic '
        'checks (the types, the number of arguments, the visibility) and the generation of the code need the attributes '
        'of the names. A big part of the translation time is spent on the search in the tables, so the search must be '
        'fast, and a record must be small, so that big programs fit in memory.')
    d.p('The static tables have a constant number of records that do not change (the table of the operations, the built-in '
        'functions). Only the search and the reading of attributes are needed for them. The dynamic tables change during '
        'the translation (the identifiers, the user types, the constants, the labels, the user procedures), and all the '
        'five operations are used for them. A dynamic table can have a static part, which is filled before the translation '
        'starts: the keywords in the identifier table, the built-in types in the type table, the built-in functions in the '
        'procedure table, the constants of the language in the constant table.')

    # ------------------------------------------------------------------ constructs
    d.h1('Constructs of PHP that define and use the objects of the tables')
    d.p(ctx.ref('tab:constructs') + ' lists the constructs. The left column is where a record is created or changed, the '
        'right column is where it is read.')
    ctx.table(d, 'tab:constructs', 'Constructs of PHP for every table')
    d.table(['Table', 'Constructs that define', 'Constructs that use'], [
        ['Identifiers', '`$x = 1;` (a variable is born at the first assignment), parameters `function f(int $a)`, `use ($v)` '
         'of a closure, `global $x;`, `static $n;`, `foreach ($a as $k => $v)`, `catch (E $e)`, `const X`, `function`, '
         '`class`, `interface`, `trait`, `enum`, `namespace`, properties, methods, class constants',
         'every name in an expression, `$this->name`, a call `f()`, `new Name`, `Name::X`, `instanceof Name`'],
        ['Types', 'the built-in names (`int`, `string`, `array`, `mixed`, `void` ...), `class`, `interface`, `trait`, `enum`, '
         '`abstract`, `final`',
         'the type of a parameter, of a property and of a result, `?T`, `A|B`, casts `(int)`, `new`, `instanceof`, `catch`'],
        ['Constants', 'the constants of the language (`PHP_INT_MAX`, `M_PI`, `E_ALL`, `true`, `false`, `null`), `const X = 1;`, '
         '`define()`, the literals `42`, `1.5`, `\'text\'`',
         'the name of a constant in an expression or in a default value, `Class::X`'],
        ['Operations', 'the operators of the language: arithmetic, comparison, logical, bitwise, assignment, `new`, `clone`, '
         '`instanceof`, casts, `print`, `yield`, `throw`, `->`, `::`',
         'the parsing of every expression (the precedence), the check of the operands and the generation of the code'],
        ['Labels', '`name:` before a statement', '`goto name;` (before or after the label)'],
        ['Procedures', '`function f() {}`, a method of a class, a closure `function () use ($v) {}`, the built-in functions',
         'a call `f()`, `$o->m()`, `C::m()`, `new C()` (the constructor)'],
    ], [2.0, 8.4, 6.0], align=['left'] * 3, size=19)

    # ------------------------------------------------------------------ structures
    d.h1('Record structures and links between the tables')
    d.p(ctx.ref('fig:links') + ' shows the six tables and the links between them. A link is not a copy of data: a field '
        'keeps the number (id) of a record of another table. The number of a record never changes while the record lives, '
        'also after the reorganization of the table, so the links stay correct. The fields are given in ' + ctx.ref('tab:records') + '.')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:links', 'tables_and_links.png', 'The tables of the translator and the links between them', landscape=True)
    ctx.table(d, 'tab:records', 'Records of the tables')
    d.table(['Table', 'Field', 'Meaning'], [
        ['Identifiers', 'name', 'the key; variables keep the sign `$`, so a variable never collides with a keyword'],
        ['', 'kind', 'keyword, variable, parameter, constant, function, class, property, method, class_constant, namespace'],
        ['', 'scope, addr', 'the number of the scope; the slot of a variable in the frame of its scope'],
        ['', 'type[]', 'ids in the type table (a union has several)'],
        ['', 'ref', 'an id in the procedure, constant or type table, or in the identifier table (for `global $x`); '
                    'for a keyword its group'],
        ['', 'flags, initialized', 'static, readonly, global, captured, public ...; does the variable have a value'],
        ['Types', 'name, key', 'the name and the lower-case key (the type names are not case-sensitive)'],
        ['', 'kind, size', 'scalar, compound, special, pseudo, resource or class; the size in bytes (empty if it depends on the value)'],
        ['', 'declarable, builtin', 'may it be written in a declaration; is it a built-in type'],
        ['', 'parent, members', 'the id of the parent class; the number of members of a class'],
        ['Constants', 'name', 'the key; for a literal it is `type:value`, for example `int:42`'],
        ['', 'kind, type, value', 'predefined, user or literal; an id in the type table; the value'],
        ['Operations', 'lexeme, fix', 'the key: the text of the operator and its form (prefix, postfix, infix, ternary)'],
        ['', 'name, arity', 'the name; the number of operands'],
        ['', 'prec, assoc', 'the precedence (a bigger number binds tighter) and the associativity (left, right, none)'],
        ['', 'operands[], result', 'ids of the allowed operand types; the id of the result type (empty if it depends on the operands)'],
        ['', 'gen', 'the name of the routine that generates the code of the operation'],
        ['Labels', 'name', 'the key'],
        ['', 'defined, kind', 'was the label defined; goto (written by the programmer) or generated (by the translator)'],
        ['', 'addr, line, refs[]', 'the address of the instruction; the line; the addresses of the jumps that wait for the label'],
        ['Procedures', 'name', 'the key in lower case (the function names are not case-sensitive)'],
        ['', 'kind, origin', 'function or procedure (it returns void); builtin or user'],
        ['', 'params[]', 'for every parameter: name, type ids, optional, variadic, byRef'],
        ['', 'min, max, returns[]', 'the number of arguments (max is empty for a variadic function); the type ids of the result'],
        ['', 'addr, ident', 'the address of the first instruction of the body; the id in the identifier table'],
    ], [2.2, 3.4, 10.8], align=['left'] * 3, size=19)

    # ------------------------------------------------------------------ methods
    d.h1('The methods of the organization and their justification')
    d.p('The organization of a table is a compromise between the memory for a record and the speed of the operations. The '
        'choice for every table is given in ' + ctx.ref('tab:methods') + ' and explained below.')
    ctx.table(d, 'tab:methods', 'The method of the organization of every table')
    d.table(['Table', 'Kind', 'Records at the start', 'Organization', 'Search'], [
        ['Identifiers', 'dynamic', '25 keywords', 'hash table with overflow chains, a prime number of buckets, a stack of scopes', 'O(1) on average'],
        ['Types', 'dynamic, a big static part', '18 built-in types', 'records in the order of adding and a sorted index of ids', 'binary search'],
        ['Constants', 'dynamic', '17 predefined', 'hash table with overflow chains; the key of a literal is its type and value', 'O(1) on average'],
        ['Operations', 'static', '70 operations', 'open addressing with linear probing, built once, 27% full', 'about 1 probe'],
        ['Labels', 'dynamic, small', 'none', 'unordered array, one set for every function', 'linear'],
        ['Procedures', 'dynamic, a static part', '8 built-in functions', 'hash table with overflow chains by the lower-case name', 'O(1) on average'],
    ], [2.0, 2.6, 2.6, 6.6, 2.6], align=['left'] * 5, size=19)
    d.p('The identifier table is the main and the biggest table, and it is searched for every name of the program. A hash table '
        'gives a search in a constant time and does not depend on the order of the names. Overflow chains are chosen and not '
        'open addressing for three reasons. First, the table loses records often: all the records of a function are deleted '
        'when its body ends, and an open table needs marks of deleted places for this. Second, the number of names is not '
        'known in advance, and a chain only gets longer when the table is full, while an open table stops to work. Third, a '
        'new record is put at the head of its chain, so the search finds the newest record with the name first. This gives '
        'the shadowing for free: the same name in an inner scope hides the name of the outer scope, and after the exit '
        'the outer record is found again (' + ctx.ref('fig:chains') + '). The number of buckets is a prime number, because '
        'the remainder of the division by a prime spreads the keys better (see the measurement below).')
    ctx.figure(d, 'fig:chains', 'chains.png', 'Buckets and overflow chains of the identifier table')
    d.p('The type table has few records (18 built-in types and the classes of the program, usually tens), the search is '
        'frequent and the adding is rare. The records are kept in the order of adding, so their numbers do not change, and '
        'a separate array of the numbers is kept sorted by the name. The search in it is the binary search: for 18 types it '
        'takes at most 5 comparisons. The sorted index gives the names in alphabetical order for messages. The adding to the '
        'middle of the index is slow, but it happens once for a class. The constant table and the procedure table '
        'use the same hash table with chains as the identifier table, because the user constants, the literals (a lot of '
        'them) and the functions are added and deleted during the translation. The key of a literal is its type and '
        'value, so a literal that is written many times is stored once.')
    d.p('The operation table is static and is searched for every token of an expression, so it needs the fastest search. It '
        'is a hash table with open addressing and linear probing. Nothing is deleted from it, so the main weakness of open '
        'addressing is not important. The table has 257 places for 70 records (27% full), so the search takes a little '
        'more than one probe, and the table is never scanned. The key is the text of the operator and its form, because '
        'the operators `-`, `+`, `++` and `--` have two forms with different priorities and arities. The precedence and the '
        'associativity are taken from the PHP manual (the table "Operator Precedence"); the unary `+` and `-` have the level of '
        '`++` and `~`, as in the grammar of PHP, and `.` is below `+` and `<<`, as in PHP 8.')
    d.p('The label table is small: a function has a few labels, usually none. The simplest structure, an unordered array with '
        'the linear search, is the fastest at this size. A label is visible only inside its function, so the whole set is thrown '
        'away at the end of the function, and a closure has its own set. A `goto` can be written before its label, so the label '
        'is created as undefined and the addresses of the jumps are saved in `refs`. When the label is defined, '
        'every saved jump is fixed up with its address.')

    # ------------------------------------------------------------------ scopes
    d.h1('Scopes and the deletion of records')
    d.p('PHP has no block scope: the blocks of `if`, `for` and `foreach` do not open a scope, and a variable lives to the '
        'end of its function. So the scopes of the translator are the global scope, a function (a method, a closure) and a '
        'class. A variable is visible only in its own scope; a closure sees only the variables of its `use` list; the names of '
        'functions, classes and constants are global. The identifier table keeps a stack of scopes; every scope remembers the '
        'records that were added in it. At the end of the scope (`exitScope`) these records are deleted from the chains. A '
        'function declared inside another function belongs to the global scope and stays. `global $x` creates a local '
        'record whose field `ref` points to the global variable, and `static $n` marks a local record with the flag static.')

    # ------------------------------------------------------------------ operations
    d.h1('Operations on the tables')
    d.p(ctx.ref('tab:ops') + ' gives the methods that make the operations. The static table of the operations has only the '
        'search and the reading of attributes. The methods of the other tables have the same names, so they are used in the '
        'same way.')
    ctx.table(d, 'tab:ops', 'Methods of the operations')
    d.table(['Table', 'Search', 'Adding', 'Read / write', 'Deleting', 'Reorganization'], [
        ['Identifiers', '`find`, `isKeyword`', '`add`', '`read`, `write`', '`exitScope`', '`reorganize` (new buckets)'],
        ['Types', '`find`', '`addClass`', '`read`, `write`', '`delete`', '`reorganize` (sort the index)'],
        ['Constants', '`find`', '`addUser`, `addLiteral`', '`read`, `write`', '`delete`', '`reorganize` (new buckets)'],
        ['Operations', '`find`', '-', '`attr`', '-', '-'],
        ['Labels', '`find`', '`reference`, `define`', '`read`, `write`, `patch`', '`closeFunction`', '`reorganize` (sort by name)'],
        ['Procedures', '`find`', '`addUser`', '`read`, `write`, `checkCall`', '`delete`', '`reorganize` (new buckets)'],
    ], [2.0, 2.4, 2.8, 3.2, 2.4, 3.6], align=['left'] * 6, size=19)
    d.p('The reorganization of the hash tables makes a new array of buckets (the default is a prime number near the half of '
        'the number of records) and builds all the chains again from the oldest record to the newest, so the order in the '
        'chains and the numbers of the records do not change. The measurement shows the effect: after the reorganization '
        'of 100 records from 4 to 61 buckets the average chain is shorter (a test in the group of the chain table).')

    # ------------------------------------------------------------------ program
    d.h1('The program')
    d.p('The program is in the folder `docs/lab8` of the repository. There is one file for every table in `src`: '
        '`Hash.php` (the hash functions), `ChainTable.php` (the hash table with chains), `IdentifierTable.php`, '
        '`TypeTable.php`, `ConstantTable.php`, `OperationTable.php`, `LabelTable.php`, `ProcedureTable.php` and `Tables.php` '
        '(the six tables together). The file `Scanner.php` is a small front end: it reads a fragment of PHP with the function '
        '`token_get_all` and fills the tables from its declarations. It is the part of the translator that uses the tables, '
        'and it is not a full parser. There are three scripts: `tests.php` (the tests), `demo.php` (the demonstration on the '
        'fragment `sample.php`) and `measure.php` (the measurement of the hash functions). They are run with `php`, '
        'and their outputs are saved in the folder `results`. The text of the main table files is in Appendix A.')

    # ------------------------------------------------------------------ tests
    d.h1('Test data and results')
    groups: dict = {}
    for c in tests['checks']:
        g = groups.setdefault(c['table'], [0, 0])
        g[0] += 1
        g[1] += 1 if c['ok'] else 0
    d.p(f'The script `tests.php` has {tests["total"]} checks. Every check gives the test data, the expected result and the '
        f'actual result and compares them. {tests["total"] - tests["failed"]} checks passed and {tests["failed"]} failed. '
        + ctx.ref('tab:groups') + ' shows the groups, and ' + ctx.ref('tab:checks') + ' gives some checks (the full list is '
        'in `results/tests.txt` and `results/tests.json`). The expected results were written from the PHP manual and from the '
        'rules of the design before the program was run.')
    ctx.table(d, 'tab:groups', 'The groups of the tests')
    d.table(['Group', 'Checks', 'Passed'], [[g, str(a), str(b)] for g, (a, b) in groups.items()] +
            [['Total', str(tests['total']), str(tests['total'] - tests['failed'])]], [8.0, 2.4, 2.4], align=['left', 'right', 'right'])
    ctx.table(d, 'tab:checks', 'Test data, expected and actual results (a selection)')
    rows = []
    for key in SELECTED:
        c = next((c for c in tests['checks'] if c['case'].startswith(key)), None)
        if c is not None:
            rows.append([c['case'], c['expected'], c['actual'], 'passed' if c['ok'] else 'FAILED'])
    d.table(['Test data (the case)', 'Expected', 'Actual', 'Result'], rows, [8.4, 2.6, 2.6, 1.6], align=['left'] * 4, size=17)

    d.h2('Demonstration on a fragment of PHP')
    d.p('The fragment `sample.php` (Appendix B) has a namespace, a constant, an interface, an abstract and a final class, '
        'a property, a constant of a class, methods, a function with a static variable, `global`, a `foreach`, a closure with '
        '`use`, a forward `goto` and calls of built-in functions. The program found no errors. ' + ctx.ref('tab:demo_ident') +
        ' shows the identifier table after the translation: only the global names are left, because the local records were '
        'deleted at the ends of the scopes (' + ctx.ref('tab:demo_exits') + ').')
    ctx.table(d, 'tab:demo_ident', 'The identifier table after the translation (the keywords are not shown)')
    d.table(['Id', 'Name', 'Kind', 'Type', 'Ref', 'Slot', 'Flags'],
            [[str(r[0]), r[1], r[2], flat(r[4]), flat(r[5]), flat(r[6]), flat(r[7])] for r in demo['identifiers']],
            [1.0, 3.6, 2.6, 1.0, 1.4, 1.4, 4.0], align=['right', 'left', 'left', 'left', 'right', 'right', 'left'], size=19)
    ctx.table(d, 'tab:demo_exits', 'The records deleted at the end of every scope')
    d.table(['Scope', 'Deleted records'], [[e['scope'], ', '.join(e['removed']) or '-'] for e in demo['scope_exits']],
            [5.4, 11.0], align=['left', 'left'], size=19)
    jump = demo['jumps']['13']
    d.p(f'The label table: the statement `goto {jump["label"]};` is at the address 13, before its label. The label was created '
        f'as undefined with the saved address 13; when `{jump["label"]}:` was met at the address {jump["target"]}, the jump was '
        f'fixed up, and its target became {jump["target"]}. At the end of the function the table was cleared. The function '
        '`total` is in the procedure table with 1 to 2 arguments and the result `int`, the closure `{closure}#1` was added and '
        'deleted at the end of its body, and the call `strlen($item)` was checked against the record of `strlen`.')

    # ------------------------------------------------------------------ hash
    d.h1('Comparison of the hash functions')
    d.p('Three hash functions were implemented. The sum of the character codes is the simplest one, but all the anagrams '
        'give the same value. The polynomial function `h = h * 31 + c` takes into account the place of a character. The '
        'PJW (ELF) function shifts the value by 4 bits and mixes the high bits back; it is used in the compilers of C. '
        'The measurement answers the question: how many records are compared in an average successful search. A '
        'record is compared when its key is checked. The data are real: the identifiers (the variables and the names) of '
        'the server part of Bookshelf (36,500 occurrences, 2,028 different names) and of five packages of Laravel '
        '(69,496 occurrences, 5,273 different names). The table was filled in the order of the first appearance, and every '
        'occurrence was searched (`measure.php`).')
    a, b = hashes['A: Bookshelf backend'], hashes['B: Laravel (5 packages)']
    ctx.table(d, 'tab:hash', 'The average number of probes of a successful search')
    rows = []
    for title, corpus in (('A', a), ('B', b)):
        for buckets in (61, 509, 4093, 4096):
            cells = {t['hash']: t for t in corpus['tables'] if t['buckets'] == buckets}
            rows.append([title, str(buckets), f'{cells["sum"]["load"]:.2f}', f'{cells["sum"]["avg_probes"]:.2f}',
                         f'{cells["poly31"]["avg_probes"]:.2f}', f'{cells["pjw"]["avg_probes"]:.2f}', f'{corpus["linear"]:.0f}'])
    d.table(['Corpus', 'Buckets', 'Load (names per bucket)', 'Sum', 'Poly 31', 'PJW', 'Linear search'], rows,
            [1.6, 1.8, 3.2, 1.8, 2.0, 1.8, 2.6], align=['center'] + ['right'] * 6, size=19)
    d.p(f'The linear search takes {a["linear"]:.0f} probes on average in the corpus A and {b["linear"]:.0f} in the corpus B, '
        f'and the hash table with 4093 buckets takes about 1.4 and 1.9 probes, which is 500 to 700 times faster. The '
        'conclusions are these. The number of buckets matters more than the function: when the load is 4 names per bucket or '
        'more, the three functions are almost equal and the table is slow, so the table must grow (the reorganization). '
        'When the load is below 1, the sum is the worst (2.03 and 2.50 against 1.36 and 1.91 of PJW), because many short names '
        'have close sums. The polynomial function and PJW are about equal on a prime number of buckets. The number of buckets '
        'must be a prime: with 4096 buckets (a power of 2) PJW falls from 1.36 to 1.98 in the corpus A and from 1.91 to 4.17 in '
        'the corpus B, because the remainder of a division by a power of 2 uses only the last bits, and PJW keeps the '
        'information of the last characters in them badly. The polynomial function is stable (1.25 and 1.84 with 4096). '
        'So the program uses PJW with a prime number of buckets, and the polynomial function is a good alternative.')

    d.h1('Conclusion')
    d.p('In this laboratory work the six tables of a translator for PHP were designed and implemented: the identifier table '
        'with 25 keywords, the type table with the 18 built-in types, the constant table with 17 predefined constants, the '
        'operation table with 70 operators, the label table and the procedure table with 8 built-in functions. The tables '
        'are linked by the numbers of the records. Each table has the methods of search, adding, reading and writing of an '
        'attribute, deleting and reorganization (the static operation table has the search and the reading only). The '
        'organization was chosen for every table: the chains for the big dynamic tables, the sorted index with the binary '
        'search for the types, the open addressing for the static operations and the plain array for the labels. The '
        f'{tests["total"]} tests passed, and the demonstration on a fragment of PHP showed the scopes, the fix-up of a forward '
        'goto and the check of a call. The measurement on the real code showed that a hash table is 500 to 700 times faster '
        'than the linear search, that the sum of codes is the weakest function, and that the number of buckets must be a prime.')


QUESTIONS = [
    ('What requirements must the organization of the tables of a translator and the operations on them meet?',
     ['The search must be fast, because a big part of the time of the translation is spent on it, and the other operations '
      'must be simple and cheap too. A record must take as little memory as possible, so that big programs fit in memory '
      'and the translation is fast; a field that cannot be coded in a fixed size (a long name) is replaced by a pointer. '
      'The organization must support the block structure: the records of a block are deleted when it ends. The operations '
      'must be made as separate procedures or macros (search, adding, reading and writing of an attribute, deleting, '
      'reorganization), so that the rest of the translator does not depend on the structure. The links between the tables '
      'must stay correct, so a record keeps its number. The choice is always a compromise between the memory and the speed.']),
    ('Give examples of static and dynamic tables of a translator.',
     ['The static tables have a constant content that is defined by the language: the table of the operations, the table of '
      'the keywords and of the built-in functions, the table of the built-in types. The dynamic tables change during the '
      'translation: the table of the identifiers, of the user types, of the constants and literals, of the labels and of the '
      'user procedures. A dynamic table can have a static part: in our program the identifier table starts with 25 keywords, '
      'the type table with 18 types, the procedure table with 8 functions and the constant table with 17 constants.']),
    ('What methods can speed up the search in the static tables of a translator?',
     ['The direct access: the place of the record is computed from the key at once (a perfect hash function, an array '
      'indexed by the code of a symbol or of a keyword). A hash table with open addressing that is built once and has a small '
      'load, as the operation table here (about one probe). The binary search in a table sorted by the key. For a very small '
      'table the linear search is enough. A table of keywords can also be a switch or a tree of letters (a trie).']),
    ('By what methods can the search in the dynamic tables of a translator be speeded up?',
     ['The hash addressing with overflow chains or with open addressing (the identifier table here); a growth of the number of '
      'buckets when the table is full (the reorganization). A sorted array with the binary search, if the records are added '
      'rarely (the type table here). A tree: a binary search tree or a balanced tree (AVL, red-black), where both the search '
      'and the adding take a logarithmic time. A separate small table for every scope, so the search starts in the '
      'inner scope. The static part can be moved to a separate table with a faster search. A cache of the last found record.']),
    ('Offer several hash functions for a table of names.',
     ['Three functions were implemented and measured. The sum of the character codes: `h = c1 + c2 + ... + cn`. The '
      'polynomial function: `h = h * 31 + c` for every character. The PJW (ELF) function: `h = (h << 4) + c`, and the '
      'high 4 bits are mixed back into the low bits. The bucket is `h mod M`, where M is a prime number. Other simple '
      'functions: the first and the last letter and the length of the name; the folding (the name is cut into groups of '
      'characters that are summed or combined with XOR); the function FNV-1a (`h = (h xor c) * 16777619`). The measurement '
      'in the report shows that the sum is the worst and that PJW and the polynomial function are close.']),
    ('Formulate the advantages and disadvantages of the different structures of the tables of a translator.',
     ['The unordered table (an array): the simplest, the adding is at the end and does not move anything, but the search is '
      'linear; it is good only for very small tables (the labels). The ordered table: the binary search is fast and the '
      'records are in the alphabetical order, but the adding in the middle moves records; it is good for tables that are '
      'filled once (the types). The tree: the search and the adding are logarithmic and the order is kept, but a record has '
      'two pointers, and a balanced tree is more complex. The hash table with open addressing: very fast and compact, but the '
      'deleting is hard (the marks of deleted places), and it becomes slow when it is full. The hash table with chains: '
      'the deleting is easy and a full table degrades slowly, but every record has a pointer, and a bad function makes a '
      'long chain (the identifier table). The direct access is the fastest, but only for a static set of keys (the operations).']),
]


def write_answers(d, ctx) -> None:
    d.h1('Answers to the control questions')
    for number, (question, paragraphs) in enumerate(QUESTIONS, 1):
        d.p(f'{number}. {question}', indent=0, keep_next=True)
        for paragraph in paragraphs:
            d.p(paragraph)


def write_appendix(d, ctx, r: dict) -> None:
    d.h1('Appendix A. Program listing', page_break=True)
    d.p('The text of the files of the tables, as they are in the repository, with the numbers of the lines (the blank lines are left out). A line that is '
        'too long for the page is continued on the next line. The files `ConstantTable.php` and `ProcedureTable.php` (the same hash table with chains as in the identifier table, '
        'with the initialization of the static part), `Tables.php`, `Scanner.php`, `tests.php`, `demo.php` and `measure.php` '
        'are in the folder `docs/lab8` and are not repeated here.')
    for number, (path, start, end, note) in enumerate(LISTING, 1):
        total = line_count(path)
        shown = f'lines {start}-{end} of {total}' if end or start > 1 else f'{total} lines'
        d.h2(f'A.{number} {path} ({shown})')
        d.p(note, keep_next=True)
        d.code(lines_of(path, start, end), size=14)
    d.h1('Appendix B. The fragment of PHP of the demonstration', page_break=True)
    d.p('The file `sample.php`. The program translates its declarations into the tables (`php docs/lab8/demo.php`).')
    d.code(lines_of('sample.php'), size=14)
