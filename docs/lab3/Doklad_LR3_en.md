# Presentation for Laboratory Work 3 (Bookshelf), English version

A spoken text for 5-6 minutes, in simple English (about B1 level). It follows the order of the pages in the report `Zvit_LR3_Bookshelf.docx` (28 pages): open the report, scroll down and read. The technical design is Appendix A of the report (pages 7-28), so you do not need a separate file.

## How to use it

- Open one file: `docs/lab3/Zvit_LR3_Bookshelf.docx`.
- Before each part of the text you see **▶ p. N**. This is the page to go to. Then just scroll down.
- The page numbers come from exporting this file to PDF with Word. The number in the footer is the same as the page (the title page is page 1). In Google Docs, Pages or on another computer the pages can move a little. Then use the name of the figure or the table, it is written next to the page number.
- Figures and tables of the technical design have the letter A: Figure A.1, Table A.5 and so on.
- The full version (213 pages, 86 tables) is in `docs/lab3/extended/`. You do not need to show it. It is only for the case when the teacher asks for details, for example the full list of input data or the seven algorithms that are not in this version.

## Route: page by page

| Page | What is on it | What I say |
|---|---|---|
| 1 | Title page | The topic, what Bookshelf is |
| 2 | Objective, tasks, theory | The aim of the work |
| 3-4 | "Performing the laboratory work", conclusion | What we did, the main results |
| 5-6 | Answers to the 7 control questions | Only scroll past |
| 7 | Appendix A, title sheet | Moving to the technical design |
| 8 | Introduction, Table A.1 | Key decisions |
| 9-10 | Tables A.2, A.3, A.4 | Coding, input and output data |
| 11-12 | Section 4, Tables A.5 and A.6 | Refined algorithms |
| 13 | Section 5, Figure A.1 | Structural scheme |
| 14-15 | Section 6, Table A.8, Figure A.2 | Functional scheme |
| 16 | Section 7, Figure A.3 | Symbols of Constantine charts |
| 17 | Figure A.4, Table A.9 | Chart: progress update |
| 18 | Figure A.5 | Chart: EPUB import |
| 19-20 | Tables A.10, A.11 | Coupling and cohesion |
| 21-23 | Section 8, Figures A.6, A.7, A.8 | Jackson charts |
| 24-26 | Section 9, Figures A.9, A.10, A.11 | Interface and prototype |
| 26-28 | Sections 10 and 11, Tables A.12, A.13, A.14 | Software, reliability, differences |

---

## The presentation with cues

### Part 1. Introduction (pages 1-7)

**▶ p. 1: the title page.**

Good afternoon. This is laboratory work number three: the technical design of our application Bookshelf. Let me remind you what it is. Bookshelf is a phone application where a reader keeps a library, follows reading progress, saves quotes, reads their own EPUB books and shares quotes and reviews with other readers.

**▶ p. 2: "Objective" and "Theoretical background".**

The aim of the work is to learn how to make a technical design with the structured approach. This means that we refine algorithms step by step, build a structural scheme and a functional scheme, and show the structure of the program with Constantine charts and Jackson charts.

**▶ pp. 3-4: "Performing the laboratory work" and "Conclusion".**

Our work is special because the application is already written and it works. So we did not invent the technical design. We described the real code. The names of modules, routes, tables and error codes are taken from the repository. This gave us one more result: we found several places where the code is different from the technical specification and the preliminary design. For example, the technical specification has password recovery, but the code does not. All these differences are in one table at the end of the report.

**▶ pp. 5-6: the answers to the control questions. Just scroll past, do not read.**

*(The seven answers to the control questions are here, on pages 5-6. If the teacher asks, you can read them.)*

### Part 2. The technical design, Appendix A (pages 7-28)

**▶ p. 7: "Appendix A. Technical design", the title sheet.**

Now the technical design itself, Appendix A.

**▶ p. 8: "Introduction" and Table A.1 "Main decisions of the technical design".**

First, the main decisions. The server part has layers: a route, a check of the request, a controller, an action or a service, an access policy and a model. Each layer does one job. One use of the system, for example a progress update, is one class. Both a controller and another action can call it. Access to other people's data is checked by policies, and a foreign resource gets the answer "not found". Business errors are stable codes, and the client makes the text for the screen.

