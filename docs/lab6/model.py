"""The COCOMO II model of Bookshelf and the analysis of its sensitivity (laboratory work 6, variant 19):
the Early Design model, the scenario "use of a new microprocessor". Writes `results.json`.

    python3 docs/lab6/model.py

Equations (the methodology, COCOMO II.1997 and the Model Definition Manual, version 1.4):
    PM   = A * SIZE**B * product(EM)              A = 2.5, SIZE in thousands of lines, no generated code
    B    = 1.01 + 0.01 * sum(W)                   five scale factors, 5 (very low) ... 0 (extra high)
    TDEV = 3.0 * PM**(0.33 + 0.2 * (B - 1.01))    months, the nominal schedule
    cost = PM * 1000                              dollars, a junior developer in Ukraine (DOU survey, 2025: 900-930)
The Early Design drivers are made from the Post-Architecture ratings (VL=1, L=2, N=3, H=4, VH=5, XH=6): the ratings of
the parts are summed and the sum is put on the scale of the Early Design driver (Manual, Tables 13, 14, 16, 17, 18).
"""

from __future__ import annotations

import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
LAB5 = HERE.parent / 'lab5'

A = 2.5
RATE = 1000
LEVELS = ['XL', 'VL', 'L', 'N', 'H', 'VH', 'XH']      # the scale of the Early Design drivers

# Effort multipliers of the Early Design drivers, by level (None: the level does not exist).
EM = {
    'PERS': [2.12, 1.62, 1.26, 1.00, 0.83, 0.63, 0.50],
    'RCPX': [0.49, 0.60, 0.83, 1.00, 1.33, 1.91, 2.72],
    'RUSE': [None, None, 0.95, 1.00, 1.07, 1.15, 1.24],
    'PDIF': [None, None, 0.87, 1.00, 1.29, 1.81, 2.61],
    'PREX': [1.59, 1.33, 1.12, 1.00, 0.87, 0.71, 0.62],
    'FCIL': [1.43, 1.30, 1.10, 1.00, 0.87, 0.73, 0.62],
    'SCED': [None, 1.43, 1.14, 1.00, 1.00, 1.00, None],
}

# The sum of the Post-Architecture ratings -> the level of the Early Design driver (index in LEVELS).
SUM_TO_LEVEL = {
    'PERS': {3: 0, 4: 0, 5: 1, 6: 1, 7: 2, 8: 2, 9: 3, 10: 4, 11: 4, 12: 5, 13: 5, 14: 6, 15: 6},
    'PREX': {3: 0, 4: 0, 5: 1, 6: 1, 7: 2, 8: 2, 9: 3, 10: 4, 11: 4, 12: 5, 13: 5, 14: 6, 15: 6},
    'RCPX': {5: 0, 6: 0, 7: 1, 8: 1, 9: 2, 10: 2, 11: 2, 12: 3, 13: 4, 14: 4, 15: 4, 16: 5, 17: 5, 18: 5, 19: 6, 20: 6, 21: 6},
    'PDIF': {8: 2, 9: 3, 10: 4, 11: 4, 12: 4, 13: 5, 14: 5, 15: 5, 16: 6, 17: 6},
    'FCIL': {2: 0, 3: 1, 4: 2, 5: 2, 6: 3, 7: 4, 8: 4, 9: 5, 10: 5, 11: 6},
}
PARTS = {'PERS': ['ACAP', 'PCAP', 'PCON'], 'RCPX': ['RELY', 'DATA', 'CPLX', 'DOCU'], 'PDIF': ['TIME', 'STOR', 'PVOL'],
         'PREX': ['AEXP', 'PEXP', 'LTEX'], 'FCIL': ['TOOL', 'SITE']}

# Post-Architecture multipliers of COCOMO II.1997 (Manual, Appendix D), for the cross-check: level 1..6 -> value.
PA = {'LTEX': {1: 1.22, 2: 1.10, 3: 1.00, 4: 0.91, 5: 0.84}, 'TOOL': {1: 1.24, 2: 1.12, 3: 1.00, 4: 0.86, 5: 0.72}}

