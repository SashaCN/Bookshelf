"""Drawing of the diagrams for the lab reports with Pillow, in the style of the lab 2 figures: Times New Roman,
black outlines, light fills, rounded boxes.

Everything is drawn in "units". A canvas of 1100 x 800 units becomes a 2200 x 1600 px picture at 320 dpi, which Word
shows 6.5 inches wide; with the default text size of 18 units the text is about 7.6 pt on paper. Wide landscape figures use
about 1700 units. The picture is drawn four times larger and shrunk, so lines and curves are smooth.

Besides the primitives (text, rect, line, arrow, ...) the module has the symbols of the notations of laboratory work 3:
 * GOST 19.701-90 data symbols (`stored_data`, `memory`, `disk`, `document`, `manual_input`, `display`, ...);
 * structure charts of Constantine (`c_module`, `c_subsystem`, `c_library`, `c_data_area`, `c_call`, `c_couple`);
 * structure charts of Jackson (`j_box`, `j_tree`).

Python 3.9 compatible.
"""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

FONT_DIR = Path('/System/Library/Fonts/Supplemental')
_FONT_FILES = {
    (False, False): 'Times New Roman.ttf',
    (True, False): 'Times New Roman Bold.ttf',
    (False, True): 'Times New Roman Italic.ttf',
    (True, True): 'Times New Roman Bold Italic.ttf',
}
_MONO_FILES = {False: 'Courier New.ttf', True: 'Courier New Bold.ttf'}

# Fills used by the figures of the lab reports.
GREY = '#EDEDED'
LIGHT = '#F5F5F5'
BLUE = '#E6EDF9'
YELLOW = '#FFF1CC'
GREEN = '#E4F2E1'
RED = '#FBE3E1'
WHITE = '#FFFFFF'

Point = tuple


