# Відповіді на контрольні питання, лабораторна робота 6 (Bookshelf)

# Answers to the control questions, laboratory work 6 (Bookshelf)

Це 11 контрольних питань із методички. Кожна відповідь коротка: її можна сказати вголос за хвилину. Під питанням є українська версія, англійська версія (рівень B1) і підказка, що показати у звіті `Zvit_LR6_Bookshelf.docx`.

These are the 11 control questions from the methodology. Every answer is short: you can say it aloud in about one minute. Under each question there is a Ukrainian version, an English version (B1 level) and a hint about what to show in the report.

---

## 1. У чому полягає призначення моделі композиції? На яких оцінках вона базується?

## 1. What is the purpose of the model of composition? On which estimates is it based?

**Українською.** Модель композиції додатка застосовують на ранній стадії конструювання, коли макетують інтерфейси, обговорюють взаємодію ПЗ з комп'ютерною системою, оцінюють продуктивність і зрілість технології. Вона базується на об'єктних покажчиках: кількості екранів, звітів і компонентів на мові третього покоління, кожного простого, середнього чи складного рівня зі своєю вагою. Витрати дорівнюють кількості нових об'єктних покажчиків, поділеній на продуктивність, яка залежить від досвіду розробників і зрілості середовища.

**In English.** The model of composition is used at the early stage of construction, when the interfaces are prototyped, the interaction of the software with the computer system is discussed, and the performance and the maturity of the technology are estimated. It is based on object points: the number of screens, reports and components in a third generation language, each of a simple, average or complex level with its own weight. The effort is the number of new object points divided by the productivity, which depends on the experience of the developers and the maturity of the environment.

**Що показати / What to show:** теорія, с. 2 / theory, p. 2.

---

## 2. У чому полягає призначення моделі раннього етапу проектування?

## 2. What is the purpose of the Early Design model?

**Українською.** Модель раннього етапу використовують, коли вимоги стабілізувалися й визначено базову архітектуру ПЗ. Вона дає грубу оцінку витрат і тривалості за малим набором чинників ще до того, як відомі подробиці проєкту. Саме її ми побудували для Bookshelf (варіант 19).

**In English.** The Early Design model is used when the requirements are stable and the basic architecture of the software is defined. It gives a rough estimate of the effort and the duration with a small set of drivers, before the details of the design are known. This is the model that we built for Bookshelf (variant 19).

**Що показати / What to show:** с. 2-3; базова оцінка, табл. 5 (с. 6) / pp. 2-3; the base estimate, Table 5 (p. 6).

---

## 3. Охарактеризуйте основне рівняння моделі раннього етапу проектування.

## 3. Characterize the main equation of the Early Design model.

**Українською.** PM = A × SIZE^B × ΠEMi + PM_auto. Тут A = 2,5, SIZE це розмір у тисячах рядків, B показує нелінійне зростання витрат разом із розміром (від 1,01 до 1,26), EMi це сім множників витрат, які описують продукт, процес і персонал, а останній доданок це витрати на автоматично згенерований код (у нас нуль). Для Bookshelf: 2,5 × 22,1^1,14 × 1,49 = 127 людино-місяців.

**In English.** PM = A × SIZE^B × ΠEMi + PM_auto. Here A = 2.5, SIZE is the size in thousands of lines, B shows the nonlinear growth of the effort with the size (from 1.01 to 1.26), EMi are the seven effort multipliers that describe the product, the process and the personnel, and the last term is the effort for the automatically generated code (zero for us). For Bookshelf: 2.5 × 22.1^1.14 × 1.49 = 127 person-months.

**Що показати / What to show:** с. 2 і табл. 5 (с. 6) / p. 2 and Table 5 (p. 6).

---

## 4. Охарактеризуйте масштабні фактори моделі COCOMO II.

## 4. Characterize the scale factors of COCOMO II.

**Українською.** Їх п'ять: передбачуваність PREC (досвід організації з такими проєктами), гнучкість розробки FLEX, авторизація архітектури й ризику RESL, зв'язність групи TEAM і зрілість процесу PMAT. З них складається показник степеня B, тож вони показують, як швидко витрати ростуть із розміром.

**In English.** There are five: precedentedness PREC (the experience of the organization with such projects), flexibility of development FLEX, architecture and risk resolution RESL, team cohesion TEAM and process maturity PMAT. They make the exponent B, so they show how fast the effort grows with the size.

**Що показати / What to show:** табл. 1 (с. 3), табл. 2 (с. 5) / Table 1 (p. 3), Table 2 (p. 5).

---

## 5. Як оцінюються масштабні фактори?

## 5. How are the scale factors estimated?

**Українською.** Кожен фактор отримує одну з шести оцінок, від дуже низької (5) до надвисокої (0), за описом із таблиці методички; зрілість процесу випливає з відповідей на питання моделі CMM. П'ять значень складають і підставляють у B = 1,01 + 0,01 × ΣWi. Приклад методички: сума 16 дає B = 1,17; у нас сума 13, тому B = 1,14.

**In English.** Every factor gets one of six ratings, from very low (5) to extra high (0), by the description in the table of the methodology; the process maturity follows from the answers to the questions of the CMM. The five values are added and put into B = 1.01 + 0.01 × ΣWi. The example of the methodology: the sum 16 gives B = 1.17. In our case the sum is 13, so B = 1.14.

**Що показати / What to show:** табл. 2, с. 5 / Table 2, p. 5.

---

## 6. У чому полягає призначення моделі етапу пост-архітектури COCOMO II?

