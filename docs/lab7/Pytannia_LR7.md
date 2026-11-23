# Відповіді на контрольні питання, лабораторна робота 7 (Bookshelf)

# Answers to the control questions, laboratory work 7 (Bookshelf)

Це 19 контрольних питань із методички. Кожна відповідь коротка: її можна сказати вголос за хвилину. Під питанням є українська версія, англійська версія (рівень B1) і підказка, що показати у звіті `Zvit_LR7_Bookshelf.docx`. Номери таблиць і рисунків у звіті відомі точно, номери сторінок ні: файл не експортовано у PDF, тому підказки йдуть за назвою розділу чи таблиці.

These are the 19 control questions from the methodology. Every answer is short: you can say it aloud in about one minute. Under each question there is a Ukrainian version, an English version (B1 level) and a hint about what to show in the report `Zvit_LR7_Bookshelf.docx`. The numbers of the tables and figures are exact; the page numbers are not (the file was not exported to PDF), so the hints use the names of sections and tables.

---

## 1. Які фактори об’єктно-орієнтованих систем впливають на метрики для їхньої оцінки і як проявляється цей вплив?

## 1. Which factors of object-oriented systems influence the metrics for their evaluation, and how does this influence appear?

**Українською.** П’ять факторів: локалізація, інкапсуляція, інформаційна закритість, спадкування й абстракція. Через локалізацію метрики застосовують до класу як до цілого, а не до окремої функції. Інкапсуляція зміщує вимір на групу властивостей і операцій (наприклад, кількість операцій у класі). Закритість дає метрики того, наскільки клас ховає внутрішнє. Спадкування дає метрики глибини, ширини й кількості нащадків ієрархії. Абстракція дає метрики в термінах класу (кількість екземплярів, частка родових класів).

**In English.** Five factors: localization, encapsulation, information hiding, inheritance and abstraction. Localization means that the information is grouped in classes, so the metrics are applied to a class as a whole and must handle the relations "one to many" and "many to many" between operations and classes. Encapsulation moves the measurement from one module to a group of properties and operations (for example, the number of operations in a class). Information hiding gives metrics that show how much of a class is hidden. Inheritance gives the metrics of the depth, the width and the number of children of a class hierarchy. Abstraction gives the metrics in the terms of the class (the number of instances, the share of the generic classes).

**Що показати / What to show:** теорія, розділ «Characteristics of object-oriented systems» / theory, "Characteristics of object-oriented systems".

---

## 2. Який вплив виявляє спадкування на зв’язаність класів?

## 2. What influence does inheritance have on the cohesion of classes?

**Українською.** Спадкування зменшує зв’язаність класу, який успадковує. Функціональність приходить від суперкласів, тому клас не можна розглядати окремо: треба враховувати всі його суперкласи. Клас із кількома батьками зрозуміти ще важче. У Bookshelf це видно на моделях і запитах: частина їхньої поведінки лежить у Model і FormRequest з Laravel.

**In English.** Inheritance lowers the cohesion of the class that inherits. The functions come from the superclasses, so the class cannot be seen as a separate module: all its superclasses must be taken into account. A class that inherits from several parents is much harder to understand. In Bookshelf this shows in the models and the requests, whose behaviour is partly in Model and FormRequest of Laravel.

**Що показати / What to show:** теорія, той самий розділ / theory, the same section.

---

## 3. Охарактеризуйте метрики зв’язаності класів за даними.

## 3. Describe the metrics of data cohesion of classes.

**Українською.** Це метрики Отта й Мехри на основі секцій даних. Для кожного виходу методу будують секцію: послідовність лексем даних (змінних і констант), потрібних для його обчислення. Лексеми, що входять у кілька секцій, називають склеєними, а ті, що входять у всі секції, сильно склеєними. Сильна зв’язаність за даними це частка сильно склеєних лексем серед усіх лексем класу, слабка це частка склеєних лексем, клейкість даних це сума кількостей секцій для кожної склеєної лексеми, поділена на добуток кількості лексем і кількості секцій. Клас без склеєних лексем має нульову зв’язаність за даними.

