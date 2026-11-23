# Presentation for Laboratory Work 8 (Bookshelf), English version

A spoken text for 5-6 minutes, in simple English (about B1 level). Topic: "Organization of tables in translators and operations on them". The language is PHP, because the backend of Bookshelf is written in PHP 8.4. All six tables of the variant for PHP are implemented. This work has a program, so there is a short live demo at the end.

The presentation follows the pages of the report `docs/lab8/Zvit_LR8_Bookshelf.docx`. The page numbers are approximate (the report was built without an export through Word; it has about 25 pages with the appendices). If the pages move, use the name of the section or the table.

## Route: page by page

| Page | What is on it | What I say |
|---|---|---|
| 1 | Title page | The topic |
| 2 | Objective, task, theory | What a table is, static and dynamic tables |
| 3 | Constructs of PHP, Table 1 | What defines and what uses the objects of the tables |
| 4 | Figure 1 (landscape page) | The six tables and the links |
| 5-6 | Table 2, "The methods of the organization", Table 3, Figure 2 | Records, the method and the reason |
| 7 | "Scopes and the deletion of records" | Scopes in PHP, deletion at the end of a block |
| 8 | "Operations on the tables", Table 4, "The program" | The operations and the files |
| 9-10 | "Test data and results", Tables 5-6 | 192 tests, expected and actual |
| 10-11 | "Demonstration on a fragment", Tables 7-8 | Walk through a PHP fragment |
| 11-12 | "Comparison of the hash functions", Table 9 | Hash functions on real code |
| 13 | Conclusion | The summary |
| 13-15 | Answers to the 6 control questions | Only scroll past |
| 16-26 | Appendix A | The text of the table files |
| 27 | Appendix B | The PHP fragment of the demo |

---

## Part 1. The presentation

**▶ p. 1: the title page.**

Good afternoon. This is laboratory work number eight: the organization of tables in translators. We chose PHP, because the backend of Bookshelf is written in PHP. We implemented all six tables of the variant: identifiers, types, constants, operations, labels and procedures.

**▶ p. 2-3: objective, theory, Table 1.**

A table is a set of records with a key. Static tables never change and depend only on the language: the table of operations and the built-in functions. Dynamic tables change during the translation: identifiers, user types, constants, labels. A dynamic table can have a static part that is filled before the start: the keywords, the built-in types, the built-in functions. Table 1 shows, for every table, which PHP constructs create records and which constructs read them. For example, a PHP variable is born at its first assignment, and a label is defined as `name:` and used in `goto`.

**▶ p. 4: Figure 1.**

Here are all six tables. A link is not a copy of data but the number of a record of another table. An identifier has a type field that points to the type table, and its ref field points to a procedure, a constant or a class. A procedure has parameters and a result type, which are numbers in the type table too. The number of a record does not change while the record lives, also after a reorganization, so the links stay correct.

**▶ p. 5-6: Tables 2, 3, Figure 2.**

The method is chosen for every table separately. The identifier table is a hash table with overflow chains and a prime number of buckets. We chose chains and not open addressing, because we delete all the records of a function when it ends, and because a new record goes to the head of its chain, so a search finds the newest record first. This gives the shadowing for free: an inner variable hides the outer one with the same name. The type table keeps the records in the order of adding and has a separate sorted index with the binary search; for 18 types it needs at most 5 comparisons. The constants and the procedures use the same hash with chains. The key of a literal is its type and value, so a literal written many times is stored once. The operation table is static: 70 operators, open addressing with linear probing, 27 percent full, so a search is about one probe. The precedence and the associativity come from the PHP manual. Labels are an unordered array with a linear search, because a function has only a few labels. If a `goto` comes before its label, the label is created as undefined and the address of the jump is saved; when the label is defined, the jump is fixed up.

**▶ p. 7: scopes.**

PHP has no block scope: `if` and `foreach` do not open one, and a variable lives to the end of its function. So the scopes are the global scope, a function (a method or a closure) and a class. The identifier table keeps a stack of scopes. At the end of a scope its records are deleted from the chains, and the outer record is visible again. A function declared inside a function belongs to the global scope and stays.

**▶ p. 8: Table 4, the program.**

All five operations are separate methods: search, adding, reading and writing an attribute, deleting, reorganization. The static operation table has only search and reading. Every table has its own file in `src`, and a small front end, `Scanner`, reads a PHP fragment with `token_get_all` and fills the tables.

**▶ p. 9-10: Tables 5, 6.**

There are 192 tests, and all of them pass. Every test has the input data, the expected result and the actual result. The expected results were written from the PHP manual and from the design rules before the program was run. For example: `**` is right-associative, `+` binds tighter than `.` (this is PHP 8), the call `strlen(5)` gives a type error, and a forward `goto` is fixed up to the address of its label.

**▶ p. 10-11: Tables 7, 8.**

The demonstration is on a PHP fragment. After the translation only the global names are left in the identifier table, because the local records were deleted at the end of the scopes; Table 8 shows what was deleted. The statement `goto done` at the address 13 was before its label; the label was defined at the address 17, and the jump was fixed up. The closure was added to the procedure table and deleted at the end of its body.

**▶ p. 11-12: Table 9.**

We compared the hash functions on real code: the identifiers of the Bookshelf backend and of five Laravel packages. The linear search compares 743 and 1313 records on average. The hash table with 4093 buckets compares about 1.4 and 1.9, which is 500 to 700 times faster. The sum of the character codes is the worst at a small load. The polynomial function and PJW are close. The main point is that the number of buckets must be a prime number: with 4096 buckets, a power of 2, PJW gets worse from 1.91 to 4.17 probes.

**▶ p. 13: conclusion.**

The six tables of a translator for PHP were designed and implemented, with links, five operations and a justified method for every table. The 192 tests pass, and the demonstration showed scopes, the fix-up of a forward jump and the check of a call.

---

## Part 2. Short live demo (1-2 minutes)

Open a terminal in the root of the repository `Bookshelf`.

1. The tests:

```
php docs/lab8/tests.php | tail -14
```

Show the line "Total: 192 checks, 192 passed, 0 failed".

2. The tables after the translation of the fragment:

```
php docs/lab8/demo.php | head -60
```

Show the log of the translation (the class, the methods, `label done defined at address 17, fix-up of goto at 13`), then "Scope exits".

3. The measurement (it takes about 5 seconds):

```
php docs/lab8/measure.php
```

Show the line `linear search` and the comparison of `sum`, `poly31` and `pjw` for 4093 and 4096 buckets.

The ready outputs are in `docs/lab8/results/` (`tests.txt`, `demo.txt`, `hash_comparison.txt`) if the terminal is not available.
