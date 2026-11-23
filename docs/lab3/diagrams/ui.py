"""Drawings of section 12 "User interface" of the technical design of Bookshelf.

    python3 docs/lab3/diagrams/ui.py            # all figures
    python3 docs/lab3/diagrams/ui.py ui_map     # one figure

The map of the screens (`ui_map`) is drawn with the library of the lab reports. The wireframes (`ui_main`, `ui_progress`,
`ui_stats`, `ui_reader`, `ui_community`, `ui_forms`, `ui_states`) are low-fidelity drawings of phones: every phone is a
`Screen` with its own canvas that is pasted into the figure, so whatever does not fit a screen (a scrolling list, a chip
row that goes on to the right) is cut at the edge of the screen like on a real phone. Everything on a screen is a
`Pane` call that adds one block under the previous one, in the order of the blocks of the real view.

Conventions of the wireframes: a solid grey block is always on the screen; a block with a dashed outline is shown only in
some cases (an error, a hint, a confirmation); grey bars stand for text that comes from the data (a quote, a post).
"""

from __future__ import annotations

import math
import sys
from pathlib import Path

from PIL import Image, ImageDraw

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parents[1] / 'tools'))

from diagram import BLUE, GREEN, GREY, LIGHT, RED, WHITE, YELLOW, Canvas  # noqa: E402

FIGURES = HERE.parent / 'figures'

# ---- palette of the wireframes ---------------------------------------------------------------------------------------
INK = '#111111'
MUTED = '#555555'
LINE = '#7F7F7F'
BLOCK = '#F0F0F0'
TRACK = '#D3D3D3'
BAR = '#CFCFCF'
PRIMARY = '#C5D3F3'
DANGER_TEXT = '#8A1C12'
MARK = '#FFE7A0'
SELECT = '#BFD3FF'
DIM = 100  # strength (0..255) of the veil behind a sheet

# ---- the phone -------------------------------------------------------------------------------------------------------
PW, PH = 400, 800  # the phone with its bezel
BEZEL = 12
SW, SH = PW - 2 * BEZEL, PH - 2 * BEZEL  # the screen
SCREEN_RADIUS = 30
TOP = 34  # room for the camera of the phone
PAD = 14
TAB_H = 66
S = 18  # the size of the text in the figures

DASH = (7, 5)


def ellipsize(c: Canvas, text: str, width: float, size: float = S, bold: bool = False) -> str:
    """Shortens a text with an ellipsis until it fits `width`."""
    if c.measure(text, size, bold) <= width:
        return text
    while text and c.measure(text + '…', size, bold) > width:
        text = text[:-1]
    return text.rstrip() + '…'


# ---- icons -----------------------------------------------------------------------------------------------------------

def star(c: Canvas, cx: float, cy: float, r: float, filled: bool) -> None:
    pts = []
    for i in range(10):
        radius = r if i % 2 == 0 else r * 0.45
        angle = -math.pi / 2 + i * math.pi / 5
        pts.append((cx + radius * math.cos(angle), cy + radius * math.sin(angle)))
    c.polygon(pts, fill=INK if filled else WHITE, outline=INK, width=1.6)


def heart(c: Canvas, cx: float, cy: float, s: float, filled: bool) -> None:
    pts = []
    for t in range(0, 360, 10):
        a = math.radians(t)
        x = 16 * math.sin(a) ** 3
        y = -(13 * math.cos(a) - 5 * math.cos(2 * a) - 2 * math.cos(3 * a) - math.cos(4 * a))
        pts.append((cx + x * s / 34, cy + y * s / 34))
    c.polygon(pts, fill=INK if filled else WHITE, outline=INK, width=1.6)


def bubble(c: Canvas, cx: float, cy: float, s: float) -> None:
    c.polygon([(cx - s / 2, cy - s / 2.4), (cx + s / 2, cy - s / 2.4), (cx + s / 2, cy + s / 4), (cx - s / 8, cy + s / 4),
               (cx - s / 3, cy + s / 2.2), (cx - s / 3, cy + s / 4), (cx - s / 2, cy + s / 4)], fill=WHITE, outline=INK, width=1.6)


def chevron(c: Canvas, cx: float, cy: float, s: float = 12, direction: str = 'left', width: float = 2.4) -> None:
    if direction == 'left':
        c.polyline([(cx + s / 3, cy - s / 1.5), (cx - s / 3, cy), (cx + s / 3, cy + s / 1.5)], width)
    elif direction == 'down':
        c.polyline([(cx - s / 1.5, cy - s / 3), (cx, cy + s / 3), (cx + s / 1.5, cy - s / 3)], width)


def dots(c: Canvas, cx: float, cy: float) -> None:
    for dx in (-9, 0, 9):
        c.circle(cx + dx, cy, 2.6, INK, INK, 1)


def cross(c: Canvas, cx: float, cy: float, s: float = 10) -> None:
    c.polyline([(cx - s, cy - s), (cx + s, cy + s)], 2.4)
    c.polyline([(cx + s, cy - s), (cx - s, cy + s)], 2.4)


def avatar(c: Canvas, cx: float, cy: float, d: float, letter: str) -> None:
    c.circle(cx, cy, d / 2, TRACK, LINE, 1.5)
    c.text(cx, cy + 1, letter, d * 0.45, bold=True)


def cover(c: Canvas, x: float, y: float, w: float, letter: str = 'B') -> None:
    h = w * 1.5
    c.rect(x, y, w, h, '#BDBDBD', LINE, 1.6, radius=3)
    c.text(x + w / 2, y + h / 2, letter, w * 0.5, bold=True, color='#FFFFFF')


def progress(c: Canvas, x: float, y: float, w: float, fraction: float, h: float = 10) -> None:
    c.rect(x, y, w, h, TRACK, None, 0, radius=h / 2)
    if fraction > 0:
        c.rect(x, y, max(h, w * fraction), h, DARK_FILL, None, 0, radius=h / 2)


DARK_FILL = '#4F4F4F'


def radio(c: Canvas, cx: float, cy: float, on: bool) -> None:
    c.circle(cx, cy, 9, WHITE, INK, 1.8)
    if on:
        c.circle(cx, cy, 4.5, INK, INK, 1)


def book_icon(c: Canvas, x: float, y: float, active: bool) -> None:
    w = 2.0 if active else 1.6
    c.polyline([(x - 11, y - 8), (x - 1, y - 6), (x - 1, y + 9), (x - 11, y + 7), (x - 11, y - 8)], w)
    c.polyline([(x + 11, y - 8), (x + 1, y - 6), (x + 1, y + 9), (x + 11, y + 7), (x + 11, y - 8)], w)


def shelf_icon(c: Canvas, x: float, y: float, active: bool) -> None:
    w = 2.0 if active else 1.6
    c.rect(x - 11, y - 9, 6, 18, None, INK, w)
    c.rect(x - 3, y - 6, 6, 15, None, INK, w)
    c.polyline([(x + 5, y - 6), (x + 11, y - 7), (x + 13, y + 8), (x + 7, y + 9), (x + 5, y - 6)], w)


def quote_icon(c: Canvas, x: float, y: float, active: bool) -> None:
    w = 2.0 if active else 1.6
    c.circle(x - 6, y - 2, 5, None, INK, w)
    c.circle(x + 7, y - 2, 5, None, INK, w)
    c.polyline([(x - 11, y - 1), (x - 11, y + 5), (x - 8, y + 9)], w)
    c.polyline([(x + 2, y - 1), (x + 2, y + 5), (x + 5, y + 9)], w)


def stats_icon(c: Canvas, x: float, y: float, active: bool) -> None:
    w = 2.0 if active else 1.6
    c.polyline([(x - 9, y + 9), (x - 9, y)], w + 1)
    c.polyline([(x, y + 9), (x, y - 9)], w + 1)
    c.polyline([(x + 9, y + 9), (x + 9, y - 3)], w + 1)


def people_icon(c: Canvas, x: float, y: float, active: bool) -> None:
    w = 2.0 if active else 1.6
    c.circle(x - 3, y - 4, 5, None, INK, w)
    c.polyline([(x - 11, y + 9), (x - 11, y + 5), (x - 3, y + 3), (x + 5, y + 5), (x + 5, y + 9)], w)
    c.circle(x + 7, y - 5, 4, None, INK, w)


TABS = [
    ('home', 'Reading', book_icon),
    ('library', 'Library', shelf_icon),
    ('quotes', 'Quotes', quote_icon),
    ('stats', 'Statistics', stats_icon),
    ('community', 'Community', people_icon),
]


# ---- a pane: a column of blocks ----------------------------------------------------------------------------------------

