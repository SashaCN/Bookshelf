"""Functional schemes of the technical design (section 9): the legend of the symbols of GOST 19.701-90, the functional
scheme of the system and the functional scheme of the built-in reading. Run from the repository root:

    python3 docs/lab3/diagrams/functional.py

and the pictures are written to docs/lab3/figures/ (gost_legend.png, functional_system.png, functional_reader.png).
"""

from __future__ import annotations

import math
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'docs' / 'tools'))

import diagram as dg  # noqa: E402
from diagram import BLUE, GREEN, GREY, LIGHT, RED, WHITE, YELLOW, Canvas  # noqa: E402

FIGURES = ROOT / 'docs' / 'lab3' / 'figures'


# ---- symbols with a bold title and details -----------------------------------------------------------------------

def inner(kind: str, x, y, w, h) -> tuple:
    """The rectangle inside a GOST symbol where the text may go."""
    if kind == 'process':
        return x, y, w, h
    if kind == 'data':
        s = min(h * 0.45, w * 0.18)
        return x + s / 2, y, w - s, h
    if kind == 'stored':
        b = min(w * 0.12, 26)
        return x + b * 0.7, y, w - b * 2.7, h
    if kind == 'memory':
        return x + w * 0.1, y + h * 0.16, w * 0.9, h * 0.84
    if kind == 'disk':
        b = min(w * 0.1, 24)
        return x + b * 0.8, y, w - 3.2 * b, h
    if kind == 'document':
        return x, y, w, h - h * 0.09
    if kind == 'input':
        return x, y + h * 0.1, w, h * 0.9
    if kind == 'display':
        tip = min(w * 0.14, 30)
        return x + tip, y, w - tip - h * 0.25, h
    raise ValueError(kind)


DRAW = {'process': dg.process, 'data': dg.data_io, 'stored': dg.stored_data, 'memory': dg.memory, 'disk': dg.disk,
        'document': dg.document, 'input': dg.manual_input, 'display': dg.display}


def put_text(c: Canvas, rect, title: str, detail: str = '', tsize=19, dsize=18, pad=3, align='center', check=True,
             top=False) -> None:
    """Writes a bold title and wrapped detail lines into `rect`, centered; raises when they do not fit."""
    rx, ry, rw, rh = rect
    margin = 10
    tl = c.wrap(title, rw - 2 * margin, tsize, bold=True) if title else []
    dl = []
    for part in detail.split('\n') if detail else []:
        dl += c.wrap(part, rw - 2 * margin, dsize)
    total = len(tl) * tsize * 1.15 + len(dl) * dsize * 1.15
    if check and total > rh - 2 * pad + 0.5:
        raise ValueError(f'text does not fit into {title!r}: needs {total:.0f}, has {rh - 2 * pad:.0f}')
    yy = ry + pad if top else ry + (rh - total) / 2
    for line in tl:
        c.text(rx + rw / 2, yy + tsize * 0.575, line, tsize, bold=True, anchor='mm')
        yy += tsize * 1.15
    for line in dl:
        x = rx + rw / 2 if align == 'center' else rx + margin
        c.text(x, yy + dsize * 0.575, line, dsize, anchor='mm' if align == 'center' else 'lm')
        yy += dsize * 1.15


def sym(c: Canvas, kind: str, x, y, w, h, title: str, detail: str = '', fill=WHITE, tsize=19, dsize=18) -> None:
    DRAW[kind](c, x, y, w, h, '', fill)
    put_text(c, inner(kind, x, y, w, h), title, detail, tsize, dsize)


def label(c: Canvas, x, y, text, size=18, anchor='mm', italic=False, fill=WHITE, bold=False) -> None:
    """Text on a white patch; `anchor` is the horizontal l/m/r and vertical t/m/b position of the patch."""
    lines = text.split('\n')
    widest = max(c.measure(line, size, bold, italic) for line in lines)
    height = len(lines) * size * 1.15
    left = x - widest / 2 if anchor[0] == 'm' else x if anchor[0] == 'l' else x - widest
    top = y - height / 2 if anchor[1] == 'm' else y if anchor[1] == 't' else y - height
    if fill:
        c.rect(left - 3, top - 1, widest + 6, height + 2, fill, None, 0)
    c.text(left + widest / 2, top + height / 2, text, size, bold=bold, italic=italic, anchor='mm')


