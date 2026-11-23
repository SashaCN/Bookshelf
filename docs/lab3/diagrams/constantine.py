"""Structure charts of Constantine of Bookshelf (laboratory work 3, section 10).

    python3 docs/lab3/diagrams/constantine.py            # draws every figure
    python3 docs/lab3/diagrams/constantine.py progress   # draws only the figures whose name contains "progress"

The figures are written to docs/lab3/figures/: cc_legend, cc_progress, cc_import, cc_epub, cc_quote, cc_position, cc_stats.

The symbols of the vertices (module, subsystem, library, data area) are those of docs/tools/diagram.py. The calls and the
couplings are drawn here, with the same notation (a diamond at the caller for a conditional call, a circle for a call in
a loop, a bar for a coroutine; a circle at the tail of a thin arrow for a coupling: empty for data, filled for control),
but with larger marks and with the labels set on the outer side of the arrow, because the marks of `c_call` and `c_couple`
(radius 4 units) are too small to tell an empty circle from a filled one on paper, and `c_couple` puts a label of a
downward arrow over the call line.

Reading the labels of the couplings: a parameter of an elementary type is written by its name in lower case (`page`);
a composite one (a model, an array, an object) by the name of its class (`UserBook`). A coupling that carries at least one
composite parameter is a coupling by stamp, otherwise it is a coupling by data.
"""

from __future__ import annotations

import math
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'docs' / 'tools'))

from diagram import (BLUE, GREEN, GREY, LIGHT, WHITE, YELLOW, Canvas, c_data_area, c_library,  # noqa: E402
                     c_module, c_subsystem)

FIGURES = ROOT / 'docs' / 'lab3' / 'figures'

# The fill of a vertex shows the layer of the server part it belongs to.
LAYER = {
    'controller': BLUE,
    'request': YELLOW,
    'action': GREEN,
    'service': GREY,
    'model': LIGHT,
    'stub': WHITE,
}

SIZE = 18
ACCESS = '#4a4a4a'


# ---- vertices -----------------------------------------------------------------------------------------------------

class Node:
    """A drawn vertex; the helpers give the points where lines enter or leave it."""

    def __init__(self, x: float, y: float, w: float, h: float, kind: str = 'module') -> None:
        self.x, self.y, self.w, self.h, self.kind = x, y, w, h, kind

    @property
    def cx(self) -> float:
        return self.x + self.w / 2

    @property
    def cy(self) -> float:
        return self.y + self.h / 2

    @property
    def bottom(self) -> float:
        return self.y + self.h

    @property
    def right(self) -> float:
        return self.x + self.w

    def b(self, dx: float = 0) -> tuple:
        return (self.cx + dx, self.y + self.h)

    def t(self, dx: float = 0) -> tuple:
        return (self.cx + dx, self.y)

    def l(self, dy: float = 0) -> tuple:  # noqa: E743
        return (self.x, self.cy + dy)

    def r(self, dy: float = 0) -> tuple:
        return (self.x + self.w, self.cy + dy)

    def edge(self, toward: tuple) -> tuple:
        """The point of the outline of an ellipse in the direction of `toward` (for data areas)."""
        dx, dy = toward[0] - self.cx, toward[1] - self.cy
        a, b = self.w / 2, self.h / 2
        k = 1 / math.sqrt((dx / a) ** 2 + (dy / b) ** 2) if (dx or dy) else 1
        return (self.cx + dx * k, self.cy + dy * k)


def module_width(c: Canvas, text: str, kind: str = 'module', repeated: bool = False, size: float = SIZE) -> float:
    """The width that `module` gives to a vertex with this text."""
    widest = max(c.measure(line, size) for line in text.split('\n'))
    return widest + {'module': 36, 'subsystem': 64, 'library': 36}[kind] + (16 if repeated else 0)


def module(c: Canvas, cx: float, y: float, text: str, kind: str = 'module', layer: str = 'service', w: float | None = None,
           h: float | None = None, size: float = SIZE, dashed: bool = False, repeated: bool = False) -> Node:
    """A module (`kind` 'module', 'subsystem' or 'library') whose width fits the text. `cx` is its centre. `repeated`
    marks a module that is drawn a second time in the figure (with a vertical bar near its left edge)."""
    lines = text.split('\n')
    widest = max(c.measure(line, size) for line in lines)
    extra = {'module': 36, 'subsystem': 64, 'library': 36}[kind] + (16 if repeated else 0)
    w = w or widest + extra
    line_h = size * 1.15
    h = h or max(56.0, len(lines) * line_h + (46 if kind == 'library' else 28))
    x = cx - w / 2
    fill = LAYER[layer]
    draw = {'module': c_module, 'subsystem': c_subsystem, 'library': c_library}[kind]
    if dashed:
        c.rect(x, y, w, h, fill, 'black', 2, dash=(9, 6))
        c.text_in(x, y, w, h, text, size, pad=8)
    elif repeated:
        draw(c, x + 16, y, w - 16, h, text, fill, size)
        c.rect(x, y, 16, h, fill, 'black', 2)
    else:
        draw(c, x, y, w, h, text, fill, size)
    return Node(x, y, w, h, kind)