**▶ pp. 9-10: Section 3, Tables A.2, A.3, A.4. Name them and scroll on, do not read.**

Next are coding, input data and output data: the statuses of a book, the format of a reader's name, the error codes, and the fields and limits of every request. All of this is taken from the validation rules in the code.

**▶ p. 11: Section 4 "Refined algorithms", Table A.5 and the pseudocode.**

Now the refined algorithms. We use the method of stepwise refinement. Level zero is the task in one sentence. Level one is the steps, with the module for each step. Level two is the pseudocode. The first algorithm is the progress update. It is one transaction that locks the row of the book. So two fast taps on "plus ten" do not mix: the second one starts where the first one finished. The built-in reader uses the same module and the same lock.

**▶ p. 12: Table A.6 and the pseudocode of the EPUB import.**

The second algorithm is the EPUB import. The file comes from a stranger, so we check it before we read it: the size, the archive, the names of the entries, and DRM protection. We read the book chapter by chapter and save each chapter before we parse the next one. So the memory does not grow with the size of the book. On the whole server only one book is imported at a time, because we have only one gigabyte of memory. And the whole operation is one transaction: the book is saved completely, or it stays as it was.

**▶ p. 13: Section 5, Figure A.1 "Structure of the system".**

The structural scheme shows the parts of the program and how they work together. At the top is the client written in Vue. In the middle is the web server. Below it is the server part in Laravel with the layers I mentioned. At the bottom are the MySQL database and the external service Open Library. An arrow goes from the part that calls to the part that is called.

**▶ pp. 14-15: Section 6, Table A.8, then Figure A.2 "Functional scheme of the system". Show one line: the EPUB file, the API client, process 6.0, the database.**

The functional scheme uses the symbols of the standard GOST 19.701-90. It shows the data flows and the devices. On the left is the client: manual input, screens, local storage, and the quote card as a document. On the right are the eight processes of the preliminary design and the database with nine data stores. Next to the arrows we wrote which fields are passed.

**▶ p. 16: Section 7, Figure A.3 "Notation of the structure charts of Constantine".**

Now the Constantine charts. These are the symbols. A module is a rectangle. A subsystem has two lines on each side. A library has lines at the top and at the bottom. A data area is an ellipse. A diamond at the caller means a conditional call, and a circle means a call in a loop. The couplings are thin arrows. An empty circle is coupling by data or by stamp, and a filled circle is coupling by control.

**▶ p. 17: Figure A.4 and Table A.9 (7.1 "Update of reading progress"). Point to the diamond near `ChangeUserBookStatus::apply`.**

The first chart is the progress update. The controller calls the module that checks the request, the module that updates the progress, and the module that counts the streak of days. The module that changes the status is called only on a condition: when the page changed and the book is not yet in the status "reading". For each module the table gives a specification: input, output and errors.

**▶ p. 18: Figure A.5 (7.2 "Import of an EPUB book"). Point to the circle with a bar near `EpubBook::chapters`.**

The second chart is the import. Here we have a call in a loop, and it is a generator. It gives one chapter at a time. So the service saves a chapter before it parses the next one.

**▶ pp. 19-20: Table A.11 "Coupling and cohesion of the main modules" and the three conclusions under it.**

This is the evaluation of the design quality. We honestly assessed the coupling and the cohesion of the modules. No module has coincidental or temporal cohesion. Most of them are functional or sequential. There are three weak places: the statistics class has informational cohesion, the method that changes the status has logical cohesion, and the class that cleans HTML is very large. The coupling is mostly by stamp, because models are passed between the layers. We do not need to change the design.

**▶ pp. 21-23: Section 8, Figure A.6 (symbols), Figure A.7 (import, 8.1), Figure A.8 (streak of days, 8.2).**

Now the Jackson charts. The structure of the program comes from the structure of its data. There are four constructions: an operation, a sequence, a selection (marked with a circle) and an iteration (marked with an asterisk). The first program is the reading of the chapters of a book: an iteration over the documents, and in each one a choice between skipping the document and handing over a chapter. The second program counts the streak of reading days. It has two iterations: one over the days, and one going back from today. Red blocks stop the program with an error.

