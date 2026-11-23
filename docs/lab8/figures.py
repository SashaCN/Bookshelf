"""The figures of the report on laboratory work 8 (drawn with Pillow): python3 docs/lab8/figures.py"""

from __future__ import annotations

import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent / 'tools'))

from diagram import BLUE, GREEN, GREY, LIGHT, RED, WHITE, YELLOW, Canvas  # noqa: E402

OUT = HERE / 'figures'
LINE = 20.5  # height of one field line


def table_box(c: Canvas, x: float, y: float, w: float, title: str, note: str, fields: list, fill: str) -> tuple:
    """A table drawn as a box: a title, a short note (the method) and the list of fields. Returns (x, y, w, h)."""
    h = 62 + LINE * len(fields) + 8
    c.rect(x, y, w, h, WHITE, 'black', 2, 6)
    c.rect(x, y, w, 52, fill, 'black', 2, 6)
    c.text(x + w / 2, y + 17, title, 18, bold=True)
    c.text(x + w / 2, y + 38, note, 14, italic=True)
    for i, field in enumerate(fields):
        c.text(x + 12, y + 62 + LINE * i + LINE / 2, field, 15, mono=True, anchor='lm', align='left')
    return x, y, w, h


def tables_and_links() -> Path:
    c = Canvas(1100, 790)
    ident = table_box(c, 380, 20, 340, 'Identifier table', 'hash, overflow chains', [
        'name       key', 'kind', 'scope', 'type[]     -> Type', 'ref        -> Procedure |', '              Constant | Type |',
        '              Identifier', 'addr       frame slot', 'flags, initialized'], BLUE)
    proc = table_box(c, 20, 20, 300, 'Procedure table', 'hash, overflow chains', [
        'name       key (lower case)', 'kind       function|procedure', 'origin     builtin|user', 'params[]   type[] -> Type',
        'min, max', 'returns[]  -> Type', 'addr       code address', 'ident      -> Identifier'], GREEN)
    const = table_box(c, 780, 20, 300, 'Constant table', 'hash, overflow chains', [
        'name       key', 'kind       predefined|user|', '              literal', 'type       -> Type', 'value'], YELLOW)
    types = table_box(c, 380, 390, 340, 'Type table', 'records + sorted index, binary search', [
        'name, key', 'kind       scalar|compound|', '              special|class ...', 'size', 'declarable, builtin', 'parent     -> Type', 'members'], GREY)
    ops = table_box(c, 20, 390, 300, 'Operation table', 'static, open addressing', [
        'lexeme, fix   key', 'name', 'arity', 'prec, assoc', 'operands[] -> Type', 'result     -> Type', 'gen        code generator'], RED)
    labels = table_box(c, 780, 390, 300, 'Label table', 'unordered, linear search', [
        'name       key', 'defined', 'kind       goto|generated', 'addr       -> Code', 'line', 'refs[]     -> Code'], LIGHT)
    c.box_text(380, 690, 340, 80, 'Generated code (instruction addresses)', GREY, 16, bold=False, radius=6)

    def link(points: list, text: str, at: tuple, head: str = 'end', anchor: str = 'mm') -> None:
        c.arrow(points, 2, head)
        c.rect(at[0] - c.measure(text, 14) / 2 - 4, at[1] - 10, c.measure(text, 14) + 8, 20, WHITE, None, 0, 0)
        c.text(at[0], at[1], text, 14, anchor=anchor)

    link([(720, 105), (780, 105)], 'ref', (750, 88))
    link([(380, 105), (320, 105)], 'ref', (350, 88), head='both')
    link([(550, 20 + ident[3]), (550, 390)], 'type, ref (class)', (550, 340))
    link([(170, 20 + proc[3]), (170, 330), (440, 330), (440, 390)], 'params, returns', (260, 314))
    link([(930, 20 + const[3]), (930, 365), (660, 365), (660, 390)], 'type', (790, 349))
    link([(320, 520), (380, 520)], 'type', (350, 503))
    link([(930, 20 + labels[3] + 370), (930, 730), (720, 730)], 'addr, refs', (930, 660))
    c.text(550, 785, 'A pointer is the number (id) of a record of another table.', 13, italic=True, anchor='mb')
    return c.save(OUT / 'tables_and_links.png')


def chains() -> Path:
    c = Canvas(1160, 420)
    c.text(580, 24, 'Identifier table: buckets and overflow chains (newest record first)', 17, bold=True)
    names = [['$x#3', '$x#0'], [], ['class'], ['$i#7', 'strlen', '$item#5'], ['total'], []]
    for b, chain in enumerate(names):
        y = 60 + b * 58
        c.rect(30, y, 70, 40, GREY, 'black', 2, 4)
        c.text(65, y + 20, f'[{b}]', 16, mono=True)
        px = 100
        for name in chain:
            c.arrow([(px, y + 20), (px + 34, y + 20)], 2)
            c.rect(px + 34, y, 130, 40, WHITE, 'black', 2, 4)
            c.text(px + 99, y + 20, name, 15, mono=True)
            px += 164
        if chain:
            c.text(px + 14, y + 20, 'end', 14, italic=True, anchor='lm', align='left')
    c.box_text(800, 70, 340, 100, 'A new record goes to the head of its chain: a search meets the newest record first, so the inner $x hides the outer $x.', LIGHT, 14, radius=6)
    c.box_text(800, 190, 340, 100, 'Scope exit: the records of the scope are deleted one by one; the ids of the other records do not change.', LIGHT, 14, radius=6)
    c.box_text(800, 310, 340, 90, 'Reorganization: a new (prime) number of buckets and all chains are built again, oldest record first.', LIGHT, 14, radius=6)
    return c.save(OUT / 'chains.png')


if __name__ == '__main__':
    print(tables_and_links(), chains(), sep='\n')
