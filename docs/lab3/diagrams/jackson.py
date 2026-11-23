"""Structure charts of Jackson of Bookshelf (laboratory work 3, section 11).

    python3 docs/lab3/diagrams/jackson.py            # draws every figure
    python3 docs/lab3/diagrams/jackson.py streak     # draws only the figures whose name contains "streak"

The figures are written to docs/lab3/figures/: jk_legend, jk_import, jk_streak, jk_report.

Notation (the methodology, "Structure charts of Jackson"): an operation is a box; a sequence is a box with the parts under
it, read from left to right; in a selection every alternative has a small circle (o) in its top right corner; the box of
an iteration has an asterisk (*) in the corner. `j_tree` of docs/tools/diagram.py draws the lines of the tree. The boxes
are drawn here (not with `j_box`), because the boxes of these charts also need a mark of a procedure (a vertical line on
each side), a fill for the alternative that stops the program, and a wrapping of the text that keeps clear of the marks.
A figure is a stack of rows; a row holds one or more panels (a tree with a title and, in the legend, a note).
"""

from __future__ import annotations

import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parents[1] / 'tools'))

from diagram import BLUE, GREEN, RED, WHITE, YELLOW, Canvas, j_tree  # noqa: E402

FIGURES = HERE.parent / 'figures'

SIZE = 18  # the size of the text in every box, in units
LEAD = 1.15  # the line height of `Canvas.text` is SIZE * LEAD
LINE_H = SIZE * LEAD
RULE = '#9A9A9A'


# ---- the tree ----------------------------------------------------------------------------------------------------

class Node:
    """A box of a chart.

    `kind`: 'seq' (no mark), 'sel' (an alternative of a selection: the circle) or 'iter' (the repeated box: the asterisk).
    The children of a node are its parts: the node is a sequence when they are plain, a selection when they are marked 'sel'.
    `stop` fills the box with red (the alternative ends the program with an error); `proc` draws a vertical line on each
    side (a procedure block). `fill` is the fill when the panel does not give one.
    """

    def __init__(self, text: str, kind: str = 'seq', children: list | None = None, stop: bool = False,
                 proc: bool = False, w: float | None = None, fill: str | None = None) -> None:
        self.text = text
        self.kind = kind
        self.children = children or []
        self.stop = stop
        self.proc = proc
        self.w = w
        self.fill = fill
        self.x = self.y = self.h = 0.0
        self.depth = 0
        self.lines: list = []

    def walk(self):
        yield self
        for child in self.children:
            yield from child.walk()


def _limits(node: Node, w: float) -> tuple:
    """Widths available for the first line and for the other lines of the text of a box. The first line of a marked box
    must stay clear of the mark in the corner (the text is centred, so the room is lost on both sides)."""
    proc = 16 if node.proc else 0
    if node.kind in ('sel', 'iter'):
        first = w - (54 if not node.proc else 70)
    else:
        first = w - 16 - proc
    return first, w - 16 - proc


def fit_lines(c: Canvas, node: Node, w: float) -> list:
    """The lines of the text of a box of width `w` at the full size. The first line of a marked box is kept short, so
    that it does not run into the mark in the corner. Raises an error when a word does not fit."""
    first, rest = _limits(node, w)
    lines: list = []
    for paragraph in node.text.split('\n'):
        current = ''
        for word in paragraph.split(' '):
            limit = first if not lines else rest
            trial = word if not current else current + ' ' + word
            if current and c.measure(trial, SIZE) > limit:
                lines.append(current)
                current = word
            else:
                current = trial
        lines.append(current)
    for i, line in enumerate(lines):
        limit = first if i == 0 else rest
        if c.measure(line, SIZE) > limit + 0.01:
            raise ValueError(f'"{line}" does not fit into a box {w} wide ({node.text!r})')
    return lines