**In English.** They are the metrics of Ott and Mehra, based on slices of data. For every output of a method a slice is built: the sequence of the data tokens (variables and constants) needed to compute it. The tokens that belong to more than one slice are glued tokens, and those that belong to all slices are strongly glued tokens. Strong data cohesion is the share of the strongly glued tokens among all the tokens of the class, weak data cohesion is the share of the glued tokens, and data adhesiveness is the sum of the numbers of slices for each glued token divided by the product of the number of tokens and the number of slices. A class without glued tokens has zero cohesion by the data.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 4. Охарактеризуйте метрики зв’язаності класів по методах.

## 4. Describe the metrics of cohesion of classes by methods.

**Українською.** Це метрики Бімена й Кенга. Два методи з’єднані прямо, якщо використовують спільну змінну екземпляра, і побічно, якщо з’єднані через інші методи. Конструктори й деструктори не враховують. TCC (сильна зв’язаність класу) це частка прямо з’єднаних пар методів серед усіх можливих пар, LCC (слабка зв’язаність) це частка пар, з’єднаних прямо або побічно. Завжди TCC не більше LCC. У цій роботі за тим самим принципом використано LCOM і LCOM4.

**In English.** They are the metrics of Bieman and Kang. Two methods are directly connected if they use a common instance variable, and indirectly if they are connected through other methods. Constructors and destructors are not counted. TCC (tight class cohesion) is the share of the directly connected pairs of methods among all the possible pairs, and LCC (loose class cohesion) is the share of the pairs that are connected directly or indirectly. Always TCC <= LCC. In this work LCOM and LCOM4 are used in the same spirit.

**Що показати / What to show:** лише відповідь; LCOM і LCOM4 у табл. 4 та аналізі LCOM / the answer only; LCOM and LCOM4 in Table 4 and the analysis of LCOM.

---

## 5. Які характеристики об’єктно-орієнтованих систем погіршують зчеплення класів?

## 5. Which characteristics of object-oriented systems make the coupling of classes worse?

**Українською.** Спадкування: клас зчеплений з усіма своїми суперкласами, і зміна суперкласу поширюється на підкласи. Також зростає кількість видів залежностей (спадкування, делегування, реалізація інтерфейсів) і видів модулів. Самі по собі ООП-механізми не гарантують слабкого зчеплення. У Bookshelf зчеплення найвище в контролерах і моделях, які зустрічаються з багатьма іншими класами.

**In English.** Inheritance: a class is coupled to all its superclasses, and a change of a superclass spreads to the subclasses. Also the number of the kinds of dependencies grows: inheritance, delegation, implementation of interfaces, plus more kinds of modules (classes, free functions, packages). The object-oriented mechanisms do not guarantee a low coupling by themselves. In Bookshelf the coupling is highest in the controllers and the models, which meet many other classes.

**Що показати / What to show:** рис. 3 та аналіз CBO і RFC / Figure 3 and the analysis of CBO and RFC.

---

## 6. Пояснити, як визначити зчеплення класів за допомогою метрики «залежність зміни між класами».

## 6. Explain how to determine the coupling of classes with the metric "change dependency between classes".

**Українською.** CDBC (Хітц і Монтазері) показує, скільком методам клієнтського класу CC може знадобитися зміна після зміни класу-сервера SC. Усе залежить від типу відношення: якщо SC це тип змінної екземпляра в CC, його суперклас або використовується через глобальну змінну, зачеплені всі n методів CC; якщо SC тип параметра або локальної змінної в j методах, зачеплено j методів; якщо SC не використовується, значення 0. Доступ через стабільний інтерфейс множать на (1 − k), де k фактор стабільності інтерфейсу. Метрику зменшують обмеженням доступу до інтерфейсу сервера та його видимості.

**In English.** CDBC (Hitz and Montazeri) shows how many methods of a client class CC may need a change after a change of a server class SC. It depends on the kind of the relation: if SC is the type of an instance variable of CC, or its superclass, or is used through a global variable, all n methods of CC are affected; if SC is the type of a parameter or of a local variable in j methods, then j methods are affected; if SC is not used, the value is 0. The access through a stable interface is multiplied by (1 - k), where k is the stability factor of the interface. The metric is lowered by limiting the access to the interface of the server and its visibility.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 7. Пояснити зміст метрики локальності даних.

## 7. Explain the meaning of the metric of locality of data.

**Українською.** Локальність даних це частка локальних даних класу серед усіх даних, які використовують його методи. Локальні дані це непублічні змінні екземпляра, захищені змінні батьків і статичні змінні класу. Висока локальність означає самодостатній клас, а отже краще повторне використання й тестованість. Метрика показує якість абстракції, яку реалізує клас.

