"""The report on laboratory work 5: the estimate of the project by LOC and FP metrics."""

from __future__ import annotations

import json
from pathlib import Path

from calc import RATE

HERE = Path(__file__).resolve().parent


def n(value: float, digits: int = 0) -> str:
    return f'{value:,.{digits}f}'


def data() -> dict:
    return json.loads((HERE / 'results.json').read_text(encoding='utf-8'))


def write(d, ctx, r: dict, inventory: dict) -> None:
    sched = r['schedule']
    fp = r['fp']
    lm = r['loc_metrics']
    fm = r['fp_metrics']
    lines = r['loc']['lines']

    # ------------------------------------------------------------------ objective, tasks, theory
    d.h1('Objective', page_break=True)
    d.p('To gain the skills of estimating the limits of the execution time of a project, and of measuring a software '
        'product and the process of its development with size-oriented (LOC) and function-oriented (FP) metrics.')

    d.h1('Tasks for the laboratory work')
    d.numbered([
        'Study the lecture material on the use of size-oriented and function-oriented metrics for the estimate of software.',
        'Measure the developed software product and the process of its development with size-oriented and '
        'function-oriented metrics.',
        'Prepare the report and answer the control questions.',
    ])

    d.h1('Theoretical background')
    d.p('The estimate of a project starts with the limits of the time of each work: the early start ES (all the works '
        'before it are done in the shortest time), the late start LS (it does not delay the whole project), the early '
        'finish EF and the late finish LF. The total reserve of a work is LS minus ES; the works with zero reserve form '
        'the critical path. The recommended rule for the distribution of the cost of a project is 40-20-40: 40% for the '
        'analysis and the design (5% of them for the planning and the system analysis), 20% for the coding and 40% for '
        'the testing and the debugging.')
    d.p('Size-oriented metrics measure the product and the process directly and are based on LOC, the number of lines '
        'in the product. The initial data are the effort E (person-months), the cost C (thousands of dollars), the size '
        'in KLOC, the pages of program documents, the errors and the people. From them the metrics are calculated: '
        'productivity `P = KLOC / E`, the cost of a line `C / KLOC`, the documentation `pages / KLOC` and the quality '
        '`errors / KLOC`.')
    d.p('Function-oriented metrics measure the product indirectly, by its functionality. Five information characteristics '
        'are counted: external inputs (EI), external outputs (EO), external queries (EQ), internal logical files (ILF) '
        'and external interface files (EIF). Each is given a rank (low, average or high) by the number of data elements '
        'and of referenced files, and the rank has a weight (' + ctx.ref('tab:weights') + '). The sum is the unadjusted '
        'count UFP. The function points are `FP = UFP × (0.65 + 0.01 × ΣFi)`, where Fi are the 14 system parameters, each '
        'from 0 (no influence) to 5 (the main influence). On the basis of FP the metrics are calculated in the same way '
        'as for LOC: `FP / E`, `C / FP`, `pages / FP`, `errors / FP`. The method suits commercial information systems. '
        'For a program with complex algorithms the feature points are used: they add the number of algorithms with the '
        'weight 3. The number of FP is converted to LOC with the number of statements per FP of the language '
        '(for example, 53 for Java, 64 for C++).')
    ctx.table(d, 'tab:weights', 'Weights of the information characteristics by rank')
    d.table(
        ['Characteristic', 'Low', 'Average', 'High'],
        [['External inputs (EI)', '3', '4', '6'], ['External outputs (EO)', '4', '5', '7'],
         ['External queries (EQ)', '3', '4', '6'], ['Internal logical files (ILF)', '7', '10', '15'],
         ['External interface files (EIF)', '5', '7', '10']],
        [5.2, 2.2, 2.2, 2.2], align=['left', 'center', 'center', 'center'],
    )

    # ------------------------------------------------------------------ the program
    d.h1('The software project')
    d.p('The project is Bookshelf, a reading tracker for a phone (the technical specification is the result of laboratory '
        'work 1, version 2). A reader keeps a library of books, updates the page that they have reached, saves quotes and '
        'thoughts, sees the statistics and the annual goal, reads their own EPUB books in the application and publishes '
        'quotes and reviews for other readers. The server part is written in PHP 8.4 with Laravel 13, the client is a '
        'progressive web application in TypeScript with Vue 3, and the data are in MySQL 8.4. The program was made in '
        'seven stages: the library, the progress, the quotes, the statistics and the goal, the PWA, the community and '
        'the reading of EPUB books. The team has three people. The network schedule of the work is in laboratory work 2.')

    # ------------------------------------------------------------------ performing
    d.h1('Performing the laboratory work')

    d.h2('1. The limits of the time of the project')
    d.p('The project has eighteen works, A to R, with the durations and the dependencies of laboratory work 2. The '
        'forward pass gives the early start and the early finish of every work, and the backward pass gives the late '
        'finish and the late start; the reserve is LS minus ES. ' + ctx.ref('tab:time') + ' gives the result.')
    ctx.table(d, 'tab:time', 'Limits of the time of the works, working days')
    rows = [[w['code'], w['name'], str(w['days']), str(w['es']), str(w['ef']), str(w['ls']), str(w['lf']), str(w['slack'])]
            for w in sched['works']]
    d.table(['Work', 'Name', 'Dur.', 'ES', 'EF', 'LS', 'LF', 'Reserve'], rows, [1.2, 5.6, 1.2, 1.1, 1.1, 1.1, 1.1, 1.6],
            align=['center', 'left', 'right', 'right', 'right', 'right', 'right', 'right'],
            shade={i for i, w in enumerate(sched['works']) if w['slack'] == 0}, size=20)
    d.p(f'The critical path is {" → ".join(["A", "B", "C", "D", "(E, F)", "J", "N", "O", "L", "M"])}. Its length is '
        f'{sched["total"]} working days, that is 11.4 weeks, and it is the shortest time of the whole project (the rows '
        'with zero reserve are shaded). The works with a reserve are G (24 days), I (14), H and K (12 each, the same '
        'reserve), P (8) and Q and R (6 each, shared). A delay of a work with a reserve up to its reserve does not move '
        'the end of the project. The sum of the durations of all the works is ' + str(r['days']) + ' person-days; this is '
        'the planned effort, and with 21 working days in a month it is ' + n(r['effort'], 1) + ' person-months. The team '
        'has three people, and the sum of the durations divided by the length of the critical path gives the average '
        f'load of {r["days"] / sched["total"]:.1f} people.')

    st = r['stages']
    ctx.table(d, 'tab:rule', 'The plan against the rule 40-20-40, person-days')
    d.table(
        ['Part of the work', 'Rule', 'Recommended', 'Plan', 'Share of the plan'],
        [['Analysis and design (A, B)', '40%', n(0.4 * r['days'], 1), str(st['design']), f'{100 * st["design"] / r["days"]:.1f}%'],
         ['Coding (C to R, the tests are written together with the code)', '20% + 40%', n(0.6 * r['days'], 1),
          str(st['code']), f'{100 * st["code"] / r["days"]:.1f}%'],
         ['Acceptance and documentation (M)', 'in the first and the last part', '-', str(st['accept']),
          f'{100 * st["accept"] / r["days"]:.1f}%']],
        [5.2, 2.4, 3.1, 1.4, 2.4], align=['left', 'left', 'right', 'right', 'right'],
    )
    d.p('The plan is far from the recommended distribution: the analysis and the design take less than a tenth of the '
        'effort, where the rule gives forty percent. In Bookshelf the plan counts only the works of the first two '
        'documents and the implementation. The technical design and the other design work of the course were done later, '
        'in laboratory works 2 to 4, and are not in the schedule. The tests are written together with the code, so the '
        f'rule cannot separate coding from testing in the plan; by the lines the tests are {100 * lm["test_share"]:.0f}% '
        f'of all the code ({n(r["tests_lines"])} lines of tests, laboratory work 4).')

    d.h2('2. Measuring with the size-oriented metrics')
    d.p('The size of the product was counted from the files of the repository: the server part, the client, the '
        'configuration, the containers and the delivery, without the automated tests, as physical lines. This is '
        f'{n(lines)} lines in {r["loc"]["files"]} files, or {r["kloc"]:.1f} KLOC (the PHP code is {n(r["loc"]["php"])} lines and '
        f'the TypeScript and Vue code is {n(r["loc"]["ts"])}). The number of laboratory work 4 is a little different '
        '(21,932 lines without tests), because here the build file and the file of the containers are also counted. The '
        'effort is the plan of the schedule. The cost is the effort multiplied by 1,000 dollars per person-month. The methodology gives the average rate of '
        '15,000 dollars, which is the level of the USA. For a Ukrainian team of junior developers the rate was taken from '
        'the salary survey of the portal DOU (2025): the median salary of a junior developer is about 900 to 930 dollars '
        'a month, and of a middle developer about 2,400. The rate is rounded to 1,000 dollars; it is a notional figure, '
        'because nobody was paid. The pages are the program '
        'documents: the technical specification (37 pages), the preliminary design (33), the technical design (22) and '
        'the four guides of laboratory work 4 (13). The errors are the defects that were recorded: four commits of the type '
        '“fix” in git and three findings of the first analysis by SonarQube (laboratory work 4). ' + ctx.ref('tab:loc_data')
        + ' is the table of the initial data.')
    ctx.table(d, 'tab:loc_data', 'Initial data for the LOC metrics')
    d.table(
        ['Project', 'Effort, person-months', 'Cost, thousand $', 'Size, KLOC', 'Documents, pages', 'Errors', 'People'],
        [['Bookshelf', n(r['effort'], 1), n(r['cost'] / 1000, 1), n(r['kloc'], 1), str(r['pages']), str(r['errors']), str(r['people'])]],
        [2.2, 2.4, 2.2, 1.8, 2.2, 1.4, 1.4], align=['left'] + ['right'] * 6,
    )
    d.p('The metrics of productivity and quality are in ' + ctx.ref('tab:loc_metrics') + '.')
    ctx.table(d, 'tab:loc_metrics', 'Size-oriented metrics of Bookshelf')
    d.table(
        ['Metric', 'Formula', 'Value'],
        [['Productivity', 'KLOC / effort', f'{lm["productivity_kloc"]:.2f} KLOC per person-month ({n(lm["productivity_loc"])} LOC)'],
         ['Productivity per person', 'KLOC / people', f'{lm["kloc_per_person"]:.1f} KLOC per person'],
         ['Cost of a line', 'cost / LOC', f'{lm["cost_per_loc"]:.2f} $ per line ({n(lm["cost_per_kloc"])} $ per KLOC)'],
         ['Documentation', 'pages / KLOC', f'{lm["doc_per_kloc"]:.1f} pages per KLOC'],
         ['Quality', 'errors / KLOC', f'{lm["errors_per_kloc"]:.2f} errors per KLOC']],
        [3.8, 3.2, 7.6], align=['left', 'left', 'left'],
    )
    d.p('The productivity of Bookshelf is about ten times higher than that of the sample projects of the methodology '
        '(0.44 to 0.50 KLOC per person-month), and the recorded errors are about ten times fewer (the samples have 2.4 to '
        '3.2 errors per KLOC). The numbers do not mean that the team is ten times better. The effort is a plan and not a '
        'time sheet, because the hours of the team were not '
        'recorded. The size of the code is large for the effort because a large part of the code was produced by '
        'generators, scaffolding and an AI coding assistant, and the interface texts, the types and the configuration '
        'are long but simple. The metrics of the errors are not comparable too: only the recorded defects are counted, and '
        'the defects that the automated tests found while writing the code were corrected at once and were not recorded.')
    d.p('The size-oriented metrics can also be given by the groups of the functions of the program, as the methodology '
        'does for the functions of the sample project. The files were put into the groups by the works of the schedule '
        'that made them (' + ctx.ref('tab:groups') + '). The effort of a group is the sum of the durations of its works.')
    ctx.table(d, 'tab:groups', 'Size, effort and productivity by the groups of the functions')
    rows = [[g['name'], g['works'], n(g['lines']), str(g['days']), n(g['effort'], 2), n(g['productivity']), n(g['cost'])]
            for g in r['groups']]
    rows.append(['Total (without the works A, B and M)', '', n(lines), str(r['stages']['code']),
                 n(r['stages']['code'] / 21, 2), n(lines / (r['stages']['code'] / 21)), n(r['stages']['code'] / 21 * RATE)])
    d.table(['Group', 'Works', 'LOC', 'Person-days', 'Effort, p.-m.', 'LOC per p.-m.', 'Cost, $'], rows,
            [5.2, 1.3, 1.3, 1.5, 1.5, 1.7, 1.6], align=['left', 'center', 'right', 'right', 'right', 'right', 'right'], size=20)
    d.p('The groups with the lowest productivity are the progress (the small server part and the screen with a long '
        'schedule) and the PWA (a short and dense service worker). The highest figure is for the group “skeleton, '
        'containers, delivery and shared code”, because the shared files (the interface texts, the routes, the '
        'containers) are large and were made by two short works.')

    d.h2('3. Measuring with the function-oriented metrics')
    d.p('The functional characteristics were counted from the code, not from memory: the routes of the server part, the '
        'requests and the resources for the data elements, the migrations for the files and the screens of the client for '
        'what the user fills and sees. The full list is in Appendix A. The user processes were counted once each (for '
        'example, the like and the removal of the like are one input), and the operator’s commands of the moderation were '
        f'not counted, because the reader does not use them. There are {fp["n_transactions"]} transactions: '
        f'{sum(fp["counts"]["EI"])} inputs, {sum(fp["counts"]["EO"])} outputs and {sum(fp["counts"]["EQ"])} queries, '
        f'{sum(fp["counts"]["ILF"])} internal logical files and {sum(fp["counts"]["EIF"])} external interface file (the '
        'catalog Open Library). The rank of every item follows from the number of its data elements and referenced files by '
        'the tables of the methodology. ' + ctx.ref('tab:fp_count') + ' is the table of the initial data.')
    ctx.table(d, 'tab:fp_count', 'Initial data for the FP metrics')
    names = {'EI': 'External inputs', 'EO': 'External outputs', 'EQ': 'External queries', 'ILF': 'Internal logical files',
             'EIF': 'External interface files'}
    from calc import WEIGHTS  # noqa: E402
    rows = []
    for key in ('EI', 'EO', 'EQ', 'ILF', 'EIF'):
        c, w = fp['counts'][key], WEIGHTS[key]
        rows.append([names[key]] + [f'{c[i]} × {w[i]} = {c[i] * w[i]}' for i in range(3)] + [str(fp['totals'][key])])
    rows.append(['Total (UFP)', '', '', '', str(fp['ufp'])])
    d.table(['Characteristic', 'Low', 'Average', 'High', 'Total'], rows, [4.4, 2.8, 2.8, 2.8, 1.6],
            align=['left', 'center', 'center', 'center', 'right'])
    d.p('The 14 system parameters were estimated for Bookshelf with the reasons given in ' + ctx.ref('tab:gsc') + '. Their sum '
        f'is {fp["gsc_sum"]}, so the adjustment is 0.65 + 0.01 × {fp["gsc_sum"]} = {fp["vaf"]:.2f}.')
    ctx.table(d, 'tab:gsc', 'System parameters of the program')
    d.table(['№', 'Parameter', 'Value', 'Reason'],
            [[str(i), name, str(v), why] for i, (name, v, why) in enumerate(r['gsc'], 1)], [0.8, 3.8, 1.2, 8.8],
            align=['center', 'left', 'center', 'left'], size=20)
    d.p(f'The number of function points is FP = {fp["ufp"]} × {fp["vaf"]:.2f} = {fp["fp"]:.1f}. ' + ctx.ref('tab:fp_metrics')
        + ' gives the metrics on the basis of the planned effort and the cost of the previous section.')
    ctx.table(d, 'tab:fp_metrics', 'Function-oriented metrics of Bookshelf')
    d.table(
        ['Metric', 'Formula', 'Value'],
        [['Productivity', 'FP / effort', f'{fm["productivity"]:.1f} FP per person-month'],
         ['Cost of a function point', 'cost / FP', f'{fm["cost_per_fp"]:.0f} $ per FP'],
         ['Documentation', 'pages / FP', f'{fm["doc_per_fp"]:.2f} pages per FP'],
         ['Quality', 'errors / FP', f'{fm["errors_per_fp"]:.3f} errors per FP'],
         ['Size', 'LOC / FP', f'{fm["loc_per_fp"]:.0f} lines per FP']],
        [4.4, 3.2, 7.0], align=['left', 'left', 'left'],
    )
    d.p('The result was checked in two ways, as the methodology does. First, the number of FP was converted to lines with '
        f'the coefficient of Table 5.13 of the methodology. PHP and TypeScript are not in the table, so the nearest '
        f'language, Java (53 statements per FP), was taken for both: {fp["fp"]:.0f} × 53 = {n(fm["loc_from_fp_java"])} lines. '
        f'The measured size is {n(lines)} lines, so the estimate by FP is {100 * fm["ratio_java"]:.0f}% of it, which is '
        'a good agreement for such a crude coefficient. The measured value is higher, because the code of the interface '
        '(the templates and the long texts) is counted in lines but not in function points. Second, the feature points '
        f'were calculated for the program with its {fp["algorithms"]} algorithms (the streak, the forecast, the pace, the '
        'parser and the sanitizer of EPUB, the anchors of quotes, the card of a quote and others): '
        f'4 × {sum(fp["counts"]["EI"])} + 5 × {sum(fp["counts"]["EO"])} + 4 × {sum(fp["counts"]["EQ"])} + 7 × '
        f'{sum(fp["counts"]["ILF"])} + 7 × {sum(fp["counts"]["EIF"])} + 3 × {fp["algorithms"]} = {fp["feature_points"]}, and '
        f'with the adjustment {fp["feature_points_adj"]:.0f}. This is {100 * (fp["feature_points_adj"] / fp["fp"] - 1):.0f}% '
        'more than FP, and the methodology says that for the systems with complex algorithms the figure is 25 to 30% more; '
        'Bookshelf is an information system with a few algorithms, so the difference is small.')

    # ------------------------------------------------------------------ conclusion
    d.h1('Conclusion')
    d.p('In this laboratory work the project Bookshelf was estimated and measured. The limits of the time were calculated '
        f'for the eighteen works: the critical path has {sched["total"]} working days, and the reserves of the other works '
        f'are from 6 to 24 days. The planned effort is {r["days"]} person-days, or {n(r["effort"], 1)} person-months, and its '
        'distribution is far from the rule 40-20-40, because the analysis and the design are only ten percent of it. '
        f'The size-oriented metrics for the product of {r["kloc"]:.1f} KLOC are: productivity {lm["productivity_kloc"]:.1f} '
        f'KLOC per person-month, the cost of a line {lm["cost_per_loc"]:.2f} dollars, {lm["doc_per_kloc"]:.1f} pages of '
        f'documents per KLOC and {lm["errors_per_kloc"]:.2f} recorded errors per KLOC. The function-oriented metrics are: '
        f'{fp["ufp"]} unadjusted and {fp["fp"]:.0f} adjusted function points, {fm["productivity"]:.0f} FP per person-month '
        f'and {fm["cost_per_fp"]:.0f} dollars per FP. The estimate of the size by FP differs from the measured size by '
        f'{100 - 100 * fm["ratio_java"]:.0f}%. The figures of the effort and the cost are the plan and the notional rate '
        'of a junior developer in Ukraine, so they show the method and not the real expenses of the team.')