def layout(c: Canvas, root: Node, leaf_w: float = 140, gap: float = 14, vgap: float = 24, min_h: float = 56) -> tuple:
    """Gives every box of the tree its place (x from 0, y from 0). Returns the width and the height of the tree."""
    for n in root.walk():
        if n.w is None:
            n.w = leaf_w

    def span(n: Node) -> float:
        if not n.children:
            return n.w
        return max(n.w, sum(span(k) for k in n.children) + gap * (len(n.children) - 1))

    def place(n: Node, left: float, depth: int) -> None:
        n.depth = depth
        s = span(n)
        if not n.children:
            n.x = left + (s - n.w) / 2
            return
        total = sum(span(k) for k in n.children) + gap * (len(n.children) - 1)
        x = left + (s - total) / 2
        for k in n.children:
            place(k, x, depth + 1)
            x += span(k) + gap
        first, last = n.children[0], n.children[-1]
        centre = ((first.x + first.w / 2) + (last.x + last.w / 2)) / 2
        n.x = min(max(centre - n.w / 2, left), left + s - n.w)

    place(root, 0, 0)
    levels = max(n.depth for n in root.walk()) + 1
    heights = [min_h] * levels
    for n in root.walk():
        n.lines = fit_lines(c, n, n.w)
        need = len(n.lines) * LINE_H + 14
        heights[n.depth] = max(heights[n.depth], 2 * int(-(-need // 2)))
    tops = []
    y = 0.0
    for h in heights:
        tops.append(y)
        y += h + vgap
    for n in root.walk():
        n.y = tops[n.depth]
        n.h = heights[n.depth]
    return span(root), y - vgap


def draw_box(c: Canvas, n: Node, ox: float, oy: float, fill: str) -> None:
    x, y, w, h = n.x + ox, n.y + oy, n.w, n.h
    c.rect(x, y, w, h, RED if n.stop else (n.fill or fill), 'black', 2)
    if n.proc:
        c.polyline([(x + 9, y), (x + 9, y + h)], 1.6)
        c.polyline([(x + w - 9, y), (x + w - 9, y + h)], 1.6)
    c.text(x + w / 2, y + h / 2, '\n'.join(n.lines), SIZE, anchor='mm')
    mx = x + w - (13 if not n.proc else 21)
    my = y + 13
    if n.kind == 'sel':
        c.circle(mx, my, 6, WHITE, 'black', 1.6)
    elif n.kind == 'iter':
        for dx, dy in ((0, 7), (6.1, 3.5), (6.1, -3.5)):  # the three strokes of an asterisk
            c.polyline([(mx - dx, my - dy), (mx + dx, my + dy)], 2)


def draw_tree(c: Canvas, root: Node, ox: float, oy: float, fill: str = WHITE, root_fill: str | None = None,
              vgap: float = 24) -> None:
    for n in root.walk():
        draw_box(c, n, ox, oy, root_fill if (n is root and root_fill) else fill)
    for n in root.walk():
        if n.children:
            j_tree(c, (n.x + ox, n.y + oy, n.w, n.h), [(k.x + ox, k.y + oy, k.w, k.h) for k in n.children], drop=vgap / 2)


# ---- the figure: rows of panels ----------------------------------------------------------------------------------

class Panel:
    """A tree with a bold title above it and, optionally, a note below it. `fill` is the fill of its boxes."""

    def __init__(self, title: str, root: Node, fill: str = WHITE, root_fill: str | None = None, note: str = '',
                 min_w: float = 0, leaf_w: float = 140, gap: float = 14, vgap: float = 24) -> None:
        self.title, self.root, self.fill, self.root_fill, self.note = title, root, fill, root_fill, note
        self.min_w, self.leaf_w, self.gap, self.vgap = min_w, leaf_w, gap, vgap


def _prepare(c: Canvas, panel: Panel) -> None:
    tree_w, tree_h = layout(c, panel.root, panel.leaf_w, panel.gap, panel.vgap)
    title_w = c.measure(panel.title, 20, bold=True)
    panel.tree_w, panel.tree_h = tree_w, tree_h
    panel.w = max(tree_w, title_w, panel.min_w)
    panel.note_lines = c.wrap(panel.note, panel.w, SIZE) if panel.note else []
    panel.h = 28 + tree_h + (14 + len(panel.note_lines) * LINE_H if panel.note else 0)


def draw_figure(name: str, rows: list, width: float = 1100, margin: float = 16, row_gap: float = 28) -> Path:
    """Draws rows of panels and saves figures/<name>.png. `rows` is a list of lists of panels."""
    scratch = Canvas(10, 10)
    for row in rows:
        for panel in row:
            _prepare(scratch, panel)
    total = margin
    row_heights = []
    for row in rows:
        row_h = max(p.h for p in row)
        used = sum(p.w for p in row)
        if used + 2 * margin > width + 0.01:
            raise ValueError(f'{name}: a row is {used + 2 * margin:.0f} units wide, the figure is {width:.0f}')
        row_heights.append(row_h)
        total += row_h + row_gap
    height = total - row_gap + margin
    c = Canvas(width, height)
    y = margin
    for index, row in enumerate(rows):
        if index:
            c.polyline([(margin, y - row_gap / 2), (width - margin, y - row_gap / 2)], 1.2, RULE)
        free = width - 2 * margin - sum(p.w for p in row)
        space = free / (len(row) + 1)
        x = margin + space
        for panel in row:
            title_x = margin if len(row) == 1 else x
            c.text(title_x, y + 12, panel.title, 20, bold=True, anchor='lm')
            ox = x + (panel.w - panel.tree_w) / 2
            draw_tree(c, panel.root, ox, y + 28, panel.fill, panel.root_fill, panel.vgap)
            if panel.note:
                c.text(x, y + 28 + panel.tree_h + 14, '\n'.join(panel.note_lines), SIZE, anchor='lt', align='left')
            x += panel.w + space
        y += row_heights[index] + row_gap
    return c.save(FIGURES / f'{name}.png')


# ---- shorthands --------------------------------------------------------------------------------------------------

def N(text: str, *children: Node, **kw) -> Node:
    """A plain box (an operation, or a sequence when it has children)."""
    return Node(text, 'seq', list(children) + kw.pop('children', []), **kw)


def O(text: str, *children: Node, **kw) -> Node:
    """An alternative of a selection (the circle in the corner)."""
    return Node(text, 'sel', list(children) + kw.pop('children', []), **kw)


def S(text: str, *children: Node, **kw) -> Node:
    """A repeated box (the asterisk in the corner)."""
    return Node(text, 'iter', list(children) + kw.pop('children', []), **kw)


def STOP(text: str, **kw) -> Node:
    """An alternative of a selection that ends the program with an error."""
    return Node(text, 'sel', [], stop=True, **kw)


# ---- the legend --------------------------------------------------------------------------------------------------

def legend() -> Path:
    third = 345
    rows = [
        [
            Panel('(a) Operation', N('A', w=120), min_w=third,
                  note='A block of code with one entry and one exit.'),
            Panel('(b) Sequence', N('A', N('B', w=96), N('C', w=96), N('D', w=96), w=96), min_w=third,
                  note='B, C and D are executed once, from left to right. A box with plain parts is a sequence.'),
            Panel('(c) Selection', N('A', O('B', w=96), O('C', w=96), O('D', w=96), w=96), min_w=third,
                  note='One of B, C and D is executed, as a condition says. Every alternative has a circle (o).'),
        ],
        [
            Panel('(d) Iteration', N('A', S('B', w=96), w=96), min_w=third,
                  note='B is executed zero or more times, while a condition holds. The repeated box has an asterisk (*).'),
            Panel('(e) Procedure block', N('A', O('B', proc=True, w=110), O('C', w=96), w=96), min_w=third,
                  note='B is a procedure that is drawn as a chart of its own, or written under the chart. It has a line on each side.'),
            Panel('(f) Alternatives that end or do nothing',
                  N('A', STOP('Stop: error', w=130), O('nothing', w=124), w=96), min_w=third,
                  note='A red box ends the program with an error. The box "nothing" or "skip" does nothing.'),
        ],
    ]
    return draw_figure('jk_legend', rows)


# ---- program 1: reading the chapters of an EPUB book -------------------------------------------------------------

def import_figure() -> Path:
    spine = N('Spine of the book', w=210)
    spine.children = [S('Document', O('Missing or not XML'), O('XML without text'), O('XML with text'))]
    text = N('Stored text', N('BookText row'), S('BookChapter'), w=170)

    produce = N('Produce the chapters of a book', w=250, children=[
        S('Document of the spine',
          N('Read and clean up the document', w=170),
          N('Skip or hand over', O('No document: skip', w=170), O('No text: skip'), O('Chapter: hand over', proc=True, w=170)),
          w=210),
    ])
    hand = N('Hand over the chapter', w=230, children=[
        N('Check the limits', STOP('Stop: too many chapters'), STOP('Stop: text too large'),
          O('Within the limits: add the characters', w=160), w=150),
        N('Yield the chapter'),
        N('Move to the next position', w=150),
    ])
    store = N('Import the text of a book', w=250, children=[
        N('Open the book'),
        N('Prepare the text'),
        S('Chapter', N('Insert the chapter row'), N('Count the chapter'),
          N('Check the quota', STOP('Stop: over the quota'), O('Within the quota: nothing', w=150), w=150), w=180),
        N('Finish the text'),
    ])
    rows = [
        [Panel('(a) Input data: the spine', spine, GREEN, leaf_w=160),
         Panel('(b) Output data: the stored text', text, YELLOW, leaf_w=160)],
        [Panel('(c) EpubBook::chapters: produce the chapters', produce, root_fill=BLUE, leaf_w=150)],
        [Panel('(d) Procedure "Hand over the chapter"', hand, root_fill=BLUE, leaf_w=150)],
        [Panel('(e) ImportBookText::import: store the chapters', store, root_fill=BLUE, leaf_w=135, gap=12)],
    ]
    return draw_figure('jk_import', rows)


# ---- program 2: the reading streak -------------------------------------------------------------------------------

def streak_figure() -> Path:
    days = N('Input of the program', S('Reading day'), N('Today'), w=220)
    streak = N('Streak', N('current'), N('longest'), N('read_today'), w=150)

    top = N('Count the reading streak', w=260, children=[
        N('Get the reading days', w=170),
        N('Find the longest run', proc=True, w=170),
        N('Find the current streak', proc=True, w=170),
        N('Return the three values', w=170),
    ])
    longest = N('Find the longest run', w=220, children=[
        S('Reading day',
          N('Count the run', O('Follows the previous day: extend the run'), O('First day or after a gap: start a new run'), w=200),
          N('Update the longest run, remember the day'), w=190),
    ])
    current = N('Find the current streak', w=240, children=[
        N('Choose the first day', O('Today is a reading day: start today'), O('Not read today: start yesterday'), w=200),
        S('Count the day and step back', w=165),
    ])
    rows = [
        [Panel('(a) Input data', days, GREEN, leaf_w=150),
         Panel('(b) Output data', streak, YELLOW, leaf_w=150)],
        [Panel('(c) ReadingStatistics::streak', top, root_fill=BLUE, leaf_w=170)],
        [Panel('(d) Find the longest run', longest, root_fill=BLUE, leaf_w=165),
         Panel('(e) Find the current streak', current, root_fill=BLUE, leaf_w=165)],
    ]
    return draw_figure('jk_streak', rows)


# ---- program 3: report and automatic hiding ----------------------------------------------------------------------

def report_figure() -> Path:
    request = N('Report request', N('Reporter'), N('Content: post or comment'), N('Reason and note'), w=190)
    result = N('Result', O('Refusal: own content'), O('Refusal: already reported'), O('Report stored'),
               O('Report stored, content hidden'), w=150)

    report = N('Report content', w=190, children=[
        STOP('Stop: own content', w=140),
        STOP('Stop: already reported', w=150),
        O('New report', N('Lock the content', w=130), N('Create the report', w=130), N('Count the open reports', w=130),
          N('Hide at the threshold', O('Threshold reached: hide the content', proc=True, w=150), O('Below it: nothing', w=124)),
          w=190),
    ])
    hide = N('Hide the content', w=190, children=[
        N('Mark the content hidden', w=170),
        N('Correct the comment counter', O('Comment hidden now: decrement counter', w=170), O('Otherwise: nothing', w=170), w=190),
        N('Reload the content'),
    ])
    reports = N('List the open reports', w=210, children=[
        O('No open reports: print a note', w=160),
        O('Open reports', S('Report row', N('Take the cells of the report', w=150),
                            N('Take the state of the content', O('deleted', w=124), O('hidden', w=124), O('visible', w=124), w=170),
                            w=150),
          N('Print the table and the total', w=150), w=150),
    ])
    rows = [
        [Panel('(a) Input data', request, GREEN, leaf_w=150),
         Panel('(b) Output data', result, YELLOW, leaf_w=125)],
        [Panel('(c) ReportContent::handle', report, root_fill=BLUE, leaf_w=140, gap=12)],
        [Panel('(d) Procedure "Hide the content" (ModerateContent::hide)', hide, root_fill=BLUE, leaf_w=150)],
        [Panel('(e) ModerationReports::handle (the command moderation:reports)', reports, root_fill=BLUE, leaf_w=140)],
    ]
    return draw_figure('jk_report', rows)


FIGURE_FUNCTIONS = {'legend': legend, 'import': import_figure, 'streak': streak_figure, 'report': report_figure}


def main() -> None:
    wanted = sys.argv[1:]
    for key, function in FIGURE_FUNCTIONS.items():
        if not wanted or any(w in f'jk_{key}' for w in wanted):
            print('written', function())


if __name__ == '__main__':
    main()
