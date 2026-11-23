"""The network schedule of laboratory work 2 (Table 2 of that report), calculated again for laboratory work 5.

Forward pass: ES = the largest EF of the predecessors, EF = ES + duration. Backward pass: LF = the smallest LS of the
successors, LS = LF - duration. The total reserve is LS - ES.
"""

from __future__ import annotations

WORKS = [
    # code, name, duration in working days, predecessors, stage of the 40-20-40 rule
    ('A', 'Technical specification', 5, '', 'design'),
    ('B', 'Preliminary design', 5, 'A', 'design'),
    ('C', 'Skeleton, Docker, CI', 5, 'B', 'code'),
    ('D', 'Authentication', 4, 'C', 'code'),
    ('E', 'Library server and Open Library', 6, 'D', 'code'),
    ('F', 'Library screens', 6, 'D', 'code'),
    ('G', 'First deployment', 3, 'E F', 'code'),
    ('H', 'Progress server and log', 4, 'E', 'code'),
    ('I', 'Progress screen', 5, 'F H', 'code'),
    ('J', 'Quotes and quote card', 7, 'E F', 'code'),
    ('K', 'Statistics and annual goal', 7, 'H', 'code'),
    ('N', 'Community: server', 8, 'E J', 'code'),
    ('O', 'Community: screens', 8, 'N F', 'code'),
    ('P', 'Moderation and abuse protection', 4, 'N', 'code'),
    ('Q', 'Reading: EPUB import', 8, 'E', 'code'),
    ('R', 'Reading: screens and progress', 9, 'Q F J', 'code'),
    ('L', 'PWA and polishing', 4, 'I K O R', 'code'),
    ('M', 'Acceptance and documentation', 5, 'G L P', 'accept'),
]


def calculate() -> dict:
    works = {c: {'code': c, 'name': n, 'days': d, 'pred': p.split(), 'stage': s} for c, n, d, p, s in WORKS}
    for code, w in works.items():  # the list is in topological order
        w['es'] = max((works[p]['ef'] for p in w['pred']), default=0)
        w['ef'] = w['es'] + w['days']
    total = max(w['ef'] for w in works.values())
    for code in reversed(list(works)):
        w = works[code]
        successors = [s for s in works.values() if code in s['pred']]
        w['lf'] = min((s['ls'] for s in successors), default=total)
        w['ls'] = w['lf'] - w['days']
        w['slack'] = w['ls'] - w['es']
    return {'works': list(works.values()), 'total': total, 'sum_days': sum(w['days'] for w in works.values()),
            'critical': [w['code'] for w in works.values() if w['slack'] == 0]}


if __name__ == '__main__':
    r = calculate()
    for w in r['works']:
        print(w['code'], w['days'], w['es'], w['ef'], w['ls'], w['lf'], w['slack'])
    print(r['total'], r['sum_days'], r['critical'])