# The ratings of Bookshelf (Post-Architecture numbers) and why.
RATINGS = {
    'ACAP': (3, 'Nominal', 'Analysts of the usual skill; the requirements were written and reviewed by the team.'),
    'PCAP': (3, 'Nominal', 'Students of the third year with a working knowledge of the languages.'),
    'PCON': (3, 'Nominal', 'The team of three is stable; no one leaves during the semester.'),
    'RELY': (3, 'Nominal', 'A failure is a nuisance for one reader, not a loss of money or life.'),
    'DATA': (3, 'Nominal', 'A small database of one reader’s library, quotes and posts.'),
    'CPLX': (3, 'Nominal', 'Mostly standard operations; the EPUB parser and the anchors of quotes are harder.'),
    'DOCU': (4, 'High', 'More documentation than the life cycle needs: the specification, the two designs, four guides.'),
    'TIME': (3, 'Nominal', 'The use of the processor stays under 50%.'),
    'STOR': (4, 'High', 'The program must live in 1 GB of memory together with the database, so the stack is trimmed.'),
    'PVOL': (3, 'Nominal', 'The platform (Docker images, PHP, Node) changes about once in six to twelve months.'),
    'AEXP': (3, 'Nominal', 'About a year of experience with applications of this kind.'),
    'PEXP': (3, 'Nominal', 'About a year of experience with the platform (Laravel, Vue, Docker).'),
    'LTEX': (3, 'Nominal', 'About a year with PHP and TypeScript and their tools.'),
    'TOOL': (4, 'High', 'A container stack, a pipeline, a linter, static analysis, test runners, an editor with checks.'),
    'SITE': (4, 'High', 'One city and one repository, with an exchange of messages and short meetings.'),
}
RUSE_LEVEL = 3    # nominal
SCED_LEVEL = 3    # nominal
# The five scale factors (5 very low ... 0 extra high) and why.
SCALE = [
    ('PREC', 'Precedentedness', 3, 'Nominal', 'The kind of product is known, but it is the first such product of the team.'),
    ('FLEX', 'Development flexibility', 2, 'High', 'The artefacts are fixed by the course, the process is chosen by the team.'),
    ('RESL', 'Architecture and risk resolution', 3, 'Nominal', 'The risks and the architecture were analysed in the specification and the designs.'),
    ('TEAM', 'Team cohesion', 2, 'High', 'The team knows each other and works in one repository.'),
    ('PMAT', 'Process maturity', 3, 'Nominal', 'Some management of the process (level 2 of CMM), as in the example of the methodology.'),
]


def early_ratings(post: dict) -> dict:
    """The levels (index in LEVELS) of the seven Early Design drivers for the Post-Architecture ratings `post`."""
    levels = {}
    for driver, parts in PARTS.items():
        levels[driver] = SUM_TO_LEVEL[driver][sum(post[p] for p in parts)]
    levels['RUSE'] = RUSE_LEVEL
    levels['SCED'] = SCED_LEVEL
    return levels


def multipliers(levels: dict) -> dict:
    return {d: EM[d][levels[d]] for d in EM}


def product(values: dict) -> float:
    result = 1.0
    for v in values.values():
        result *= v
    return result


def estimate(size_kloc: float, b: float, em_product: float) -> dict:
    pm = A * size_kloc ** b * em_product
    tdev = 3.0 * pm ** (0.33 + 0.2 * (b - 1.01))
    return {'pm': pm, 'cost': pm * RATE, 'tdev': tdev, 'staff': pm / tdev}


