# Відповіді на контрольні питання, лабораторна робота 3 (Bookshelf)

# Answers to the control questions, laboratory work 3 (Bookshelf)

Це 7 контрольних питань із методички. Кожна відповідь коротка: її можна сказати вголос за хвилину. Нижче кожного питання є українська версія, англійська версія (рівень B1) і підказка, що показати у звіті `Zvit_LR3_Bookshelf.docx`. Номери сторінок такі самі, як у доповіді. Додаткові запитання, які можуть поставити окрім цих семи, є в таблицях доповіді (`Doklad_LR3.md` і `Doklad_LR3_en.md`).

These are the 7 control questions from the methodology. Every answer is short: you can say it aloud in about one minute. Under each question there is a Ukrainian version, an English version (B1 level) and a hint about what to show in the report. The page numbers are the same as in the presentation. Other questions that the teacher may ask are in the tables of the presentation (`Doklad_LR3.md` and `Doklad_LR3_en.md`).

---

## 1. Що розуміють під структурною і функціональною схемами ПЗ? У яких випадках їх застосовують? Чим вони відрізняються для ПЗ з різною архітектурою?

## 1. What are the structural and the functional scheme of software? When are they used? How do they differ for software with different architectures?

**Українською.** Структурна схема показує склад частин програми та їхню взаємодію за керуванням: які є частини й хто кого викликає. Функціональна схема показує, як компоненти взаємодіють через потоки інформації: які дані в потоках, які файли й пристрої використовуються. Вона записується за ГОСТ 19.701-90 і містить більше інформації. Структурну схему будують першою, коли програму ділять на частини. Функціональну будують після неї, коли треба показати потоки даних і пристрої. Для комплексу програм схеми показують самі програми, їхні виклики, вхід і вихід. Для системи з базою даних вони показують підсистеми, структуру бази, форми введення й звіти.

**In English.** A structural scheme shows the parts of a program and how they work together by control: which parts there are and who calls whom. A functional scheme shows how the components work together through information flows: what data is in the flows, and which files and devices are used. It is drawn with the symbols of GOST 19.701-90, and it has more information. We build the structural scheme first, when we divide the program into parts. We build the functional scheme after it, when we need to show the data flows and the devices. For a set of programs, the schemes show the programs, their calls, and their input and output. For a system with a database, they show the subsystems, the structure of the database, the input forms and the reports.

**Що показати / What to show:** рис. А.1 (с. 13) і рис. А.2 (с. 15) / Figure A.1 (p. 13) and Figure A.2 (p. 15).

---

## 2. На яких властивостях ПЗ заснований метод покрокової деталізації? Чому з ним отримують лише структурні алгоритми? У чому, на вашу думку, головна складність методу?

## 2. On what properties of software is the method of stepwise refinement based? Why does it give only structured algorithms? What, in your opinion, is the main difficulty of the method?

**Українською.** Метод спирається на три властивості. По-перше, складне завдання можна розбити на простіші підзавдання, тобто на ієрархію. По-друге, будь-який алгоритм можна скласти з трьох структур з одним входом і одним виходом: слідування, вибору й циклу. По-третє, кожен крок має визначені вхід і вихід, тож кроки можна уточнювати незалежно. Кожне уточнення замінює крок композицією цих трьох структур, а така композиція сама є структурною. Тому на виході тільки структурні алгоритми. Головна складність у виборі розбиття на верхніх рівнях: якщо помилилися там, це видно пізно, коли нижні рівні вже розписані.

**In English.** The method is based on three properties. First, a complex task can be split into simpler subtasks, so we get a hierarchy. Second, any algorithm can be built from three structures with one entry and one exit: sequence, selection and loop. Third, every step has a clear input and output, so we can refine the steps independently. Each refinement replaces a step with a combination of these three structures, and such a combination is also structured. This is why we get only structured algorithms. For me the main difficulty is the choice of the split at the top levels: if we make a mistake there, we see it late, when the lower levels are already written.

**Що показати / What to show:** табл. А.5 і А.6 (с. 11-12) / Tables A.5 and A.6 (pp. 11-12).

---

## 3. Як використовується метод покрокової деталізації під час розробки алгоритмів і структури ПЗ?

## 3. How is the method of stepwise refinement used in the development of algorithms and of the structure of software?

**Українською.** Завдання формулюємо одним реченням, це рівень нуль. Потім ділимо його на кілька кроків, що з'єднані слідуванням, вибором і циклом, і вказуємо модуль для кожного кроку, це рівень один. Далі кожен крок уточнюємо так само, доки він не стане оператором мови чи готовим модулем, це рівень два з псевдокодом. Дерево кроків і є структурою програми: кроки стають модулями, повторювані кроки стають спільними модулями, а дані між кроками стають параметрами. У нас так уточнено оновлення прогресу й імпорт EPUB.

**In English.** We write the task in one sentence, this is level zero. Then we split it into a few steps connected by sequence, selection and loop, and we name a module for each step, this is level one. After that we refine every step in the same way until it is an operator of the language or a ready module, this is level two with pseudocode. The tree of steps is the structure of the program: steps become modules, repeated steps become common modules, and the data between the steps becomes parameters. In our work we refined the progress update and the EPUB import in this way.

**Що показати / What to show:** табл. А.5 і А.6, псевдокод (с. 11-12) / Tables A.5 and A.6, pseudocode (pp. 11-12).

---

## 4. Для чого будують структурні карти Константайна?

## 4. What are the structure charts of Constantine built for?

