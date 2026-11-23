"""All calculations of laboratory work 5: time bounds, LOC metrics, function points. Writes `results.json`.

    python3 docs/lab5/measure.py   # size by groups (loc.json), once
    python3 docs/lab5/calc.py      # everything else

Sources: `schedule.py` (the network schedule of laboratory work 2), `loc.json` (the measured lines), `fp_inventory.json`
(the inventory of the transactions and files, counted from the code) and the constants below (the facts about the work).
"""

from __future__ import annotations

import json
from pathlib import Path

from schedule import calculate

HERE = Path(__file__).resolve().parent

# The facts of the project (the sources are explained in the report).
DAYS_PER_MONTH = 21          # working days in a person-month
PEOPLE = 3                   # the team
RATE = 1000                  # dollars per person-month: the median of a junior developer in Ukraine (DOU survey, 2025: 900-930), rounded
ERRORS = {'fix commits in git': 4, 'Sonar: first analysis (1 vulnerability, 2 smells)': 3}
DOC_PAGES = {'Technical specification, version 2': 37, 'Preliminary design': 33, 'Technical design (short)': 22,
             'Guides of the user, system programmer, programmer and operator (lab 4, Appendices C-F)': 13}
TEST_LINES = 23212           # lines of automated tests (laboratory work 4, Table 2)

# The works of the schedule that make each group of the code (see measure.py).
GROUP_WORKS = {'reader': 'QR', 'community': 'NOP', 'stats': 'K', 'quotes': 'J', 'progress': 'HI', 'library': 'EF',
               'auth': 'D', 'pwa': 'L', 'infra': 'CG'}

# Tables 5.5-5.9 of the methodology: (rows by files, columns by data elements) -> rank.
RANKS = ['low', 'average', 'high']
MATRIX = [[0, 0, 1], [0, 1, 2], [1, 2, 2]]          # row: files (<=1, 2, >2); column: data elements (3 bands)
WEIGHTS = {'EI': (3, 4, 6), 'EO': (4, 5, 7), 'EQ': (3, 4, 6), 'ILF': (7, 10, 15), 'EIF': (5, 7, 10)}
LOC_PER_FP = {'Java': 53, 'C++': 64, 'Visual Basic': 32, 'Delphi Pascal': 29}   # a part of Table 5.13
GSC = [  # the 14 system parameters: (name, value 0-5, reason)
    ('Data communication', 4, 'A client-server application over HTTPS, a PWA, and a call to the Open Library catalog.'),
    ('Distributed data processing', 3, 'Processing is split: the server stores and counts, the client draws the card, the chart and the reader.'),
    ('Performance', 3, 'The technical specification sets response times, but the load of a personal tracker is small.'),
    ('Heavily used configuration', 3, 'The program must run on a server with 1 GB of memory, so the stack was trimmed for it.'),
    ('Transaction rate', 2, 'A reader makes a few actions a day.'),
    ('On-line data entry', 5, 'All data are entered on-line; there is no batch input.'),
    ('End-user efficiency', 4, 'A phone interface, one task on a screen, quick buttons like “+10 pages”.'),
    ('On-line update', 4, 'Most logical files are updated on-line: progress, quotes, goals, posts, reports.'),
    ('Complex processing', 3, 'The streak, the forecast of the finish date, the parsing and sanitizing of EPUB, the anchors of quotes.'),
    ('Reusability', 2, 'One product, but its components (UI blocks, actions, services) are written to be reused inside it.'),
    ('Installation ease', 4, 'One command for development (`make up`), a script and a pipeline for the server.'),
    ('Operational ease', 3, 'A backup script and an automatic rollout exist; copies outside the server are made by hand.'),
    ('Multiple sites', 1, 'One production site and one language of the interface.'),
    ('Facilitate change', 4, 'Layers, small classes, more than two thousand tests, static analysis and a linter.'),
]


def rank(kind: str, files: int, elements: int) -> int:
    """0, 1 or 2 for low, average, high."""
    if kind in ('ILF', 'EIF'):
        row = 0 if files <= 1 else 1 if files <= 5 else 2
        col = 0 if elements <= 19 else 1 if elements <= 50 else 2
    elif kind == 'EI':
        row = 0 if files <= 1 else 1 if files == 2 else 2
        col = 0 if elements <= 4 else 1 if elements <= 15 else 2
    else:  # EO, EQ
        row = 0 if files <= 1 else 1 if files <= 3 else 2
        col = 0 if elements <= 4 else 1 if elements <= 19 else 2
    return MATRIX[row][col]


