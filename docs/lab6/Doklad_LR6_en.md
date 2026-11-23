# Presentation for Laboratory Work 6 (Bookshelf), English version

A spoken text for 5-6 minutes, in simple English (about B1 level). Variant 19: the Early Design model of COCOMO II, the scenario "use of a new microprocessor". The work is about calculations, so there is no demo of the program: you show the calculations in the report and explain how every number is connected with the scenario.

The presentation follows the order of the pages of the report `docs/lab6/Zvit_LR6_Bookshelf.docx` (12 pages). The page numbers come from exporting the file to PDF with Word. In Google Docs or Pages the pages can move a little. Then use the name of the section or the table.

## Route: page by page

| Page | What is on it | What I say |
|---|---|---|
| 1 | Title page | The topic, variant 19 |
| 2-3 | Objective, tasks, theory, Table 1 | The equation of the Early Design model, the factors |
| 4 | "The software project and the scenario" | How the scenario was translated to our project |
| 5 | "1. The size of the product", "2. The scale factors", Table 2 | The size and the exponent B |
| 5-6 | "3. The effort multipliers", Tables 3-4 | The effort multipliers |
| 6 | "4. The base estimate", Table 5 | The base estimate |
| 7 | "5. The scenario ...", Table 6 | The scenario and the comparison with the saving |
| 8 | "6. The sensitivity to the other parameters", Tables 7-8 | Sensitivity to other parameters |
| 9 | Conclusion | The summary |
| 9-12 | Answers to the 11 control questions | Only scroll past |
| 12 | Appendix A, Table 9 | The ratings of the drivers with reasons |

---

## Part 1. The presentation

**▶ p. 1: the title page.**

Good afternoon. This is laboratory work number six: the analysis of the sensitivity of a software project with the model COCOMO II. Our project is Bookshelf, a phone application for a library, reading progress and quotes. Our variant is number nineteen: the Early Design model and the scenario "use of a new microprocessor".

**▶ pp. 2-3: "Objective", "Theoretical background", Table 1.**

The aim is to use COCOMO II to see how the estimate of a project changes when the conditions change. The main equation of the Early Design model says: the effort in person-months is 2.5 times the size in thousands of lines to the power B, times seven effort multipliers. The exponent B depends on five scale factors, in Table 1. The multipliers describe the product, the process and the personnel. The duration is calculated from the effort. The analysis of sensitivity is simple: we make a base estimate, change the factors that the scenario touches, and look at the difference.

**▶ p. 4: "The software project and the scenario".**

The scenario in the methodology is this: the customer offers a new and cheaper microprocessor. The experience of the team with its language and tools falls from nominal to very low, and the tools for it are primitive, so the tools fall from high to very low. We do not have a microprocessor of our own, so we translated the scenario to our conditions: the team is offered a cheaper server with another processor architecture, for example ARM instead of x86. The container images and the build would have to be made again, there is no experience, and we assume the tools are primitive, as in the methodology. The saving is the difference in the price of the server. The whole server budget is a student credit of about one hundred dollars for a year, so it is the biggest saving that the scenario can give.

**▶ p. 5: "1. The size of the product", "2. The scale factors", Table 2.**

We take the size measured in laboratory work 5: 22.1 thousand lines. We estimated the five scale factors with reasons in Table 2: precedentedness is nominal, flexibility is high, risk is nominal, team cohesion is high, and process maturity is nominal. The sum is 13, so B is 1.14.

**▶ pp. 5-6: "3. The effort multipliers", Tables 3-4.**

Now the effort multipliers. The seven drivers of the Early Design model are made from the drivers of the post-architecture model: the ratings of the parts are summed, and the sum is put on a scale. For example, personnel experience is made from the experience with the application, the platform and the language. All 15 ratings with reasons are in Appendix A. Three drivers are different from nominal: the reliability and complexity of the product is high, because we have more documentation than needed. The platform difficulty is high, because the whole application must fit into 1 GB of memory. The facilities are high, because of the containers, the pipeline, the linters and the tests. The product of the multipliers is 1.49.

**▶ p. 6: "4. The base estimate", Table 5.**

The base estimate is 127 person-months, 127 thousand dollars at the notional rate of 1,000 dollars per person-month (a junior developer in Ukraine, the DOU survey), 17 months, and about 8 people on average. We say honestly: it is 26 times more than our plan of 4.9 person-months. The model is calibrated on industrial projects with ordinary productivity, and Bookshelf was made by three people with generators and an assistant. So the absolute numbers are not a forecast of the real expenses. But the analysis of sensitivity is relative: the percentages of change do not depend on the size.