QUESTIONS = [
    ('Describe the recommended rule for the distribution of the cost of a project.',
     ['The recommended rule is 40-20-40: forty percent of the cost goes to the analysis and the design (five percent '
      'of them to the planning and the system analysis), twenty percent to the coding and forty percent to the testing '
      'and the debugging. The rule shows that the coding, which looks like the main work, is the smallest part of the '
      'cost, and that a lack of time at the beginning and at the end is paid for later.']),
    ('Name the size-oriented metrics.',
     ['They are based on LOC, the number of lines of the product: productivity (KLOC per person-month), the cost of a line '
      '(cost per KLOC), the documentation (pages per KLOC) and the quality (errors per KLOC). Sometimes the average number '
      'of people is also used.']),
    ('What are the size-oriented metrics used for?',
     ['They measure the product and the process of its development directly and give simple numbers for the planning: '
      'with the productivity and the cost of a line from earlier projects one can estimate the effort and the cost of a new '
      'project, compare projects and teams, and follow the quality.']),
    ('Define the advantages and the disadvantages of the size-oriented metrics.',
     ['The advantages: the lines are easy to count automatically, the numbers are simple, and many companies keep such '
      'data. The disadvantages: the number of lines depends on the language and the style of the programmer, so projects in '
      'different languages are not comparable; a good short program looks less productive than a long one; the size is known '
      'exactly only when the program is ready, so it is hard to use at the beginning of the project; and the metrics do '
      'not show the functionality or the quality of the product.']),
    ('What is a function point?',
     ['A function point is a unit of the functional size of a program. It measures what the program does for the '
      'user, not how many lines it has. The number of function points is calculated from the information characteristics '
      'of the program and is corrected with the system parameters: FP = UFP × (0.65 + 0.01 × ΣFi).']),
    ('Which information characteristics does the function point depend on?',
     ['It depends on five characteristics: the number of external inputs, external outputs, external queries, internal '
      'logical files and external interface files. Each has a rank, low, average or high, which is found by the number of data '
      'elements and of referenced files, and each rank has a weight. The sum is multiplied by the correction from the '
      '14 system parameters.']),
]