class Canvas:
    def __init__(self, width: float, height: float, background: str = 'white', supersample: int = 4, out: int = 2):
        self.w = width
        self.h = height
        self.k = supersample * out  # drawing pixels per unit
        self.out = out
        self.img = Image.new('RGB', (int(round(width * self.k)), int(round(height * self.k))), background)
        self.d = ImageDraw.Draw(self.img)
        self._fonts: dict = {}

    # ---- text -------------------------------------------------------------------------------------------------

    def font(self, size: float, bold: bool = False, italic: bool = False, mono: bool = False) -> ImageFont.FreeTypeFont:
        key = (round(size * self.k), bold, italic, mono)
        if key not in self._fonts:
            name = _MONO_FILES[bold] if mono else _FONT_FILES[(bold, italic)]
            self._fonts[key] = ImageFont.truetype(str(FONT_DIR / name), key[0])
        return self._fonts[key]

    def measure(self, text: str, size: float = 18, bold: bool = False, italic: bool = False, mono: bool = False) -> float:
        """Width in units of one line of text."""
        return self.font(size, bold, italic, mono).getlength(text) / self.k

    def wrap(self, text: str, width: float, size: float = 18, bold: bool = False, italic: bool = False,
             mono: bool = False) -> list:
        """Breaks text into lines not wider than `width` units (explicit line breaks are kept)."""
        lines = []
        for paragraph in text.split('\n'):
            words = paragraph.split(' ')
            current = ''
            for word in words:
                trial = word if not current else current + ' ' + word
                if current and self.measure(trial, size, bold, italic, mono) > width:
                    lines.append(current)
                    current = word
                else:
                    current = trial
            lines.append(current)
        return lines

    def text(self, x: float, y: float, text: str, size: float = 18, bold: bool = False, italic: bool = False,
             mono: bool = False, anchor: str = 'mm', color: str = 'black', align: str = 'center',
             leading: float = 1.15) -> float:
        """Draws (possibly multi-line) text around (x, y). `anchor` is two letters: horizontal l/m/r, vertical t/m/b.
        Returns the height of the text block."""
        lines = text.split('\n')
        line_h = size * leading
        total = line_h * len(lines)
        h_anchor, v_anchor = anchor[0], anchor[1]
        top = y - (0 if v_anchor == 't' else total / 2 if v_anchor == 'm' else total)
        font = self.font(size, bold, italic, mono)
        widest = max(self.measure(line, size, bold, italic, mono) for line in lines)
        for i, line in enumerate(lines):
            line_w = self.measure(line, size, bold, italic, mono)
            if h_anchor == 'l':
                left = x
            elif h_anchor == 'r':
                left = x - widest
            else:
                left = x - widest / 2
            if align == 'center':
                lx = left + (widest - line_w) / 2
            elif align == 'right':
                lx = left + widest - line_w
            else:
                lx = left
            baseline = top + line_h * i + line_h / 2
            self.d.text((lx * self.k, baseline * self.k), line, font=font, fill=color, anchor='lm')
        return total

    def text_in(self, x: float, y: float, w: float, h: float, text: str, size: float = 18, bold: bool = False,
                italic: bool = False, mono: bool = False, pad: float = 8, color: str = 'black', align: str = 'center',
                min_size: float = 9, anchor: str = 'mm') -> float:
        """Wraps the text to the box and, if it still does not fit, makes the font smaller. Returns the size used."""
        s = size
        while True:
            lines = self.wrap(text, w - 2 * pad, s, bold, italic, mono)
            if len(lines) * s * 1.15 <= h - 2 * pad or s <= min_size:
                break
            s -= 0.5
        wrapped = '\n'.join(lines)
        if anchor == 'mm':
            self.text(x + w / 2, y + h / 2, wrapped, s, bold, italic, mono, 'mm', color, align)
        elif anchor == 'lm':
            self.text(x + pad, y + h / 2, wrapped, s, bold, italic, mono, 'lm', color, align)
        elif anchor == 'mt':
            self.text(x + w / 2, y + pad, wrapped, s, bold, italic, mono, 'mt', color, align)
        elif anchor == 'lt':
            self.text(x + pad, y + pad, wrapped, s, bold, italic, mono, 'lt', color, align)
        return s

    # ---- shapes -----------------------------------------------------------------------------------------------

    def _p(self, pts):
        return [(px * self.k, py * self.k) for px, py in pts]

    def rect(self, x: float, y: float, w: float, h: float, fill: str | None = WHITE, outline: str | None = 'black',
             width: float = 2, radius: float = 0, dash: tuple | None = None) -> None:
        if dash:
            if fill:
                self.d.rectangle(self._p([(x, y), (x + w, y + h)]), fill=fill)
            self.polyline([(x, y), (x + w, y), (x + w, y + h), (x, y + h), (x, y)], width=width, color=outline or 'black', dash=dash)
            return
        box = self._p([(x, y), (x + w, y + h)])
        if radius:
            self.d.rounded_rectangle(box, radius=radius * self.k, fill=fill, outline=outline, width=int(width * self.k))
        else:
            self.d.rectangle(box, fill=fill, outline=outline, width=int(width * self.k))

    def ellipse(self, x: float, y: float, w: float, h: float, fill: str | None = WHITE, outline: str | None = 'black',
                width: float = 2) -> None:
        self.d.ellipse(self._p([(x, y), (x + w, y + h)]), fill=fill, outline=outline, width=int(width * self.k))

    def polygon(self, pts: list, fill: str | None = WHITE, outline: str | None = 'black', width: float = 2) -> None:
        if fill:
            self.d.polygon(self._p(pts), fill=fill)
        if outline:
            self.polyline(list(pts) + [pts[0]], width=width, color=outline)

    def diamond(self, cx: float, cy: float, w: float, h: float, fill: str | None = WHITE, outline: str | None = 'black',
                width: float = 2) -> None:
        self.polygon([(cx, cy - h / 2), (cx + w / 2, cy), (cx, cy + h / 2), (cx - w / 2, cy)], fill, outline, width)

    def polyline(self, pts: list, width: float = 2, color: str = 'black', dash: tuple | None = None) -> None:
        """A polyline with round joints; `dash` is (dash, gap) in units."""
        w = max(1, int(round(width * self.k)))
        if not dash:
            self.d.line(self._p(pts), fill=color, width=w, joint='curve')
            r = w / 2
            for px, py in self._p(pts):
                self.d.ellipse((px - r, py - r, px + r, py + r), fill=color)
            return
        on, off = dash
        for (x1, y1), (x2, y2) in zip(pts, pts[1:]):
            length = math.hypot(x2 - x1, y2 - y1)
            if length == 0:
                continue
            ux, uy = (x2 - x1) / length, (y2 - y1) / length
            pos = 0.0
            while pos < length:
                end = min(pos + on, length)
                self.d.line(self._p([(x1 + ux * pos, y1 + uy * pos), (x1 + ux * end, y1 + uy * end)]), fill=color, width=w)
                pos += on + off

    line = polyline

    def arrow(self, pts: list, width: float = 2, head: str = 'end', color: str = 'black', dash: tuple | None = None,
              head_len: float = 14, head_w: float = 9, open_head: bool = False) -> None:
        """A polyline with an arrowhead at the end (`head='end'`), at both ends (`'both'`) or at the start (`'start'`)."""
        pts = list(pts)
        line_pts = list(pts)
        shorten = head_len * 0.8
        if head in ('end', 'both'):
            line_pts[-1] = self._back(pts[-1], pts[-2], shorten)
        if head in ('start', 'both'):
            line_pts[0] = self._back(pts[0], pts[1], shorten)
        self.polyline(line_pts, width, color, dash)
        if head in ('end', 'both'):
            self._head(pts[-2], pts[-1], head_len, head_w, color, open_head, width)
        if head in ('start', 'both'):
            self._head(pts[1], pts[0], head_len, head_w, color, open_head, width)

    @staticmethod
    def _back(tip: tuple, other: tuple, dist: float) -> tuple:
        dx, dy = other[0] - tip[0], other[1] - tip[1]
        length = math.hypot(dx, dy) or 1
        return (tip[0] + dx / length * dist, tip[1] + dy / length * dist)

    def _head(self, frm: tuple, tip: tuple, length: float, half_w: float, color: str, open_head: bool, width: float) -> None:
        dx, dy = tip[0] - frm[0], tip[1] - frm[1]
        norm = math.hypot(dx, dy) or 1
        ux, uy = dx / norm, dy / norm
        bx, by = tip[0] - ux * length, tip[1] - uy * length
        left = (bx - uy * half_w / 2 * 1.0, by + ux * half_w / 2 * 1.0)
        right = (bx + uy * half_w / 2 * 1.0, by - ux * half_w / 2 * 1.0)
        if open_head:
            self.polyline([left, tip, right], width=width, color=color)
        else:
            self.d.polygon(self._p([tip, left, right]), fill=color)

    def circle(self, cx: float, cy: float, r: float, fill: str | None = WHITE, outline: str | None = 'black',
               width: float = 1.5) -> None:
        self.ellipse(cx - r, cy - r, 2 * r, 2 * r, fill, outline, width)

    def box_text(self, x: float, y: float, w: float, h: float, text: str, fill: str | None = WHITE, size: float = 18,
                 bold: bool = False, italic: bool = False, mono: bool = False, radius: float = 8, width: float = 2,
                 outline: str = 'black', pad: float = 8, align: str = 'center', anchor: str = 'mm') -> None:
        """A (rounded) box with wrapped, fitted text."""
        self.rect(x, y, w, h, fill, outline, width, radius)
        self.text_in(x, y, w, h, text, size, bold, italic, mono, pad, align=align, anchor=anchor)

    # ---- output -----------------------------------------------------------------------------------------------

    def save(self, path: str | Path, dpi: int = 320) -> Path:
        path = Path(path)
        path.parent.mkdir(parents=True, exist_ok=True)
        final = self.img.resize((int(round(self.w * self.out)), int(round(self.h * self.out))), Image.LANCZOS)
        final.save(path, dpi=(dpi, dpi), optimize=True)
        return path


