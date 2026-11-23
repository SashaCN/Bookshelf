"""The report on laboratory work 6: the COCOMO II model of Bookshelf and the analysis of its sensitivity.
Variant 19: the Early Design model, the scenario “use of a new microprocessor”."""

from __future__ import annotations

import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
LEVEL_NAMES = {'XL': 'Extra low', 'VL': 'Very low', 'L': 'Low', 'N': 'Nominal', 'H': 'High', 'VH': 'Very high', 'XH': 'Extra high'}
DRIVER_NAMES = {'PERS': 'Personnel capability', 'RCPX': 'Product reliability and complexity', 'RUSE': 'Required reuse',
                'PDIF': 'Platform difficulty', 'PREX': 'Personnel experience', 'FCIL': 'Facilities', 'SCED': 'Schedule'}
PARTS = {'PERS': ['ACAP', 'PCAP', 'PCON'], 'RCPX': ['RELY', 'DATA', 'CPLX', 'DOCU'], 'PDIF': ['TIME', 'STOR', 'PVOL'],
         'PREX': ['AEXP', 'PEXP', 'LTEX'], 'FCIL': ['TOOL', 'SITE']}


def n(value: float, digits: int = 0) -> str:
    return f'{value:,.{digits}f}'


def pct(value: float, digits: int = 1) -> str:
    return f'{value:+.{digits}f}%'


def data() -> dict:
    return json.loads((HERE / 'results.json').read_text(encoding='utf-8'))