def write_answers(d, ctx) -> None:
    d.h1('Answers to the control questions')
    for number, (question, paragraphs) in enumerate(QUESTIONS, 1):
        d.p(f'{number}. {question}', indent=0, keep_next=True)
        for paragraph in paragraphs:
            d.p(paragraph)


def write_appendix(d, ctx, inventory: dict) -> None:
    from calc import rank, RANKS
    d.h1('Appendix A. Inventory of the functional characteristics')
    d.p('The list is made from the code of the program (the routes, the requests, the resources, the migrations and the '
        'screens of the client). DET is the number of data elements, FTR is the number of the referenced files, and the '
        'rank follows from them by the tables of the methodology. The lists of the data elements of every item are in the '
        'file `docs/lab5/fp_inventory.json`.')
    ctx.table(d, 'tab:app_tx', 'Transactions: inputs, outputs and queries')
    rows = []
    for t in inventory['transactions']:
        rows.append([t['id'], t['name'], str(len(t['dets'])), str(len(t['ftrs'])), RANKS[rank(t['type'], len(t['ftrs']), len(t['dets']))]])
    d.table(['ID', 'Process', 'DET', 'FTR', 'Rank'], rows, [1.3, 9.0, 1.1, 1.1, 1.8],
            align=['left', 'left', 'right', 'right', 'left'], size=19)
    ctx.table(d, 'tab:app_files', 'Logical files')
    rows = []
    for f in inventory['ilf']:
        rows.append([f['id'], f['name'], str(f['ret']), str(len(f['dets'])), RANKS[rank('ILF', f['ret'], len(f['dets']))]])
    for f in inventory['eif']:
        rows.append([f['id'], f['name'], str(f['ret']), str(len(f['dets'])), RANKS[rank('EIF', f['ret'], len(f['dets']))]])
    d.table(['ID', 'File', 'RET', 'DET', 'Rank'], rows, [1.3, 9.0, 1.1, 1.1, 1.8],
            align=['left', 'left', 'right', 'right', 'left'], size=19)