# ---- GOST 19.701-90 symbols (data and processing) -----------------------------------------------------------------

def _label(c: Canvas, x, y, w, h, text, size, bold=False, pad=10, anchor='mm', mono=False):
    c.text_in(x, y, w, h, text, size, bold, pad=pad, anchor=anchor, mono=mono)


def process(c: Canvas, x, y, w, h, text, fill=WHITE, size=18, bold=False) -> None:
    """Processing: a rectangle."""
    c.rect(x, y, w, h, fill, 'black', 2)
    _label(c, x, y, w, h, text, size, bold)


def data_io(c: Canvas, x, y, w, h, text, fill=WHITE, size=18) -> None:
    """Data (input/output without a device): a parallelogram."""
    s = min(h * 0.45, w * 0.18)
    c.polygon([(x + s, y), (x + w, y), (x + w - s, y + h), (x, y + h)], fill)
    _label(c, x + s / 2, y, w - s, h, text, size)


def _side(cx: float, y: float, h: float, bulge: float, sign: int, reverse: bool = False) -> list:
    """Points of a curved vertical side: a half-ellipse around x = cx that bulges by `bulge` to the left (sign -1) or
    to the right (sign +1), running from the top to the bottom (or back with `reverse`)."""
    pts = [(cx + sign * bulge * math.sin(math.radians(t)), y + h / 2 - (h / 2) * math.cos(math.radians(t)))
           for t in range(0, 181, 9)]
    return pts[::-1] if reverse else pts