class Pane:
    """A column of blocks. Every call draws one block at the cursor and moves the cursor down."""

    def __init__(self, c: Canvas, x: float, y: float, w: float, scratch: Canvas | None = None):
        self.c = c
        self.x = x
        self.y = y
        self.y0 = y
        self.w = w
        self.tail = 0.0  # the gap after the last block
        self.scratch = scratch if scratch is not None else Canvas(4, 4)

    # -- geometry
    @property
    def height(self) -> float:
        return self.y - self.y0 - self.tail

    def advance(self, h: float, gap: float = 10) -> None:
        self.y += h + gap
        self.tail = gap

    def skip(self, h: float) -> None:
        self.y += h
        self.tail = 0

    def warn(self, what: str, text: str, width: float) -> None:
        print(f'  warning: {what} "{text}" is wider than {width:.0f}')

    # -- text
    def text(self, s: str, size: float = S, bold: bool = False, italic: bool = False, mono: bool = False, color: str = INK,
             align: str = 'left', gap: float = 8, w: float | None = None, x: float | None = None, leading: float = 1.2) -> int:
        w = self.w if w is None else w
        x = self.x if x is None else x
        lines = self.c.wrap(s, w, size, bold, italic, mono)
        for i, line in enumerate(lines):
            width = self.c.measure(line, size, bold, italic, mono)
            if width > w + 0.5:
                self.warn('text', line, w)
            top = self.y + i * size * leading
            if align == 'left':
                self.c.text(x, top, line, size, bold, italic, mono, 'lt', color)
            elif align == 'center':
                self.c.text(x + w / 2, top, line, size, bold, italic, mono, 'mt', color)
            else:
                self.c.text(x + w, top, line, size, bold, italic, mono, 'rt', color)
        self.advance(len(lines) * size * leading, gap)
        return len(lines)

    def link(self, s: str, size: float = S, color: str = INK, align: str = 'left', gap: float = 8, h: float = 30) -> None:
        width = self.c.measure(s, size)
        x = self.x if align == 'left' else self.x + (self.w - width) / 2 if align == 'center' else self.x + self.w - width
        self.c.text(x, self.y + h / 2, s, size, anchor='lm', color=color)
        self.c.polyline([(x, self.y + h / 2 + size * 0.62), (x + width, self.y + h / 2 + size * 0.62)], 1.4, color)
        self.advance(h, gap)

    def bars(self, n: int, last: float = 0.6, step: float = 20, h: float = 9, color: str = BAR, gap: float = 10,
             x: float | None = None, w: float | None = None) -> None:
        w = self.w if w is None else w
        x = self.x if x is None else x
        for i in range(n):
            width = w * (last if i == n - 1 else 1.0)
            self.c.rect(x, self.y + i * step + (step - h) / 2, width, h, color, None, 0, radius=h / 2)
        self.advance(n * step, gap)

    # -- buttons
    def _button_box(self, x: float, y: float, w: float, h: float, label: str, kind: str, size: float, dashed: bool = False) -> None:
        c = self.c
        bold = kind == 'primary'
        if kind == 'primary':
            c.rect(x, y, w, h, PRIMARY, INK, 2, radius=9, dash=DASH if dashed else None)
        elif kind == 'danger':
            c.rect(x, y, w, h, RED, DANGER_TEXT, 2, radius=9, dash=DASH if dashed else None)
        elif kind == 'secondary':
            c.rect(x, y, w, h, WHITE, LINE, 1.8, radius=9, dash=DASH if dashed else None)
        elif kind == 'selected':
            c.rect(x, y, w, h, WHITE, INK, 3.4, radius=9)
        elif kind == 'chip_on':
            c.rect(x, y, w, h, DARK_FILL, DARK_FILL, 1.8, radius=h / 2)
        elif kind == 'chip':
            c.rect(x, y, w, h, WHITE, LINE, 1.8, radius=h / 2)
        color = '#FFFFFF' if kind == 'chip_on' else DANGER_TEXT if kind == 'danger_text' else INK
        if kind in ('link', 'danger_text'):
            tw = c.measure(label, size)
            c.text(x + w / 2, y + h / 2, label, size, anchor='mm', color=color)
            c.polyline([(x + w / 2 - tw / 2, y + h / 2 + size * 0.62), (x + w / 2 + tw / 2, y + h / 2 + size * 0.62)], 1.4, color)
            return
        text = ellipsize(c, label, w - 12, size, bold)
        if text != label:
            self.warn('button label', label, w - 12)
        c.text(x + w / 2, y + h / 2, text, size, bold, anchor='mm', color=color)

    def button(self, label: str, kind: str = 'secondary', h: float = 44, w: float | None = None, size: float = S,
               dashed: bool = False, align: str = 'left', gap: float = 10) -> None:
        natural = self.c.measure(label, size, kind == 'primary') + 32
        width = self.w if w is None else (natural if w == 0 else w)
        x = self.x if align == 'left' else self.x + (self.w - width) / 2 if align == 'center' else self.x + self.w - width
        self._button_box(x, self.y, width, h, label, kind, size, dashed)
        self.advance(h, gap)

    def row(self, items: list, h: float = 44, gap: float = 8, size: float = S, advance: bool = True, pad: float = 16) -> list:
        """A row of buttons. An item is `(label, kind)` (the width of the text) or `(label, kind, weight)` (a share of
        what is left). Returns the boxes."""
        c = self.c
        fixed = 0.0
        weights = 0.0
        widths: list = []
        for item in items:
            label, kind = item[0], item[1]
            weight = item[2] if len(item) > 2 else None
            if weight is None:
                width = c.measure(label, size, kind == 'primary') + 2 * pad
                fixed += width
                widths.append(width)
            else:
                weights += weight
                widths.append(None)
        free = self.w - fixed - gap * (len(items) - 1)
        if weights == 0 and free < -0.5:
            print(f'  warning: row {[i[0] for i in items]} is wider than the pane by {-free:.0f}')
        x = self.x
        boxes = []
        for item, width in zip(items, widths):
            label, kind = item[0], item[1]
            weight = item[2] if len(item) > 2 else None
            if width is None:
                width = free * weight / weights
            dashed = len(item) > 3 and item[3]
            self._button_box(x, self.y, width, h, label, kind, size, dashed)
            boxes.append((x, self.y, width, h))
            x += width + gap
        if advance:
            self.advance(h, 10)
        return boxes

    def chips(self, labels: list, active: int, h: float = 40, gap: float = 8, size: float = S) -> None:
        x = self.x
        for i, label in enumerate(labels):
            width = self.c.measure(label, size) + 30
            self._button_box(x, self.y, width, h, label, 'chip_on' if i == active else 'chip', size)
            x += width + gap
        self.advance(h, 12)

    def segmented(self, label: str, options: list, active: int, hide_label: bool = False) -> None:
        c = self.c
        if not hide_label:
            self.text(label, gap=6)
        h = 52
        c.rect(self.x, self.y, self.w, h, '#E3E3E3', None, 0, radius=11)
        n = len(options)
        cell = (self.w - 8) / n
        for i, option in enumerate(options):
            x = self.x + 4 + i * cell
            if i == active:
                c.rect(x, self.y + 4, cell - 2, h - 8, WHITE, LINE, 1.4, radius=8)
            c.text(x + cell / 2 - 1, self.y + h / 2, option, S, bold=(i == active), anchor='mm')
        self.advance(h, 12)

    # -- fields
    def field(self, label: str, kind: str = 'input', value: str = '', placeholder: str = '', rows: int = 3,
              hint: bool | str = False, error: str = '', counter: str = '', h: float = 44, gap: float = 12) -> None:
        c = self.c
        if label:
            self.text(label, gap=5)
        invalid = bool(error)
        outline = DANGER_TEXT if invalid else LINE
        width = 2.6 if invalid else 1.8
        if kind == 'textarea':
            h = rows * 24 + 20
        c.rect(self.x, self.y, self.w, h, WHITE, outline, width, radius=9)
        if value:
            c.text(self.x + 12, self.y + (h / 2 if kind != 'textarea' else 22), value, S, anchor='lm')
        elif placeholder:
            c.text(self.x + 12, self.y + (h / 2 if kind != 'textarea' else 22), placeholder, S, italic=True, anchor='lm', color='#7A7A7A')
        if kind == 'select':
            chevron(c, self.x + self.w - 24, self.y + h / 2, 12, 'down')
        if kind == 'search':
            c.circle(self.x + self.w - 26, self.y + h / 2 - 2, 8, None, MUTED, 1.8)
            c.polyline([(self.x + self.w - 20, self.y + h / 2 + 4), (self.x + self.w - 13, self.y + h / 2 + 11)], 2, MUTED)
        self.advance(h, 5)
        if error:
            self.text(error, color=DANGER_TEXT, gap=4)
        if hint:
            self.bars(1, 0.55, step=16, h=8, gap=4)
        if counter:
            self.text(counter, size=S, color=MUTED, align='right', gap=gap)
        elif not error and not hint:
            self.tail = gap
            self.y += gap - 5
        else:
            self.y += 4
            self.tail = gap

    # -- notices
    def notice(self, text: str, kind: str = 'soft', dashed: bool = False, retry: bool = False, h: float | None = None) -> None:
        """A message box: `soft` (grey), `danger` (red) or `accent`."""
        c = self.c
        fill = {'soft': BLOCK, 'danger': RED, 'accent': PRIMARY}[kind]
        color = DANGER_TEXT if kind == 'danger' else INK
        inner = self.w - 24 - (c.measure('Retry', S) + 16 if retry else 0)
        lines = c.wrap(text, inner, S)
        height = h or len(lines) * S * 1.2 + 20
        c.rect(self.x, self.y, self.w, height, fill, DANGER_TEXT if kind == 'danger' else LINE, 1.6, radius=9, dash=DASH if dashed else None)
        for i, line in enumerate(lines):
            if c.measure(line, S) > inner + 0.5:
                self.warn('notice', line, inner)
            c.text(self.x + 12, self.y + 10 + i * S * 1.2, line, S, anchor='lt', color=color)
        if retry:
            word = 'Retry'
            tw = c.measure(word, S, True)
            tx = self.x + self.w - 12 - tw
            c.text(tx, self.y + height / 2, word, S, True, anchor='lm', color=color)
            c.polyline([(tx, self.y + height / 2 + 11), (tx + tw, self.y + height / 2 + 11)], 1.4, color)
        self.advance(height, 10)

    def tag(self, name: str) -> None:
        """A small label with the name of a component of the code, over the top left corner of the next block."""
        w = self.c.measure(name, S, mono=True) + 14
        self.c.rect(self.x + 6, self.y - 4, w, 26, YELLOW, INK, 1.5, radius=4)
        self.c.text(self.x + 6 + w / 2, self.y + 9, name, S, mono=True, anchor='mm')

    # -- containers
    def card(self, build, pad: float = 12, fill: str = WHITE, outline: str = LINE, radius: float = 14, dashed: bool = False,
             gap: float = 12, width: float = 1.8) -> None:
        inner_w = self.w - 2 * pad
        probe = Pane(self.scratch, 0, 0, inner_w, self.scratch)
        build(probe)
        h = probe.height + 2 * pad
        self.c.rect(self.x, self.y, self.w, h, fill, outline, width, radius=radius, dash=DASH if dashed else None)
        build(Pane(self.c, self.x + pad, self.y + pad, inner_w, self.scratch))
        self.advance(h, gap)

    def divider(self, gap: float = 10) -> None:
        self.c.polyline([(self.x - 4, self.y), (self.x + self.w + 4, self.y)], 1.4, LINE)
        self.advance(0, gap)

    def header(self, title: str, back: bool = False, action: str | None = None, avatar_letter: str | None = 'S') -> None:
        """The header of a screen (PageHeader): a back arrow, the title, an optional action button, the round avatar."""
        c = self.c
        h = 60
        cy = self.y + h / 2
        x = self.x
        if back:
            chevron(c, x + 14, cy, 12, 'left')
            x += 38
        right = self.x + self.w
        if avatar_letter:
            avatar(c, right - 20, cy, 40, avatar_letter)
            right -= 50
        if action:
            width = c.measure(action, S, True) + 28
            c.rect(right - width, cy - 20, width, 40, PRIMARY, INK, 2, radius=9)
            c.text(right - width / 2, cy, action, S, True, anchor='mm')
            right -= width + 8
        shown = ellipsize(c, title, right - x, 28, True)
        c.text(x, cy, shown, 28, True, anchor='lm')
        self.advance(h, 8)


