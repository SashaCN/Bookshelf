# Presentation for Laboratory Work 7 (Bookshelf), English version

A spoken text for 5-6 minutes, in simple English (about B1 level). Variant 19: the suite of Chidamber and Kemerer (WMC, DIT, NOC, CBO, RFC, LCOM). The work is about calculations, so there is no demo of the application: you show the tables in the report and explain where the numbers come from and what they mean for the code.

The presentation follows the order of the sections of the report `docs/lab7/Zvit_LR7_Bookshelf.docx` (about 17 pages). The report was not exported to PDF, so the page numbers are only estimates; it is safer to use the name of the section and the number of the table or figure (these are exact). The numbers in the report are made by the script `docs/lab7/measure.php`; the result is in `docs/lab7/results.json`.

## Route through the report

| Approximate page | Section | What I say |
|---|---|---|
| 1 | Title page | The topic, variant 19 |
| 2-3 | Objective, Tasks, Theoretical background, Tables 1-2 | The six metrics and how to read them |
| 3-4 | The software project, Table 3 | What we measured: 145 classes of backend/app |
| 5-6 | The measurement method, Table 4 | The script, the counting rules, the check by hand on PostPolicy |
| 7-10 | Results, Tables 5-8, Figures 1-3 | The summary, the groups of classes, the worst classes, inheritance |
| 11-12 | Analysis of the results | Each metric in turn |
| 13-14 | Conclusions and recommendations, Table 9 | The conclusion and what to improve |
| 14-16 | Answers to the control questions | Only turn the pages |
| 16-18 | Appendix A, Table 10 | Every class with its values |

---

## Part 1. The presentation

**Title page.**

Good afternoon. Laboratory work number seven is about metrics of object-oriented systems. Our project is Bookshelf, an application for a library and for the progress of reading. Our variant is nineteen: the suite of Chidamber and Kemerer, six metrics.

**Theoretical background, Tables 1-2.**

There are six metrics. WMC is the number of methods of a class, with a weight for the complexity. DIT is the depth of a class in the inheritance tree. NOC is the number of direct children. CBO is the number of other classes that the class is connected to. RFC is its own methods plus the methods of other classes that it calls. LCOM shows how much the methods of a class are not connected through common properties. There are no official limits, so in Table 2 I use common rules of thumb, for example WMC up to twenty and CBO up to nine. They only tell us where to look, they are not a verdict.

**The software project, Table 3.**

We measure the server part: the folder backend/app, written in Laravel. It has one hundred and fifty files: one hundred and thirty-seven classes, eight enumerations and five traits. The traits are not counted in the statistics, because they are not classes. So one hundred and forty-five classes were measured, about seven and a half thousand lines. We did not measure the migrations, the tests and the code of the libraries. The client in Vue and TypeScript is outside this work too: a Vue component is not a class, and TypeScript code is mostly functions.

**The measurement method, Table 4.**

I did not count by hand. The script measure.php reads every file with the parser PHP-Parser, builds the syntax tree and counts the six metrics. The rules are in Table 4. For WMC I count the methods declared in the class itself, with the weight one; inherited methods are not counted. There is also a cyclomatic variant, WMC_cc. For DIT I follow the chain of parents, including the framework classes; DIT_app counts only the project. I checked the script by hand on the class PostPolicy: five methods, three coupled classes, nine for RFC, and LCOM of ten. The script gives the same numbers.

**Results, Tables 5-8, Figures 1-3.**

The summary is this. The classes are small: the mean WMC is three, the median is two, and one hundred and twenty-nine of one hundred and forty-five classes have five methods or fewer. The mean CBO is almost five, and less than three if we count only the classes of the project. The mean RFC is ten. Figure 1 shows a long tail on the right: a few classes are much bigger than the typical one.

Almost all the inheritance comes from the framework. The project has only one hierarchy of its own: the abstract Controller with twenty-one children. The biggest DIT is three, for the requests, through FormRequest and two Request classes. Inside the project the maximum is one.

**Analysis of the results.**

For WMC only HtmlSanitizer is above twenty: it has twenty-three methods and a cyclomatic complexity of one hundred and thirty-one. The reading of EPUB books and the import from Goodreads are more complex than the rest of the system. ExportUserData has sixteen methods, but they are simple.

For CBO twenty classes are above nine: controllers, models and providers. The biggest is QuoteController with nineteen. A controller by its nature meets requests, actions, models and resources. For RFC only ReadingStatistics is above fifty, with fifty-two.

LCOM must be read with care. Sixty-three of ninety-five classes with two or more methods have no properties at all: actions and controllers without state. So their LCOM is high, although the class is fine. LCOM4, the number of groups of methods, is more useful: eighty-nine classes have one group or none.

**Conclusions and recommendations, Table 9.**

The conclusion is that the design is healthy. The classes are small, the inheritance is shallow, the links are moderate, and this is a result of the chosen structure: one action for one operation, thin requests and resources. What to improve. First, split HtmlSanitizer by stages; its tests will protect the change. Second, move the export and the quote of the day out of QuoteController into separate controllers. Third, move the blocking logic out of the model User into a trait, as it is already done for VisibleToReaders. Fourth, cut the long methods of the Goodreads import and of the OpenLibrary client. And add limits of the metrics to the static analysis, so that growth is seen at once. Thank you, I am ready for questions.

---

## If you want it shorter

Skip Table 2 and the section about the rules (Table 4): say only that a script did the counting, and give the summary and the recommendations. It takes 3-4 minutes.

## If they ask: why is LCOM so big

The metric looks for common properties. A class without properties (an action, a controller, a policy) has all pairs of methods "unconnected". It is a feature of the Laravel style, where dependencies come in the parameters of the methods. That is why we also look at LCOM4.

## Questions you may get

| Question | Short answer | What to show |
|---|---|---|
| Why the metrics of Chidamber and Kemerer? | It is variant 19 in the methodology. | Table 1 |
| What exactly did you measure? | 145 classes of the server part backend/app: 137 classes and 8 enumerations; 5 traits are measured but not in the statistics. | Table 3 |
| Why not the client? | Vue components are not classes, and TypeScript is mostly functions; the metrics do not fit. | Section The software project |
| How did you count WMC? | The number of methods declared in the class, weight 1; inherited methods and trait methods are not counted. A second variant uses the cyclomatic weight. | Table 4 |
| Are the numbers right? | Checked by hand on PostPolicy: WMC 5, CBO 3, RFC 9, LCOM 10, LCOM4 2; the script gave the same. | Section The measurement method |
| Which class is the worst? | HtmlSanitizer: 23 methods, cyclomatic complexity 131, 566 lines. | Table 5, Table 7 |
| Why is DIT 3 for the requests? | It is the depth of Laravel: FormRequest, Request, Request of Symfony. The project has no inheritance of its own there. | Figure 3 |
| Why is NOC of Controller 21? | It is an empty base class with no behaviour; a change of it breaks nothing. | Table 8 |
| What would you change first? | Split HtmlSanitizer, move the export out of QuoteController. | Table 9 |
| Can we trust the limits? | No, they are rules of thumb from the literature and the sources differ. The metrics only say where to look. | Table 2 |
| What are the limits of the method? | It is static analysis: calls through variables of an unknown type are recognised by the name of the method, so RFC can differ a little from the real value. | The end of Conclusions |

---

## Control questions

In short: 19 answers at the end of the report (about pages 14-16). The full answers in two languages are in `docs/lab7/Pytannia_LR7.md`.