def write(d, ctx, r: dict) -> None:
    base = r['base']
    s0, s1, s2, s3 = r['scenarios']

    # ------------------------------------------------------------------ objective, tasks, theory
    d.h1('Objective', page_break=True)
    d.p('To apply the model COCOMO II to the analysis of the sensitivity of a software project to a change of its '
        'conditions.')

    d.h1('Tasks for the laboratory work')
    d.numbered([
        'Study the lecture material on the analysis of the sensitivity of software to a change of the requirements with the '
        'model COCOMO II.',
        'For the own project (the one with the technical specification) build the model COCOMO II and estimate the '
        'sensitivity to the scenario. The variant is 19: the Early Design model, the scenario “use of a new '
        'microprocessor”.',
        'Prepare the report and answer the control questions.',
    ])

    d.h1('Theoretical background')
    d.p('The model COCOMO II has three parts: the model of the composition of an application (it estimates the effort by '
        'object points: screens, reports and components in a third generation language), the model of the early stage of '
        'design (when the requirements are stable and the basic architecture is defined) and the model of the '
        'post-architecture stage (when the architecture exists and the product is developed). All of them need the size of '
        'the product, in lines of code, object points or function points.')
    d.p('The main equation of the Early Design model is `PM = A × SIZE^B × ΠEMi + PM_auto`. Here PM is the effort in '
        'person-months, A = 2.5, SIZE is the size in thousands of lines, the term with the generated code is zero when '
        'no code is generated, and EMi are the seven effort multipliers. The exponent `B = 1.01 + 0.01 × ΣWi` shows that the '
        'effort grows faster than the size; it depends on five scale factors Wi, each estimated from 5 (very low) to 0 '
        '(extra high). The seven drivers of the Early Design model are ' + ctx.ref('tab:drivers') + '. Every driver has six '
        'or seven levels, from extra low to extra high, and a multiplier for each level.')
    ctx.table(d, 'tab:drivers', 'Scale factors and effort multipliers of the Early Design model')
    d.table(
        ['Group', 'Name', 'Meaning'],
        [['Scale factors', 'PREC', 'Precedentedness: the earlier experience of the organization with such projects'],
         ['', 'FLEX', 'Flexibility of the development process'],
         ['', 'RESL', 'Architecture and risk resolution: the amount of the risk analysis'],
         ['', 'TEAM', 'Team cohesion: how well the developers work together'],
         ['', 'PMAT', 'Process maturity (the questions of the CMM)'],
         ['Effort multipliers', 'PERS', 'Personnel capability'],
         ['', 'RCPX', 'Product reliability and complexity'],
         ['', 'RUSE', 'Required reuse'],
         ['', 'PDIF', 'Platform difficulty'],
         ['', 'PREX', 'Personnel experience'],
         ['', 'FCIL', 'Facilities: the support tools and the multisite development'],
         ['', 'SCED', 'Required schedule']],
        [3.4, 2.0, 9.4], align=['left', 'left', 'left'],
    )
    d.p('The Early Design drivers are made from the drivers of the post-architecture model: the numerical ratings of the '
        'parts (very low is 1, low is 2, nominal is 3, high is 4, very high is 5, extra high is 6) are summed, and the '
        'sum is put on the scale of the Early Design driver. For example, PERS is made from ACAP, PCAP and PCON, and PREX '
        'from AEXP, PEXP and LTEX. The post-architecture model has 17 cost drivers instead of 7 and one more factor for the '
        'changes of the requirements (the percent of the code that is thrown away because of them); the size there is '
        'the new code plus the reused code, reduced by the part that costs less.')
    d.p('The cost is `C = PM × rate`, where the rate is the cost of a person-month. The methodology gives the average '
        'rate of 15,000 dollars, which is the level of the USA; for a Ukrainian team of junior developers the rate is '
        '1,000 dollars (the salary survey of the portal DOU, 2025: the median of a junior developer is about 900 to 930 '
        'dollars a month, of a middle developer about 2,400). The nominal duration is `TDEV = 3.0 × PM^(0.33 + 0.2 × (B − 1.01))` months. The duration is not the effort divided by '
        'the number of people: adding people does not shorten the project in proportion, and the model shows only the '
        'average load PM / TDEV.')
    d.p('The analysis of the sensitivity of a project shows how much the estimate of the effort, the cost and the '
        'duration changes when the conditions change. With COCOMO II it is done in this way: the base estimate is made; '
        'then the drivers that the change touches are given new ratings, and the estimate is calculated again; the '
        'difference of the two estimates is the sensitivity to the change. The scenario of the variant, as the methodology '
        'describes it, is the use of a new and cheaper microprocessor. The experience of the team with its language and '
        'tools falls from nominal to very low, and the tools for it (compilers, assemblers, debuggers) are primitive, so '
        'the tools fall from high to very low. The question is whether the saving on the processor pays for the growth of '
        'the effort.')

    # ------------------------------------------------------------------ the program and the scenario
    d.h1('The software project and the scenario')
    d.p('The project is Bookshelf, a reading tracker for a phone, the same as in laboratory works 1 to 5: a reader keeps '
        'a library, updates the progress, saves quotes, sees the statistics and the goal, reads EPUB books and publishes '
        'quotes. The server part is PHP 8.4 with Laravel, the client is a PWA in TypeScript with Vue, the database is '
        'MySQL, and everything runs in containers on one small cloud server with 1 GB of memory (laboratory work 2).')
    d.p('The program does not have a microprocessor of its own, so the scenario is translated to the real conditions of the '
        'project. The processor in question is the processor of the server. The scenario says: the team is offered a '
        'cheaper server with a processor of another architecture (for example, an ARM server instead of x86-64). Then the '
        'container images, the extensions of PHP and the build in the pipeline must be made again for the new '
        'architecture; the team has no experience with it (the experience with the language and the tools falls to very '
        'low, as in the methodology), and the tools for it are assumed to be primitive (the tools fall to very low, as in the '
        'methodology). The saving is the difference in the price of the server. The whole budget of the project for the '
        f'server is the student credit of about {r["savings_credit"]} dollars for a year, so this is the largest saving '
        f'that the scenario can give; for comparison the methodology gives the saving of {n(r["savings_chip"])} dollars for the chip.')

    # ------------------------------------------------------------------ performing
    d.h1('Performing the laboratory work')

    d.h2('1. The size of the product')
    d.p(f'The Early Design model needs the size in thousands of lines. The measured size of the product is {r["size"]:.1f} KLOC '
        f'({n(r["size"] * 1000)} lines of the server part, the client, the configuration and the delivery, without the tests; '
        f'laboratory work 5). The size that follows from the function points of laboratory work 5 is {r["fp_size"]:.1f} KLOC '
        '(320 FP × 53 statements per FP for Java). The model takes the measured size, because the product is built; the '
        'estimate by the size from FP is given in ' + ctx.ref('tab:base') + ' for comparison.')

    d.h2('2. The scale factors')
    d.p('The five scale factors were estimated for the team and the project; ' + ctx.ref('tab:scale') + f' gives the values. '
        f'The sum is {r["w_sum"]}, so `B = 1.01 + 0.01 × {r["w_sum"]} = {r["b"]:.2f}`.')
    ctx.table(d, 'tab:scale', 'Scale factors of Bookshelf')
    d.table(['Factor', 'Rating', 'Value', 'Reason'],
            [[f'{key}, {name}', level, str(w), why] for key, name, w, level, why in r['scale']] +
            [['Sum', '', str(r['w_sum']), f'B = {r["b"]:.2f}']],
            [3.8, 1.8, 1.2, 8.0], align=['left', 'left', 'center', 'left'], size=20)

    d.h2('3. The effort multipliers')
    d.p('The 15 post-architecture ratings of the team and the product are given with the reasons in Appendix A. From them '
        + ctx.ref('tab:em') + ' makes the seven Early Design drivers by the rule of the sums. The multipliers are from the '
        'table of the Early Design model (' + ctx.ref('tab:emtable') + '). The product of the multipliers is '
        f'{r["base_product"]:.4f}. The product is above 1, because the documentation and the memory limit raise the '
        'drivers RCPX and PDIF, and the good tools lower FCIL.')
    ctx.table(d, 'tab:em', 'The Early Design drivers of Bookshelf (the base case)')
    rows = []
    for driver in ('PERS', 'RCPX', 'RUSE', 'PDIF', 'PREX', 'FCIL', 'SCED'):
        if driver in PARTS:
            parts = ', '.join(f'{p} {r["post"][p]}' for p in PARTS[driver])
            total = str(s0['sums'][driver])
        else:
            parts, total = 'as the Post-Architecture driver', '-'
        rows.append([f'{driver}, {DRIVER_NAMES[driver].lower()}', parts, total, LEVEL_NAMES[r['base_levels'][driver]],
                     f'{r["base_em"][driver]:.2f}'])
    rows.append(['Product of the multipliers', '', '', '', f'{r["base_product"]:.4f}'])
    d.table(['Driver', 'Ratings of the parts', 'Sum', 'Level', 'Multiplier'], rows, [5.6, 4.6, 1.1, 1.8, 1.6],
            align=['left', 'left', 'center', 'left', 'right'], size=20)
    ctx.table(d, 'tab:emtable', 'Multipliers of the Early Design model by level')
    d.table(['Driver', 'XL', 'VL', 'L', 'N', 'H', 'VH', 'XH'],
            [[k] + ['-' if v is None else f'{v:.2f}' for v in values] for k, values in EM_TABLE.items()],
            [2.0] + [1.5] * 7, align=['left'] + ['center'] * 7, size=20)

    d.h2('4. The base estimate')
    d.p('The base estimate is made by the equations of the theory. ' + ctx.ref('tab:base') + ' gives it, with the estimate '
        'from the size by FP for comparison.')
    ctx.table(d, 'tab:base', 'The base estimate of Bookshelf')
    fp = r['fp_est']
    d.table(
        ['Value', 'Size measured', 'Size from FP'],
        [['Size, KLOC', f'{r["size"]:.1f}', f'{r["fp_size"]:.1f}'],
         ['Exponent B', f'{r["b"]:.2f}', f'{r["b"]:.2f}'],
         ['Product of the multipliers', f'{r["base_product"]:.4f}', f'{r["base_product"]:.4f}'],
         ['Effort PM = 2.5 × SIZE^B × ΠEM, person-months', n(base['pm'], 1), n(fp['pm'], 1)],
         ['Cost, thousand dollars (1,000 per person-month)', n(base['cost'] / 1000), n(fp['cost'] / 1000)],
         ['Duration TDEV, months', n(base['tdev'], 1), n(fp['tdev'], 1)],
         ['Average load PM / TDEV, people', n(base['staff'], 1), n(fp['staff'], 1)]],
        [7.4, 3.2, 3.2], align=['left', 'right', 'right'],
    )
    d.p(f'The model gives {base["pm"]:.0f} person-months and about {base["tdev"]:.0f} months for a team of about '
        f'{base["staff"]:.0f} people. The planned effort of the project is {r["actual_effort"]:.1f} person-months '
        f'({r["actual_days"]} person-days, laboratory work 5), which is {base["pm"] / r["actual_effort"]:.0f} times less. The '
        'model is calibrated on industrial projects with an ordinary productivity; Bookshelf was made by three people with '
        'generators and an AI coding assistant, and its effort is a plan and not a time sheet. For this reason the absolute '
        'numbers of the model are not a forecast of the real expenses. The sensitivity is relative: the percentages of the '
        'change do not depend on the size, so they remain valid, and the money is counted at the notional rate of a '
        'junior developer in Ukraine.')

    d.h2('5. The scenario “use of a new microprocessor”')
    d.p('In the scenario the experience of the team with the language and the tools of the new processor (LTEX) falls from '
        'nominal (3) to very low (1), and the support tools (TOOL) fall from high (4) to very low (1). The change goes '
        'through the sums: PREX = AEXP + PEXP + LTEX goes from 3 + 3 + 3 = 9 to 3 + 3 + 1 = 7, that is from nominal to low '
        '(the multiplier 1.12), and FCIL = TOOL + SITE goes from 4 + 4 = 8 to 1 + 4 = 5, that is from high to low (from 0.87 '
        'to 1.10). The other drivers do not change. ' + ctx.ref('tab:scen') + ' gives the result of the scenario and of two '
        'parts of it, the experience alone and the tools alone.')
    ctx.table(d, 'tab:scen', 'The scenario against the base case')
    rows = []
    for s in r['scenarios']:
        rows.append([s['key'], s['name'], LEVEL_NAMES[s['levels']['PREX']] + ' / ' + LEVEL_NAMES[s['levels']['FCIL']],
                     f'{s["product"]:.3f}', n(s['pm'], 1), pct(s['d_pct']) if s['key'] != 'S0' else '-',
                     n(s['cost'] / 1000), n(s['tdev'], 1), n(s['staff'], 1)])
    d.table(['', 'Case', 'PREX / FCIL', 'ΠEM', 'PM', 'Change', 'Cost, k$', 'TDEV, mo.', 'People'], rows,
            [0.8, 5.2, 2.7, 1.2, 1.2, 1.4, 1.4, 1.4, 1.2],
            align=['center', 'left', 'left', 'right', 'right', 'right', 'right', 'right', 'right'], size=19)
    d.p(f'The scenario raises the product of the multipliers from {r["base_product"]:.3f} to {s3["product"]:.3f}, that is by '
        f'{s3["d_pct"]:.1f}%. The effort grows from {base["pm"]:.0f} to {s3["pm"]:.0f} person-months (by {s3["d_pm"]:.0f}), the '
        f'cost by {n(s3["d_cost"] / 1000)} thousand dollars, and the duration by {s3["d_tdev"]:.1f} months '
        f'(from {base["tdev"]:.1f} to {s3["tdev"]:.1f}). The tools give a bigger part of the growth ({s2["d_pct"]:.0f}%) than '
        f'the experience ({s1["d_pct"]:.0f}%), and the two parts are multiplied, so together they give more than the sum '
        f'of the separate changes ({s1["d_pct"] + s2["d_pct"]:.0f}%).')
    d.p('The result was checked with the multipliers of the post-architecture model (COCOMO II.1997): the experience with the '
        'language and the tools goes from 1.00 to 1.22 and the tools go from 0.86 to 1.24, so the two drivers change the '
        f'effort by the factor {r["pa_ratio"]:.2f} ({(r["pa_ratio"] - 1) * 100:.0f}% more). The coarse model of the early '
        f'stage gives {s3["d_pct"]:.0f}%; the direction is the same and the size of the change is of the same order, and '
        'the more detailed model gives a stronger effect, because it keeps the difference between the tools of the high '
        'and the very low level that the early model puts together with SITE.')
    d.p('The saving must be compared with the extra cost. The scenario saves at most the price of the server for the '
        f'project, about {r["savings_credit"]} dollars, or, in the example of the methodology, {n(r["savings_chip"])} dollars. '
        f'The extra cost of the effort is {n(s3["d_cost"] / 1000)} thousand dollars at the rate of a junior developer, which is '
        f'{n(s3["d_cost"] / r["savings_chip"])} times more than even the larger saving, and {n(s3["d_cost"] / r["savings_credit"])} '
        'times more than the real one. So the cheaper processor does not pay '
        'for itself. The decision would change only if the new processor were used for a very large number of copies of the '
        'product, but Bookshelf is one service on one server.')

    d.h2('6. The sensitivity to the other parameters')
    d.p('To see how large the effect of the scenario is among the other possible changes, every driver of the model was '
        'moved one level down (worse) and one level up (better) from its base level, and the change of the effort was '
        'calculated (' + ctx.ref('tab:sens') + '). The size and the exponent were changed too (' + ctx.ref('tab:sens_size') + ').')
    ctx.table(d, 'tab:sens', 'Change of the effort when one driver moves by one level')
    rows = []
    for s in r['sens']:
        def cell(x):
            return '-' if x is None else f'{LEVEL_NAMES[x["level"]]} ({x["em"]:.2f}), {pct(x["pct"], 0)}'
        rows.append([s['driver'], f'{LEVEL_NAMES[s["level"]]} ({s["em"]:.2f})', cell(s['worse']), cell(s['better'])])
    d.table(['Driver', 'Base', 'One level worse', 'One level better'], rows, [1.8, 3.6, 4.7, 4.7],
            align=['left', 'left', 'left', 'left'], size=20)
    ctx.table(d, 'tab:sens_size', 'Change of the effort when the size changes')
    rows = [[f'{s["pct"]:+d}%', n(s['size'], 1), n(s['pm'], 1), pct(100 * (s['pm'] / base['pm'] - 1))] for s in r['sens_size']]
    d.table(['Size change', 'Size, KLOC', 'Effort, person-months', 'Effort change'], rows, [2.8, 3.0, 4.2, 3.2],
            align=['right'] * 4)
    sw = r['sens_scale'][0]
    d.p(f'A change of one scale factor by one step changes the exponent B by 0.01 and the effort by about '
        f'{sw["worse"]["pct"]:.1f}% (up) or {sw["better"]["pct"]:.1f}% (down) at this size; all five factors act in the same way. '
        'The size acts almost in proportion: 10% of the size gives about 11% of the effort. Among the drivers the strongest '
        'are the reliability and complexity of the product and the platform difficulty (about 40% for one level), then the '
        'capability of the personnel (17 to 26%) and the facilities and the experience (12 to 16%); the schedule and the '
        'reuse are weak. The scenario of the new processor, with 42%, acts on the experience and the tools together and '
        'is as strong as a worsening of the product or the platform by one level, and stronger than the change of any '
        'other single driver.')

    # ------------------------------------------------------------------ conclusion
    d.h1('Conclusion')
    d.p('In this laboratory work the model COCOMO II (the Early Design model) was built for Bookshelf, and the sensitivity '
        f'of the project to the use of a new microprocessor was estimated. With the size of {r["size"]:.1f} KLOC, the exponent '
        f'{r["b"]:.2f} and the product of the multipliers {r["base_product"]:.3f} the base estimate is {base["pm"]:.0f} '
        f'person-months, {n(base["cost"] / 1000)} thousand dollars and {base["tdev"]:.0f} months. In the scenario the '
        'experience with the language and the tools of the new processor falls, and the tools become primitive: the '
        f'effort grows by {s3["d_pct"]:.0f}% to {s3["pm"]:.0f} person-months, the cost by {n(s3["d_cost"] / 1000)} thousand '
        f'dollars and the duration by {s3["d_tdev"]:.1f} months. The saving on the processor is tens of times (at the best) '
        'and hundreds of times (really) smaller than the extra cost, so the scenario is not worth it. The drivers that influence the effort most are the '
        'reliability and complexity of the product and the platform difficulty. The absolute numbers of the model are '
        'much higher than the plan of the project, because the model is calibrated for ordinary industrial productivity; '
        'the relative changes are the result of the analysis.')


