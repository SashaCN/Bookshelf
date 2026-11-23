# Presentation for Laboratory Work 4 (Bookshelf), English version

A spoken text for 5-6 minutes, in simple English (about B1 level), plus a live demo of the program. The methodology says that at the defence you must show the results on the screen, show your skills and answer questions. So this file has three parts: the presentation by the report, the demo script and the answers to questions.

The presentation follows the order of the pages of the report `docs/lab4/Zvit_LR4_Bookshelf.docx` (38 pages). The page numbers come from exporting the file to PDF with Word. In Google Docs or Pages the pages can move a little. Then use the name of the section or the table.

## Route: page by page

| Page | What is on it | What I say |
|---|---|---|
| 1 | Title page | The topic, what Bookshelf is |
| 2 | Objective, tasks, theory | The aim, the documentation by GOST 19.101-77 |
| 3-4 | Table 1, "Performing", Tables 2 and 3 | What we did, the size of the program, the documents |
| 5-6 | "Results of the program", Tables 4-8 | Results of the checks and of the program |
| 6-14 | "Testing with third-party tools", Tables 9-11, Figures 1-13 | Checks by outside tools (SonarQube and others) |
| 14-15 | Conclusion | Short |
| 15-17 | Answers to the 5 control questions | Only scroll past |
| 18-23 | Appendix A, the listing (A.1-A.6) | The main modules |
| 23-25 | Appendix B, Figures 14-18 | The interface |
| 26-29 | Appendix C, the user guide | Show the structure |
| 30-32 | Appendix D, the system programmer's guide | Installation |
| 33-35 | Appendix E, the programmer's guide | How to change the program |
| 36-38 | Appendix F, the operator's guide | Moderation |

---

## Part 1. The presentation

**▶ p. 1: the title page.**

Good afternoon. This is laboratory work number four: the implementation of a software product. Our product is Bookshelf. It is a phone application where a reader keeps a library, follows reading progress, saves quotes, reads their own EPUB books and shares quotes with other readers.

**▶ p. 2: "Objective" and "Theoretical background", Table 1 at the top of p. 3.**

The aim of the work is to develop a program for the individual task, to debug it and to prepare the documentation. The documentation by GOST 19.101-77 has two kinds: for the developers and for the users. The first kind has the program text, the description of the program, and the guides of the programmer and of the system programmer. The second kind has the description of application and the guides of the user and of the operator.

**▶ pp. 3-4: "Performing the laboratory work", Table 2 "Size of the program" and Table 3 "Documents of the program".**

The program was already written in laboratory works 1-3, in seven stages, from the library to the reader. We chose the languages like this: the client is in TypeScript, and the server part is in PHP 8.4 with Laravel, because the technical specification requires it. Table 2 shows the size: 430 files and more than 45 thousand lines, and more than 23 thousand of them are automatic tests. Table 3 shows where each document of GOST is: the program text is in Appendix A, the user guide in C, the system programmer's guide in D, the programmer's guide in E, and the operator's guide in F.

**▶ pp. 5-6: "Results of the program", Table 4, then Tables 5-8.**

Now the results. Table 4 shows a full run of all the checks of the project. Pint and Larastan have no errors. The server tests: 1053, and all of them passed. The client tests: 1181, and all of them passed. ESLint and the type check are clean, and the client builds without errors. Then there are real answers of the running program. Table 5 is the answer to a progress update: the book, how many pages were added, and the streak of reading days. Table 6 is a refusal, when the reader gives a page that the book does not have: the program answers with an error code. Tables 7 and 8 show the work of the moderator: after three reports a post is hidden automatically, and the moderator looks at the list of reports and restores the post with one command.

**▶ pp. 6-14: "Testing with third-party tools", Tables 9-11, Figures 1-13.**

Besides our own tests, the program was checked by outside tools. The main one is SonarQube, a platform that analyses the quality and the security of code. A member of our team ran it on a local computer. The result is in Figure 1: the quality gate is passed, security, reliability and maintainability have the rating A with no open issues, the coverage by tests is 96.2 percent, and there are no duplications. Table 9 collects these measures: 5,425 lines of code, the server part with 98.9 percent coverage, and the client with 95.2 percent. In Figures 3 to 8 you can see the list of issues, the code by parts, the coverage, and the size and the complexity by files. The lowest coverage is in the entry points and in thin wrappers of requests, which the tests replace with fakes, so this is expected. The first analysis found one vulnerability and two remarks (Table 10). The vulnerability was a weak hash, sha1, in a cache key, and we changed it to sha256. After the fixes and the new tests the coverage grew from 91.8 to 96.2 percent. Table 11 and Figures 9 to 13 show other tools: Larastan with no errors, Pint, 281 Pest tests with 99.2 percent coverage, and the coverage of the client by Vitest, 96.44 percent of lines. We must be honest about the limits: these checks were made on the core of the program, stages 1-5, without the community and the reader. For the whole program, Table 4 applies.

**▶ pp. 15-17: the answers to the control questions. Just scroll past.**

*(The five answers to the control questions are on pages 15-17. You can read them if you need.)*

**▶ pp. 18-23: Appendix A "Program listing" (A.1-A.6). Show A.1 on p. 18 and A.4 on p. 20.**

Appendix A has the text of the main modules with line numbers, the same modules as in the structure charts of the previous work. For example, the progress update on page 18 is one transaction that locks the row of the book, so two fast taps do not mix. And on page 20 there is the generator of EPUB chapters: it gives the chapters one by one, so the memory does not grow with the size of the book. All the comments in the code are in English, as the rules of the project require.

**▶ pp. 23-25: Appendix B "User interface", Figures 14-18.**