def stored_data(c: Canvas, x, y, w, h, text, fill=WHITE, size=18) -> None:
    """Stored data (the device is not specified): the left side bulges outwards, the right side is concave."""
    b = min(w * 0.12, 26)
    left = _side(x + b, y, h, b, -1)  # top to bottom, bulging to the left
    right = _side(x + w - b, y, h, b, -1, reverse=True)  # bottom to top, curving into the shape
    c.polygon(left + right, fill)
    _label(c, x + b, y, w - 2 * b, h, text, size)


def memory(c: Canvas, x, y, w, h, text, fill=WHITE, size=18) -> None:
    """Internal (operative) memory: a rectangle with a line near the top and one near the left side."""
    c.rect(x, y, w, h, fill, 'black', 2)
    c.polyline([(x, y + h * 0.16), (x + w, y + h * 0.16)], 1.5)
    c.polyline([(x + w * 0.1, y), (x + w * 0.1, y + h)], 1.5)
    _label(c, x + w * 0.1, y + h * 0.16, w * 0.9, h * 0.84, text, size)


def disk(c: Canvas, x, y, w, h, text, fill=WHITE, size=18) -> None:
    """Direct access storage (a magnetic disk): a drum lying on its side."""
    b = min(w * 0.1, 24)
    left = _side(x + b, y, h, b, -1)
    right = _side(x + w - b, y, h, b, 1, reverse=True)
    c.polygon(left + right, fill)
    c.polyline(_side(x + w - b, y, h, b, -1), 1.5)
    _label(c, x + b, y, w - 2.5 * b, h, text, size)


def document(c: Canvas, x, y, w, h, text, fill=WHITE, size=18) -> None:
    """Document (printed data): a rectangle with a wavy bottom."""
    wave = h * 0.09
    base = y + h - wave
    bottom = [(x + w - w * t / 40, base + wave * math.sin(t / 40 * 2 * math.pi)) for t in range(0, 41)]
    c.polygon([(x, y), (x + w, y)] + bottom, fill)
    _label(c, x, y, w, h - wave, text, size)


def manual_input(c: Canvas, x, y, w, h, text, fill=WHITE, size=18) -> None:
    """Manual input (a keyboard): a rectangle with a slanted top side."""
    c.polygon([(x, y + h * 0.2), (x + w, y), (x + w, y + h), (x, y + h)], fill)
    _label(c, x, y + h * 0.1, w, h * 0.9, text, size)


def display(c: Canvas, x, y, w, h, text, fill=WHITE, size=18) -> None:
    """Display: a pointed left side, a rounded right side."""
    tip = min(w * 0.14, 30)
    top = [(x + tip, y), (x + w - h / 2, y)]
    arc = [(x + w - h / 2 + (h / 2) * math.cos(math.radians(t)), y + h / 2 + (h / 2) * math.sin(math.radians(t))) for t in range(-90, 91, 8)]
    c.polygon([(x + tip, y)] + top[1:] + arc + [(x + tip, y + h), (x, y + h / 2)], fill)
    _label(c, x + tip, y, w - tip - h * 0.25, h, text, size)


# ---- structure charts of Constantine ------------------------------------------------------------------------------

def c_module(c: Canvas, x, y, w, h, text, fill=WHITE, size=17, bold=False) -> None:
    """A module: a plain rectangle with the name inside."""
    c.rect(x, y, w, h, fill, 'black', 2)
    _label(c, x, y, w, h, text, size, bold, pad=8)


def c_subsystem(c: Canvas, x, y, w, h, text, fill=WHITE, size=17, bold=False) -> None:
    """A subsystem or program: a rectangle with two vertical lines on each side."""
    c.rect(x, y, w, h, fill, 'black', 2)
    gap = min(10, w * 0.06)
    c.polyline([(x + gap, y), (x + gap, y + h)], 1.5)
    c.polyline([(x + w - gap, y), (x + w - gap, y + h)], 1.5)
    _label(c, x + gap, y, w - 2 * gap, h, text, size, bold, pad=8)


def c_library(c: Canvas, x, y, w, h, text, fill=WHITE, size=17, bold=False) -> None:
    """A library: a rectangle with two horizontal lines, near the top and near the bottom."""
    c.rect(x, y, w, h, fill, 'black', 2)
    gap = min(10, h * 0.16)
    c.polyline([(x, y + gap), (x + w, y + gap)], 1.5)
    c.polyline([(x, y + h - gap), (x + w, y + h - gap)], 1.5)
    _label(c, x, y + gap, w, h - 2 * gap, text, size, bold, pad=6)


def c_data_area(c: Canvas, x, y, w, h, text, fill=WHITE, size=17, bold=False) -> None:
    """A data area: an ellipse."""
    c.ellipse(x, y, w, h, fill, 'black', 2)
    _label(c, x + w * 0.1, y + h * 0.1, w * 0.8, h * 0.8, text, size, bold, pad=4)