def area(c: Canvas, cx: float, y: float, text: str, w: float | None = None, h: float | None = None,
         size: float = SIZE, repeated: bool = False) -> Node:
    """A data area: an ellipse. `repeated` marks an area that is drawn more than once in a figure (to keep the lines
    short) with a vertical bar near the left edge, as the stores are marked in the data flow diagrams."""
    lines = text.split('\n')
    widest = max(c.measure(line, size) for line in lines)
    w = w or widest / 0.78 + 18
    h = h or max(60.0, len(lines) * size * 1.15 / 0.72 + 14)
    x = cx - w / 2
    c_data_area(c, x, y, w, h, text, WHITE, size)
    if repeated:
        bar = x + w * 0.09
        half = (h / 2) * math.sqrt(max(0.0, 1 - ((bar - (x + w / 2)) / (w / 2)) ** 2))
        c.polyline([(bar, y + h / 2 - half), (bar, y + h / 2 + half)], 1.8)
    return Node(x, y, w, h, 'area')


def frame(c: Canvas, x: float, y: float, w: float, h: float, title: str, at: str = 'top') -> None:
    """A dashed frame around the modules of one part of the system, with its name on the upper or lower edge."""
    c.rect(x, y, w, h, None, '#555555', 1.6, dash=(10, 6))
    tw = c.measure(title, SIZE, italic=True) + 16
    ty = y if at == 'top' else y + h
    c.rect(x + 18, ty - 13, tw, 26, WHITE, None, 0)
    c.text(x + 26, ty, title, SIZE, italic=True, anchor='lm')


def note(c: Canvas, x: float, y: float, w: float, text: str, size: float = SIZE) -> float:
    """A note in a dashed box; returns its height."""
    lines = c.wrap(text, w - 24, size)
    h = len(lines) * size * 1.18 + 22
    c.rect(x, y, w, h, '#FFFEF5', '#555555', 1.4, dash=(7, 5))
    c.text(x + 12, y + 11, '\n'.join(lines), size, anchor='lt', align='left', leading=1.18)
    return h


# ---- calls and couplings ------------------------------------------------------------------------------------------

def _unit(p: tuple, q: tuple) -> tuple:
    dx, dy = q[0] - p[0], q[1] - p[1]
    n = math.hypot(dx, dy) or 1
    return dx / n, dy / n


def call(c: Canvas, a: tuple, b: tuple, kind: str = 'seq', cond: bool = False, loop: bool = False,
         once: bool = False) -> tuple:
    """A call from the point `a` of the caller to the point `b` of the called module. `kind`: 'seq' (solid), 'par' (dashed)
    or 'co' (a coroutine: a bar across the line near its start). `cond` puts a diamond at the caller, `loop` a circle.
    Returns the point where the line really starts (after the mark) and the point where it ends."""
    x1, y1 = a
    start = a
    if cond:
        w, h = 26, 32
        c.diamond(x1, y1 + h / 2, w, h, WHITE, 'black', 2)
        start = (x1, y1 + h)
    elif loop:
        r = 12
        cx, cy = x1, y1 + r
        ux, uy = _unit((cx, cy), b)
        c.circle(cx, cy, r, WHITE, 'black', 2)
        start = (cx + ux * r, cy + uy * r)
    ux, uy = _unit(start, b)
    c.arrow([start, b], width=2, dash=(9, 6) if kind == 'par' else None, head_len=14, head_w=10)
    if kind == 'co':
        px, py = start[0] + ux * 16, start[1] + uy * 16
        nx, ny = -uy, ux
        c.polyline([(px + nx * 9, py + ny * 9), (px - nx * 9, py - ny * 9)], 3)
    if once:
        c.text(x1 + 14, y1 + 20, '1', 16, bold=True, anchor='lm')
    return start, b