**In English.** Locality of data is the share of the data that is local to the class among all the data that its methods use. Local data are the non-public instance variables, the protected variables of the parents and the static variables of the class. A high value means that the class is self-sufficient, which gives a better reuse and testability. It is a measure of the quality of the abstraction that the class implements.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 8. Які метрики входять у набір Чидамбера й Кемерера? Які задачі вони вирішують?

## 8. Which metrics make up the suite of Chidamber and Kemerer? Which tasks do they solve?

**Українською.** Шість метрик: WMC (зважені методи на клас), DIT (висота дерева спадкування), NOC (кількість дітей), CBO (зчеплення між класами), RFC (відгук для класу) і LCOM (недолік зв’язаності в методах). Вони оцінюють якість проєктування класів: розмір і складність (WMC), спадкування (DIT, NOC), зчеплення (CBO, RFC) і зв’язаність (LCOM). Допомагають знайти класи, які складно писати, тестувати й змінювати.

**In English.** Six metrics: WMC (weighted methods per class), DIT (depth of the inheritance tree), NOC (number of children), CBO (coupling between object classes), RFC (response for a class) and LCOM (lack of cohesion in methods). They evaluate the quality of the design of classes: the size and the complexity (WMC), the inheritance (DIT, NOC), the coupling (CBO, RFC) and the cohesion (LCOM). They help to find the classes that are hard to write, test and change.

**Що показати / What to show:** табл. 1 / Table 1.

---

## 9. Як можна підраховувати кількість методів у класі?

## 9. How can the number of methods in a class be counted?

**Українською.** Є кілька прийнятних способів. Рахувати лише методи, оголошені в самому класі (успадковані вже пораховано в їхніх класах); або оголошені й усі успадковані (це підкреслює простір станів класу); або проміжний варіант, наприклад оголошені й успадковані безпосередньо від батьків. Будь-який варіант прийнятний, якщо він однаковий для всього проєкту. У цій роботі рахуємо методи, оголошені в тілі класу, вага кожного 1, а цикломатична складність є другим варіантом.

**In English.** There are several accepted ways. Count only the methods declared in the class (the inherited ones are counted in their own classes); or count the declared and all the inherited methods (it stresses the state space of the class); or something in between, for example the declared methods and the methods inherited directly from the parents. Any way is acceptable if it is the same for the whole project. In this work the methods declared in the class body are counted, the weight is 1 for each, and the cyclomatic complexity is a second variant.

**Що показати / What to show:** табл. 4 (правила підрахунку) / Table 4 (counting rules).

---

## 10. Які метрики Чидамбера й Кемерера оцінюють зчеплення класів? Поясніть їхній зміст.

## 10. Which metrics of Chidamber and Kemerer evaluate the coupling of classes? Explain their meaning.

**Українською.** CBO і RFC. CBO це кількість інших класів, з якими з’єднаний клас (його методи використовують їхні методи чи змінні), статична складова зовнішніх зв’язків. RFC це кількість методів множини відгуку: власні методи плюс методи інших класів, які вони викликають; це динамічна складова, потенційна взаємодія з іншими класами. Великі значення ускладнюють тестування й зміни. DIT і NOC теж виражають зчеплення через спадкування.

**In English.** CBO and RFC. CBO is the number of the other classes to which the class is connected (its methods use their methods or variables); it is the static part of the external links. RFC is the number of the methods of the response set: the own methods plus the methods of other classes that they call; it is the dynamic part, the potential interaction with other classes. Large values make the testing and the change harder. DIT and NOC also express the coupling through the inheritance.

**Що показати / What to show:** табл. 1, табл. 5 та аналіз CBO і RFC / Table 1, Table 5 and the analysis of CBO and RFC.

---

## 11. Яка метрика Чидамбера й Кемерера оцінює зв’язаність класу? Пояснити її зміст.

## 11. Which metric of Chidamber and Kemerer evaluates the cohesion of a class? Explain its meaning.

**Українською.** LCOM. Кожен метод використовує якісь змінні екземпляра. P це кількість пар методів без спільних змінних, Q кількість пар зі спільними змінними, LCOM = P − Q (не менше нуля). Якщо всі методи використовують одні змінні, LCOM дорівнює 0. Велике значення означає слабко зв’язані методи, і клас, імовірно, варто розділити на два чи більше.