**Українською.** Вони показують результат поділу програми на модулі та зв'язки між модулями: виклики (послідовні, умовні, у циклі) і передачу даних та керування. За ними оцінюють якість поділу двома критеріями: зчепленням і зв'язаністю. Також за ними знаходять, що можна покращити, і складають специфікації інтерфейсів модулів. У нашому звіті карти для оновлення прогресу й імпорту EPUB, а оцінка зчеплення й зв'язаності в таблиці А.11. Слабкі місця: клас статистики, метод зміни статусу й великий клас очищення HTML.

**In English.** They show the result of dividing a program into modules and the links between the modules: the calls (sequential, conditional, in a loop) and the passing of data and control. With them we judge the quality of the split by two criteria: coupling and cohesion. We also find what can be improved, and we write the specifications of the module interfaces. In our report there are charts for the progress update and for the EPUB import, and the evaluation of coupling and cohesion is in Table A.11. The weak places are the statistics class, the method that changes the status, and the large class that cleans HTML.

**Що показати / What to show:** рис. А.4 і А.5 (с. 17-18), табл. А.11 (с. 19-20) / Figures A.4 and A.5 (pp. 17-18), Table A.11 (pp. 19-20).

---

## 5. Чим структурні карти Джексона відрізняються від структурних карт Константайна?

## 5. How do the structure charts of Jackson differ from the structure charts of Constantine?

**Українською.** Карта Константайна це граф модулів: вона показує, який модуль кого викликає і які дані та керування передаються. Тобто вона описує структуру між модулями й дозволяє оцінити зчеплення та зв'язаність. Карта Джексона це дерево будови однієї програми чи модуля зсередини. Її виводять зі структури даних, які програма обробляє, і вона має лише чотири конструкції: операцію, слідування, вибір та ітерацію. Вона схожа на блок-схему. Перша відповідає на питання «які модулі й як вони пов'язані», друга на питання «у якому порядку модуль обробляє свої дані». Імпорт EPUB у нас намальований обома способами.

**In English.** A Constantine chart is a graph of modules. It shows which module calls which one, and what data and control are passed. So it describes the structure between the modules, and it lets us judge coupling and cohesion. A Jackson chart is a tree of the inside of one program or module. We build it from the structure of the data that the program processes, and it has only four constructions: operation, sequence, selection and iteration. It is similar to a flowchart. The first answers the question "which modules are there and how are they connected", and the second answers "in what order does one module process its data". We drew the EPUB import in both ways.

**Що показати / What to show:** рис. А.5 (с. 18) і рис. А.7 (с. 22) / Figure A.5 (p. 18) and Figure A.7 (p. 22).

---

## 6. Що покладено в основу методик Джексона і Варньє-Орра? Чим вони відрізняються?

## 6. What is the basis of the methods of Jackson and of Warnier-Orr? How do they differ?

**Українською.** В основі обох методів лежить структура даних: структуру програми виводять зі структури даних, які вона обробляє. Тому програма для добре структурованих даних сама добре структурована, і її легко змінювати, коли змінюються дані. У методі Джексона будують структури вхідних і вихідних даних, поєднують відповідні частини в одне дерево, і це структура програми, яку малюють прямокутниками. Метод Варньє-Орра орієнтований на вихід: за основу беруть структуру потрібних вихідних даних і виводять програму від неї назад. Ієрархію записують вкладеними фігурними дужками. Отже, різниця в тому, з чого починають (вхід і вихід разом чи вихід) та в позначеннях.

**In English.** Both methods are based on the structure of data: we get the structure of a program from the structure of the data that it processes. So a program for well-structured data is also well structured, and it is easy to change when the data changes. In the Jackson method, we build the structures of the input data and of the output data, and we join the matching parts into one tree. This tree is the structure of the program, and we draw it with boxes. The Warnier-Orr method is focused on the output: we start from the structure of the required output data and we get the program from it, step by step backwards. The hierarchy is written with nested curly brackets. So the difference is in the starting point (input and output together, or only output) and in the notation.

**Що показати / What to show:** це відповідь без рисунка; якщо питають про Джексона, покажіть рис. А.6 і А.7 (с. 21-22) / this answer has no figure; if they ask about Jackson, show Figures A.6 and A.7 (pp. 21-22).

---

## 7. Для яких розробок доцільно використовувати структурні методології?

## 7. For what kinds of developments is it reasonable to use structured methodologies?

**Українською.** Їх доцільно використовувати там, де функції й дані чітко визначені й їх можна розкласти на частини. Це інформаційні системи й системи обробки даних, програми з зрозумілим входом, виходом і алгоритмом. Також вони добре підходять для середніх і великих проєктів з кількома розробниками й стабільними вимогами, бо діаграми й специфікації стають спільною мовою команди. Гірше вони підходять там, де поведінка залежить від подій та стану інтерфейсу або вимоги швидко змінюються. У нашому проєкті ми поєднали підходи: сценарії використання уточнено як структурні алгоритми, а реалізовано класами об'єктно-орієнтованого фреймворку.

**In English.** It is reasonable to use them when the functions and the data are clearly defined and can be split into parts. These are information systems and data processing systems, and programs with a clear input, output and algorithm. They are also good for medium and large projects with several developers and stable requirements, because diagrams and specifications become a common language of the team. They are less suitable when the behaviour depends on events and on the state of the interface, or when the requirements change quickly. In our project we combined the approaches: we refined the use cases as structured algorithms, and we implemented them as classes of an object-oriented framework.

**Що показати / What to show:** окремо нічого не показуйте; можна згадати табл. А.5-А.6 (с. 11-12) як приклад / nothing special to show; you can mention Tables A.5-A.6 (pp. 11-12) as an example.
