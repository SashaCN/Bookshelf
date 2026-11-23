"""The figures of the report on laboratory work 7, drawn from results.json with Pillow (python3 docs/lab7/figures.py)."""

from __future__ import annotations

import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent / 'tools'))

from diagram import BLUE, GREEN, GREY, LIGHT, RED, YELLOW, WHITE, Canvas  # noqa: E402

FIG = HERE / 'figures'


def load() -> dict:
    return json.loads((HERE / 'results.json').read_text(encoding='utf-8'))


def _bins(values: list, edges: list) -> list:
    """Counts of the values by bins; `edges` are the lower bounds, the last bin is open ("16+")."""
    counts = [0] * len(edges)
    for v in values:
        index = max(i for i, e in enumerate(edges) if v >= e)
        counts[index] += 1
    labels = []
    for i, e in enumerate(edges):
        if i == len(edges) - 1:
            labels.append(f'{e}+')
        elif edges[i + 1] - 1 == e:
            labels.append(str(e))
        else:
            labels.append(f'{e}-{edges[i + 1] - 1}')
    return list(zip(labels, counts))


def _histogram(c: Canvas, x: float, y: float, w: float, h: float, title: str, data: list, fill: str) -> None:
    c.text(x + w / 2, y - 26, title, 19, bold=True)
    peak = max(n for _, n in data)
    tick = 5 if peak <= 25 else 10 if peak <= 60 else 20
    top = (int(peak * 1.12) // tick + 1) * tick
    left = x + 44
    bottom = y + h - 34
    area_h = bottom - y
    c.line([(left, y), (left, bottom), (x + w, bottom)], 1.5)
    for step in range(0, top + 1, tick):
        yy = bottom - area_h * step / top
        c.line([(left - 5, yy), (left, yy)], 1.2)
        c.text(left - 9, yy, str(step), 14, anchor='rm')
    slot = (x + w - left) / len(data)
    for i, (label, n) in enumerate(data):
        bx = left + slot * i + slot * 0.16
        bw = slot * 0.68
        bh = area_h * n / top
        c.rect(bx, bottom - bh, bw, bh, fill, 'black', 1.2)
        if n:
            c.text(bx + bw / 2, bottom - bh - 9, str(n), 14)
        c.text(bx + bw / 2, bottom + 15, label, 14)


def histograms(r: dict) -> None:
    cl = [x for x in r['classes'] if x['kind'] in ('class', 'enum')]
    c = Canvas(1100, 760)
    specs = [
        ('WMC (methods per class)', [x['wmc'] for x in cl], [0, 1, 2, 3, 4, 5, 6, 8, 11, 16], BLUE),
        ('CBO (coupled classes)', [x['cbo'] for x in cl], [0, 1, 2, 3, 4, 5, 6, 7, 8, 10, 13, 16], GREEN),
        ('RFC (response for a class)', [x['rfc'] for x in cl], [0, 1, 4, 7, 10, 13, 16, 20, 25, 30, 40], YELLOW),
        ('LCOM4 (groups of methods)', [x['lcom4'] for x in cl], [0, 1, 2, 3, 4, 5, 6, 8], RED),
    ]
    for index, (title, values, edges, fill) in enumerate(specs):
        col, row = index % 2, index // 2
        _histogram(c, 40 + col * 540, 60 + row * 370, 490, 290, title, _bins(values, edges), fill)
    c.text(550, 745, 'Horizontal: value of the metric. Vertical: number of classes (145 in all).', 15, italic=True)
    c.save(FIG / 'histograms.png')


def categories(r: dict) -> None:
    cats = [(k, v) for k, v in r['by_category'].items()]
    cats.sort(key=lambda kv: -kv[1]['n'])
    row_h = 40
    height = 90 + row_h * len(cats)
    c = Canvas(1100, height)
    cols = [('WMC', 'wmc', 7, BLUE), ('CBO', 'cbo', 12, GREEN), ('RFC', 'rfc', 18, YELLOW)]
    label_w = 190
    panel_w = 230
    for ci, (title, key, scale_max, fill) in enumerate(cols):
        x0 = label_w + ci * (panel_w + 70)
        c.text(x0 + panel_w / 2, 28, f'Mean {title}', 19, bold=True)
        for i, (name, v) in enumerate(cats):
            y = 60 + i * row_h
            val = v[key]['mean']
            bw = panel_w * min(val, scale_max) / scale_max
            c.rect(x0, y + 6, bw, row_h - 14, fill, 'black', 1.2)
            c.text(x0 + bw + 8, y + row_h / 2 - 1, f'{val:.1f}', 15, anchor='lm')
        c.line([(x0, 56), (x0, 60 + len(cats) * row_h)], 1.5)
    for i, (name, v) in enumerate(cats):
        y = 60 + i * row_h
        c.text(label_w - 12, y + row_h / 2 - 1, f'{name} ({v["n"]})', 16, anchor='rm')
    c.save(FIG / 'categories.png')


def inheritance(r: dict) -> None:
    cl = [x for x in r['classes'] if x['kind'] in ('class', 'enum')]
    families: dict = {}
    for x in cl:
        if x['dit'] == 0:
            continue
        key = tuple(x['chain'])
        if x['chain'] and x['chain'][0].startswith('App\\') and len(x['chain']) == 1:
            pass
        families.setdefault(key, []).append(x)
    rows = sorted(families.items(), key=lambda kv: -len(kv[1]))
    c = Canvas(1100, 90 + 84 * len(rows))
    c.text(550, 26, 'Inheritance chains of the classes of Bookshelf (the arrow points to the parent)', 19, bold=True)
    for i, (chain, members) in enumerate(rows):
        y = 60 + i * 84
        cats = sorted({m['category'] for m in members})
        label = f'{len(members)} ' + ('classes' if len(members) > 1 else 'class') + f'\n({", ".join(cats)})'
        c.box_text(20, y, 220, 64, label, BLUE, 16)
        x = 20 + 220
        for depth, parent in enumerate(chain):
            c.arrow([(x, y + 32), (x + 36, y + 32)], 1.6, head_len=11, head_w=8)
            x += 36
            app = parent.startswith('App\\')
            short = parent.split('\\')[-1]
            vendor = '\\'.join(parent.split('\\')[:-1]) or 'PHP built-in'
            w = 250 if depth < 3 else 250
            c.box_text(x, y, w, 64, short + ('\n(in the app)' if app else '\n' + vendor), BLUE if app else GREY, 15)
            x += w
    c.save(FIG / 'inheritance.png')


def main() -> None:
    r = load()
    FIG.mkdir(exist_ok=True)
    histograms(r)
    categories(r)
    inheritance(r)
    print('figures written')


if __name__ == '__main__':
    main()