EM_TABLE = {
    'PERS': [2.12, 1.62, 1.26, 1.00, 0.83, 0.63, 0.50],
    'RCPX': [0.49, 0.60, 0.83, 1.00, 1.33, 1.91, 2.72],
    'RUSE': [None, None, 0.95, 1.00, 1.07, 1.15, 1.24],
    'PDIF': [None, None, 0.87, 1.00, 1.29, 1.81, 2.61],
    'PREX': [1.59, 1.33, 1.12, 1.00, 0.87, 0.71, 0.62],
    'FCIL': [1.43, 1.30, 1.10, 1.00, 0.87, 0.73, 0.62],
    'SCED': [None, 1.43, 1.14, 1.00, 1.00, 1.00, None],
}

QUESTIONS = [
    ('What is the purpose of the model of the composition of an application? On which estimates is it based?',
     ['The model of the composition is used at the early stage of the construction, when the user interfaces are '
      'prototyped, the interaction of the software with the computer system is discussed, the performance is estimated and '
      'the maturity of the technology is defined. It is based on object points, an indirect measure of the software: the '
      'number of screens, reports and components in a third generation language, each of a simple, average or complex '
      'level with its weight. The effort is the number of the new object points divided by the productivity of the '
      'development, which depends on the experience of the developers and the maturity of the environment.']),
    ('What is the purpose of the Early Design model?',
     ['The Early Design model is used when the requirements are stabilized and the basic architecture of the software is '
      'defined. It gives a rough estimate of the effort and the duration with a small set of drivers, before the details '
      'of the design are known.']),
    ('Characterize the main equation of the Early Design model.',
     ['The equation is PM = A × SIZE^B × ΠEMi + PM_auto, where A = 2.5, SIZE is the size in thousands of lines, B is the '
      'exponent that shows the nonlinear growth of the effort with the size (from 1.01 to 1.26), EMi are the seven effort '
      'multipliers that describe the product, the process and the personnel, and the last term is the effort for the '
      'automatically generated code.']),
    ('Characterize the scale factors of COCOMO II.',
     ['There are five scale factors: precedentedness (PREC, the experience of the organization with such projects), '
      'flexibility of the development (FLEX), architecture and risk resolution (RESL), team cohesion (TEAM) and process '
      'maturity (PMAT). They make the exponent B and so show how the effort grows with the size.']),
    ('How are the scale factors estimated?',
     ['Every factor gets one of six ratings, from very low (5) to extra high (0), by the description of the table of the '
      'methodology; the process maturity follows from the answers to the questions of the CMM. The sum of the five '
      'values is put into B = 1.01 + 0.01 × ΣWi. For example, the sum 16 gives B = 1.17, and the sum 15 gives B = 1.16.']),
    ('What is the purpose of the post-architecture model of COCOMO II?',
     ['It is used when the architecture of the software is formed and the development goes on. It is the most detailed '
      'model and gives a more exact estimate than the Early Design model.']),
    ('How does the main equation of the post-architecture model differ from the equation of the Early Design model?',
     ['It has the same form, but it has a factor for the changes of the requirements (the percent of the code thrown away '
      'because of them), the size is the sum of the new code and the reused code (reduced by the factors of the cost '
      'of the reuse), and the multiplier of the correction depends on 17 cost drivers instead of 7.']),
    ('What are the cost drivers of the post-architecture model and how are they calculated?',
     ['They are 17 characteristics of the product (reliability, the size of the database, complexity, reuse, documentation), '
      'of the platform (the time and the memory constraints, the volatility), of the personnel (the capability of the '
      'analysts and the programmers, the experience, the continuity) and of the project (the tools, the multisite '
      'development, the schedule). Every driver is rated from very low to extra high, and the rating gives a multiplier '
      'from the table of Boehm; the product of all the multipliers is the correction.']),
    ('How is the duration of the development determined in COCOMO II?',
     ['By the equation TDEV = 3.0 × PM^(0.33 + 0.2 × (B − 1.01)) × SCED% / 100, where PM is the effort without the effect of '
      'the schedule, B is the exponent and SCED% is the percent of the compression or the stretching of the nominal '
      'schedule (from 75 to 160%). The duration depends on the effort and not directly on the number of people.']),
    ('What is the analysis of the sensitivity of a software project?',
     ['It is the study of how the estimates of the project, the effort, the cost and the duration, change when the '
      'conditions of the development change: the salary, the capacity of the memory, the processor, the budget. It shows '
      'which changes are dangerous and whether a saving in one place is lost in another.']),
    ('How to apply the model COCOMO II to the analysis of the sensitivity?',
     ['The base estimate is made with the model. Then the scenario is described through the drivers that it changes (for '
      'example, the lower experience and the primitive tools), the new ratings and multipliers are taken, and the estimate '
      'is calculated again. The difference between the new and the base effort, cost and duration is compared with the '
      'gain that the scenario gives (the saving on a processor, the salary or the memory), and the decision is made.']),
]