def disk_left(x, y, h, yy) -> float:
    """The x of the curved left side of a direct access storage symbol at height `yy` (see diagram.disk)."""
    b = min(1e9, 24)
    u = min(max((yy - y) / h, 0.0), 1.0)
    s = math.sqrt(max(0.0, 1 - (1 - 2 * u) ** 2))
    return x + b - b * s


def arrow(c: Canvas, pts, both=False, dash=None, width=2) -> None:
    c.arrow(pts, width=width, head='both' if both else 'end', head_len=13, head_w=9, dash=dash)


def number(c: Canvas, x, y, n) -> None:
    """A number in a circle on an arrow: the number of the flow in the text."""
    c.circle(x, y, 13, WHITE, 'black', 1.6)
    c.text(x, y + 0.5, str(n), 18, bold=True, anchor='mm')


# ---- the legend of the symbols -----------------------------------------------------------------------------------

def gost_legend() -> Path:
    W, H = 900, 1004
    c = Canvas(W, H)
    rows = [
        ('process', 'Processing', 'a program component that processes data'),
        ('data', 'Data', 'data in transit; the medium is not shown'),
        ('stored', 'Stored data', 'data on a medium that is not specified'),
        ('memory', 'Internal memory', 'the memory of a running program'),
        ('disk', 'Direct access storage', 'a disk, a database'),
        ('document', 'Document', 'data printed or saved as a document'),
        ('input', 'Manual input', 'data typed or chosen by a person'),
        ('display', 'Display', 'data shown on a screen'),
    ]
    y = 20
    for kind, name, text in rows:
        DRAW[kind](c, 30, y, 230, 72, '', WHITE)
        c.text(290, y + 22, name, 23, bold=True, anchor='lm')
        c.text(290, y + 52, text, 19, anchor='lm')
        y += 100
    arrow(c, [(30, y + 36), (260, y + 36)])
    c.text(290, y + 22, 'Flow of data', 23, bold=True, anchor='lm')
    c.text(290, y + 52, 'an arrow; its label is the composition of the data', 19, anchor='lm')
    y += 100
    arrow(c, [(30, y + 36), (260, y + 36)], both=True)
    c.text(290, y + 22, 'Request and answer', 23, bold=True, anchor='lm')
    c.text(290, y + 52, 'a double arrow: the data go both ways', 19, anchor='lm')
    return c.save(FIGURES / 'gost_legend.png')


# ---- the functional scheme of the system -------------------------------------------------------------------------