## 6. What is the purpose of the post-architecture model of COCOMO II?

**Українською.** Її використовують, коли архітектуру ПЗ вже сформовано й іде розробка. Це найдетальніша модель, яка дає точнішу оцінку, ніж модель раннього етапу.

**In English.** It is used when the architecture of the software is formed and the development goes on. It is the most detailed model and gives a more exact estimate than the Early Design model.

**Що показати / What to show:** теорія, с. 3; перевірка за її множниками, с. 7 / theory, p. 3; the check with its multipliers, p. 7.

---

## 7. Чим відрізняється основне рівняння моделі етапу пост-архітектури від аналогічного рівняння моделі раннього етапу проектування?

## 7. How does the main equation of the post-architecture model differ from that of the Early Design model?

**Українською.** Форма та сама, але є коефіцієнт змін вимог (відсоток коду, який відкидають через зміни), розмір це сума нового й повторно використаного коду (останній зменшують коефіцієнтами вартості повторного використання), а множник виправлення залежить від 17 чинників витрат замість семи.

**In English.** The form is the same, but there is a factor for the changes of requirements (the percent of code thrown away because of them), the size is the sum of the new code and the reused code (the latter is reduced by factors of the cost of reuse), and the correction multiplier depends on 17 cost drivers instead of seven.

**Що показати / What to show:** теорія, с. 3 / theory, p. 3.

---

## 8. Що таке фактори витрат моделі етапу пост-архітектури і як вони обчислюються?

## 8. What are the cost drivers of the post-architecture model and how are they calculated?

**Українською.** Це 17 характеристик продукту (надійність, розмір бази даних, складність, повторне використання, документація), платформи (обмеження часу й пам'яті, мінливість), персоналу (можливості аналітиків і програмістів, досвід, безперервність) і проєкту (засоби, розподілена розробка, графік). Кожна оцінюється від дуже низької до надвисокої, оцінка дає множник за таблицею Боема, а добуток усіх множників є поправкою. У лабі ми взяли 15 з них і склали з них чинники раннього етапу.

**In English.** They are 17 characteristics of the product (reliability, database size, complexity, reuse, documentation), of the platform (the time and memory constraints, volatility), of the personnel (the capability of analysts and programmers, experience, continuity) and of the project (the tools, multisite development, the schedule). Each is rated from very low to extra high, the rating gives a multiplier from Boehm's table, and the product of all multipliers is the correction. In the lab we used 15 of them to make the drivers of the Early Design model.

**Що показати / What to show:** додаток A, табл. 9 (с. 12) / Appendix A, Table 9 (p. 12).

---

## 9. Як визначається тривалість розробки в моделі COCOMO II?

## 9. How is the duration of development determined in COCOMO II?

**Українською.** За рівнянням TDEV = 3,0 × PM^(0,33 + 0,2 × (B − 1,01)) × SCED% / 100, де PM це витрати без урахування графіка, B показник степеня, а SCED% відсоток стиснення чи розтягування номінального графіка (від 75 до 160 %). Тривалість залежить від витрат, а не прямо від кількості людей. У нас: 17 місяців у базовому випадку, 19 у сценарії.

**In English.** By the equation TDEV = 3.0 × PM^(0.33 + 0.2 × (B − 1.01)) × SCED% / 100, where PM is the effort without the effect of the schedule, B is the exponent, and SCED% is the percent of compression or stretching of the nominal schedule (from 75 to 160 percent). The duration depends on the effort and not directly on the number of people. For us: 17 months in the base case, 19 in the scenario.

**Що показати / What to show:** табл. 5 (с. 6), табл. 6 (с. 7) / Table 5 (p. 6), Table 6 (p. 7).

---

## 10. Що таке аналіз чутливості програмного проекту?

## 10. What is the analysis of the sensitivity of a software project?

**Українською.** Це дослідження того, як оцінки проєкту (витрати, вартість, тривалість) змінюються, коли змінюються умови розробки: зарплата, обсяг пам'яті, процесор, бюджет. Воно показує, які зміни небезпечні і чи не з'їдять збитки в одному місці економію в іншому.

**In English.** It is the study of how the estimates of the project (effort, cost, duration) change when the conditions of development change: the salary, the memory, the processor, the budget. It shows which changes are dangerous and whether a loss in one place eats the saving in another.

**Що показати / What to show:** с. 7-8 / pp. 7-8.

---

## 11. Як застосувати модель COCOMO II до аналізу чутливості?

## 11. How to apply the model COCOMO II to the analysis of sensitivity?

**Українською.** Спочатку робимо базову оцінку. Потім описуємо сценарій через чинники, яких він торкається (у нас нижчий досвід і примітивні засоби), беремо нові рейтинги й множники та рахуємо оцінку заново. Різницю нових і базових витрат, вартості й тривалості порівнюємо з вигодою сценарію (економія на процесорі, зарплаті чи пам'яті) і ухвалюємо рішення. У нас витрати зросли на 41,6 %, економія в десятки і сотні разів менша, тож сценарій невигідний.

**In English.** First we make the base estimate. Then we describe the scenario through the drivers that it changes (for us, lower experience and primitive tools), take the new ratings and multipliers, and calculate the estimate again. The difference between the new and the base effort, cost and duration is compared with the gain of the scenario (the saving on a processor, a salary or memory), and the decision is made. For us the effort grew by 41.6 percent, the saving is tens to hundreds of times smaller, so the scenario is not worth it.

**Що показати / What to show:** розд. 5, табл. 6 (с. 7) / Section 5, Table 6 (p. 7).
