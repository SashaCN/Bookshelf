# Відповіді на контрольні питання, лабораторна робота 5 (Bookshelf)

# Answers to the control questions, laboratory work 5 (Bookshelf)

Це 6 контрольних питань із методички. Кожна відповідь коротка: її можна сказати вголос за хвилину. Під питанням є українська версія, англійська версія (рівень B1) і підказка, що показати у звіті `Zvit_LR5_Bookshelf.docx`.

These are the 6 control questions from the methodology. Every answer is short: you can say it aloud in about one minute. Under each question there is a Ukrainian version, an English version (B1 level) and a hint about what to show in the report.

---

## 1. Охарактеризувати рекомендоване правило розподілу витрат проекту.

## 1. Describe the recommended rule for the distribution of the cost of a project.

**Українською.** Рекомендоване правило 40-20-40. Сорок відсотків витрат іде на аналіз і проєктування (з них п'ять відсотків на планування й системний аналіз), двадцять відсотків на кодування й сорок відсотків на тестування й налагодження. Правило показує, що кодування, яке здається головною роботою, найменша частина витрат, і що нестача часу на початку й наприкінці потім коштує дорожче. У нашому плані на аналіз і проєктування лише 10 днів зі 103, менше десятої частини.

**In English.** The recommended rule is 40-20-40. Forty percent of the cost goes to the analysis and the design (five percent of them to the planning and the system analysis), twenty percent to the coding, and forty percent to the testing and the debugging. The rule shows that coding, which looks like the main work, is the smallest part of the cost, and that a lack of time at the beginning and at the end costs more later. In our plan only 10 days of 103 go to the analysis and the design, less than a tenth.

**Що показати / What to show:** табл. 3 (с. 5) / Table 3 (p. 5).

---

## 2. Назвати розмірно-орієнтовані метрики.

## 2. Name the size-oriented metrics.

**Українською.** Вони засновані на LOC, кількості рядків продукту. Це продуктивність (тисяч рядків на людино-місяць), вартість рядка (вартість на тисячу рядків), документація (сторінок на тисячу рядків) і якість (помилок на тисячу рядків). Іноді додають середню кількість людей. У Bookshelf: 4,5 тисячі рядків на людино-місяць, 22 центи за рядок, 4,8 сторінки й 0,32 помилки на тисячу рядків.

**In English.** They are based on LOC, the number of lines of the product. They are productivity (thousands of lines per person-month), the cost of a line (cost per thousand lines), the documentation (pages per thousand lines) and the quality (errors per thousand lines). Sometimes the average number of people is also used. In Bookshelf: 4.5 thousand lines per person-month, 22 cents per line, 4.8 pages and 0.32 errors per thousand lines.

**Що показати / What to show:** табл. 4-5 (с. 6) / Tables 4-5 (p. 6).

---

## 3. Для чого використовують розмірно-орієнтовані метрики?

## 3. What are the size-oriented metrics used for?

**Українською.** Вони прямо вимірюють продукт і процес розробки та дають прості числа для планування. Маючи продуктивність і вартість рядка з минулих проєктів, можна оцінити витрати й вартість нового, порівняти проєкти й команди та стежити за якістю.

**In English.** They measure the product and the process of development directly and give simple numbers for planning. With the productivity and the cost of a line from earlier projects, one can estimate the effort and the cost of a new one, compare projects and teams, and follow the quality.

**Що показати / What to show:** табл. 5 (с. 6) / Table 5 (p. 6).

---

## 4. Визначити переваги й недоліки розмірно-орієнтованих метрик.

## 4. Define the advantages and the disadvantages of the size-oriented metrics.

**Українською.** Переваги: рядки легко рахувати автоматично, числа прості, а багато компаній вже зберігають такі дані. Недоліки: кількість рядків залежить від мови й стилю програміста, тому проєкти на різних мовах не порівняти; добра коротка програма виглядає менш продуктивною за довгу; точний розмір відомий лише коли програма готова, тож на початку проєкту метрику важко застосувати; метрики не показують ні функціональності, ні якості продукту.

**In English.** The advantages: lines are easy to count automatically, the numbers are simple, and many companies already keep such data. The disadvantages: the number of lines depends on the language and the style of the programmer, so projects in different languages cannot be compared; a good short program looks less productive than a long one; the exact size is known only when the program is ready, so the metric is hard to use at the beginning of a project; and the metrics show neither the functionality nor the quality of the product.

**Що показати / What to show:** відповідь у звіті, с. 10; приклад: продуктивність у 10 разів вища за зразки методички (с. 6) / the answer is in the report, p. 10; an example: the productivity is ten times higher than in the samples of the methodology (p. 6).

---

## 5. Що таке функціональний покажчик?

## 5. What is a function point?

**Українською.** Функціональний покажчик це одиниця функціонального розміру програми. Він міряє, що програма робить для користувача, а не скільки в ній рядків. Кількість покажчиків обчислюють за інформаційними характеристиками й коригують системними параметрами: FP = UFP × (0,65 + 0,01 × ΣFi). У Bookshelf UFP 291, сума параметрів 45, FP = 320.

**In English.** A function point is a unit of the functional size of a program. It measures what the program does for the user, not how many lines it has. The number of points is calculated from the information characteristics and corrected with the system parameters: FP = UFP × (0.65 + 0.01 × ΣFi). In Bookshelf UFP is 291, the sum of the parameters is 45, and FP is 320.

**Що показати / What to show:** табл. 7-9 (с. 7-8) / Tables 7-9 (pp. 7-8).

---

## 6. Від яких інформаційних характеристик залежить функціональний покажчик?

## 6. Which information characteristics does the function point depend on?

**Українською.** Від п'яти: кількості зовнішніх введень, зовнішніх виведень, зовнішніх запитів, внутрішніх логічних файлів і зовнішніх інтерфейсних файлів. Для кожного елемента визначають ранг, низький, середній чи високий, за кількістю елементів даних і посилань на файли, а кожен ранг має вагу. Суму множать на поправку з 14 системних параметрів. У нас 32 введення, 8 виведень, 16 запитів, 10 внутрішніх файлів і 1 зовнішній.

**In English.** On five of them: the number of external inputs, external outputs, external queries, internal logical files and external interface files. For every item a rank, low, average or high, is found by the number of data elements and of referenced files, and every rank has a weight. The sum is multiplied by the correction from the 14 system parameters. We have 32 inputs, 8 outputs, 16 queries, 10 internal files and 1 external file.

**Що показати / What to show:** табл. 1 (с. 3), табл. 7 (с. 7), додаток A (с. 11-13) / Table 1 (p. 3), Table 7 (p. 7), Appendix A (pp. 11-13).