The interface. It is made for a phone: a bottom bar with five tabs, and one task on a screen. The screenshots in Figures 14-18 are taken from the running program: the sign-in, the books being read, the library, the page of a book, the quotes, the quote card, the statistics, the community, and the reading screen with a selected quote.

**▶ pp. 26-38: Appendices C, D, E, F. Scroll slowly and name them.**

And four guides. The user guide (pages 26-29): how to register, add a book, update progress, save a quote, see the statistics, read an EPUB book and publish a post. The system programmer's guide (pages 30-32): how to start the program with the command `make up` and how to install it on a server, the settings and the typical problems. The programmer's guide (pages 33-35): the layout of the repository, the way of a request through the layers, and how to add a new function and check it. The operator's guide (pages 36-38): the moderation commands and the checking of the system. Thank you for your attention. We are ready to show the program and answer your questions.

---

## Part 2. The demo of the program on the screen

Preparation (before the defence, once): in the terminal in the root of the repository run `make up`. Open `http://localhost:8000` in the browser, with the window size of a phone (DevTools, device mode, 390 by 844). Do not create accounts on the production server. Show the program locally.

The script for 3-4 minutes:

1. **Registration.** Press "Зареєструватися" (Register), type a name, an email and a password of 8 characters or more.
2. **A book.** Library, "Додати книгу" (Add a book), find a book in the Open Library catalog, or press "Додати вручну" (Add by hand) with the number of pages. Set the status "Читаю" (Reading).
3. **Progress.** On the screen "Читаю" press "+10", then "Я на сторінці…" and type a number. Show the answer with the streak. Try a page that is bigger than the number of pages: the program says that there is no such page.
4. **A quote and a card.** "Цитати" (Quotes), "Додати" (Add), save a quote, press "Поділитися" (Share) and show the card.
5. **Statistics.** The tab "Статистика", set a goal for the year and show the streak, the pace and the forecast.
6. **The reader.** On the page of a book press "Завантажити EPUB" (prepare a small file), "Читати", select some text and press "Зберегти цитату". Show that the saved quote is highlighted.
7. **Community.** "Спільнота", "Написати", publish a quote. From a second account (another browser window, `127.0.0.1:8000`) report the post. Three reports from different readers hide the post automatically.
8. **The operator.** In the terminal: `docker compose exec app php artisan moderation:reports`, then `moderation:restore post <id>`.

If needed, show the tests: `make test` (about one minute) and `make lint`.

---

## If you want it shorter

Scroll past pages 18-23 (the listing), the figures of SonarQube (say only about Figure 1 and Table 9) and the story about the guides (pages 26-38). The presentation takes 3-4 minutes, and the demo covers the rest.

## If they ask: why do we need documentation?

So that people who did not write the program can use it and support it: a user sees how to work with it, a system programmer how to install it, a programmer how to change it, an operator how to moderate. Without it the product depends on the memory of its authors.

## Questions you may get

| Question | Short answer | What to show |
|---|---|---|
| Why these languages? | The technical specification requires PHP with Laravel. TypeScript and Vue give types and fast development for the client, and TypeScript is a superset of JavaScript from the methodology. | p. 3 |
| How much code is in the program? | 430 files and more than 45 thousand lines, and more than half of them are tests. | Table 2, p. 4 |
| How did you debug it? | With automatic tests (1053 and 1181), static checks (Pint, Larastan, ESLint, vue-tsc) and by working with the running program. | Table 4, p. 5 |
| Where do the results of the program come from? | They are real answers: we created four demo readers through the API, saved the outputs and deleted the readers. | Tables 5-8, pp. 5-6 |
| Which documents of GOST did you make? | The program text, the description, the description of application, and the guides of the programmer, of the system programmer, of the operator and of the user. We did not make a form or a maintenance manual, because the program has no hardware. | Table 3, p. 4 |
| How do you install the program? | For development `make up`. For a server the script `bootstrap-server.sh` and the deployment with GitHub Actions. | Appendix D, pp. 30-31 |
| How do you add a new function? | A migration, a form request, an action class, a thin controller and a route, tests, and then the client. | Appendix E, pp. 33-34 |
| How does moderation work? | Three reports from different readers hide a post automatically, and the operator looks at the list with a command and decides. | Tables 7-8 (p. 6), Appendix F |
| What is the weak place? | The database backups are on the same disk, and copying them outside the server is done by hand. | Appendix D, p. 32 |
| What is SonarQube and what does it show? | A platform for static analysis of code. It shows if the quality gate is passed, the ratings of security, reliability and maintainability (A to E), the number of issues, the coverage by tests and the duplications. For us: A, A, A, 0 issues, coverage 96.2 percent. | Figure 1, Table 9, pp. 7-8 |
| Did SonarQube check the whole program? | No. The screenshots are from the core of the program (stages 1-5, 5,425 lines). The community and the reader were added later, and for them we have only the checks of Table 4. | p. 6 |
| Why 281 tests in Pest, but 1053 in Table 4? | These are different versions: 281 tests on the core of the program during the analysis, 1053 on the whole program with the community and the reader. | Table 4 (p. 5), Table 11 (p. 13) |
| Why is the coverage of the client lower? | The lowest coverage is in the entry points (`App.vue`, `main.ts`) and in thin wrappers of requests, which the tests replace with fakes. There is almost no logic of their own. | Figure 5, p. 10 |
| What did SonarQube find and what was fixed? | One vulnerability (sha1 hash in a cache key, changed to sha256) and two remarks (the accessibility of the quote card and one false positive). The coverage grew from 91.8 to 96.2 percent. | Table 10, p. 12 |
| Is there password recovery? | No. In Fortify only registration is switched on. | laboratory work 3, the differences |

---

## Control questions

In short: 5 answers on pages 15-17 of the report. The full answers in two languages are in `docs/lab4/Pytannia_LR4.md`.