def functional_system() -> Path:
    W, H = 1750, 960
    c = Canvas(W, H)
    A = 13  # head length used for the short arrows

    # ---- the client (left) ----
    c0, c1, c2 = (15, 215), (275, 240), (555, 235)  # x and width of the three columns

    sym(c, 'input', c0[0], 20, c0[1], 76, 'EPUB file', 'chosen on the book page', GREY)
    sym(c, 'input', c1[0], 20, c1[1], 76, 'Forms and selection', 'of the reader', GREY)
    sym(c, 'process', c2[0], 20, c2[1], 76, 'Service worker', 'src/sw, /sw.js', BLUE)
    sym(c, 'display', c0[0], 185, c0[1], 70, 'Screens', 'lists, cards, charts', GREY)
    sym(c, 'process', c1[0], 170, c1[1], 300, 'Views and components', 'src/views, src/components: forms, lists, the reader', BLUE)
    sym(c, 'stored', c2[0], 170, c2[1], 80, 'Cache Storage', 'app shell files', YELLOW)
    sym(c, 'stored', c0[0], 290, c0[1], 96, 'localStorage', 'fontSize, lineHeight, font, theme', YELLOW)
    sym(c, 'process', c2[0], 300, c2[1], 90, 'API client', 'src/api: http.ts and 6 modules', BLUE)
    sym(c, 'process', c0[0], 410, c0[1], 100, 'Quote card renderer', 'quoteCard.ts: text, title, authors, page', BLUE)
    sym(c, 'stored', c2[0], 470, c2[1], 80, 'Cookies', 'session, XSRF-TOKEN', YELLOW)
    sym(c, 'memory', c1[0], 560, c1[1], 80, 'Pinia stores', '8 stores', YELLOW)
    sym(c, 'document', c0[0], 580, c0[1], 84, 'Quote card image', 'PNG file', GREY)

    arrow(c, [(122, 96), (322, 170)])                       # EPUB file -> views
    label(c, 215, 130, 'file')
    arrow(c, [(395, 96), (395, 170)])                       # forms -> views
    label(c, 408, 133, 'credentials, search query, page,\nquote, post, comment, report', 18, 'lm')
    arrow(c, [(275, 220), (230, 220)])                      # views -> screens
    arrow(c, [(275, 338), (230, 338)], both=True)           # views <-> localStorage
    arrow(c, [(275, 460), (230, 460)])                      # views -> renderer
    arrow(c, [(122, 510), (122, 580)])                      # renderer -> document
    label(c, 135, 545, 'PNG 1080 × 1350', 18, 'lm')
    arrow(c, [(395, 470), (395, 560)], both=True)           # views <-> stores
    label(c, 382, 515, 'lists, quotes, posts', 18, 'rm')
    arrow(c, [(515, 340), (555, 340)])                      # views -> API client
    arrow(c, [(515, 600), (535, 600), (535, 372), (555, 372)], both=True)  # stores <-> API client
    arrow(c, [(672, 96), (672, 170)], both=True)            # service worker <-> cache
    label(c, 685, 133, 'shell files', 18, 'lm')
    arrow(c, [(672, 390), (672, 470)], both=True)           # API client <-> cookies
    label(c, 685, 430, 'session cookie,\nXSRF-TOKEN', 18, 'lm')

    # ---- the server (middle) ----
    sx, sw_ = 1000, 290
    ys = [20 + 92 * i for i in range(9)]
    sym(c, 'stored', sx, ys[0], sw_, 70, 'Static files of the SPA', 'index.html, assets/*, sw.js', YELLOW)
    arrow(c, [(790, 68), (sx, 68)])                         # service worker -> static files
    label(c, 895, 40, 'GET index.html,\nassets/*, sw.js', 18)

    procs = [
        ('2.0 Library and catalog', 'AddBookToLibrary, OpenLibraryClient',
         'q, work key, status', '-> entries, user_book', ['D2, D3 RW', 'cache RW']),
        ('3.0 Progress', 'UpdateProgress, RecordReadingLog',
         'user_book_id, page', '-> user_book, pages', ['D3 RW', 'D4 W']),
        ('6.0 Reading', 'ImportBookText, EpubImporter, SaveReadingPosition',
         'file, chapter, position', '-> text, chapter, html', ['D5, D7 RW', 'locks RW']),
        ('5.0 Quotes', 'SaveQuote, PickQuoteOfTheDay',
         'content, note, anchor', '-> quote', ['D5 RW', 'D3 R']),
        ('4.0 Statistics and goal', 'ReadingStatistics, GoalProgress',
         'days, year, target_books', '-> stats, goal', ['D3, D4 R', 'D6 RW']),
        ('1.0 Account', 'Fortify, Sanctum, MeController',
         'email, password, name', '-> session, user', ['D1 RW', 'sessions RW']),
        ('7.0 Community', 'PublishPost, PostComments, FeedController',
         'type, quote_id, content', '-> post, feed page', ['D8 RW', 'D1, D2, D3 R', 'D5, D9 R']),
        ('8.0 Moderation', 'ReportContent, ModerateContent, BlockReader',
         'reason, note, handle', '-> report, profile', ['D9 RW', 'D8 W']),
    ]
    bus_x = 820
    taps = []
    disk_top, disk_x, disk_w = 150, 1400, 335
    disk_bottom = ys[8] + 70
    for i, (title, detail, req, ans, store) in enumerate(procs, start=1):
        y = ys[i]
        sym(c, 'process', sx, y, sw_, 70, title, detail, GREEN)
        ty = y + 56
        taps.append(ty)
        arrow(c, [(bus_x, ty), (sx, ty)], both=True)
        label(c, 910, ty - 5, req + '\n' + ans.replace('->', '→'), 18, 'mb')
        # to the database
        dty = ty + 8 if i == 1 else ty
        dx = disk_left(disk_x, disk_top, disk_bottom - disk_top, dty)
        arrow(c, [(sx + sw_, dty), (dx, dty)], both=True)
        label(c, 1345, dty - 5, '\n'.join(store), 18, 'mb')
    c.polyline([(bus_x, taps[0]), (bus_x, taps[-1])], 2)
    arrow(c, [(790, 345), (bus_x, 345)])                    # API client -> bus
    c.circle(bus_x, 345, 4, 'black', 'black', 1)

    # calls between the processes
    arrow(c, [(1145, ys[4]), (1145, ys[3] + 70)])
    label(c, 1158, ys[4] - 11, 'anchor: chapter, start, end', 18, 'lm', fill=None)
    arrow(c, [(1145, ys[3]), (1145, ys[2] + 70)])
    label(c, 1158, ys[3] - 11, 'user_book, page', 18, 'lm', fill=None)

    # the moderator
    sym(c, 'input', 900, 870, 190, 70, 'Console commands', 'moderation:*', GREY)
    sym(c, 'document', 1110, 870, 190, 70, 'Console output', 'table of reports', GREY)
    arrow(c, [(1040, 870), (1040, ys[8] + 70)])
    label(c, 1052, 848, 'type, id', 18, 'lm', fill=None)
    arrow(c, [(1240, ys[8] + 70), (1240, 870)])
    label(c, 1252, 848, 'reports, state', 18, 'lm', fill=None)

    # ---- the stores and the catalog (right) ----
    dg.disk(c, disk_x, disk_top, disk_w, disk_bottom - disk_top, '', YELLOW)
    lines = [
        ('MySQL 8.4,', True), ('database bookshelf', True),
        ('D1  users', False), ('D2  books, authors,', False), ('      author_book', False), ('D3  user_books', False),
        ('D4  reading_logs', False), ('D5  quotes', False), ('D6  reading_goals', False),
        ('D7  book_texts,', False), ('      book_chapters', False),
        ('D8  posts, likes,', False), ('      comments, follows', False), ('D9  reports, blocks', False),
        ('sessions: session store', False), ('cache: Open Library', False), ('      answers, 24 h', False),
        ('cache_locks: lock', False), ('      epub-import', False),
    ]
    ty0 = disk_top + 44
    step = (disk_bottom - disk_top - 88) / (len(lines) - 1)
    for k, (text, bold) in enumerate(lines):
        yy = ty0 + k * step
        if bold:
            c.text(disk_x + disk_w / 2 - 12, yy, text, 20, bold=True, anchor='mm')
        else:
            c.text(disk_x + 40, yy, text, 18, anchor='lm')
    c.rect(disk_x, 112, disk_w, 34, GREY, 'black', 2, 6)
    c.text(disk_x + disk_w / 2, 129, 'Open Library (external service)', 19, bold=True, anchor='mm')
    arrow(c, [(sx + sw_, 129), (disk_x, 129)], both=True)
    label(c, 1345, 124, 'q → docs', 18, 'mb')
    notes = [
        'D1 to D9 are the data stores of the preliminary design. R: read, W: write, RW: read and write.',
        'A line from the API client to a process carries the fields of the request and, after the arrow, of the answer.',
        'The session store and the cache of the server are tables of MySQL (the drivers are "database").',
    ]
    ny = 760
    for note in notes:
        for line in c.wrap(note, 760, 18):
            c.text(20, ny, line, 18, italic=True, anchor='lm')
            ny += 22
        ny += 8
    return c.save(FIGURES / 'functional_system.png')