# ---- a screen of a phone ---------------------------------------------------------------------------------------------

class Screen:
    """One phone: the screen is drawn on a canvas of its own and pasted into the figure by `place`."""

    def __init__(self, title: str, route: str, tab: str | None = None, tabs: bool = True, dim: tuple | None = None):
        self.c = Canvas(SW, SH)
        self.title = title
        self.route = route
        self.tab = tab
        self.tabs = tabs
        self.p = Pane(self.c, PAD, TOP, SW - 2 * PAD)

    @property
    def bottom(self) -> float:
        return SH - (TAB_H if self.tabs else 0)

    def check(self) -> None:
        """Warns when the blocks reach below the tab bar (they are cut there, as on a real phone)."""
        if self.p.y - self.p.tail > self.bottom + 1:
            print(f'  note: "{self.title}" runs {self.p.y - self.p.tail - self.bottom:.0f} units under the bottom edge')

    def tab_bar(self) -> None:
        c = self.c
        top = SH - TAB_H
        c.rect(0, top, SW, TAB_H, WHITE, None, 0)
        c.polyline([(0, top), (SW, top)], 1.6, LINE)
        widths = []
        for name, label, _ in TABS:
            widths.append(c.measure(label, S, bold=(name == self.tab)))
        gap = (SW - sum(widths)) / (len(TABS) + 1)
        x = gap
        for (name, label, icon), width in zip(TABS, widths):
            cx = x + width / 2
            icon(c, cx, top + 20, name == self.tab)
            c.text(cx, top + 48, label, S, bold=(name == self.tab), anchor='mm')
            if name == self.tab:
                c.rect(cx - width / 2 - 4, top + 3, width + 8, 3, INK, None, 0)
            x += width + gap

    def veil(self, below: float) -> None:
        """Dims the screen above the line `below` (the page behind a sheet)."""
        k = self.c.k
        mask = Image.new('L', self.c.img.size, 0)
        ImageDraw.Draw(mask).rectangle((0, 0, self.c.img.size[0], int(below * k)), fill=DIM)
        self.c.img.paste((0, 0, 0), (0, 0), mask)

    def finish(self) -> None:
        if self.tabs:
            self.tab_bar()

    def place(self, main: Canvas, x: float, y: float) -> None:
        """Draws the phone with its caption on the figure, the top left corner of the phone at (x, y)."""
        self.finish()
        self.check()
        main.text(x + PW / 2, y - 46, self.title, 21, bold=True, anchor='mm')
        main.text(x + PW / 2, y - 20, self.route, S, mono=True, anchor='mm')
        main.rect(x, y, PW, PH, '#DDDDDD', INK, 3, radius=46)
        k = main.k
        mask = Image.new('L', self.c.img.size, 0)
        ImageDraw.Draw(mask).rounded_rectangle((0, 0, self.c.img.size[0] - 1, self.c.img.size[1] - 1), radius=SCREEN_RADIUS * k, fill=255)
        main.img.paste(self.c.img, (int(round((x + BEZEL) * k)), int(round((y + BEZEL) * k))), mask)
        main.rect(x + BEZEL, y + BEZEL, SW, SH, None, '#666666', 1.5, radius=SCREEN_RADIUS)
        main.rect(x + PW / 2 - 30, y + 20, 60, 8, '#333333', None, 0, radius=4)


def figure(name: str, screens: list, margin: float = 22, top: float = 62) -> Path:
    """A row of phones on one canvas, saved as `figures/<name>.png`."""
    n = len(screens)
    width = 1750
    gap = (width - 2 * margin - n * PW) / (n - 1) if n > 1 else 0
    height = top + PH + 12
    c = Canvas(width, height)
    for i, screen in enumerate(screens):
        screen.place(c, margin + i * (PW + gap), top)
    path = c.save(FIGURES / f'{name}.png')
    print(f'{name}: {width} x {height}  ->  {path.name}')
    return path


# =====================================================================================================================
# Pieces that several screens share
# =====================================================================================================================

def book_row(p: Pane, title: str = 'Book title', authors: str = 'Author name', pages: str | None = None, fraction: float | None = None,
             percent: str | None = None, feedback: str | None = None, stars: int | None = None, cover_w: float = 52) -> None:
    """The cover on the left and the title, the authors and the progress on the right (BookCard, ReadingCard)."""
    c = p.c
    cover(c, p.x, p.y, cover_w)
    tx = p.x + cover_w + 12
    tw = p.w - cover_w - 12
    y = p.y
    c.text(tx, y, ellipsize(c, title, tw, S, True), S, True, anchor='lt')
    y += 22
    c.text(tx, y, ellipsize(c, authors, tw), S, anchor='lt', color=MUTED)
    y += 26
    if fraction is not None:
        progress(c, tx, y, tw, fraction)
        y += 18
    if pages:
        c.text(tx, y, pages, S, anchor='lt', color=MUTED)
        if percent:
            c.text(tx + tw, y, percent, S, anchor='rt', color=MUTED)
        if feedback:
            fw = c.measure(feedback, S, True)
            c.rect(tx + 112, y - 2, fw + 12, 26, WHITE, LINE, 1.4, radius=6, dash=DASH)
            c.text(tx + 118, y, feedback, S, True, anchor='lt')
        y += 24
    if stars is not None:
        for i in range(5):
            star(c, tx + 10 + i * 24, y + 10, 10, i < stars)
        y += 24
    p.advance(max(cover_w * 1.5, y - p.y), 0)
    p.tail = 0


def quote_block(p: Pane, lines: int = 3, source: str | None = None) -> None:
    """A passage: a bar of the accent colour on the left and the text as grey bars."""
    c = p.c
    h = lines * 20
    c.rect(p.x, p.y + 2, 4, h - 4, DARK_FILL, None, 0)
    p.bars(lines, 0.7, x=p.x + 14, w=p.w - 14, gap=6)
    if source:
        p.text(source, color=MUTED, gap=8)


def post_head(p: Pane, name: str = 'Reader name', handle: str = '@handle · 5 Oct', menu: bool = True) -> None:
    c = p.c
    avatar(c, p.x + 20, p.y + 20, 40, 'R')
    c.text(p.x + 52, p.y + 11, name, S, True, anchor='lm')
    c.text(p.x + 52, p.y + 32, handle, S, anchor='lm', color=MUTED)
    if menu:
        dots(c, p.x + p.w - 14, p.y + 20)
    p.advance(44, 8)


def post_footer(p: Pane, likes: str = '12', comments: str = '3', liked: bool = False) -> None:
    c = p.c
    p.divider(6)
    heart(c, p.x + 16, p.y + 20, 22, liked)
    c.text(p.x + 36, p.y + 20, likes, S, anchor='lm', color=ACCENT if liked else INK)
    bubble(c, p.x + 96, p.y + 20, 22)
    c.text(p.x + 116, p.y + 20, comments, S, anchor='lm')
    p.advance(40, 0)


ACCENT = INK


def post_card(p: Pane, review: bool = False, detail: bool = False, hidden: bool = False, book_line: str = 'Book title · Author · p. 42') -> None:
    def build(q: Pane) -> None:
        post_head(q)
        if hidden:
            q.notice('Hidden by moderation', 'danger', dashed=True)
        if book_line:
            q.text(book_line, color=MUTED, gap=8)
        if review:
            c = q.c
            c.rect(q.x, q.y, 74, 26, BLOCK, LINE, 1.4, radius=13)
            c.text(q.x + 37, q.y + 13, 'Review', S, anchor='mm')
            for i in range(5):
                star(c, q.x + 100 + i * 22, q.y + 13, 9, i < 4)
            q.advance(26, 8)
            q.bars(4 if detail else 3, 0.5)
        else:
            quote_block(q, 4 if detail else 3)
            q.bars(1 if not detail else 2, 0.8, color='#DDDDDD', gap=8)
        post_footer(q)

    p.card(build, pad=12)


# =====================================================================================================================
# Figure ui_main: sign in, the Reading screen, the library, adding a book
# =====================================================================================================================

def s_signin() -> Screen:
    s = Screen('Sign in', '/login', tabs=False)
    p = s.p
    p.skip(78)
    p.text('Bookshelf', 40, bold=True, gap=2)
    p.text('Reading and quotes tracker', color=MUTED, gap=22)

    def form(q: Pane) -> None:
        q.text('Sign in', 26, bold=True, gap=10)
        q.notice('Session expired. Sign in again.', 'soft', dashed=True)
        q.field('Email')
        q.field('Password')
        q.notice('Wrong email or password.', 'danger', dashed=True)
        q.button('Sign in', 'primary', gap=0)

    p.card(form, pad=16, radius=18, gap=18)
    c = s.c
    w1 = c.measure('No account yet? ', S)
    w2 = c.measure('Register', S, True)
    x = (SW - w1 - w2) / 2
    c.text(x, p.y + 12, 'No account yet? ', S, anchor='lm', color=MUTED)
    c.text(x + w1, p.y + 12, 'Register', S, True, anchor='lm')
    c.polyline([(x + w1, p.y + 23), (x + w1 + w2, p.y + 23)], 1.4)
    return s