**▶ pp. 24-26: Section 9, Figure A.9 (the map of screens), Figures A.10 and A.11 (screenshots of the real application).**

The interface. It is a phone application with a bottom bar of five tabs: Reading, Library, Quotes, Statistics and Community. The prototype is the working application itself. The screenshots in Figures A.10 and A.11 come from it: the reading screen, the selection of a quote, the contents of a book, the quotes, the quote card, and the statistics with the goal.

**▶ pp. 26-28: Sections 10 and 11, Table A.13 "Main risks…" and Table A.14 "Differences…".**

At the end we speak about the technical means and the reliability. The system works on one virtual machine in Azure with two processors and one gigabyte of memory. The biggest risk is the backup of the database: the copies are on the same disk, and the copy outside the server is made by hand. For each risk we give an action. We have 1053 automatic tests on the server and 1181 on the client, and all of them passed. And in Table A.14 you can see the differences between the code and the specification that I mentioned at the beginning. Thank you for your attention. We are ready for your questions.

---

## If you want it shorter

Scroll past pages 8-10 (decisions, coding, data) and pages 26-28 (software, reliability, differences). Then the presentation takes about 4 minutes, and the teacher still gets the main parts: algorithms, schemes and charts.

## If they ask: why do we need a technical design?

Before we write code, we need a description of the program that the whole team can use: which modules exist, how they call each other, and what data they take and give. The structure charts help us to check the quality of the split into modules before the implementation. The specifications of the interfaces help us to avoid the most expensive mistakes, which are usually found at the integration testing.

## Other questions you may get

| Question | Short answer | What to show |
|---|---|---|
| How is a Constantine chart different from a Jackson chart? | A Constantine chart shows the modules, the calls and the data between them. A Jackson chart shows the structure of one program, taken from the structure of its data, and it has only four constructions. | Figure A.5 (p. 18) and Figure A.7 (p. 22) |
| What are coupling by data, by stamp and by control? | By data: we pass simple parameters. By stamp: we pass a composite object, for example a model. By control: we pass a parameter that switches the logic of the other module. | Figure A.3 (p. 16), Table A.11 (pp. 19-20) |
| What are the levels of cohesion? | From the best: functional, sequential, informational, procedural, temporal, logical, coincidental. | Table A.11 (pp. 19-20) |
| What is stepwise refinement? | We state the task in one sentence, split it into steps, and refine each step until it is an operator or a ready module. This is why the algorithms are structured. | Tables A.5, A.6 (pp. 11-12) |
| Why did you refine only two algorithms? | They show all the structures: a transaction with a lock, a loop, conditional calls and limits. The other seven algorithms are in the full version. | `extended/`, Section 7 |
| What is a generator in the import? | A method that gives the chapters one by one. So a chapter is saved before the next one is parsed, and the memory does not grow with the size of the book. | Figure A.5 (p. 18), Figure A.7 (p. 22) |
| Why do you import only one book at a time? | Parsing an untrusted file can use hundreds of megabytes, and the server has only one gigabyte. So we use a global lock, and a request that waits too long gets the answer "busy". | Table A.6 (p. 12) |
| How do you protect the system from a harmful EPUB? | We check the archive and the limits. We read XML without entities and without network. We do not clean the HTML: we build a new tree from allowed elements without any attributes. | Table A.6 (p. 12) |
| What is the prototype in your work? | It is the working application itself. The screens in the screenshots are taken from it, not drawn. | Figures A.10, A.11 (pp. 25-26) |
| Why is password recovery in the specification but not in the code? | It was not implemented. In Fortify only registration is switched on, and a reset link needs a mail service. We wrote it down as a difference and as future work. | Table A.14 (p. 28) |
| What is the biggest risk? | The database backups. They are on the same disk, and a tested restore is not described. We recommend copying them outside the server and testing a restore once. | Table A.13 (p. 27) |
| How do you know that the design matches the code? | We checked the names, routes, limits and error codes against the repository. The automatic tests are green: 1053 on the server and 1181 on the client. | p. 4, p. 27 |
| What were the control questions? | Seven questions: structural and functional schemes, stepwise refinement, Constantine charts, Jackson charts, the methods of Jackson and Warnier-Orr, and where structured methods are used. | pp. 5-6 |
