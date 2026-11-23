# Відповіді на контрольні питання, лабораторна робота 4 (Bookshelf)

# Answers to the control questions, laboratory work 4 (Bookshelf)

Це 5 контрольних питань із методички. Кожна відповідь коротка: її можна сказати вголос за хвилину. Під питанням є українська версія, англійська версія (рівень B1) і підказка, що показати у звіті `Zvit_LR4_Bookshelf.docx`.

These are the 5 control questions from the methodology. Every answer is short: you can say it aloud in about one minute. Under each question there is a Ukrainian version, an English version (B1 level) and a hint about what to show in the report.

---

## 1. Описати етап реалізації та налагодження ПЗ.

## 1. Describe the stage of implementation and debugging of software.

**Українською.** На етапі реалізації програму пишуть мовою програмування за технічним проектом: кодують модулі, створюють структури даних і інтерфейси, будують інтерфейс користувача. Одночасно йде налагодження. Програміст знаходить помилки тестами, налагоджувачем і за журналами, з'ясовує причину, усуває її й перевіряє ще раз, що програма робить те, що сказано в специфікації. Етап закінчується працюючою програмою та її документацією. У Bookshelf модулі писали від контролерів донизу до моделей, зрізами за функціями, і кожен зріз налагоджували автоматичними тестами до початку наступного.

**In English.** At the stage of implementation the program is written in a programming language by the technical design: we code the modules, make the data structures and the interfaces, and build the user interface. Debugging goes at the same time. The programmer finds errors with tests, with a debugger and by reading logs, finds the cause, removes it, and checks again that the program does what the specification says. The stage ends with a working program and its documentation. In Bookshelf we wrote the modules from the controllers down to the models, slice by slice, and we debugged every slice with automatic tests before the next one.

**Що показати / What to show:** табл. 4 (с. 5), Додаток E, розд. E.6 (с. 35) / Table 4 (p. 5), Appendix E, Section E.6 (p. 35).

---

## 2. Перелічити інструментальні засоби для розробки ПП.

## 2. List the tools for the development of software.

**Українською.** Це текстовий редактор або інтегроване середовище розробки, компілятор чи інтерпретатор, налагоджувач, система контролю версій, засоби збірки й менеджери пакетів, бібліотеки та фреймворки, засоби тестування й статичного аналізу, засоби документування, а також засоби доставки: контейнери й безперервна інтеграція. У нашому проєкті: PHP і Composer, Laravel, Node.js і npm, Vite, TypeScript і Vue, Docker Compose, Git і GitHub Actions, Pest і Vitest для тестів, Larastan, Pint, ESLint і vue-tsc для статичних перевірок.

**In English.** The tools are: a text editor or an integrated development environment, a compiler or an interpreter, a debugger, a version control system, build tools and package managers, libraries and frameworks, tools for testing and static analysis, tools for documentation, and tools for delivery, which are containers and continuous integration. In our project we used PHP and Composer, Laravel, Node.js and npm, Vite, TypeScript and Vue, Docker Compose, Git and GitHub Actions, Pest and Vitest for the tests, and Larastan, Pint, ESLint and vue-tsc for the static checks.

**Що показати / What to show:** Додаток E, розд. E.5, табл. 18 (с. 34) / Appendix E, Section E.5, Table 18 (p. 34).

---

## 3. Дати визначення та характеристику етапу стихійного програмування.

## 3. Define the stage of spontaneous programming and characterize it.

**Українською.** Стихійне програмування це перший етап в історії програмування, до середини 1960-х років. Програму писала одна людина під конкретну машину, машинним кодом чи асемблером, без методу. Не було правил проектування й стандартів стилю, програма була індивідуальною роботою, а її якість залежала від майстерності автора. Такі програми були короткими, їх було важко читати, а іншій людині майже неможливо підтримувати. Документації як окремого продукту не було, а програму зазвичай писали «до кінця» й не змінювали.

**In English.** Spontaneous programming is the first stage in the history of programming, up to the middle of the 1960s. One person wrote a program for a concrete machine, in machine code or in assembler, without a method. There were no rules of design and no standards of style. A program was an individual work, and its quality depended on the skill of its author. Such programs were short, hard to read, and almost impossible for another person to support. There was no documentation as a separate product, and a program was usually written "to the end" and not changed.

**Що показати / What to show:** відповідь у звіті, с. 15-17; окремого рисунка немає / the answer is in the report, pp. 15-17; there is no separate figure.

---

## 4. Надати характеристику структурному та модульному етапу програмування.

## 4. Characterize the stages of structured and modular programming.

**Українською.** Структурне програмування (кінець 1960-х і 1970-ті) спирається на ідею, що програму будують із трьох структур з одним входом і одним виходом: слідування, вибору й циклу. Оператора переходу уникають, програму проектують зверху вниз і уточнюють покроково, тож її легко читати й перевіряти. Модульне програмування ділить програму на модулі. Модуль має назву, виконує одне завдання, має чіткий інтерфейс і прихований вміст. Модулі пишуть, тестують і змінюють окремо, і над ними можуть працювати різні люди. Якість поділу оцінюють зчепленням і зв'язаністю. Bookshelf поєднує обидві ідеї: сценарії використання уточнені як структурні алгоритми й реалізовані малими модулями (класами) зі слабким зчепленням.

**In English.** Structured programming (the end of the 1960s and the 1970s) is based on the idea that a program is built of three structures with one entry and one exit: sequence, selection and loop. The jump operator is avoided, the program is designed from the top down and refined step by step, so it is easy to read and to check. Modular programming divides a program into modules. A module has a name, does one task, and has a clear interface and a hidden inside. The modules are written, tested and changed separately, and different people can work on them. The quality of the division is judged by coupling and cohesion. Bookshelf uses both ideas: its use cases are refined as structured algorithms and implemented as small modules (classes) with weak coupling.

**Що показати / What to show:** лістинг модулів, Додаток А (с. 18-23), напр. A.1 (с. 18) / the listing of the modules, Appendix A (pp. 18-23), for example A.1 (p. 18).

---

## 5. Дати визначення документації до ПЗ. Описати її призначення.

## 5. Define the documentation of software and describe its purpose.

**Українською.** Документація до ПЗ це набір документів, які описують програму, її будову, використання та супровід. Вона буває двох видів: для розробників (текст програми, опис програми, посібники програміста й системного програміста) і для користувачів (опис застосування, посібники користувача й оператора). Призначення документації: дати користувачам запускати програму правильно, дати розробникам розуміти, змінювати й тестувати її, і зробити з програми продукт, який можна передати, підтримувати й розвивати після того, як автори пішли. Документація Bookshelf у додатках A, C, D, E і F звіту.

**In English.** The documentation of software is a set of documents that describe the program, its structure, its use and its support. It has two kinds: for the developers (the program text, the description of the program, the guides of the programmer and of the system programmer) and for the users (the description of application, the guides of the user and of the operator). The purpose of the documentation is to let users run the program correctly, to let developers understand, change and test it, and to make the program a product that can be handed over, supported and developed after the authors have left. The documentation of Bookshelf is in Appendices A, C, D, E and F of the report.

**Що показати / What to show:** табл. 1 (с. 3) і табл. 3 (с. 4) / Table 1 (p. 3) and Table 3 (p. 4).