**In English.** LCOM. Every method uses some instance variables. P is the number of pairs of methods without a common variable, Q is the number of pairs with a common variable, and LCOM = P - Q (not below zero). If all the methods use the same variables, LCOM is 0. A high value means that the methods are weakly connected, and the class should probably be split into two or more classes.

**Що показати / What to show:** табл. 1 та аналіз LCOM / Table 1 and the analysis of LCOM.

---

## 12. Як добитися незалежності метрики WMC від реалізації?

## 12. How can the independence of the metric WMC from the implementation be achieved?

**Українською.** Кількість методів залежить від того, як записано обов’язок: одна складна операція з багатьма параметрами чи кілька простих. Щоб прибрати цю залежність, Лоренц і Кідд використовують метрику, нормалізовану за кількістю аргументів: добуток кількості методів на середню кількість аргументів однаковий для обох варіантів. Також дають середню кількість аргументів методу (NPAVG). Вага за складністю методів (WMC_cc) теж зменшує залежність.

**In English.** The number of the methods depends on the way a duty is written: one complex operation with many parameters or several simple ones. To remove this, Lorenz and Kidd use the normalized size by the number of the arguments: the metric NMA (the number of methods multiplied by the average number of arguments) is the same for both versions. The average number of arguments of a method (NPAVG) is also given. A weight by the complexity of the methods (WMC_cc) reduces the dependence too.

**Що показати / What to show:** табл. 4 (WMC і WMC_cc) / Table 4 (WMC and WMC_cc).

---

## 13. Як можна оцінити інформаційну закритість класу?

## 13. How can the information hiding of a class be evaluated?

**Українською.** У наборі Чидамбера-Кемерера такої метрики немає, тому запропоновано поведінкову інформаційну закритість: частку операцій, видимих ззовні, серед усіх операцій класу. Чим ближче до 1, тим більше клас показує іншим, чим менше, тим більше сховано. Фактори MHF і AHF Абреу (частка схованих методів і властивостей) вимірюють це для системи.

**In English.** The suite of Chidamber and Kemerer has no such metric, so the behavioural information hiding is proposed: the share of the operations that are visible from outside (the external weighted operations) among all the operations of the class. The nearer it is to 1, the more the class shows to the others; the smaller, the more is hidden. The factors MHF and AHF of Abreu (the share of the hidden methods and of the hidden attributes) measure it for the system.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 14. Порівняйте набори Чидамбера-Кемерера й Лоренца-Кидда. Чим вони схожі? У чому відмінність?

## 14. Compare the suites of Chidamber-Kemerer and Lorenz-Kidd. What is similar and what is different?

**Українською.** Обидва набори орієнтовані на класи й використовують розмір класу, спадкування та складність. Лоренц і Кідд беруть WMC, DIT і NOC в іншій формі. Набір Чидамбера-Кемерера академічний: шість метрик проєкту класів, з зчепленням і зв’язаністю (CBO, RFC, LCOM). Набір Лоренца-Кідда практичний, промисловий: розмір класу, операції, перевизначені й додані підкласом, індекс спеціалізації, розмір і складність операції, кількість параметрів і метрики для планування проєкту (сценарії, ключові класи, підсистеми), з рекомендованими значеннями. Прямих метрик зчеплення й зв’язаності в ньому немає.

**In English.** Both are class oriented and both use the size of a class, the inheritance and the complexity. Lorenz and Kidd take WMC, DIT and NOC from Chidamber and Kemerer in a different form. The suite of Chidamber and Kemerer is academic, six metrics for the design of classes, with the coupling and the cohesion (CBO, RFC, LCOM). The suite of Lorenz and Kidd is practical and industrial: the size of a class, the operations overridden and added by a subclass, the index of specialization, the size and the complexity of an operation, the number of parameters, and metrics for the planning of a project (scenarios, key classes, subsystems), with recommended values. It has no direct coupling and cohesion metrics.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 15. На які цілі орієнтований набір метрик Фернандо Абреу?

## 15. For which goals is the suite of metrics of Fernando Abreu oriented?

**Українською.** Набір MOOD (1994) має охопити основні механізми ООП (інкапсуляцію, спадкування, поліморфізм, передачу повідомлень), бути формально визначеним (без суб’єктивності виміру), не залежати від розміру продукту й від мови програмування.