def couple(c: Canvas, line: tuple, label: str, side: str = 'L', down: bool = True, control: bool = False,
           t: float = 0.5, off: float = 22, shift: tuple = (0, 0), lead: float = 20, tail: float = 16,
           head: float = 4, span: tuple = (0.0, 1.0)) -> None:
    """A coupling next to a call. `line` is the pair returned by `call`. The thin arrow runs parallel to the call, at the
    distance `off` on the side `side` ('L' is the left of the page, 'R' the right, for a call that goes down), and has a
    small circle at the tail: empty for a coupling by data (or by stamp), filled for a coupling by control. `down` is the
    direction of the flow: True from the caller to the called module (parameters), False back (results). The label is put
    at the fraction `t` of the arrow on its outer side."""
    s, e = line
    ux, uy = _unit(s, e)
    n = (-uy, ux) if side == 'L' else (uy, -ux)
    near = lead if down else lead - 6   # the end near the caller
    far = head if down else tail         # the end near the called module
    if uy > 0.2:
        # Keep both ends of the arrow clear of the boxes: on a slanted call the offset moves an end into a box.
        near = max(near, (16 - n[1] * off) / uy)
        far = max(far, (14 + n[1] * off) / uy)
    a = (s[0] + n[0] * off + ux * near, s[1] + n[1] * off + uy * near)
    z = (e[0] + n[0] * off - ux * far, e[1] + n[1] * off - uy * far)
    a, z = ((a[0] + (z[0] - a[0]) * span[0], a[1] + (z[1] - a[1]) * span[0]),
            (a[0] + (z[0] - a[0]) * span[1], a[1] + (z[1] - a[1]) * span[1]))
    p1, p2 = (a, z) if down else (z, a)
    dx, dy = _unit(p1, p2)
    c.arrow([(p1[0] + dx * 6, p1[1] + dy * 6), p2], width=1.5, head_len=11, head_w=8)
    c.circle(p1[0], p1[1], 6, 'black' if control else WHITE, 'black', 1.6)
    m = (a[0] + (z[0] - a[0]) * t + n[0] * 9 + shift[0], a[1] + (z[1] - a[1]) * t + n[1] * 9 + shift[1])
    if n[0] < 0:
        c.text(m[0], m[1], label, SIZE, italic=True, anchor='rm', align='right')
    else:
        c.text(m[0], m[1], label, SIZE, italic=True, anchor='lm', align='left')


def access(c: Canvas, p: tuple, q: tuple, mode: str = 'rw') -> None:
    """Access of a module (`p` on its outline) to a data area (`q` on its outline): a dotted line with an arrowhead at the
    end that data flows to ('r': to the module, 'w': to the area, 'rw': both)."""
    head = {'r': 'start', 'w': 'end', 'rw': 'both'}[mode]
    c.arrow([p, q], width=1.7, head=head, color=ACCESS, dash=(3, 5), head_len=12, head_w=9)


def link(c: Canvas, parent: Node, child: Node, pdx: float = 0, cdx: float = 0, down: str | None = None,
         up: str | None = None, control_down: str | None = None, kind: str = 'seq', cond: bool = False,
         loop: bool = False, once: bool = False, down_side: str = 'L', up_side: str = 'R', down_t: float = 0.5,
         up_t: float = 0.5, control_t: float = 0.5, down_shift: tuple = (0, 0), up_shift: tuple = (0, 0),
         control_shift: tuple = (0, 0), control_side: str = 'R', down_off: float = 22, up_off: float = 22,
         control_off: float = 22, down_span: tuple = (0.0, 1.0), up_span: tuple = (0.0, 1.0),
         control_span: tuple = (0.0, 1.0), order: int | None = None, tag: str | None = None,
         tag_side: str = 'L', tag_t: float = 0.5) -> tuple:
    """A call from the bottom of `parent` to the top of `child` with its couplings: `down` the parameters by data or
    stamp, `control_down` the parameter by control, `up` the result."""
    for node, dx in ((parent, pdx), (child, cdx)):
        if abs(dx) > node.w / 2 + 0.5:
            print('warning: a call leaves the box at', round(dx), 'but the box is only', round(node.w), 'wide')
    line = call(c, parent.b(pdx), child.t(cdx), kind, cond, loop, once)
    if order is not None:
        # the order of the calls of one caller, written beside the start of the call
        ux, uy = _unit(*line)
        sx, sy = line[0]
        c.text(sx + 14, sy + 24, str(order), SIZE, bold=True, anchor='lm')
    if tag:
        ux, uy = _unit(*line)
        n = (-uy, ux) if tag_side == 'L' else (uy, -ux)
        mx = line[0][0] + (line[1][0] - line[0][0]) * tag_t + n[0] * 14
        my = line[0][1] + (line[1][1] - line[0][1]) * tag_t + n[1] * 14
        c.text(mx, my, tag, SIZE, bold=True, anchor='rm' if n[0] < 0 else 'lm')
    if down:
        couple(c, line, down, down_side, True, False, down_t, off=down_off, shift=down_shift, span=down_span)
    if control_down:
        couple(c, line, control_down, control_side, True, True, control_t, off=control_off, shift=control_shift,
               span=control_span)
    if up:
        couple(c, line, up, up_side, False, False, up_t, off=up_off, shift=up_shift, span=up_span)
    return line


