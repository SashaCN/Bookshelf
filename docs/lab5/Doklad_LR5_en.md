# Presentation for Laboratory Work 5 (Bookshelf), English version

A spoken text for 5-6 minutes, in simple English (about B1 level). The work is about calculations, so there is no demo of the program: you show the calculations in the report and explain where the numbers come from.

The presentation follows the order of the pages of the report `docs/lab5/Zvit_LR5_Bookshelf.docx` (13 pages). The page numbers come from exporting the file to PDF with Word. In Google Docs or Pages the pages can move a little. Then use the name of the section or the table.

## Route: page by page

| Page | What is on it | What I say |
|---|---|---|
| 1 | Title page | The topic |
| 2-3 | Objective, tasks, theory, Table 1 | Time limits, the rule 40-20-40, LOC and FP |
| 3 | "The software project" | What the project is |
| 4 | "1. The limits of the time of the project", Table 2 | Early and late times, reserve, critical path |
| 5 | Table 3, start of "2. Measuring with the size-oriented metrics" | The rule 40-20-40 against our plan |
| 5-6 | Tables 4, 5 | Initial data and LOC metrics |
| 6-7 | Table 6 | Size and productivity by groups of functions |
| 7-8 | "3. Measuring with the function-oriented metrics", Tables 7-9 | Function points |
| 9 | Conclusion | The summary |
| 10-11 | Answers to the 6 control questions | Only scroll past |
| 11-13 | Appendix A, Tables 10-11 | The list of transactions and files |

---

## Part 1. The presentation

**▶ p. 1: the title page.**

Good afternoon. This is laboratory work number five: the estimate of a project with size-oriented and function-oriented metrics. Our project is Bookshelf, a phone application where a reader keeps a library, follows reading progress, saves quotes, reads EPUB books and shares quotes.

**▶ pp. 2-3: "Objective", "Theoretical background", Table 1.**

The aim is to learn how to estimate the time limits of a project and to measure the product and the process of its development in two ways. The first way counts lines of code, LOC. The second way counts functionality: five characteristics, which are inputs, outputs, queries, internal files and external interface files. Each of them has a rank, low, average or high, and a weight from Table 1.

**▶ p. 4: "1. The limits of the time of the project", Table 2.**

First, the time limits. We took the eighteen works of the network schedule from laboratory work 2 and calculated them again with a forward and a backward pass. In Table 2 every work has an early start, a late start, an early finish, a late finish and a reserve. A work with a zero reserve is critical. The critical path is 57 working days, about eleven and a half weeks, the same as in laboratory work 2. The biggest reserve, 24 days, is for the first deployment. The sum of the durations of all the works is 103 person-days, that is 4.9 person-months. These are our planned effort.

**▶ p. 5: Table 3.**

The rule 40-20-40 says that forty percent of the effort goes to the analysis and the design. In our plan only 10 days go there, less than a tenth. The reason is simple: we made the technical design and the other design work later, in laboratory works 2 to 4, and they are not in the schedule. And we write tests together with the code, so the plan cannot separate coding from testing.

**▶ pp. 5-6: "2. Measuring with the size-oriented metrics", Tables 4-5.**

Now LOC. We counted the lines of the repository without the automated tests: 22,101 lines, that is 22.1 thousand. The effort is 4.9 person-months. The cost uses the rate of 1,000 dollars per person-month. It is the median of a junior developer in Ukraine from the DOU survey, while the methodology has 15 thousand, the level of the USA. It is a notional number, because nobody got a salary. The documents are 105 pages, and we recorded seven errors. From this the productivity is 4.5 thousand lines per person-month, a line costs 22 cents, there are 4.8 pages of documents per thousand lines, and 0.32 errors per thousand lines. We must be honest: the productivity is about ten times higher than in the examples of the methodology. It does not mean that we are ten times better. Our effort is a plan and not a time sheet, a large part of the code came from generators and an assistant, and the errors are only the recorded ones.

**▶ pp. 6-7: Table 6.**

Table 6 divides the size and the effort by groups of functions: the reader, the community, statistics, quotes, progress, the library, accounts, the PWA, and the shared code. The lowest productivity is in progress and the PWA, and the highest is in the shared code.

**▶ pp. 7-8: "3. Measuring with the function-oriented metrics", Tables 7-9.**

Now function points. We counted them from the code, not from memory: from the routes, the requests, the resources, the migrations and the screens. We got 56 transactions: 32 inputs, 8 outputs and 16 queries, and also 10 internal logical files and one external file, the Open Library catalog. The ranks from the tables of the methodology give 291 unadjusted points. The 14 system parameters add up to 45, so the adjustment is 0.65 plus 0.45, that is 1.10. The result is 320 function points, a productivity of 65 per person-month and 15 dollars per point.

**▶ pp. 8-9: the checks and the conclusion.**

We checked the result in two ways. First, 320 points times 53 statements for Java gives 16,965 lines, which is 77 percent of the measured size. PHP and TypeScript are not in the table of the methodology. For such a rough coefficient it is good. Second, the feature points with our 13 algorithms are 383, about 20 percent more. It is close to the 25-30 percent that the methodology expects for systems with complex algorithms. In short: the time limit is 57 days, the effort is 4.9 person-months, the size is 22.1 thousand lines, and the program has 320 function points. Thank you. We are ready to answer your questions.

---

## If you want it shorter

Skip Table 6 and the checks at the end of section 3 (pp. 8-9). The presentation takes 3-4 minutes.

## If they ask: why two different metrics

LOC is easy to count, but it depends on the language and the style. Function points measure what the program does for the user, so they can be estimated before the code. Two estimates together let us check one against the other.

## Questions you may get

| Question | Short answer | What to show |
|---|---|---|
| Where do the 103 person-days come from? | It is the sum of the durations of all eighteen works of the network schedule. It is a plan, not a time sheet: nobody recorded the hours. | p. 4 |
| Why 1,000 dollars per person-month? | The methodology has an average rate of 15 thousand, the level of the USA. We took the median of a junior developer in Ukraine from the DOU survey (about 900 to 930 dollars), rounded. Nobody was really paid, so the cost is notional. | p. 5 |
| Why is the productivity so high? | We plan the effort and do not measure it, a part of the code came from generators and an assistant, and the interface has long simple texts and types. | Table 5, p. 6 |
| Where do the seven errors come from? | Four commits of the type fix in git and three findings of the first SonarQube analysis. Errors that the tests found while writing the code were fixed at once and not recorded. | p. 5 |
| Why only 10 percent for design in the plan? | The schedule has only the specification and the preliminary design. The technical design and other design work were done later in laboratory works 2-4. | Table 3, p. 5 |
| How did you count the function points? | From the code: routes, requests, resources, migrations and screens. Every process is counted once, and operator commands are not counted. The whole list is in Appendix A. | p. 7, Appendix A |
| Why 53 statements per point for Java? | PHP and TypeScript are not in Table 5.13 of the methodology, so we took the nearest language. | p. 8 |
| What are feature points? | FP with one more characteristic, the number of algorithms (weight 3). It is for systems with complex algorithms. We have 13 algorithms. | pp. 8-9 |
| Do LOC and FP agree? | The estimate by FP is 77 percent of the measured size. The difference comes from the interface code, which counts in lines but not in points. | p. 8 |
| What is the weak place of the estimate? | The effort and the cost are a plan and a notional rate, so the metrics show the method and not the real expenses. | conclusion, p. 9 |

---

## Control questions

In short: 6 answers on pages 10-11 of the report. The full answers in two languages are in `docs/lab5/Pytannia_LR5.md`.