def write_answers(d, ctx) -> None:
    d.h1('Answers to the control questions')
    for number, (question, paragraphs) in enumerate(QUESTIONS, 1):
        d.p(f'{number}. {question}', indent=0, keep_next=True)
        for paragraph in paragraphs:
            d.p(paragraph)


def write_appendix(d, ctx, r: dict) -> None:
    d.h1('Appendix A. Ratings of the post-architecture drivers')
    d.p('The ratings of the parts of the Early Design drivers for the base case. The numbers are 1 (very low), 2 (low), '
        '3 (nominal), 4 (high), 5 (very high). The ratings are the estimate of the authors of the report.')
    ctx.table(d, 'tab:app_ratings', 'Ratings of the drivers of the base case')
    names = {'ACAP': 'Analyst capability', 'PCAP': 'Programmer capability', 'PCON': 'Personnel continuity',
             'RELY': 'Required reliability', 'DATA': 'Database size', 'CPLX': 'Product complexity',
             'DOCU': 'Documentation to the life-cycle needs', 'TIME': 'Execution time constraint',
             'STOR': 'Main storage constraint', 'PVOL': 'Platform volatility', 'AEXP': 'Application experience',
             'PEXP': 'Platform experience', 'LTEX': 'Language and tool experience', 'TOOL': 'Use of software tools',
             'SITE': 'Multisite development'}
    rows = [[k, names[k], f'{v[1]} ({v[0]})', v[2]] for k, v in r['ratings'].items()]
    d.table(['Driver', 'Name', 'Rating', 'Reason'], rows, [1.4, 4.0, 2.2, 7.6], align=['left', 'left', 'left', 'left'], size=19)