**In English.** The suite MOOD (1994) aims to cover the basic mechanisms of the object paradigm (encapsulation, inheritance, polymorphism, message passing), to be formally defined (no subjective measuring), to be independent of the size of the product and independent of the programming language.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 16. Охарактеризуйте склад набору метрик Фернандо Абреу.

## 16. Describe the composition of the suite of metrics of Fernando Abreu.

**Українською.** Шість факторів у діапазоні від 0 до 1: MHF (фактор закритості методу) і AHF (фактор закритості властивості) для інкапсуляції; MIF (фактор спадкування методу) і AIF (фактор спадкування властивості) для спадкування; POF (фактор поліморфізму); COF (фактор зчеплення) для передачі повідомлень, частка пар класів у відношенні «клієнт-постачальник» серед усіх можливих пар.

**In English.** Six factors in the range from 0 to 1: MHF (method hiding factor) and AHF (attribute hiding factor) for the encapsulation; MIF (method inheritance factor) and AIF (attribute inheritance factor) for the inheritance; POF (polymorphism factor); COF (coupling factor) for the message passing, the share of the pairs of classes in the client-supplier relation among all the possible pairs.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 17. Порівняйте набори Чидамбера-Кемерера й Фернандо Абреу. Чим вони схожі? У чому відмінність?

## 17. Compare the suites of Chidamber-Kemerer and Fernando Abreu. What is similar and what is different?

**Українською.** Обидва академічні й формально визначені, обидва охоплюють спадкування і зчеплення. Набір Чидамбера-Кемерера вимірює кожен клас (шість значень на клас) і має зв’язаність (LCOM) та множину відгуку; набір Абреу вимірює систему в цілому (шість відсотків), не залежить від розміру й мови та додає закритість і поліморфізм, яких у першого немає.

**In English.** Both are academic and formally defined, and both cover the inheritance and the coupling. The suite of Chidamber and Kemerer measures each class (six values per class) and has the cohesion (LCOM) and the response set; the suite of Abreu measures the whole system (six percentages), is independent of the size and the language, and adds the hiding and the polymorphism, which the first one does not have.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 18. Порівняйте набори Лоренца-Кидда й Фернандо Абреу. Чим вони схожі? У чому відмінність?

## 18. Compare the suites of Lorenz-Kidd and Fernando Abreu. What is similar and what is different?

**Українською.** Обидва описують спадкування й розмір системи. Набір Лоренца-Кідда практичний: абсолютні значення для класів і операцій з рекомендованими межами та метрики для планування. Набір Абреу теоретичний: відносні фактори для всієї системи з формальними визначеннями, містить закритість, поліморфізм і фактор зчеплення, яких у Лоренца-Кідда немає в такому вигляді.

**In English.** Both describe the inheritance and the size of the system. The suite of Lorenz and Kidd is practical: absolute values for classes and operations, with recommended limits and the metrics for planning. The suite of Abreu is theoretical: relative factors for the whole system with the formal definitions, and it has the hiding, the polymorphism and the coupling factor, which Lorenz and Kidd do not have in this form.

**Що показати / What to show:** лише відповідь / the answer only.

---

## 19. Дайте характеристику метрик для об’єктно-орієнтованого тестування.

## 19. Characterize the metrics for object-oriented testing.

**Українською.** Це метрики Байндера, що впливають на тестованість. Інкапсуляція: LCOM (більше станів для тестів), відсоток публічних і захищених, публічний доступ до даних. Спадкування: кількість кореневих класів (набір тестів на кожне дерево), коефіцієнт об’єднання по входу (множинне спадкування), NOC і DIT (методи суперкласу тестують знову для кожного підкласу). Складність: WMC і кількість методів. Поліморфізм: відсоток неперевизначених і динамічних запитів, стрибки класу й системи. Для Bookshelf метрики кажуть, що контролери й дії (високий RFC) потребують найбільше налаштувань у тестах.

**In English.** They are the metrics of Binder that influence the testability. Encapsulation: LCOM (more states to test), percent public and protected, public access to data. Inheritance: the number of root classes (a set of tests for each tree), the fan-in (multiple inheritance), NOC and DIT (the methods of a superclass are retested for each subclass). Complexity: WMC and the number of methods. Polymorphism: the percent of the not overridden calls, the percent of the dynamic calls and the bounce metrics for the class and for the system. For Bookshelf the metrics say that the controllers and the actions (a high RFC) need the most set-up in the tests.

**Що показати / What to show:** лише відповідь / the answer only.

---