# ---- the functional scheme of the built-in reading ---------------------------------------------------------------

class Item:
    def __init__(self, col, row, kind, title, detail, fill):
        self.col, self.row, self.kind, self.title, self.detail, self.fill = col, row, kind, title, detail, fill
        self.x = self.y = self.w = self.h = 0.0

    @property
    def cx(self): return self.x + self.w / 2

    @property
    def cy(self): return self.y + self.h / 2

    @property
    def top(self): return self.y

    @property
    def bottom(self): return self.y + self.h

    @property
    def left(self): return self.x

    @property
    def right(self): return self.x + self.w


def needed(c: Canvas, item: Item, pad=3) -> float:
    """The height a symbol needs for its text."""
    rx, ry, rw, rh = inner(item.kind, 0, 0, item.w, 100)
    tl = c.wrap(item.title, rw - 20, 19, bold=True)
    dl = []
    for part in item.detail.split('\n') if item.detail else []:
        dl += c.wrap(part, rw - 20, 18)
    total = len(tl) * 19 * 1.15 + len(dl) * 18 * 1.15 + 2 * pad
    factor = {'process': 1.0, 'data': 1.0, 'stored': 1.0, 'memory': 1 / 0.84, 'disk': 1.0, 'document': 1 / 0.91,
              'input': 1 / 0.9, 'display': 1.0}[item.kind]
    return total * factor + 3