**▶ p. 7: "5. The scenario ...", Table 6.**

Now the scenario. The experience with the language and the tools of the new processor falls to very low, and the tools fall to very low too. The sum of the experience falls from 9 to 7, so personnel experience goes from nominal to low. The sum of the tools falls from 8 to 5, so facilities go from high to low. The product of the multipliers grows from 1.49 to 2.11. The effort grows by 41.6 percent, to 180 person-months. It is 53 thousand dollars more, and the duration is 2.2 months longer. The tools give a bigger part of the growth, 26 percent, than the experience, 12 percent. A check with the multipliers of the post-architecture model gives 76 percent: the direction is the same. Let us compare it with the saving. The scenario saves at most one hundred dollars, or one thousand in the example of the methodology, and the growth of the cost is 53 times bigger even than the larger sum and 529 times bigger than the real one. The cheaper processor does not pay for itself.

**▶ p. 8: "6. The sensitivity to the other parameters", Tables 7-8.**

At last, we compare this scenario with other changes. We moved every driver by one level. The strongest are the reliability and complexity of the product and the platform difficulty, about 40 percent for one level. Then the capability of personnel, 17 to 26 percent, and the facilities and the experience, 12 to 16. The size acts almost in proportion: plus ten percent of size gives plus eleven percent of effort. One scale factor changes the effort by about three percent. Our scenario, 42 percent, is as strong as the worsening of the product or the platform by one level.

**▶ p. 9: the conclusion.**

In short. The base estimate is 127 person-months. The scenario of the new microprocessor makes it 42 percent bigger, and the saving is tens of times (the larger sum) or hundreds of times (the real one) smaller than the extra cost, so the scenario is not worth it. The strongest influence on the effort comes from the reliability, the complexity of the product, and the difficulty of the platform. Thank you. We are ready to answer your questions.

---

## If you want it shorter

Skip p. 8 (sensitivity to other parameters) and the story about the multipliers (pp. 5-6), and say only the base estimate and the scenario. The presentation takes 3-4 minutes.

## If they ask: what is sensitivity

It is how much the estimate (effort, cost, duration) changes if the conditions of the project change. We look at the difference between the base estimate and the estimate with the new values of the factors.

## Questions you may get

| Question | Short answer | What to show |
|---|---|---|
| Why this model and this scenario? | It is variant 19 in Table 6.9 of the methodology: the Early Design model and the use of a new microprocessor. | p. 2 |
| You have no microprocessor. What did you change? | We translated the scenario to the server: a cheaper server with another processor architecture. The team has no experience with it, and we assume the tools are primitive, as in the methodology. | p. 4 |
| Where does the size 22.1 thousand lines come from? | It was measured in the repository without tests in laboratory work 5. The estimate by function points gives 17 thousand, and it is given for comparison. | p. 5, Table 5 |
| Where does B = 1.14 come from? | B = 1.01 + 0.01 times the sum of the five scale factors; the sum is 13. | Table 2, p. 5 |
| Where do the values of the multipliers come from? | From the table of the Early Design multipliers by level; the level comes from the sum of the ratings of the parts. The table is in the report. | Tables 3-4, p. 6 |
| Why 127 person-months, but 4.9 in reality? | The model is calibrated on industrial projects. We worked as three with generators and an assistant, and our effort is a plan. The relative percentages matter. | p. 6 |
| Why 41.6 percent growth? | The experience (language and tools) falls to very low, and the tools too; two multipliers grow together: 1.12 and 1.10 instead of 1.00 and 0.87. | Table 6, p. 7 |
| Is the scenario worth it? | No: the saving is at most one hundred (or one thousand) dollars, the extra cost is 53 thousand at the notional rate, that is 53 and 529 times more. | p. 7 |
| What would influence more? | Worsening the reliability and complexity of the product or the difficulty of the platform by one level: about 40 percent. | Table 7, p. 8 |
| How is the post-architecture model different? | It has 17 drivers instead of 7 and a factor for changes of requirements. We checked the scenario with its multipliers: +76 percent, the same trend. | p. 7, answers |
| Can we trust the money? | No, the rate of 1,000 dollars per person-month is notional (the median of a junior developer in Ukraine from DOU; the methodology has 15 thousand, the level of the USA). The point is to compare the base case with the scenario. | p. 7 |

---

## Control questions

In short: 11 answers on pages 9-12 of the report. The full answers in two languages are in `docs/lab6/Pytannia_LR6.md`.