def function_points(inventory: dict) -> dict:
    counts = {k: [0, 0, 0] for k in WEIGHTS}
    for t in inventory['transactions']:
        counts[t['type']][rank(t['type'], len(t['ftrs']), len(t['dets']))] += 1
    for f in inventory['ilf']:
        counts['ILF'][rank('ILF', f['ret'], len(f['dets']))] += 1
    for f in inventory['eif']:
        counts['EIF'][rank('EIF', f['ret'], len(f['dets']))] += 1
    totals = {k: sum(c * w for c, w in zip(counts[k], WEIGHTS[k])) for k in WEIGHTS}
    ufp = sum(totals.values())
    gsc_sum = sum(v for _, v, _ in GSC)
    vaf = 0.65 + 0.01 * gsc_sum
    algorithms = len(inventory['algorithms'])
    feature_points = 4 * sum(counts['EI']) + 5 * sum(counts['EO']) + 4 * sum(counts['EQ']) + 7 * sum(counts['ILF']) \
        + 7 * sum(counts['EIF']) + 3 * algorithms
    return {'counts': counts, 'totals': totals, 'ufp': ufp, 'gsc_sum': gsc_sum, 'vaf': vaf, 'fp': ufp * vaf,
            'algorithms': algorithms, 'feature_points': feature_points, 'feature_points_adj': feature_points * vaf,
            'n_transactions': len(inventory['transactions'])}


def main() -> dict:
    schedule = calculate()
    loc = json.loads((HERE / 'loc.json').read_text(encoding='utf-8'))
    inventory = json.loads((HERE / 'fp_inventory.json').read_text(encoding='utf-8'))

    days = schedule['sum_days']
    effort = days / DAYS_PER_MONTH
    kloc = loc['total']['lines'] / 1000
    pages = sum(DOC_PAGES.values())
    errors = sum(ERRORS.values())
    cost = effort * RATE

    stages = {'design': 0, 'code': 0, 'accept': 0}
    for w in schedule['works']:
        stages[w['stage']] += w['days']
    rule = {'design': 0.40, 'code': 0.20, 'test': 0.40}

    groups = []
    by_code = {w['code']: w for w in schedule['works']}
    for key, g in loc['groups'].items():
        g_days = sum(by_code[c]['days'] for c in GROUP_WORKS[key])
        g_effort = g_days / DAYS_PER_MONTH
        groups.append({'key': key, 'name': g['name'], 'works': ', '.join(GROUP_WORKS[key]), 'files': g['files'],
                       'lines': g['lines'], 'days': g_days, 'effort': g_effort, 'cost': g_effort * RATE,
                       'productivity': g['lines'] / g_effort, 'cost_per_loc': g_effort * RATE / g['lines']})
    fp = function_points(inventory)

    result = {
        'schedule': schedule, 'loc': loc['total'], 'kloc': kloc, 'days': days, 'effort': effort, 'cost': cost,
        'people': PEOPLE, 'pages': pages, 'doc_pages': DOC_PAGES, 'errors': errors, 'error_sources': ERRORS,
        'stages': stages, 'rule': rule, 'groups': groups, 'fp': fp, 'gsc': GSC,
        'loc_metrics': {
            'productivity_loc': loc['total']['lines'] / effort, 'productivity_kloc': kloc / effort,
            'cost_per_loc': cost / loc['total']['lines'], 'cost_per_kloc': cost / kloc,
            'doc_per_kloc': pages / kloc, 'errors_per_kloc': errors / kloc, 'kloc_per_person': kloc / PEOPLE,
            'test_share': TEST_LINES / (TEST_LINES + loc['total']['lines']),
        },
        'fp_metrics': {
            'productivity': fp['fp'] / effort, 'cost_per_fp': cost / fp['fp'], 'doc_per_fp': pages / fp['fp'],
            'errors_per_fp': errors / fp['fp'], 'loc_per_fp': loc['total']['lines'] / fp['fp'],
            'loc_from_fp_java': fp['fp'] * LOC_PER_FP['Java'], 'ratio_java': fp['fp'] * LOC_PER_FP['Java'] / loc['total']['lines'],
        },
        'loc_per_fp_table': LOC_PER_FP,
        'tests_lines': TEST_LINES,
    }
    (HERE / 'results.json').write_text(json.dumps(result, indent=1, ensure_ascii=False), encoding='utf-8')
    return result


if __name__ == '__main__':
    r = main()
    print('days', r['days'], 'effort', round(r['effort'], 2), 'kloc', round(r['kloc'], 3), 'cost', round(r['cost']))
    print('loc metrics', {k: round(v, 3) for k, v in r['loc_metrics'].items()})
    print('stages', r['stages'])
    fp = r['fp']
    print('counts', fp['counts'], 'totals', fp['totals'], 'UFP', fp['ufp'], 'gsc', fp['gsc_sum'], 'VAF', round(fp['vaf'], 2),
          'FP', round(fp['fp'], 1), 'feature', fp['feature_points'], round(fp['feature_points_adj'], 1))
    print('fp metrics', {k: round(v, 3) for k, v in r['fp_metrics'].items()})
    for g in r['groups']:
        print(g['key'], g['days'], round(g['effort'], 2), g['lines'], round(g['productivity']), round(g['cost']))