def c_call(c: Canvas, parent: tuple, child: tuple, kind: str = 'seq', cond: bool = False, loop: bool = False,
           once: bool = False, color: str = 'black') -> None:
    """The call of a module. `parent` and `child` are the points where the line leaves the caller and enters the
    called module. `kind`: 'seq' (a solid arrow), 'par' (a dashed arrow) or 'co' (a coroutine: a bar at the start).
    `cond` puts a diamond at the caller (a conditional call), `loop` a semicircle (the called module is called in a
    loop), `once` the digit 1 (called once)."""
    x1, y1 = parent
    x2, y2 = child
    dash = (8, 6) if kind == 'par' else None
    start = (x1, y1)
    if cond:
        c.diamond(x1, y1 + 8, 14, 16)
        start = (x1, y1 + 16)
    elif loop:
        c.ellipse(x1 - 9, y1 - 9, 18, 18, WHITE, 'black', 2)
        start = (x1, y1 + 9)
    c.arrow([start, (x2, y2)], width=1.8, dash=dash, color=color, head_len=12, head_w=8)
    if kind == 'co':
        dx, dy = x2 - x1, y2 - y1
        n = math.hypot(dx, dy) or 1
        nx, ny = -dy / n, dx / n
        c.polyline([(start[0] + nx * 7, start[1] + ny * 7), (start[0] - nx * 7, start[1] - ny * 7)], 2)
    if once:
        c.text(x1 + 12, y1 + 18, '1', 14, bold=True, anchor='lm')


def c_couple(c: Canvas, p1: tuple, p2: tuple, label: str, control: bool = False, side: str = 'right',
             size: float = 14, offset: float = 16) -> None:
    """A coupling of two modules, drawn next to the call line: a thin arrow with a small circle at the tail (empty for
    coupling by data, filled for coupling by control) and the passed parameters as a label.
    `p1` is the tail, `p2` the head; the arrow is moved by `offset` units to the side of the call line."""
    dx, dy = p2[0] - p1[0], p2[1] - p1[1]
    n = math.hypot(dx, dy) or 1
    sign = 1 if side == 'right' else -1
    nx, ny = -dy / n * sign, dx / n * sign
    a = (p1[0] + nx * offset, p1[1] + ny * offset)
    b = (p2[0] + nx * offset, p2[1] + ny * offset)
    # the arrow is a little shorter than the line so it does not touch the boxes
    ax, ay = a[0] + dx / n * 10, a[1] + dy / n * 10
    bx, by = b[0] - dx / n * 6, b[1] - dy / n * 6
    c.arrow([(ax, ay), (bx, by)], width=1.4, head_len=9, head_w=6)
    c.circle(ax, ay, 4, 'black' if control else WHITE, 'black', 1.4)
    mx, my = (ax + bx) / 2 + nx * 6, (ay + by) / 2 + ny * 6
    c.text(mx, my, label, size, anchor='lm' if side == 'right' else 'rm', italic=True)


# ---- structure charts of Jackson ----------------------------------------------------------------------------------

def j_box(c: Canvas, x, y, w, h, text, kind: str = 'seq', size=17, fill=WHITE, bold=False) -> None:
    """A block of a Jackson chart. `kind`: 'op' or 'seq' (a plain box), 'sel' (a small circle "o" in the top right corner:
    one of the alternatives) or 'iter' (an asterisk in the corner: repeated)."""
    c.rect(x, y, w, h, fill, 'black', 2)
    _label(c, x, y, w, h, text, size, bold, pad=7)
    if kind == 'sel':
        c.circle(x + w - 12, y + 12, 6, WHITE, 'black', 1.6)
    elif kind == 'iter':
        c.text(x + w - 12, y + 13, '*', 24, bold=True, anchor='mm')


def j_tree(c: Canvas, parent: tuple, children: list, drop: float = 26) -> None:
    """Lines from a parent box (`(x, y, w, h)`) to child boxes: down from the bottom of the parent to a horizontal bar,
    then down to the top of each child."""
    px, py, pw, ph = parent
    start = (px + pw / 2, py + ph)
    bar_y = children[0][1] - drop
    c.polyline([start, (start[0], bar_y)], 2)
    xs = [bx + bw / 2 for bx, by, bw, bh in children]
    c.polyline([(min(xs + [start[0]]), bar_y), (max(xs + [start[0]]), bar_y)], 2)
    for (bx, by, bw, bh), cx in zip(children, xs):
        c.polyline([(cx, bar_y), (cx, by)], 2)