def run() -> dict:
    loc = json.loads((LAB5 / 'loc.json').read_text(encoding='utf-8'))
    lab5 = json.loads((LAB5 / 'results.json').read_text(encoding='utf-8'))
    size = loc['total']['lines'] / 1000
    w_sum = sum(w for _, _, w, _, _ in SCALE)
    b = 1.01 + 0.01 * w_sum

    post = {k: v[0] for k, v in RATINGS.items()}
    base_levels = early_ratings(post)
    base_em = multipliers(base_levels)
    base_product = product(base_em)
    base = estimate(size, b, base_product)

    # The scenarios. The methodology changes two drivers: the experience with the language and the tools of the new
    # processor goes from nominal to very low, and the tools for it are primitive (from high to very low).
    scenarios = []
    for key, name, changes in [
        ('S0', 'Base: the usual processor', {}),
        ('S1', 'New processor: only the experience (LTEX: nominal to very low)', {'LTEX': 1}),
        ('S2', 'New processor: only the tools (TOOL: high to very low)', {'TOOL': 1}),
        ('S3', 'New processor: both (the scenario of the variant)', {'LTEX': 1, 'TOOL': 1}),
    ]:
        p = dict(post)
        p.update(changes)
        levels = early_ratings(p)
        em = multipliers(levels)
        est = estimate(size, b, product(em))
        scenarios.append({'key': key, 'name': name, 'changes': changes, 'sums': {d: sum(p[x] for x in PARTS[d]) for d in PARTS},
                          'levels': {d: LEVELS[i] for d, i in levels.items()}, 'em': em, 'product': product(em), **est,
                          'd_pm': est['pm'] - base['pm'], 'd_pct': 100 * (est['pm'] / base['pm'] - 1),
                          'd_cost': est['cost'] - base['cost'], 'd_tdev': est['tdev'] - base['tdev']})

    # The cross-check with the Post-Architecture multipliers of the two drivers that the scenario changes.
    pa_before = PA['LTEX'][3] * PA['TOOL'][4]
    pa_after = PA['LTEX'][1] * PA['TOOL'][1]

    # Break-even: how much the new processor must save to pay for the extra effort.
    s3 = scenarios[3]

    # Sensitivity: every Early Design driver one level worse and one level better than the base.
    sens = []
    for d in EM:
        row = {'driver': d, 'level': LEVELS[base_levels[d]], 'em': base_em[d]}
        for tag, step in (('worse', +1 if d in ('RCPX', 'RUSE', 'PDIF') else -1),
                          ('better', -1 if d in ('RCPX', 'RUSE', 'PDIF') else +1)):
            index = base_levels[d] + step
            if 0 <= index < len(LEVELS) and EM[d][index] is not None:
                row[tag] = {'level': LEVELS[index], 'em': EM[d][index], 'pct': 100 * (EM[d][index] / base_em[d] - 1)}
            else:
                row[tag] = None
        sens.append(row)
    sens_scale = []
    for key, name, w, _, _ in SCALE:
        row = {'key': key, 'w': w}
        for tag, delta in (('worse', +1), ('better', -1)):
            if 0 <= w + delta <= 5:
                b2 = b + 0.01 * delta
                row[tag] = {'w': w + delta, 'b': b2, 'pct': 100 * (size ** b2 / size ** b - 1)}
            else:
                row[tag] = None
        sens_scale.append(row)
    sens_size = [{'pct': p, 'size': size * (1 + p / 100), **estimate(size * (1 + p / 100), b, base_product)}
                 for p in (-20, -10, 0, 10, 20)]
    fp_size = lab5['fp_metrics']['loc_from_fp_java'] / 1000
    fp_est = estimate(fp_size, b, base_product)

    result = {
        'size': size, 'fp_size': fp_size, 'w_sum': w_sum, 'b': b, 'post': post, 'ratings': RATINGS, 'scale': SCALE,
        'base_levels': {d: LEVELS[i] for d, i in base_levels.items()}, 'base_em': base_em, 'base_product': base_product,
        'base': base, 'scenarios': scenarios, 'pa_before': pa_before, 'pa_after': pa_after, 'pa_ratio': pa_after / pa_before,
        'sens': sens, 'sens_scale': sens_scale, 'sens_size': sens_size, 'fp_est': fp_est,
        'actual_effort': lab5['effort'], 'actual_days': lab5['days'], 'rate': RATE,
        'savings_credit': 100, 'savings_chip': 1000,
        'saving_needed_pct_of_credit': s3['d_cost'] / 100,
    }
    (HERE / 'results.json').write_text(json.dumps(result, indent=1, ensure_ascii=False), encoding='utf-8')
    return result


if __name__ == '__main__':
    r = run()
    print('size', round(r['size'], 3), 'B', round(r['b'], 2), 'base levels', r['base_levels'], 'product', round(r['base_product'], 4))
    print('base', {k: round(v, 2) for k, v in r['base'].items()})
    for s in r['scenarios']:
        print(s['key'], s['levels']['PREX'], s['levels']['FCIL'], round(s['product'], 4), round(s['pm'], 1), round(s['cost']),
              round(s['tdev'], 1), round(s['staff'], 1), 'd%', round(s['d_pct'], 1), 'dcost', round(s['d_cost']))
    print('PA ratio', round(r['pa_ratio'], 3), 'fp est', {k: round(v, 1) for k, v in r['fp_est'].items()})
    for s in r['sens']:
        print(s['driver'], s['level'], s['em'], s['worse'], s['better'])
    for s in r['sens_scale']:
        print(s['key'], s['w'], s['worse'], s['better'])
    for s in r['sens_size']:
        print(s['pct'], round(s['size'], 1), round(s['pm'], 1))
