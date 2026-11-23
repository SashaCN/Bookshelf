# Відповіді на контрольні питання, лабораторна робота 8 (Bookshelf)

# Answers to the control questions, laboratory work 8 (Bookshelf)

Це 6 контрольних питань із методички. Кожна відповідь коротка: її можна сказати вголос за хвилину. Під питанням є українська версія, англійська версія (рівень B1) і підказка, що показати у звіті `Zvit_LR8_Bookshelf.docx` (номери сторінок орієнтовні) чи в програмі.

These are the 6 control questions from the methodology. Every answer is short: you can say it aloud in about one minute. Under each question there is a Ukrainian version, an English version (B1 level) and a hint about what to show in the report `Zvit_LR8_Bookshelf.docx` (the page numbers are approximate) or in the program.

---

## 1. Яким вимогам повинні задовольняти організація таблиць транслятора й реалізація операцій роботи з ними?

## 1. What requirements must the organization of the tables of a translator and the operations on them meet?

**Українською.** Пошук має бути швидким, бо на нього йде значна частина часу трансляції. Запис має займати мінімум пам'яті, щоб вистачило пам'яті для великих програм: поле, яке не закодуєш фіксованим розміром (довге ім'я), замінюють покажчиком. Організація має підтримувати блокову структуру: записи блока видаляються, коли блок закінчився. Операції (пошук, додавання, читання й запис атрибута, видалення, реорганізація) роблять окремими процедурами чи макросами, щоб решта транслятора не залежала від структури. Зв'язки між таблицями не повинні ламатися, тому запис тримає свій незмінний номер. Вибір завжди компроміс між пам'яттю й швидкістю.

**In English.** The search must be fast, because a big part of the translation time is spent on it. A record must take as little memory as possible, so that big programs fit in memory: a field that cannot have a fixed size (a long name) is replaced by a pointer. The organization must support the block structure: the records of a block are deleted when the block ends. The operations (search, adding, reading and writing an attribute, deleting, reorganization) are made as separate procedures or macros, so that the rest of the translator does not depend on the structure. The links between the tables must not break, so a record keeps its number. The choice is always a compromise between memory and speed.

**Що показати / What to show:** теорія, с. 2; методи організації, с. 5-6; номери записів як зв'язки, рис. 1 (с. 4) / theory, p. 2; the methods, pp. 5-6; the record numbers as links, Figure 1 (p. 4).

---

## 2. Наведіть приклади статичних і динамічних таблиць транслятора.

## 2. Give examples of static and dynamic tables of a translator.

**Українською.** Статичні мають постійний вміст, який задає мова: таблиця операцій, ключових слів, вбудованих функцій, вбудованих типів. Динамічні змінюються під час трансляції: таблиця ідентифікаторів, користувацьких типів, констант і літералів, міток, користувацьких процедур. Динамічна таблиця може мати статичну частину. У нашій програмі таблиця ідентифікаторів починається з 25 ключових слів, таблиця типів із 18 типів, таблиця процедур із 8 функцій, таблиця констант із 17 констант. Таблиця операцій повністю статична (70 операторів).

**In English.** Static tables have a constant content that the language defines: the table of operations, of keywords, of built-in functions, of built-in types. Dynamic tables change during the translation: the table of identifiers, of user types, of constants and literals, of labels, of user procedures. A dynamic table can have a static part. In our program the identifier table starts with 25 keywords, the type table with 18 types, the procedure table with 8 functions and the constant table with 17 constants. The operation table is fully static (70 operators).

**Що показати / What to show:** теорія, с. 2; табл. 3 (с. 5-6) / theory, p. 2; Table 3 (pp. 5-6).

---

## 3. Які методи прискорення пошуку можна ефективно використовувати для статичних таблиць транслятора?

## 3. What methods can speed up the search in the static tables of a translator?

**Українською.** Прямий доступ: місце запису обчислюється за ключем одразу (досконала хеш-функція, масив, індексований кодом символу чи ключового слова). Хеш-таблиця з відкритою адресацією, яку будують один раз і заповнюють мало: так зроблена наша таблиця операцій (27 відсотків, приблизно одна проба). Двійковий пошук у таблиці, відсортованій за ключем. Для зовсім малої таблиці досить лінійного пошуку. Ключові слова можна шукати й через switch чи префіксне дерево.

**In English.** Direct access: the place of a record is computed from the key at once (a perfect hash function, an array indexed by the code of a symbol or of a keyword). A hash table with open addressing that is built once and is not very full: our operation table is made in this way (27 percent full, about one probe). The binary search in a table sorted by the key. For a very small table the linear search is enough. Keywords can also be found with a switch or a prefix tree.

**Що показати / What to show:** обґрунтування таблиці операцій, с. 6; тест «the average search takes less than 2 probes» у табл. 6 (с. 10) / the justification of the operation table, p. 6; the test "the average search takes less than 2 probes" in Table 6 (p. 10).

---

## 4. Якими методами можна прискорити пошук у динамічних таблицях транслятора?

## 4. By what methods can the search in the dynamic tables of a translator be speeded up?

**Українською.** Хеш-адресація з ланцюжками переповнення чи з відкритою адресацією (таблиця ідентифікаторів у нас); збільшення кількості кошиків, коли таблиця наповнилась (реорганізація). Відсортований масив із двійковим пошуком, якщо записи додають рідко (таблиця типів у нас). Дерево: двійкове дерево пошуку чи збалансоване (AVL, червоно-чорне), де і пошук, і додавання логарифмічні. Окрема мала таблиця для кожної області видимості, тоді пошук починається з внутрішньої. Статичну частину можна винести в окрему таблицю з швидшим пошуком. Ще кеш останнього знайденого запису.

**In English.** Hash addressing with overflow chains or with open addressing (the identifier table in our work); a bigger number of buckets when the table is full (the reorganization). A sorted array with the binary search, if records are added rarely (the type table in our work). A tree: a binary search tree or a balanced tree (AVL, red-black), where both the search and the adding take a logarithmic time. A separate small table for every scope, so the search starts in the inner scope. The static part can be moved to a separate table with a faster search. Also a cache of the last found record.

**Що показати / What to show:** табл. 9 (с. 11-12): хеш у 500-700 разів швидший за лінійний пошук; рис. 2 (с. 6) / Table 9 (pp. 11-12): the hash is 500 to 700 times faster than the linear search; Figure 2 (p. 6).

---

## 5. Запропонуйте декілька хеш-функцій для таблиці імен.

## 5. Offer several hash functions for a table of names.

**Українською.** Ми реалізували й виміряли три. Сума кодів символів: `h = c1 + c2 + ... + cn`. Поліноміальна: `h = h * 31 + c` для кожного символу. PJW (ELF): `h = (h << 4) + c`, а старші 4 біти підмішуються назад у молодші. Кошик це `h mod M`, де M просте число. Інші прості варіанти: перша й остання літери та довжина імені; згортання (ім'я ріжуть на групи символів, які додають чи об'єднують XOR); FNV-1a (`h = (h xor c) * 16777619`). Вимірювання на реальному коді (Bookshelf і Laravel) показало: сума найгірша при малому навантаженні (2,03 проби проти 1,36 у PJW на бекенді Bookshelf, 4093 кошики); PJW і поліноміальна близькі; при кошиках, кількість яких є степенем двійки, PJW погіршується (з 1,91 до 4,17 на Laravel).

**In English.** We implemented and measured three. The sum of the character codes: `h = c1 + c2 + ... + cn`. The polynomial function: `h = h * 31 + c` for every character. PJW (ELF): `h = (h << 4) + c`, and the high 4 bits are mixed back into the low bits. The bucket is `h mod M`, where M is a prime number. Other simple variants: the first and the last letter and the length of the name; folding (the name is cut into groups of characters that are added or combined with XOR); FNV-1a (`h = (h xor c) * 16777619`). The measurement on real code (Bookshelf and Laravel) showed: the sum is the worst at a small load (2.03 probes against 1.36 of PJW on the Bookshelf backend, 4093 buckets); PJW and the polynomial function are close; when the number of buckets is a power of 2, PJW gets worse (from 1.91 to 4.17 on Laravel).

**Що показати / What to show:** розділ «Comparison of the hash functions», табл. 9 (с. 11-12); у програмі `php docs/lab8/measure.php` і файл `src/Hash.php` / the section "Comparison of the hash functions", Table 9 (pp. 11-12); in the program `php docs/lab8/measure.php` and the file `src/Hash.php`.

---

## 6. Сформулюйте переваги й недоліки різних структур таблиць транслятора.

## 6. Formulate the advantages and disadvantages of the different structures of the tables of a translator.

**Українською.** Невпорядкований масив: найпростіший, додавання в кінець, але пошук лінійний; годиться для зовсім малих таблиць (наші мітки). Впорядкована таблиця: швидкий двійковий пошук і алфавітний порядок, але додавання в середину зсуває записи; годиться для таблиць, що заповнюються один раз (наші типи). Дерево: пошук і додавання логарифмічні, порядок зберігається, але в записі два покажчики, а збалансоване дерево складніше. Хеш із відкритою адресацією: дуже швидкий і компактний, але видаляти важко (позначки видалених місць), а при заповненні сповільнюється. Хеш із ланцюжками: видалення просте, повна таблиця деградує повільно, але кожен запис має покажчик, а погана функція дає довгий ланцюжок (наша таблиця ідентифікаторів). Прямий доступ найшвидший, але лише для статичної множини ключів (наші операції).

**In English.** The unordered array: the simplest, adding is at the end, but the search is linear; good only for very small tables (our labels). The ordered table: fast binary search and the alphabetical order, but adding in the middle moves records; good for tables that are filled once (our types). The tree: search and adding are logarithmic and the order is kept, but a record has two pointers, and a balanced tree is more complex. The hash table with open addressing: very fast and compact, but deleting is hard (marks of deleted places), and it gets slow when it is full. The hash table with chains: deleting is easy and a full table degrades slowly, but every record has a pointer, and a bad function makes a long chain (our identifier table). Direct access is the fastest, but only for a static set of keys (our operations).

**Що показати / What to show:** табл. 3 з обґрунтуванням кожного методу (с. 5-6) / Table 3 with the justification of every method (pp. 5-6).