def title(c: Canvas, text: str, y: float = 24) -> None:
    c.text(c.w / 2, y, text, 20, bold=True)


# ---- figures ------------------------------------------------------------------------------------------------------

def legend() -> None:
    """The notation: vertices, calls, couplings, the fills."""
    c = Canvas(1150, 1010)
    wrap_w = 285

    def text_at(x: float, y: float, text: str) -> None:
        c.text(x, y, '\n'.join(c.wrap(text, wrap_w, SIZE)), SIZE, anchor='lm', align='left')

    # ---- left column: vertices, data areas, frames, fills
    x0, tx = 30, 235
    c.text(x0, 28, 'Vertices and data', 20, bold=True, anchor='lm')
    y = 62
    module(c, x0 + 90, y, 'Class::method', 'module', 'service', w=180, h=50)
    text_at(tx + 45, y + 25, 'Module: a method or a function.')
    y += 70
    module(c, x0 + 90, y, 'Class::method', 'subsystem', 'service', w=180, h=50)
    text_at(tx + 45, y + 25, 'Subsystem: a module whose inner structure is drawn in another figure or not at all.')
    y += 82
    module(c, x0 + 90, y, 'Class::method', 'library', 'service', w=180, h=56)
    text_at(tx + 45, y + 28, 'Library: a function of a class of helpers that several modules call.')
    y += 82
    module(c, x0 + 90, y, 'Class::method', 'library', 'service', w=180, h=56, repeated=True)
    text_at(tx + 45, y + 28, 'A bar at the left edge: the same module drawn again. Its calls are drawn where it first appears.')
    y += 82
    module(c, x0 + 90, y, 'Class::method', 'module', 'stub', w=180, h=50, dashed=True)
    text_at(tx + 45, y + 25, 'A dashed outline: a module of another figure, drawn only as the caller.')
    y += 78
    area(c, x0 + 50, y, 'table', w=100, h=56)
    area(c, x0 + 165, y, 'table', w=100, h=56, repeated=True)
    text_at(tx + 45, y + 28, 'Data area: a table of the database, the cache or a file. A bar: the same area drawn again.')
    y += 78
    m = Node(x0, y, 180, 44)
    c_module(c, m.x, m.y, m.w, m.h, 'Class::method', GREY, SIZE)
    ar = area(c, x0 + 90, y + 100, 'table', w=130, h=48)
    access(c, (m.x + 45, m.bottom), (m.x + 45, ar.y + 3), 'r')
    access(c, (m.x + 135, m.bottom), (m.x + 135, ar.y + 3), 'w')
    text_at(tx + 45, y + 70, 'Access to a data area: a dotted arrow. Read (left): it points to the module. Write (right): it points to the area.')
    y += 166
    frame(c, x0, y + 14, 180, 54, 'class')
    text_at(tx + 45, y + 41, 'A frame: the modules of one class.')
    y += 96
    c.text(x0, y, 'Fill of a vertex: the layer of the server part', 20, bold=True, anchor='lm')
    fy = y + 30
    for i, (name, layer) in enumerate([('controller', 'controller'), ('form request', 'request'), ('action', 'action'),
                                       ('service, library', 'service'), ('model', 'model')]):
        fx = x0 + (i % 2) * 260
        fyy = fy + (i // 2) * 44
        c.rect(fx, fyy, 52, 30, LAYER[layer], 'black', 2)
        c.text(fx + 66, fyy + 15, name, SIZE, anchor='lm')

    # ---- right column: calls and couplings
    x1 = 610
    c.text(x1, 28, 'Calls', 20, bold=True, anchor='lm')
    y = 52
    items = [
        ('Sequential call. Called modules of one caller are called from left to right.', {}),
        ('Parallel call (dashed). Bookshelf has none; shown to complete the notation.', {'kind': 'par'}),
        ('Conditional call: a diamond at the caller.', {'cond': True}),
        ('Call in a loop: a circle at the caller.', {'loop': True}),
        ('Coroutine: a bar across the line. A generator that gives one chapter at a time.', {'kind': 'co'}),
    ]
    for text, opts in items:
        a = (x1 + 40, y)
        b = (x1 + 40, y + 62)
        call(c, a, b, **opts)
        c.text(x1 + 85, y + 33, '\n'.join(c.wrap(text, 400, SIZE)), SIZE, anchor='lm', align='left')
        y += 84
    c.text(x1, y + 6, 'Couplings (next to a call)', 20, bold=True, anchor='lm')
    y += 34
    items = [
        ('Data: elementary parameters (lower case: page, path). Empty circle at the sender.', False, 'page'),
        ('Stamp: a composite parameter, named by its class (UserBook). Empty circle too.', False, 'UserBook'),
        ('Control: a parameter that steers the called module. Filled circle.', True, 'ReaderError'),
    ]
    for text, control, label in items:
        a = (x1 + 140, y)
        b = (x1 + 140, y + 80)
        call(c, a, b)
        couple(c, (a, b), label, 'L', True, control, 0.5)
        c.text(x1 + 185, y + 40, '\n'.join(c.wrap(text, 330, SIZE)), SIZE, anchor='lm', align='left')
        y += 100
    text = ('A coupling arrow that points back to the caller carries the result of the call. A label that starts with ? '
            '(?path) is a result that may be null.')
    c.text(x1, y + 8, '\n'.join(c.wrap(text, 520, SIZE)), SIZE, anchor='lt', align='left')
    c.save(FIGURES / 'cc_legend.png')


def progress() -> None:
    """Update of reading progress (process 3.0)."""
    c = Canvas(1150, 850)
    ctrl = module(c, 575, 20, 'ReadingProgressController::store', layer='controller')
    req = module(c, 165, 250, 'UpdateProgressRequest', layer='request')
    upd = module(c, 575, 250, 'UpdateProgress::handle', layer='action')
    stat = module(c, 985, 250, 'ReadingStatistics::streak', layer='service')
    pol = module(c, 165, 500, 'UserBookPolicy::update', layer='request')
    chg = module(c, 420, 500, 'ChangeUserBookStatus::apply', layer='action')
    rec = module(c, 900, 500, 'RecordReadingLog::handle', layer='action')

    link(c, ctrl, req, pdx=-120, up='UpdateProgressRequest', up_t=0.78)
    link(c, ctrl, upd, down='UserBook, page', up='ProgressUpdate')
    link(c, ctrl, stat, pdx=120, down='User', up='streak array')
    link(c, req, pol, cdx=0, down='User, UserBook', up='Response')
    link(c, upd, chg, pdx=-90, cdx=10, cond=True, down='UserBook', control_down='BookStatus::Reading',
         down_side='R', control_side='L', control_t=0.7)
    link(c, upd, rec, pdx=90, cdx=-60, down='UserBook,\nfromPage, toPage', up='?ReadingLog',
         down_side='R', up_side='L')

    books = area(c, 575, 700, 'user_books (D3)', w=210)
    users = area(c, 800, 700, 'users (D1)', w=170)
    logs = area(c, 1030, 700, 'reading_logs (D4)', w=220)
    access(c, upd.b(), books.t(), 'rw')
    access(c, rec.b(-60), users.edge((830, 640)), 'r')
    access(c, rec.b(60), logs.edge((980, 640)), 'w')
    access(c, stat.b(60), logs.edge((1045, 640)), 'r')
    note(c, 20, 590, 430, 'ChangeUserBookStatus::apply is called only when the page differs from current_page and the '
                          'status is not reading; it changes the UserBook it receives and does not save it. '
                          'RecordReadingLog::handle is always called and returns null when the page did not change. '
                          'UpdateProgress::handle runs in one transaction and reads the row of user_books with '
                          'lockForUpdate, so two quick taps are handled one after the other.')
    c.save(FIGURES / 'cc_progress.png')


def quote() -> None:
    """Saving a quote selected in the text (processes 5.0 and 6.0)."""
    c = Canvas(1150, 990)
    ctrl = module(c, 575, 20, 'QuoteController::store', layer='controller')
    req = module(c, 165, 250, 'StoreQuoteRequest', layer='request')
    save = module(c, 575, 250, 'SaveQuote::create', layer='action')
    res = module(c, 700, 510, 'ResolveQuoteAnchor::handle', layer='action')
    ofh = module(c, 300, 770, 'Text::ofHtml', 'library', 'service')
    slc = module(c, 640, 770, 'Text::slice', 'library', 'service')
    skl = module(c, 960, 770, 'Text::skeleton', 'library', 'service')

    link(c, ctrl, req, pdx=-80, up='StoreQuoteRequest', up_t=0.78)
    link(c, ctrl, save, down='UserBook, data', up='Quote')
    link(c, save, res, pdx=80, cdx=-60, cond=True, down='UserBook, anchor,\ncontent', up='anchor columns',
         down_side='L', up_side='R')
    link(c, res, ofh, pdx=-110, down='html', up='text', up_t=0.72)
    link(c, res, slc, pdx=0, down='text, start, length', up='?selected', down_t=0.8)
    link(c, res, skl, pdx=110, down='text', up='skeleton')

    quotes = area(c, 165, 480, 'quotes (D5)', w=190)
    texts = area(c, 985, 400, 'book_texts (D7)', w=220)
    chapters = area(c, 985, 490, 'book_chapters (D7)', w=250)
    access(c, save.b(-60), quotes.edge((300, 500)), 'w')
    access(c, res.r(-14), texts.edge((850, 480)), 'r')
    access(c, res.r(14), chapters.edge((850, 540)), 'r')
    c.text(960, 842, 'called twice: for the selected\ntext and for the quote', SIZE, anchor='mt', align='center')
    note(c, 20, 900, 1110, 'StoreQuoteRequest checks access through UserBookPolicy::update, as in the chart of the progress '
                           'update. SaveQuote::create also refuses a page above total_pages (its private method).')
    c.save(FIGURES / 'cc_quote.png')


def position() -> None:
    """Saving the reading position (process 6.0), which raises the page through the progress update."""
    c = Canvas(1150, 900)
    ctrl = module(c, 575, 20, 'ReadingPositionController::update', layer='controller')
    req = module(c, 175, 230, 'SaveReadingPositionRequest', layer='request')
    act = module(c, 575, 230, 'SaveReadingPosition::handle', layer='action')
    prog = module(c, 340, 470, 'SaveReadingPosition::progressAt', layer='action')
    raise_ = module(c, 850, 470, 'SaveReadingPosition::raisePage', layer='action')
    upd = module(c, 850, 710, 'UpdateProgress::handle', 'subsystem', 'action')

    link(c, ctrl, req, pdx=-125, up='SaveReadingPositionRequest', up_t=0.78)
    link(c, ctrl, act, down='BookText, chapter,\nchapterProgress', up='PositionSaved')
    link(c, act, prog, pdx=-100, cdx=30, down='BookText, chapter,\nchapterProgress', up='progress', down_side='L', up_side='R')
    link(c, act, raise_, pdx=100, cdx=-30, down='UserBook, progress', up='UserBook', down_side='R', up_side='L')
    link(c, raise_, upd, cond=True, down='UserBook, page', up='ProgressUpdate')

    texts = area(c, 420, 620, 'book_texts (D7)', w=220)
    entries = area(c, 620, 700, 'user_books (D3)', w=210)
    chapters = area(c, 140, 620, 'book_chapters (D7)', w=250)
    access(c, act.b(-30), texts.edge((520, 620)), 'w')
    access(c, act.b(30), entries.edge((620, 700)), 'r')
    access(c, prog.b(-90), chapters.edge((240, 620)), 'r')
    note(c, 20, 790, 400, 'The lock of the row of user_books is taken once in SaveReadingPosition::handle and again '
                          'in UpdateProgress::handle, inside the same transaction.')
    c.save(FIGURES / 'cc_position.png')


def import_book() -> None:
    """Import of an EPUB book (process 6.0)."""
    c = Canvas(1750, 880)
    ctrl = module(c, 880, 20, 'BookTextController::store', layer='controller')
    req = module(c, 200, 240, 'StoreBookTextRequest', layer='request')
    imp = module(c, 880, 240, 'ImportBookText::handle', layer='action', w=420)
    busy = module(c, 250, 540, 'ImportBusyException', layer='action')
    opn = module(c, 560, 540, 'EpubImporter::open', 'subsystem', 'service')
    dele = module(c, 850, 540, 'DeleteBookText::handle', layer='action')
    chap = module(c, 1150, 540, 'EpubBook::chapters', 'subsystem', 'service')
    ins = module(c, 1480, 540, 'BookChapter::insert', layer='model')

    link(c, ctrl, req, pdx=-95, up='StoreBookTextRequest', up_t=0.82)
    link(c, ctrl, imp, down='UserBook, path,\nsourceName', up='BookText')
    link(c, imp, busy, pdx=-190, cdx=30, cond=True)
    c.text(410, 455, 'the lock is not obtained\nin 5 seconds', SIZE, italic=True, anchor='rm', align='right')
    link(c, imp, opn, pdx=-110, cdx=20, down='path', up='EpubBook', down_side='L', up_side='R', up_t=0.3)
    link(c, imp, dele, pdx=-20, cdx=-10, down='UserBook', down_t=0.72)
    link(c, imp, chap, pdx=80, cdx=-20, kind='co', loop=True, up='ParsedChapter', up_side='R')
    link(c, imp, ins, pdx=170, cdx=-20, loop=True, down='columns array', down_side='R')

    cache = area(c, 1580, 150, 'cache: lock\n"epub-import"', w=220, h=70)
    books = area(c, 1580, 250, 'user_books (D3)', w=220)
    texts_r = area(c, 1580, 340, 'book_texts (D7)', w=220, repeated=True)
    afile = area(c, 470, 780, 'uploaded file\n(temporary)', w=210, h=70)
    texts = area(c, 780, 790, 'book_texts (D7)', w=210, repeated=True)
    quotes = area(c, 1030, 790, 'quotes (D5)', w=190)
    chapters = area(c, 1480, 790, 'book_chapters (D7)', w=250)
    access(c, imp.r(-25), cache.edge((1400, 190)), 'rw')
    access(c, imp.r(0), books.edge((1400, 290)), 'r')
    access(c, imp.r(25), texts_r.edge((1400, 380)), 'rw')
    access(c, opn.b(-40), afile.edge((500, 780)), 'r')
    access(c, dele.b(-50), texts.edge((800, 790)), 'rw')
    access(c, dele.b(50), quotes.edge((1010, 790)), 'w')
    access(c, ins.b(), chapters.t(), 'w')
    note(c, 1010, 20, 420, 'The whole import runs in one transaction. EpubBook::chapters is a generator: it gives one '
                           'chapter, which is stored before the next one is parsed.')
    c.save(FIGURES / 'cc_import.png')


def row(c: Canvas, specs: list, y: float, x0: float, gap: float = 36) -> list:
    """Vertices in a row from the left edge `x0`; each spec is (text, kind, layer, repeated)."""
    nodes = []
    x = x0
    for text, kind, layer, repeated in specs:
        w = module_width(c, text, kind, repeated)
        nodes.append(module(c, x + w / 2, y, text, kind, layer, repeated=repeated))
        x += w + gap
    return nodes


def epub() -> None:
    """The Epub service: the two subsystems of the import of an EPUB book, drawn in full."""
    c = Canvas(1750, 950)
    stub = module(c, 875, 20, 'ImportBookText::handle', layer='stub', dashed=True)
    opn = module(c, 390, 210, 'EpubImporter::open', layer='service')
    chap = module(c, 1380, 210, 'EpubBook::chapters', layer='service')

    arc = module(c, 120, 450, 'EpubArchive::open', layer='service')
    anc = module(c, 420, 450, 'EpubImporter::assertNotEncrypted', layer='service')
    pkg = module(c, 850, 450, 'PackageReader::read', 'subsystem', 'service')
    rd, prs, rd_p, prs_p, rsv, txt = row(c, [
        ('EpubArchive::read', 'library', 'service', False), ('SafeXml::parse', 'library', 'service', False),
        ('EpubArchive::read', 'library', 'service', True), ('SafeXml::parse', 'library', 'service', True),
        ('EpubPaths::resolve', 'library', 'service', False), ('Text::collapse', 'library', 'service', False)],
        800, 236, gap=48)

    rd2 = module(c, 1110, 450, 'EpubArchive::read', 'library', 'service', repeated=True)
    prs2 = module(c, 1350, 450, 'SafeXml::parse', 'library', 'service', repeated=True)
    san = module(c, 1610, 450, 'HtmlSanitizer::sanitize', 'subsystem', 'service')
    txt2 = module(c, 1625, 800, 'Text::ofHtml, length,\ncollapse', 'library', 'service', repeated=True)

    link(c, stub, opn, pdx=-75, down='path', up='EpubBook')
    link(c, stub, chap, pdx=75, kind='co', loop=True, up='ParsedChapter', up_side='R')
    link(c, opn, arc, pdx=-80, down='path, EpubLimits', up='EpubArchive', down_side='L', up_side='R', up_t=0.3)
    link(c, opn, anc, pdx=-20, cdx=-20, down='EpubArchive', down_side='L', down_t=0.8)
    link(c, opn, pkg, pdx=80, cdx=-30, down='EpubArchive', up='EpubPackage', down_side='L', up_side='R')
    read_opts = dict(down='name, maxBytes', control_down='ReaderError', down_span=(0.02, 0.5),
                     control_span=(0.5, 1.0), control_side='L')
    link(c, anc, rd, pdx=-70, cdx=20, up='?string', **read_opts)
    link(c, anc, prs, pdx=60, cdx=-20, cond=True, down='xml', up='DOMDocument')
    link(c, pkg, rd_p, pdx=-70, cdx=20, up='?string', up_t=0.8, **read_opts)
    link(c, pkg, prs_p, pdx=-20, cdx=-10, down='xml', down_t=0.8, up='DOMDocument', up_t=0.93)
    link(c, pkg, rsv, pdx=40, loop=True, down='base, reference', down_t=0.72, up='?path', up_t=0.62)
    link(c, pkg, txt, pdx=90, loop=True, down='label', down_t=0.85, up='text', up_t=0.55)
    link(c, chap, rd2, pdx=-85, loop=True, up='?string', **read_opts)
    link(c, chap, prs2, pdx=0, loop=True, down='xml', up='?DOMDocument', up_t=0.3)
    link(c, chap, san, pdx=85, loop=True, down='DOMDocument', down_t=0.75, up='SanitizedChapter')
    link(c, san, txt2, down='html', up='text, length')

    afile = area(c, 105, 860, 'uploaded file', w=190, h=64)
    access(c, arc.b(-30), afile.edge((110, 800)), 'r')
    access(c, rd.l(), afile.edge((230, 860)), 'r')
    c.save(FIGURES / 'cc_epub.png')


def drop(c: Canvas, parent: Node, child: Node, **kw) -> tuple:
    """A vertical call: the line leaves the parent right above the centre of the child."""
    return link(c, parent, child, pdx=child.cx - parent.cx, **kw)


def stats() -> None:
    """Reading statistics and the annual goal (process 4.0)."""
    c = Canvas(1750, 940)
    xs = [110, 380, 650, 920, 1190, 1450]
    summ = module(c, 780, 20, 'StatsController::summary', layer='controller', w=1420)
    # --- the methods of ReadingStatistics that the controller calls, in the order of the calls
    names = ['today()', 'pagesBetween()', 'streak()', 'pace()', 'booksFinished()', 'forecasts()']
    r1 = [module(c, x, 170, n, 'library', 'service') for x, n in zip(xs, names)]
    td, pb, st, pa, bf, fc = r1
    dp = module(c, xs[1], 330, 'dailyPages()', 'library', 'service')
    pb2 = module(c, xs[3], 330, 'pagesBetween()', 'library', 'service', repeated=True)
    pa2 = module(c, xs[5], 330, 'pace()', 'library', 'service', repeated=True)
    frame(c, 20, 148, 1545, 272, 'class ReadingStatistics (the User is given to its constructor)', at='bottom')

    drop(c, summ, td, up='CarbonImmutable')
    drop(c, summ, pb, down='from, to', up='pages')
    drop(c, summ, st, up='streak array')
    drop(c, summ, pa, up='pace')
    drop(c, summ, bf, down='from, to', up='count')
    link(c, summ, fc, pdx=fc.cx - summ.cx, up='forecast list')
    drop(c, pb, dp, down='from, to', up='days array')
    link(c, pa, pb2, down='from, to', up='pages')
    link(c, fc, pa2, down=None, up='pace')

    logs = area(c, 520, 470, 'reading_logs (D4)', w=220)
    books = area(c, 1190, 470, 'user_books (D3)', w=210)
    logs2 = area(c, 1652, 175, 'reading_logs (D4)', w=170, repeated=True)
    access(c, dp.b(30), logs.edge((500, 470)), 'r')
    access(c, st.b(), logs.edge((600, 470)), 'r')
    access(c, bf.b(), books.t(), 'r')
    access(c, fc.b(-60), books.edge((1280, 470)), 'r')
    access(c, fc.r(), logs2.edge((1560, 198)), 'r')

    # --- the daily chart and the goal: the same methods, drawn again with a bar
    daily = module(c, 300, 590, 'StatsController::daily', layer='controller')
    goal = module(c, 1000, 590, 'GoalController::show', layer='controller')
    gp = module(c, 1000, 730, 'GoalProgress::handle', layer='service')
    td2 = module(c, 150, 760, 'today()', 'library', 'service', repeated=True)
    dp2 = module(c, 470, 760, 'dailyPages()', 'library', 'service', repeated=True)
    td3 = module(c, 780, 860, 'today()', 'library', 'service', repeated=True)
    bf2 = module(c, 1220, 860, 'booksFinished()', 'library', 'service', repeated=True)
    link(c, daily, td2, pdx=-70, cdx=0, cond=True, up='CarbonImmutable', up_side='L')
    link(c, daily, dp2, pdx=70, cdx=0, down='from, to', up='days array')
    link(c, goal, gp, down='User, year', up='goal array')
    link(c, gp, td3, pdx=-60, cdx=0, up='CarbonImmutable', up_side='L')
    link(c, gp, bf2, pdx=60, cdx=0, down='from, to', down_side='R', up='count', up_side='L')
    note(c, 20, 850, 600, 'streak(), pace() and forecasts() also call today(); these calls are not drawn. '
                          'GoalController::update stores the goal in reading_goals and then calls GoalProgress::handle '
                          'in the same way.')
    goals = area(c, 1450, 740, 'reading_goals (D6)', w=230)
    access(c, gp.r(), goals.edge((1300, 770)), 'r')
    c.save(FIGURES / 'cc_stats.png')


BUILDERS = {'legend': legend, 'progress': progress, 'quote': quote, 'position': position, 'import': import_book, 'epub': epub, 'stats': stats}


def main() -> None:
    wanted = sys.argv[1:]
    for name, fn in BUILDERS.items():
        if not wanted or any(w in name for w in wanted):
            fn()
            print('drawn', name)


if __name__ == '__main__':
    main()