def s_reading() -> Screen:
    s = Screen('Reading', '/', tab='home')
    p = s.p
    p.header('Reading')

    def card1(q: Pane) -> None:
        book_row(q, 'Book title', 'Author name', '120 of 300 pp.', 0.4, '40%', '+18 pp.')
        q.skip(12)
        q.row([('+10', 'secondary'), ('+25', 'secondary'), ("I'm on page…", 'secondary', 1), ('Read', 'secondary')], gap=6, pad=12, advance=False)
        q.advance(44, 0)

    def card2(q: Pane) -> None:
        book_row(q, 'Another book', 'Author name', '35 of 280 pp.', 0.12, '12%')
        q.skip(12)
        q.row([('+10', 'secondary'), ('+25', 'secondary'), ("I'm on page…", 'secondary', 1)], gap=6, pad=12, advance=False)
        q.advance(44, 0)

    p.card(card1, radius=16)
    p.card(card2, radius=16, gap=16)

    def daily(q: Pane) -> None:
        q.text('Quote of the day', color=MUTED, gap=8)
        quote_block(q, 3)
        q.text('Book title · Author · p. 42', color=MUTED, gap=8)
        q.button('Share', 'secondary', w=0, h=44, gap=0)

    p.card(daily, radius=16, pad=14)
    return s


def s_library() -> Screen:
    s = Screen('Library', '/library', tab='library')
    p = s.p
    p.header('Library', action='Add book')
    p.skip(0)
    p.chips(['Want 3', 'Reading 2', 'Finished 12', 'Abandoned 1'], 1)
    for title, authors, pages, frac in [('Book title', 'Author name', '120 of 300 pp.', 0.4), ('Another book', 'Author name', '35 of 280 pp.', 0.12),
                                         ('Third book', 'Author name', '210 of 240 pp.', 0.88)]:
        p.card(lambda q, a=(title, authors, pages, frac): book_row(q, a[0], a[1], a[2], a[3]), radius=16, gap=12)
    return s


def s_add_book() -> Screen:
    s = Screen('Add a book', '/library/add', tab='library')
    p = s.p
    p.header('Add a book', back=True)
    p.field('Title or author', 'search', value='atomic')

    def result(q: Pane, state: str) -> None:
        c = q.c
        cover(c, q.x, q.y, 48)
        tx = q.x + 60
        bw = 66
        tw = q.w - 60 - bw - 8
        c.text(tx, q.y, ellipsize(c, 'Book title', tw, S, True), S, True, anchor='lt')
        c.text(tx, q.y + 22, ellipsize(c, 'Author name', tw), S, anchor='lt', color=MUTED)
        c.text(tx, q.y + 44, '2018 · 320 pp.', S, anchor='lt', color=MUTED)
        if state == 'add':
            q._button_box(q.x + q.w - bw, q.y + 12, bw, 40, 'Add', 'primary', S)
        else:
            c.text(q.x + q.w, q.y + 22, 'In library', S, anchor='rm', color=MUTED)
            c.text(tx, q.y + 66, '', S)
        q.advance(72, 0)

    for state in ('add', 'in', 'add'):
        p.card(lambda q, st=state: result(q, st), radius=16, gap=10)
    p.text('Cannot find your book?', color=MUTED, align='center', gap=0)
    p.link('Add manually', align='center')
    return s


# =====================================================================================================================
# Figure ui_progress: the book card in three parts and the states of the progress update
# =====================================================================================================================

def section_title(p: Pane, title: str, link: str | None = None) -> None:
    p.c.text(p.x, p.y + 12, title, S, True, anchor='lm')
    if link:
        width = p.c.measure(link, S)
        p.c.text(p.x + p.w - width, p.y + 12, link, S, anchor='lm')
        p.c.polyline([(p.x + p.w - width, p.y + 23), (p.x + p.w, p.y + 23)], 1.4)
    p.advance(26, 4)


def page_form(p: Pane, label: str, value: str, cancel: bool = False, error: str = '', hide_label: bool = False, save_on: bool = True) -> None:
    """PageJumpForm: the number input and "Save" (and "Cancel" when it appears on demand)."""
    if not hide_label:
        p.text(label, gap=5)
    items = [('', 'input', 1), ('Save', 'primary' if save_on else 'secondary')]
    if cancel:
        items.append(('Cancel', 'secondary'))
    c = p.c
    width_other = sum(c.measure(i[0], S, i[1] == 'primary') + 32 for i in items[1:]) + 8 * (len(items) - 1)
    iw = p.w - width_other
    invalid = bool(error)
    c.rect(p.x, p.y, iw, 44, WHITE, DANGER_TEXT if invalid else LINE, 2.6 if invalid else 1.8, radius=9)
    c.text(p.x + 12, p.y + 22, value, S, anchor='lm')
    x = p.x + iw + 8
    for label_, kind in items[1:]:
        width = c.measure(label_, S, kind == 'primary') + 32
        p._button_box(x, p.y, width, 44, label_, kind, S)
        x += width + 8
    p.advance(44, 6)
    if error:
        p.text(error, color=DANGER_TEXT, gap=2)


def s_book_top() -> Screen:
    s = Screen('Book card, top', '/library/:id', tab='library')
    p = s.p
    c = s.c
    p.header('Book title', back=True)
    top = p.y
    cover(c, p.x, top, 96)
    tx = p.x + 112
    c.text(tx, top + 6, 'Subtitle', S, anchor='lt', color=MUTED)
    c.text(tx, top + 32, 'Author name', S, True, anchor='lt')
    c.text(tx, top + 58, '2018 · added by hand', S, anchor='lt', color=MUTED)
    c.rect(tx, top + 90, 92, 32, BLOCK, LINE, 1.4, radius=16)
    c.text(tx + 46, top + 106, 'Reading', S, True, anchor='mm')
    p.skip(144 + 16)
    progress(c, p.x, p.y, p.w, 0.4)
    p.skip(20)
    p.text('120 of 300 pp.', color=MUTED, gap=14)
    page_form(p, "I'm on page", '120')
    p.skip(6)

    def read_block(q: Pane) -> None:
        q.text('Read in the app', 22, bold=True, gap=8)
        progress(q.c, q.x, q.y, q.w, 0.23)
        q.skip(20)
        q.text('Read 23%', color=MUTED, gap=10)
        q.button('Continue reading', 'primary', align='center')
        q.row([('Replace file', 'link'), ('Delete text', 'danger_text')], gap=16, advance=False, pad=4)
        q.advance(44, 4)
        q.text('Highlights of quotes in the text will be reset; the quotes stay.', size=S, color=MUTED, gap=0)

    p.card(read_block, pad=14, radius=16)
    return s


def s_book_middle() -> Screen:
    s = Screen('Book card, middle', '/library/:id', tab='library')
    p = s.p
    c = s.c
    p.skip(8)
    p.button('Finished', 'primary')
    p.button('Put aside', 'secondary', gap=18)
    page_form(p, 'Number of pages', '300', save_on=False)
    p.skip(10)
    p.text('Rating', bold=True, gap=4)
    for i in range(5):
        star(c, p.x + 20 + i * 40, p.y + 18, 15, i < 4)
    p.skip(46)
    section_title(p, 'Quotes', 'All quotes')
    for _ in range(2):
        def item(q: Pane) -> None:
            q.bars(2, 0.7, gap=4)
            q.text('p. 42', color=MUTED, gap=0)

        p.card(item, pad=10, radius=12, gap=8)
    p.button('Add quote', 'secondary', align='center')
    p.skip(8)
    return s


def s_book_bottom() -> Screen:
    s = Screen('Book card, bottom', '/library/:id', tab='library')
    p = s.p
    p.skip(4)
    section_title(p, 'What readers write')
    for i in range(5):
        star(s.c, p.x + 11 + i * 24, p.y + 12, 10, i < 4)
    s.c.text(p.x + 132, p.y + 12, '4.5 · 2 reviews', S, anchor='lm')
    p.skip(34)
    post_card(p, review=True, book_line='')
    p.button('Write a review', 'secondary', align='center', gap=16)
    section_title(p, 'Reading history')

    def journal(q: Pane) -> None:
        for i, (day, span, gained) in enumerate([('5 Oct', 'from 100 to 118', '+18 pp.'), ('4 Oct', 'from 82 to 100', '+18 pp.')]):
            q.c.text(q.x, q.y + 12, day, S, True, anchor='lm')
            q.c.text(q.x, q.y + 34, span, S, anchor='lm', color=MUTED)
            q.c.text(q.x + q.w, q.y + 22, gained, S, True, anchor='rm')
            q.advance(52, 0)
            if i == 0:
                q.divider(0)
                q.skip(2)

    p.card(journal, pad=12, radius=12, gap=10)
    p.button('Show more', 'secondary', dashed=True)
    p.divider(6)
    p.link('Remove from library', color=DANGER_TEXT, h=34)
    return s


def s_reading_states() -> Screen:
    s = Screen('Reading: updating progress', '/', tab='home')
    p = s.p
    p.header('Reading')

    def after_tap(q: Pane) -> None:
        book_row(q, 'Book title', 'Author name', '138 of 300 pp.', 0.46, '46%', '+18 pp.')
        q.skip(12)
        q.row([('+10', 'secondary'), ('+25', 'secondary'), ("I'm on page…", 'secondary', 1), ('Read', 'secondary')], gap=6, pad=12, advance=False)
        q.advance(44, 0)

    def typing(q: Pane) -> None:
        book_row(q, 'Another book', 'Author name', '35 of 280 pp.', 0.12, '12%')
        q.skip(12)
        page_form(q, '', '350', cancel=True, hide_label=True, error='Enter a page number from 0 to 280.', save_on=False)

    def at_end(q: Pane) -> None:
        book_row(q, 'Third book', 'Author name', '240 of 240 pp.', 1.0, '100%')
        q.skip(10)
        q.text('You reached the end! Mark the book as finished?', gap=6)
        q.button('Mark as finished', 'primary', gap=0)

    p.card(after_tap, radius=16, gap=8)
    p.card(typing, radius=16, gap=8)
    p.card(at_end, radius=16)
    return s