def functional_reader() -> Path:
    W = 1150
    c = Canvas(W, 100)  # for measuring only
    cols = {'c': (15, 300), 's': (375, 360), 'd': (800, 335)}
    items = []

    def add(col, row, kind, title, detail, fill):
        it = Item(col, row, kind, title, detail, fill)
        it.x, it.w = cols[col]
        items.append(it)
        return it

    CL, SV, DB = BLUE, GREEN, YELLOW
    r1c = add('c', 1, 'input', 'EPUB file', 'BookTextBlock: size up to 15 MB', GREY)
    r1s = add('s', 1, 'process', 'StoreBookTextRequest', 'BookTextController::store; UserBookPolicy; file up to 15 MB; 20 uploads an hour', SV)
    r2s = add('s', 2, 'process', 'ImportBookText', 'one import at a time (waits up to 5 s for the lock)', SV)
    r2d = add('d', 2, 'disk', 'cache_locks', 'lock epub-import, 120 s', DB)
    r3s = add('s', 3, 'process', 'EpubImporter::open', 'EpubArchive: limits, names, DRM; PackageReader: spine, titles', SV)
    r4s = add('s', 4, 'process', 'EpubBook::chapters', 'SafeXml, then HtmlSanitizer builds a new tree', SV)
    r5s = add('s', 5, 'data', 'ParsedChapter', 'position, title, html, characters', WHITE)
    r6c = add('c', 6, 'process', 'Stores library and quotes', 'has_text is set; old anchors cleared', CL)
    r6s = add('s', 6, 'process', 'ImportBookText', 'one transaction: replaces the old text, checks the quota', SV)
    r6d = add('d', 6, 'disk', 'book_texts, book_chapters', 'quotes: anchors of the old text cleared', DB)
    r7c = add('c', 7, 'display', 'Book page', 'the button "Read"', GREY)
    r8c = add('c', 8, 'process', 'ReaderView', 'loads the text of the entry', CL)
    r8s = add('s', 8, 'process', 'BookTextController::show', 'UserBookPolicy; contents and position', SV)
    r8d = add('d', 8, 'disk', 'book_texts', 'chapters: position, title', DB)
    r9c = add('c', 9, 'process', 'ReaderView.showChapter', 'asks for a chapter, fetches the next one', CL)
    r9s = add('s', 9, 'process', 'BookChapterController::show', 'BookTextPolicy; html, highlights', SV)
    r9d = add('d', 9, 'disk', 'book_chapters, quotes', 'content; start_offset, end_offset', DB)
    r10c = add('c', 10, 'display', 'Reading screen', 'ReaderChapter: html and highlights', GREY)
    r11c = add('c', 11, 'input', 'Text selection', 'SelectedText: text, start, end', GREY)
    r12c = add('c', 12, 'process', 'ReaderView.saveQuote', 'content, note, anchor (chapter, start, end)', CL)
    r12s = add('s', 12, 'process', 'SaveQuote', 'QuoteController::store; ResolveQuoteAnchor', SV)
    r12d = add('d', 12, 'disk', 'quotes', 'book_text_id, chapter_position, start_offset, end_offset', DB)
    r13c = add('c', 13, 'process', 'usePositionSaver', 'every 15 s, on hiding, on leaving', CL)
    r13s = add('s', 13, 'process', 'SaveReadingPosition', 'ReadingPositionController; progress', SV)
    r13d = add('d', 13, 'disk', 'book_texts', 'chapter_position, chapter_progress, progress', DB)
    r14c = add('c', 14, 'process', 'Library store', 'the entry with the raised page', CL)
    r14s = add('s', 14, 'process', 'UpdateProgress', 'page = round(progress × pages), if greater', SV)
    r14d = add('d', 14, 'disk', 'user_books, reading_logs', 'current_page, status; from_page, to_page', DB)

    # heights
    n_rows = 14
    row_h = {}
    for it in items:
        row_h[it.row] = max(row_h.get(it.row, 0), needed(c, it))
    gap = 24
    top = 50
    y = top
    row_y = {}
    for r in range(1, n_rows + 1):
        row_y[r] = y
        y += row_h[r] + gap
    H = int(y - gap + 30)
    c = Canvas(W, H)
    for it in items:
        it.y, it.h = row_y[it.row], row_h[it.row]

    # column bands and headers
    bands = {'c': ('Client (browser)', '#F4F7FD'), 's': ('Server part (Laravel)', '#F3F9F2'), 'd': ('MySQL', '#FFFAEB')}
    for col, (name, fill) in bands.items():
        x, w = cols[col]
        c.rect(x - 8, 8, w + 16, H - 16, fill, '#9A9A9A', 1.2, 8, dash=(8, 6))
        c.text(x + w / 2, 28, name, 21, bold=True, anchor='mm')
    for it in items:
        DRAW[it.kind](c, it.x, it.y, it.w, it.h, '', it.fill)
        put_text(c, inner(it.kind, it.x, it.y, it.w, it.h), it.title, it.detail)

    # the line between the upload and the reading
    sep = (r7c.bottom + r8c.top) / 2
    c.polyline([(8, sep), (W - 8, sep)], 1.5, color='#555555', dash=(10, 6))
    c.text(W - 14, sep - 12, 'upload of a book above, reading below', 18, italic=True, anchor='rm')

    def num(n, x, y):
        number(c, x, y, n)

    def vert(a: Item, b: Item, n, both=False):
        arrow(c, [(a.cx, a.bottom), (b.cx, b.top)], both=both)
        num(n, a.cx, (a.bottom + b.top) / 2)

    def horiz(a: Item, b: Item, n, both=False, y=None):
        yy = a.cy if y is None else y
        x1 = a.right if a.x < b.x else a.left
        x2 = b.left if a.x < b.x else b.right
        arrow(c, [(x1, yy), (x2, yy)], both=both)
        num(n, (x1 + x2) / 2, yy)

    horiz(r1c, r1s, 1)
    vert(r1s, r2s, 2)
    horiz(r2s, r2d, 3, both=True)
    vert(r2s, r3s, 4)
    vert(r3s, r4s, 5)
    vert(r4s, r5s, 6)
    vert(r5s, r6s, 7)
    horiz(r6s, r6d, 8, both=True)
    horiz(r6s, r6c, 9)
    vert(r6c, r7c, 10)
    vert(r7c, r8c, 11)
    horiz(r8c, r8s, 12, both=True)
    horiz(r8s, r8d, 13, both=True)
    vert(r8c, r9c, 14)
    horiz(r9c, r9s, 15, both=True)
    horiz(r9s, r9d, 16, both=True)
    vert(r9c, r10c, 17)
    vert(r10c, r11c, 18)
    vert(r11c, r12c, 19)
    horiz(r12c, r12s, 20, both=True)
    horiz(r12s, r12d, 21, both=True)
    horiz(r13c, r13s, 22)
    horiz(r13s, r13d, 23, both=True)
    vert(r13s, r14s, 24)
    horiz(r14s, r14d, 25, both=True)
    horiz(r14s, r14c, 26)
    return c.save(FIGURES / 'functional_reader.png')


if __name__ == '__main__':
    gost_legend()
    functional_system()
    functional_reader()
    print('figures written')