def main_ui_progress() -> None:
    figure('ui_progress', [s_book_top(), s_book_middle(), s_book_bottom(), s_reading_states()])


# =====================================================================================================================
# Figure ui_stats: statistics, the list of quotes and the quote card
# =====================================================================================================================

def stat_tile(c: Canvas, x: float, y: float, w: float, h: float, label: str, value: str, unit: str = '', hint: str = '') -> None:
    c.rect(x, y, w, h, WHITE, LINE, 1.8, radius=14)
    c.text(x + 12, y + 14, label, S, anchor='lm', color=MUTED)
    c.text(x + 12, y + 44, value, 28, True, anchor='lm')
    if unit:
        c.text(x + 12 + c.measure(value, 28, True) + 6, y + 48, unit, S, anchor='lm', color=MUTED)
    if hint:
        c.text(x + 12, y + 74, hint, S, anchor='lm', color=MUTED)


def tile_grid(p: Pane, tiles: list, h: float = 90) -> None:
    w = (p.w - 12) / 2
    for i, tile in enumerate(tiles):
        stat_tile(p.c, p.x + (i % 2) * (w + 12), p.y + (i // 2) * (h + 12), w, h, *tile)
    rows = (len(tiles) + 1) // 2
    p.advance(rows * h + (rows - 1) * 12, 12)


def s_stats_top() -> Screen:
    s = Screen('Statistics, top', '/stats', tab='stats')
    p = s.p
    p.header('Statistics')

    def goal(q: Pane) -> None:
        c = q.c
        q.text('Goal for 2026', 22, bold=True, gap=6)
        c.text(q.x, q.y + 20, '7', 40, True, anchor='lm')
        c.text(q.x + 34, q.y + 26, 'read · goal 24', S, anchor='lm', color=MUTED)
        q.skip(46)
        progress(c, q.x, q.y, q.w, 7 / 24)
        q.skip(22)
        q.text('You are on track. Still needed: 3 books', bold=True, gap=10)
        q.row([('Change goal', 'secondary', 1), ('Remove', 'danger_text')], gap=8, pad=14, advance=False)
        q.advance(44, 0)

    p.card(goal, pad=14, radius=16)
    tile_grid(p, [('Streak', '5 days', '', 'Longest: 12 days'), ('Pace', '21.5', 'pp./day', 'last 14 days'),
                  ('This month', '420 pp.', '', '2 books'), ('This year', '3 100 pp.', '', '7 books')])

    return s


def s_stats_bottom() -> Screen:
    s = Screen('Statistics, bottom', '/stats', tab='stats')
    p = s.p
    p.skip(8)

    def chart(q: Pane) -> None:
        c = q.c
        c.text(q.x, q.y + 20, 'Pages per day', 22, True, anchor='lm')
        bx = q.x + q.w
        for label in ('90', '30', '7'):
            width = 46
            bx -= width
            if label == '30':
                c.rect(bx, q.y, width, 40, DARK_FILL, DARK_FILL, 1.6, radius=20)
                c.text(bx + width / 2, q.y + 20, label, S, anchor='mm', color='#FFFFFF')
            else:
                c.rect(bx, q.y, width, 40, WHITE, LINE, 1.6, radius=20)
                c.text(bx + width / 2, q.y + 20, label, S, anchor='mm')
            bx -= 4
        q.advance(40, 10)
        heights = [20, 35, 0, 48, 60, 30, 0, 12, 40, 70, 55, 22, 0, 18, 44, 66, 28, 0, 38, 52, 25, 62, 34, 0, 16, 46, 58, 30, 42, 64]
        base = q.y + 96
        n = len(heights)
        bw = q.w / n
        for i, hgt in enumerate(heights):
            x = q.x + i * bw
            if hgt == 0:
                c.rect(x + 1, base - 2, bw - 2, 2, TRACK, None, 0)
            else:
                c.rect(x + 1, base - hgt, bw - 2, hgt, DARK_FILL if i == n - 1 else '#9A9A9A', None, 0)
        q.skip(104)
        c.text(q.x, q.y + 12, '6 Sep', S, anchor='lm', color=MUTED)
        c.text(q.x + q.w, q.y + 12, '5 Oct', S, anchor='rm', color=MUTED)
        q.skip(34)
        for label, value in [('Total', '540 pp.'), ('Average', '18 pp./day'), ('Best day', '12 Sep, 62 pp.')]:
            c.text(q.x, q.y + 11, label, S, anchor='lm', color=MUTED)
            c.text(q.x + q.w, q.y + 11, value, S, True, anchor='rm')
            q.skip(26)

    p.card(chart, pad=14, radius=16, gap=14)
    section_title_display(p, 'When I will finish')

    def forecast(q: Pane) -> None:
        for i, (line2, line3, bold3) in enumerate([('Left 180 pp. · 18 pp./day', '≈ 15 Oct (in 10 days)', True),
                                                   ('Left 245 pp.', 'Not enough data for a forecast yet', False)]):
            q.c.text(q.x, q.y + 11, 'Book title' if i == 0 else 'Another book', S, True, anchor='lm')
            q.c.text(q.x, q.y + 34, line2, S, anchor='lm', color=MUTED)
            q.c.text(q.x, q.y + 57, line3, S, bold3, anchor='lm', color=INK if bold3 else MUTED)
            q.advance(70, 0)
            if i == 0:
                q.divider(0)
                q.skip(6)

    p.card(forecast, pad=14, radius=16)
    return s


def section_title_display(p: Pane, title: str) -> None:
    p.text(title, 22, bold=True, gap=8)


def quote_card_item(p: Pane, insight: bool, favorite: bool, anchored: bool) -> None:
    def build(q: Pane) -> None:
        c = q.c
        top = q.y
        if insight:
            c.rect(q.x, q.y, 88, 28, BLOCK, LINE, 1.4, radius=14)
            c.text(q.x + 44, q.y + 14, 'Insight', S, anchor='mm')
            q.skip(36)
        inner = q.w - 44
        c.rect(q.x, q.y + 2, 4, 56, DARK_FILL, None, 0)
        q.bars(3, 0.7, x=q.x + 14, w=inner - 14, gap=6)
        star(c, q.x + q.w - 18, top + 16, 13, favorite)
        q.bars(1, 0.8, color='#DDDDDD', gap=6)
        q.text('Book title · Author · p. 42', color=MUTED, gap=8)
        q.divider(10)
        row = [('Share', 'secondary'), ('Edit', 'secondary')]
        if anchored:
            row.append(('Open in text', 'secondary'))
        else:
            row.append(('Publish', 'secondary'))
        boxes = q.row(row, gap=8, pad=12, advance=False)
        if anchored:
            q.advance(44, 8)
            q.row([('Publish', 'secondary')], gap=8, pad=12, advance=False)
        c.text(q.x + q.w, q.y + 22, 'Delete', S, anchor='rm', color=DANGER_TEXT)
        c.polyline([(q.x + q.w - c.measure('Delete', S), q.y + 33), (q.x + q.w, q.y + 33)], 1.4, DANGER_TEXT)
        q.advance(44, 0)

    p.card(build, pad=14, radius=16, gap=12)


def s_quotes() -> Screen:
    s = Screen('Quotes', '/quotes', tab='quotes')
    p = s.p
    p.header('Quotes', action='Add')
    c = s.c
    x = p.x
    for i, label in enumerate(['All', 'Favorites']):
        width = c.measure(label, S) + 36
        p._button_box(x, p.y, width, 40, label, 'chip_on' if i == 0 else 'chip', S)
        x += width + 8
    p.advance(40, 12)
    p.field('', 'select', value='All books', gap=12)
    quote_card_item(p, insight=False, favorite=False, anchored=False)
    quote_card_item(p, insight=True, favorite=True, anchored=True)
    return s


def s_quote_card() -> Screen:
    s = Screen('Quote card', '/quotes/:id/share', tab='quotes')
    p = s.p
    c = s.c
    p.header('Quote card', back=True)
    p.segmented('Template', ['Light', 'Dark', 'Accent'], 0)
    w = p.w
    h = w * 1.25
    k = w / 1080
    x, y = p.x, p.y
    c.rect(x, y, w, h, '#F7F3EA', LINE, 1.8, radius=10)
    c.text(x + 96 * k, y + 360 * k, '“', 96, True, anchor='lm', color=DARK_FILL)
    for i in range(6):
        width = (w - 192 * k) * (0.55 if i == 5 else 1.0)
        c.rect(x + 96 * k, y + (500 + i * 75) * k, width, 10, BAR, None, 0, radius=5)
    c.rect(x + 96 * k, y + 990 * k, 120 * k, 6 * k + 1, DARK_FILL, None, 0)
    c.text(x + 96 * k, y + 1040 * k + 8, 'Book title', S, True, anchor='lm')
    c.text(x + 96 * k, y + 1040 * k + 34, 'Author · p. 42', S, anchor='lm', color=MUTED)
    c.text(x + 96 * k, y + 1270 * k, 'Bookshelf', S, True, anchor='lm', color=MUTED)
    p.advance(h, 14)
    p.button('Share', 'primary')
    return s


def main_ui_stats() -> None:
    figure('ui_stats', [s_stats_top(), s_stats_bottom(), s_quotes(), s_quote_card()])


# =====================================================================================================================
# Figure ui_reader: the reader, the selection bar, the contents and the settings
# =====================================================================================================================

def reader_top(s: Screen, fraction: float = 0.24) -> float:
    """The top bar and the thin progress strip of the reader. Returns where the text starts."""
    c = s.c
    h = 62
    c.rect(0, 0, SW, h + TOP - 14, '#F4F4F4', None, 0)
    c.polyline([(0, h + TOP - 14), (SW, h + TOP - 14)], 1.6, LINE)
    cy = TOP - 14 + h / 2 + 4
    chevron(c, 28, cy, 12, 'left')
    c.text(54, cy - 12, 'Book title', S, True, anchor='lm')
    c.text(54, cy + 11, 'Chapter 3 of 12 · Title', S, anchor='lm', color=MUTED)
    # the contents icon and "Aa"
    ix = SW - 92
    for dy in (-8, 0, 8):
        c.polyline([(ix - 8, cy + dy), (ix + 12, cy + dy)], 2.2)
        c.circle(ix - 15, cy + dy, 1.4, INK, INK, 1)
    c.text(SW - 36, cy, 'Aa', 24, True, anchor='mm')
    y = h + TOP - 14
    c.rect(0, y, SW, 4, TRACK, None, 0)
    c.rect(0, y, SW * fraction, 4, DARK_FILL, None, 0)
    return y + 4


def reader_text(s: Screen, top: float, mark: bool = True, selection: bool = False, rows: int = 11) -> None:
    """Heading and paragraphs of a chapter as grey bars; a saved quote is highlighted, a selection is blue."""
    c = s.c
    x, w = 22, SW - 44
    y = top + 22
    c.rect(x, y, w * 0.6, 14, '#8C8C8C', None, 0, radius=7)
    y += 40
    step = 26
    for i in range(rows):
        width = w * (0.62 if i in (4, rows - 1) else 1.0)
        if mark and i in (1, 2):
            c.rect(x - 3, y + i * step - 7, (width if i == 2 else w) + 6, 24, MARK, None, 0, radius=5)
            if i == 2:
                pass
        if selection and i in (6, 7):
            end = w * 0.55 if i == 7 else w
            start = w * 0.3 if i == 6 else 0
            c.rect(x + start, y + i * step - 7, end - start, 24, SELECT, None, 0)
        c.rect(x, y + i * step, width if not (mark and i == 2) else w * 0.7, 10, BAR, None, 0, radius=5)
        if mark and i == 1:
            pass
    if selection:
        sx, sy = x + w * 0.3, y + 6 * step + 17
        c.circle(sx, sy + 8, 7, '#6C8EE8', INK, 1.4)
        c.polyline([(sx, sy - 10), (sx, sy + 1)], 2.2, '#6C8EE8')
        ex, ey = x + w * 0.55, y + 7 * step + 17
        c.circle(ex, ey + 8, 7, '#6C8EE8', INK, 1.4)
        c.polyline([(ex, ey - 10), (ex, ey + 1)], 2.2, '#6C8EE8')


def wavy_break(c: Canvas, y: float, x0: float = 22, x1: float = SW - 22) -> None:
    pts = []
    n = 24
    for i in range(n + 1):
        pts.append((x0 + (x1 - x0) * i / n, y + (5 if i % 2 else -5)))
    c.polyline(pts, 1.6, MUTED)


def s_reader() -> Screen:
    s = Screen('Reader', '/read/:id', tabs=False)
    c = s.c
    top = reader_top(s)
    reader_text(s, top, mark=True, rows=9)
    wavy_break(c, top + 22 + 40 + 9 * 26 + 18)
    p = Pane(c, 22, top + 22 + 40 + 9 * 26 + 44, SW - 44)
    p.button('Next chapter', 'primary')
    p.button('Previous chapter', 'secondary')
    return s


def s_selection() -> Screen:
    s = Screen('Text selected', '/read/:id', tabs=False)
    c = s.c
    top = reader_top(s, 0.31)
    reader_text(s, top, mark=True, selection=True, rows=13)
    bar_h = 190
    y = SH - bar_h
    c.rect(0, y, SW, bar_h, '#F4F4F4', None, 0)
    c.polyline([(0, y), (SW, y)], 1.8, LINE)
    p = Pane(c, PAD, y + 12, SW - 2 * PAD)
    p.field('', 'textarea', rows=2, placeholder='My thought', gap=8)
    p.row([('Save quote', 'primary', 1), ('Add note', 'secondary'), ('Cancel', 'secondary')], gap=6, pad=12, advance=False)
    return s


def sheet(s: Screen, title: str, height: float, close: bool = True) -> Pane:
    """A bottom sheet over a dimmed screen; returns the pane for its content."""
    c = s.c
    top = SH - height
    s.veil(top)
    c.rect(-2, top, SW + 4, height + 40, WHITE, LINE, 1.8, radius=20)
    c.text(PAD + 4, top + 34, title, 24, True, anchor='lm')
    if close:
        cross(c, SW - 34, top + 34, 9)
    return Pane(c, PAD + 4, top + 66, SW - 2 * PAD - 8)


def s_contents() -> Screen:
    s = Screen('Contents sheet', '/read/:id', tabs=False)
    top = reader_top(s)
    reader_text(s, top, mark=True, rows=14)
    p = sheet(s, 'Contents', 470)
    c = s.c
    rows = [('1', 'Chapter title'), ('2', 'Chapter title'), ('3', 'Chapter title'), ('4', 'Chapter 4'), ('5', 'Chapter title'),
            ('6', 'Chapter title'), ('7', 'Chapter title')]
    for number, label in rows:
        current = number == '3'
        c.text(p.x + 26, p.y + 22, number, S, anchor='rm', color=MUTED)
        c.text(p.x + 42, p.y + 22, label, S, current, anchor='lm')
        if current:
            c.text(p.x + p.w, p.y + 22, 'You are here', S, anchor='rm', color=INK)
        c.polyline([(p.x, p.y + 46), (p.x + p.w, p.y + 46)], 1.2, TRACK)
        p.skip(48)
    return s


def s_settings() -> Screen:
    s = Screen('Reader settings sheet', '/read/:id', tabs=False)
    top = reader_top(s)
    reader_text(s, top, mark=True, rows=14)
    p = sheet(s, 'Appearance', 520)
    c = s.c

    def stepper(label: str, left: str, value: str, right: str, left_size: float, right_size: float) -> None:
        c.text(p.x, p.y + 22, label, S, True, anchor='lm')
        bx = p.x + p.w
        c.rect(bx - 44, p.y, 44, 44, WHITE, LINE, 1.8, radius=9)
        c.text(bx - 22, p.y + 22, right, right_size, True, anchor='mm')
        c.text(bx - 44 - 30, p.y + 22, value, S, anchor='mm')
        c.rect(bx - 44 - 60 - 44, p.y, 44, 44, WHITE, LINE, 1.8, radius=9)
        c.text(bx - 44 - 60 - 22, p.y + 22, left, left_size, True, anchor='mm')
        p.advance(44, 12)

    stepper('Font size', 'A', '18', 'A', 16, 26)
    stepper('Line spacing', '−', '1.6', '+', 24, 24)
    p.text('Font', bold=True, gap=4)
    p.row([('Serif', 'selected', 1), ('Sans', 'secondary', 1)], gap=8)
    p.text('Theme', bold=True, gap=4)
    p.row([('As app', 'selected', 1), ('Light', 'secondary', 1)], gap=8)
    p.row([('Sepia', 'secondary', 1), ('Dark', 'secondary', 1)], gap=8)
    p.link('Reset settings')
    return s


def main_ui_reader() -> None:
    figure('ui_reader', [s_reader(), s_selection(), s_contents(), s_settings()])


# =====================================================================================================================
# Figure ui_community: the feed, a post with comments, the profile of a reader, a new post
# =====================================================================================================================

def feed_post(q: Pane, liked: bool = False, comment: bool = True) -> None:
    post_head(q)
    q.text('Book title · Author · p. 42', color=MUTED, gap=8)
    quote_block(q, 3)
    if comment:
        q.bars(1, 0.8, color='#DDDDDD', gap=8)
    post_footer(q, '12', '3', liked)


def s_feed() -> Screen:
    s = Screen('Feed', '/community', tab='community')
    p = s.p
    p.header('Community', action='Write')
    c = s.c
    bw = c.measure('Find', S) + 32
    c.rect(p.x, p.y, p.w - bw - 8, 44, WHITE, LINE, 1.8, radius=9)
    c.text(p.x + 12, p.y + 22, 'Nickname or name', S, italic=True, anchor='lm', color='#7A7A7A')
    p._button_box(p.x + p.w - bw, p.y, bw, 44, 'Find', 'secondary', S)
    p.advance(44, 12)
    p.segmented('Feed', ['Following', 'All'], 0, hide_label=True)
    p.card(lambda q: feed_post(q, liked=True), pad=12, radius=16)
    p.card(lambda q: feed_post(q), pad=12, radius=16)
    return s


def comment_item(p: Pane, mine: bool = False, hidden: bool = False) -> None:
    def build(q: Pane) -> None:
        c = q.c
        avatar(c, q.x + 20, q.y + 20, 40, 'R')
        tx = q.x + 52
        tw = q.w - 52
        c.text(tx, q.y + 11, 'Reader name · 4 Oct', S, True, anchor='lm')
        inner = Pane(c, tx, q.y + 30, tw, q.scratch)
        if hidden:
            c.rect(tx, inner.y, 190, 26, RED, DANGER_TEXT, 1.4, radius=13, dash=DASH)
            c.text(tx + 95, inner.y + 13, 'Hidden by moderation', S, anchor='mm', color=DANGER_TEXT)
            inner.advance(26, 6)
        inner.bars(2, 0.7, gap=2)
        if mine:
            inner.link('Delete', color=DANGER_TEXT, h=44, gap=0)
        else:
            inner.link('Report', color=MUTED, h=44, gap=0)
        q.advance(inner.y - q.y, 0)

    p.card(build, pad=12, radius=16)


def s_post() -> Screen:
    s = Screen('Post with comments', '/community/posts/:id', tab='community')
    p = s.p
    p.header('Post', back=True)

    def post(q: Pane) -> None:
        post_head(q)
        q.text('Book title · Author · p. 42', color=MUTED, gap=8)
        quote_block(q, 3)
        post_footer(q, '12', '3', False)

    p.card(post, pad=12, radius=16, gap=10)
    p.text('Comments', 22, bold=True, gap=6)
    p.text('Your comment', gap=4)
    c = s.c
    c.rect(p.x, p.y, p.w, 58, WHITE, LINE, 1.8, radius=9)
    c.text(p.x + 12, p.y + 20, 'Write a comment…', S, italic=True, anchor='lm', color='#7A7A7A')
    p.advance(58, 6)
    c.text(p.x, p.y + 22, '0 of 1000', S, anchor='lm', color=MUTED)
    bw = c.measure('Send', S, True) + 32
    c.rect(p.x + p.w - bw, p.y, bw, 44, '#E6E6E6', LINE, 1.8, radius=9)
    c.text(p.x + p.w - bw / 2, p.y + 22, 'Send', S, True, anchor='mm', color=MUTED)
    p.advance(44, 10)
    comment_item(p)
    return s


def s_reader_profile() -> Screen:
    s = Screen('Profile of a reader', '/u/:handle', tab='community')
    p = s.p
    c = s.c
    p.header('Reader name', back=True)
    avatar(c, p.x + 32, p.y + 32, 64, 'R')
    c.text(p.x + 78, p.y + 18, 'Reader name', 26, True, anchor='lm')
    c.text(p.x + 78, p.y + 48, '@handle', S, anchor='lm', color=MUTED)
    p.skip(78)
    p.bars(2, 0.7, gap=12)
    w = (p.w - 16) / 3
    for i, (label, value) in enumerate([('Posts', '12'), ('Followers', '8'), ('Following', '5')]):
        x = p.x + i * (w + 8)
        c.rect(x, p.y, w, 78, WHITE, LINE, 1.8, radius=14)
        c.text(x + 10, p.y + 17, label, S, anchor='lm', color=MUTED)
        c.text(x + 10, p.y + 52, value, 28, True, anchor='lm')
    p.advance(78, 12)
    p.button('Follow', 'primary')
    p.link('Block', color=DANGER_TEXT, h=34, gap=10)
    p.text('Posts', 22, bold=True, gap=8)
    p.card(lambda q: feed_post(q, comment=False), pad=12, radius=16)
    return s


def s_compose() -> Screen:
    s = Screen('New post', '/community/new', tab='community')
    p = s.p
    c = s.c
    p.header('New post', back=True)
    p.text('Everyone in Bookshelf will see the post. Nothing is published until you press "Publish".', color=MUTED, gap=10)
    p.segmented('What to publish', ['Quote', 'Review'], 0)
    p.text('Choose a quote', bold=True, gap=6)
    for chosen in (True, False):
        def option(q: Pane, on: bool = chosen) -> None:
            radio(q.c, q.x + 10, q.y + 14, on)
            q.bars(2, 0.6, x=q.x + 30, w=q.w - 30, gap=2)
            q.c.text(q.x + 30, q.y + 12, 'Book title', S, anchor='lm', color=MUTED)
            q.skip(22)

        p.card(option, pad=10, radius=12, gap=8, outline=INK if chosen else LINE, width=2.6 if chosen else 1.8)
    p.text('A copy of the text is published: later changes of the quote do not change the post.', color=MUTED, gap=8)
    p.field('Your thought (optional)', 'textarea', rows=2, counter='0 of 2000', gap=8)
    p.row([('Publish', 'primary', 1), ('Cancel', 'secondary')], gap=8, advance=False)
    return s


def main_ui_community() -> None:
    figure('ui_community', [s_feed(), s_post(), s_reader_profile(), s_compose()])


# =====================================================================================================================
# Figure ui_forms: a quote form, the profile, the sheet of a saved quote, the report dialog
# =====================================================================================================================

def s_quote_form() -> Screen:
    s = Screen('Quote form', '/quotes/new', tab='quotes')
    p = s.p
    p.header('New quote', back=True)
    p.field('Book', 'select', placeholder='Choose a book', gap=10)
    p.segmented('Type', ['Quote', 'Insight'], 0)
    p.field('Text', 'textarea', rows=4, error='Enter text up to 2000 characters.', counter='0 of 2000', gap=8)
    p.field('My thought', 'textarea', rows=2, gap=10)
    p.field('Page', 'input', gap=12)
    p.row([('Save', 'primary', 1), ('Cancel', 'secondary')], gap=8, advance=False)
    return s


def s_profile() -> Screen:
    s = Screen('Profile (top part)', '/profile', tab=None)
    p = s.p
    c = s.c
    p.header('Profile', back=True)
    p.field('Name', value='Reader name', gap=8)
    p.text('Nickname', gap=4)
    c.text(p.x + 2, p.y + 22, '@', S, anchor='lm', color=MUTED)
    c.rect(p.x + 22, p.y, p.w - 22, 44, WHITE, LINE, 1.8, radius=9)
    c.text(p.x + 34, p.y + 22, 'handle', S, anchor='lm')
    p.advance(44, 5)
    p.bars(1, 0.8, step=16, h=8, gap=8)
    p.text('About me', gap=4)
    c.rect(p.x, p.y, p.w, 62, WHITE, LINE, 1.8, radius=9)
    p.advance(62, 5)
    c.rect(p.x, p.y + 4, p.w * 0.6, 8, BAR, None, 0, radius=4)
    c.text(p.x + p.w, p.y + 8, '0 of 280', S, anchor='rm', color=MUTED)
    p.advance(20, 8)
    p.text('Email', gap=2)
    p.text('reader@example.com', color=MUTED, gap=2)
    p.bars(1, 0.8, step=16, h=8, gap=8)
    p.field('Time zone', 'select', value='Europe/Kyiv', gap=2, h=44)
    p.bars(1, 0.7, step=16, h=8, gap=10)
    p.button('Save', 'primary', gap=6)
    p.link('My public profile', h=30, gap=0)
    p.link('Blocked readers', h=30, gap=0)
    return s


def s_highlight() -> Screen:
    s = Screen('Sheet of a saved quote', '/read/:id', tabs=False)
    top = reader_top(s)
    reader_text(s, top, mark=True, rows=14)
    p = sheet(s, 'Saved quote', 420)
    quote_block(p, 3)
    p.text('My thought', color=MUTED, gap=2)
    p.bars(1, 0.7, gap=12)
    p.row([('Open quote', 'secondary'), ('Remove highlight', 'danger_text')], gap=10, pad=14, advance=False)
    p.advance(44, 10)

    def confirm(q: Pane) -> None:
        q.text('The quote will be deleted together with the highlight.', gap=8)
        q.row([('Yes, delete', 'danger', 1), ('Cancel', 'secondary', 1)], gap=8, advance=False)
        q.advance(44, 0)

    p.card(confirm, pad=12, radius=12, dashed=True)
    return s


def s_report() -> Screen:
    s = Screen('Report dialog', '/community', tabs=False)
    p = s.p
    p.header('Community', action='Write')
    p.card(lambda q: feed_post(q), pad=12, radius=16)
    c = s.c
    s.veil(SH)
    h = 436
    top = SH - h - 16
    c.rect(PAD, top, SW - 2 * PAD, h, WHITE, LINE, 1.8, radius=24)
    q = Pane(c, PAD + 16, top + 16, SW - 2 * PAD - 32)
    q.text('Report', 22, bold=True, gap=8)
    q.text('Reason', gap=4)
    for i, label in enumerate(['Spam', 'Abuse or inappropriate content', 'Other']):
        c.rect(q.x, q.y, q.w, 44, WHITE, INK if i == 0 else LINE, 2.6 if i == 0 else 1.6, radius=9)
        radio(c, q.x + 24, q.y + 22, i == 0)
        c.text(q.x + 46, q.y + 22, ellipsize(c, label, q.w - 56), S, anchor='lm')
        q.advance(44, 4)
    q.skip(6)
    q.text('Explanation (optional)', gap=4)
    c.rect(q.x, q.y, q.w, 70, WHITE, LINE, 1.8, radius=9)
    q.advance(70, 4)
    q.text('0 of 500', color=MUTED, align='right', gap=8)
    q.row([('Send report', 'primary', 1), ('Cancel', 'secondary')], gap=8, advance=False)
    return s


def main_ui_forms() -> None:
    figure('ui_forms', [s_quote_form(), s_profile(), s_highlight(), s_report()])


# =====================================================================================================================
# Figure ui_states: loading, empty, error and offline
# =====================================================================================================================

def tag_at(c: Canvas, x: float, y: float, name: str) -> None:
    """A small yellow label with the name of a component of the code."""
    w = c.measure(name, S, mono=True) + 14
    c.rect(x, y, w, 26, YELLOW, INK, 1.5, radius=4)
    c.text(x + w / 2, y + 13, name, S, mono=True, anchor='mm')


def s_loading() -> Screen:
    s = Screen('Loading', '/library', tab='library')
    p = s.p
    c = s.c
    p.header('Library', action='Add book')
    p.chips(['Want 3', 'Reading 2', 'Finished 12', 'Abandoned 1'], 0)
    p.skip(22)
    top = p.y
    for _ in range(3):
        c.rect(p.x, p.y, p.w, 118, '#F7F7F7', LINE, 1.8, radius=16)
        c.rect(p.x + 12, p.y + 14, 52, 78, TRACK, None, 0, radius=3)
        c.rect(p.x + 78, p.y + 20, p.w * 0.5, 12, TRACK, None, 0, radius=6)
        c.rect(p.x + 78, p.y + 44, p.w * 0.35, 12, TRACK, None, 0, radius=6)
        p.advance(118, 12)
    tag_at(c, p.x + 6, top - 30, 'SkeletonList')
    return s


def s_empty() -> Screen:
    s = Screen('Empty list', '/quotes', tab='quotes')
    p = s.p
    c = s.c
    p.header('Quotes', action='Add')
    x = p.x
    for i, label in enumerate(['All', 'Favorites']):
        width = c.measure(label, S) + 36
        p._button_box(x, p.y, width, 40, label, 'chip_on' if i == 0 else 'chip', S)
        x += width + 8
    p.advance(40, 12)
    p.field('', 'select', value='All books', gap=14)
    p.skip(22)
    top = p.y

    def empty(q: Pane) -> None:
        q.skip(10)
        q.text('No quotes yet', 22, bold=True, align='center', gap=8)
        q.text('Save passages and your own thoughts from books to come back to them.', color=MUTED, align='center', gap=14)
        q.button('Add quote', 'primary', w=0, align='center', gap=0)
        q.skip(10)

    p.card(empty, pad=20, radius=16, dashed=True)
    tag_at(c, p.x + 6, top - 30, 'EmptyState')
    return s


def s_error() -> Screen:
    s = Screen('Error and banners', '/', tab='home')
    c = s.c
    p = s.p
    # AppBanners: a strip over the whole width, above the header
    y = TOP - 10
    c.rect(0, y, SW, 66, RED, None, 0)
    lines = c.wrap('No internet. Changes will not be saved until the connection returns.', SW - 28, S)
    for i, line in enumerate(lines):
        c.text(SW / 2, y + 14 + i * 22, line, S, anchor='mt', color=DANGER_TEXT)
    y += 66
    c.rect(0, y, SW, 56, PRIMARY, None, 0)
    c.text(14, y + 28, 'A new version is available.', S, anchor='lm')
    bw = c.measure('Update', S, True) + 28
    c.rect(SW - 14 - bw, y + 8, bw, 40, WHITE, INK, 1.8, radius=9)
    c.text(SW - 14 - bw / 2, y + 28, 'Update', S, True, anchor='mm')
    p.y = y + 56 + 30
    p.tail = 0
    tag_at(c, PAD, y + 56 + 2, 'AppBanners')
    p.header('Reading')
    p.skip(22)
    top = p.y
    p.notice('Could not complete the request. Try again.', 'danger', retry=True)
    tag_at(c, p.x + 6, top - 30, 'ErrorNotice')
    return s


def wifi_off(c: Canvas, cx: float, cy: float, r: float) -> None:
    for k, radius in enumerate((r, r * 0.66, r * 0.33)):
        pts = []
        for deg in range(-45, 46, 5):
            a = math.radians(deg)
            pts.append((cx + radius * math.sin(a), cy + r * 0.6 - radius * math.cos(a)))
        c.polyline(pts, 3.2, MUTED)
    c.circle(cx, cy + r * 0.62, 3.4, MUTED, MUTED, 1)
    c.polyline([(cx - r * 0.85, cy - r * 0.55), (cx + r * 0.85, cy + r * 0.85)], 3.2, MUTED)


def s_offline_gate() -> Screen:
    s = Screen('No connection', 'OfflineGate', tabs=False)
    c = s.c
    p = s.p
    p.y = 250
    wifi_off(c, SW / 2, 270, 38)
    p.y = 330
    p.text('No connection', 26, bold=True, align='center', gap=10)
    p.text('The app cannot reach the server. Check the internet and try again.', color=MUTED, align='center', gap=16)
    p.button('Retry', 'primary', w=130, align='center')
    return s


def main_ui_states() -> None:
    figure('ui_states', [s_loading(), s_empty(), s_error(), s_offline_gate()])


# =====================================================================================================================
# Figure ui_map: the map of the screens
# =====================================================================================================================

def node(c: Canvas, x: float, y: float, w: float, h: float, title: str, sub: str = '', fill: str = BLUE, mono_sub: bool = True,
         dash: tuple | None = None, bold: bool = True) -> None:
    c.rect(x, y, w, h, fill, 'black', 2, radius=8, dash=dash)
    if sub:
        c.text(x + w / 2, y + h / 2 - 12, title, S, bold, anchor='mm')
        c.text(x + w / 2, y + h / 2 + 13, sub, S, mono=mono_sub, anchor='mm')
    else:
        c.text(x + w / 2, y + h / 2, title, S, bold, anchor='mm')


def draw_map() -> None:
    c = Canvas(1750, 904)
    BW, BH = 250, 64
    STRIDE = 285
    X0 = 35

    def colx(i: int) -> float:
        return X0 + i * STRIDE

    # ---- the start of the application and the guard ----
    node(c, 35, 36, 235, 70, 'BootScreen', 'while /api/me is asked', GREY, mono_sub=False)
    node(c, 35, 150, 235, 70, 'OfflineGate', 'server unreachable', GREY, mono_sub=False)
    c.arrow([(80, 106), (80, 150)])
    c.text(92, 128, 'no answer', S, anchor='lm')
    c.arrow([(235, 150), (235, 106)])
    c.text(225, 128, 'Retry', S, anchor='rm')
    node(c, 355, 36, 330, 70, 'Router guard', 'redirectFor() in router/access.ts', GREY, mono_sub=False)
    c.arrow([(270, 71), (355, 71)])
    c.text(312, 54, 'answer', S, anchor='mm')

    # the guest zone
    c.rect(765, 22, 520, 198, WHITE, 'black', 2, radius=10, dash=(10, 6))
    c.text(1025, 42, 'Guest only (guestOnly)', S, True, anchor='mm')
    node(c, 785, 70, 215, 70, 'Sign in', '/login', YELLOW)
    node(c, 1055, 70, 215, 70, 'Registration', '/register', YELLOW)
    c.arrow([(1000, 105), (1055, 105)], head='both')
    c.text(1025, 168, 'redirected here when not signed in', S, anchor='mm')
    c.arrow([(685, 71), (765, 71)])
    c.text(725, 54, 'guest', S, anchor='mm')

    # ---- the layout with the tab bar ----
    top = 250
    c.rect(15, top, 1720, 495, WHITE, 'black', 2, radius=12)
    c.text(30, top + 18, 'AppLayout: sign-in required (requiresAuth); AppBanners on top, the screen, the tab bar (TabBar) at the bottom',
           S, True, anchor='lm')
    c.arrow([(520, 106), (520, top)])
    c.text(532, 160, 'signed in', S, anchor='lm')
    c.arrow([(1020, 220), (1020, top)])
    c.text(1032, 236, 'success: Reading', S, anchor='lm')

    tab_y = top + 42
    row_y = [tab_y + 100 + r * 94 for r in range(4)]  # rows of screens

    tabs = [('Reading «Читаю»', 'home'), ('Library «Бібліотека»', 'library'), ('Quotes «Цитати»', 'quotes'),
            ('Statistics «Статистика»', 'stats'), ('Community «Спільнота»', 'community')]
    for i, (label, _) in enumerate(tabs):
        node(c, colx(i), tab_y, BW, BH, label, 'tab', LIGHT, mono_sub=False)
    node(c, colx(5), tab_y, BW, BH, 'Avatar in the header', 'opens the profile', GREY, mono_sub=False)

    columns = [
        [('Reading', '/')],
        [('Library', '/library'), ('Add a book', '/library/add'), ('Book card', '/library/:id')],
        [('Quotes', '/quotes'), ('New quote', '/quotes/new'), ('Edit quote', '/quotes/:id/edit'), ('Quote card', '/quotes/:id/share')],
        [('Statistics', '/stats')],
        [('Feed', '/community'), ('New post', '/community/new'), ('Post', '/community/posts/:id'), ('Reader profile', '/u/:handle')],
        [('Profile', '/profile'), ('Blocked readers', '/community/blocked'), ('Not found', '/:pathMatch(.*)*')],
    ]
    for i, screens in enumerate(columns):
        x = colx(i)
        for r, (name, path) in enumerate(screens):
            fill = GREY if i == 5 else BLUE
            node(c, x, row_y[r], BW, BH, name, path, fill)
        # tab -> first screen
        c.arrow([(x + BW / 2, tab_y + BH), (x + BW / 2, row_y[0])])
        # the first screen leads to the others
        if len(screens) > 1 and i != 5:
            spine = x - 14
            first_mid = row_y[0] + BH / 2
            last_mid = row_y[len(screens) - 1] + BH / 2
            c.polyline([(x, first_mid), (spine, first_mid), (spine, last_mid)], 2)
            for r in range(1, len(screens)):
                mid = row_y[r] + BH / 2
                c.arrow([(spine, mid), (x, mid)], head_len=12, head_w=8)
    # the profile leads to the list of blocked readers (the last but one box of the sixth column)
    x = colx(5)
    c.arrow([(x + BW / 2, row_y[0] + BH), (x + BW / 2, row_y[1])], head_len=12, head_w=8)

    # ---- the reader, outside of the layout ----
    reader_y = 790
    node(c, colx(1), reader_y, BW, BH, 'Reader', '/read/:id', GREEN)
    c.arrow([(colx(1) + BW / 2, row_y[2] + BH), (colx(1) + BW / 2, reader_y)])
    c.text(colx(1) + BW / 2 + 14, 772, 'Read, Continue reading', S, anchor='lm')
    c.text(colx(1) + BW + 20, reader_y + BH / 2, 'full screen, no tab bar (requiresAuth)', S, anchor='lm')

    # ---- legend ----
    lx, ly = 1130, 776
    for k, (fill, label, dash) in enumerate([(BLUE, 'screen with the tab bar', None), (GREY, 'outside the five tabs', None),
                                             (YELLOW, 'guest only', None), (GREEN, 'full screen, outside the layout', None)]):
        c.rect(lx + (k % 2) * 300, ly + (k // 2) * 40, 34, 26, fill, 'black', 1.6, radius=4)
        c.text(lx + (k % 2) * 300 + 44, ly + (k // 2) * 40 + 13, label, S, anchor='lm')
    c.arrow([(lx, ly + 94), (lx + 40, ly + 94)], head_len=11, head_w=7)
    c.text(lx + 50, ly + 94, 'navigation inside a tab', S, anchor='lm')
    path = c.save(FIGURES / 'ui_map.png')
    print(f'ui_map: 1750 x 904  ->  {path.name}')


def main_ui_main() -> None:
    figure('ui_main', [s_signin(), s_reading(), s_library(), s_add_book()])


FIGURE_JOBS = {
    'ui_map': draw_map,
    'ui_main': main_ui_main,
    'ui_progress': main_ui_progress,
    'ui_stats': main_ui_stats,
    'ui_reader': main_ui_reader,
    'ui_community': main_ui_community,
    'ui_forms': main_ui_forms,
    'ui_states': main_ui_states,
}


def main() -> None:
    names = sys.argv[1:] or list(FIGURE_JOBS)
    for name in names:
        FIGURE_JOBS[name]()


if __name__ == '__main__':
    main()
