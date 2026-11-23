"""Structure schemes of the technical design (section 8): the system, the modules of the server part and the modules of
the client. Run from the repository root:

    python3 docs/lab3/diagrams/structure.py

and the three pictures are written to docs/lab3/figures/ (structure_system.png, structure_server.png,
structure_client.png). The names of classes and files are the real ones; the lists of the files of the client are read
from the disk and checked against the numbers that the text of the section gives.
"""

from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'docs' / 'tools'))

from diagram import BLUE, GREEN, GREY, LIGHT, RED, WHITE, YELLOW, Canvas  # noqa: E402

FIGURES = ROOT / 'docs' / 'lab3' / 'figures'
FRONT = ROOT / 'frontend' / 'src'


# ---- helpers -----------------------------------------------------------------------------------------------------

def node(c: Canvas, x, y, w, h, title, detail='', fill=WHITE, tsize=19, dsize=18, pad=4, radius=8, width=2,
         dash=None) -> None:
    """A rounded box with a bold title and wrapped detail lines, centered. Raises when the text does not fit."""
    c.rect(x, y, w, h, fill, 'black', width, radius, dash=dash)
    lines = c.wrap(detail, w - 2 * pad - 8, dsize) if detail else []
    title_lines = c.wrap(title, w - 2 * pad - 8, tsize, bold=True) if title else []
    total = len(title_lines) * tsize * 1.15 + len(lines) * dsize * 1.15
    if total > h - 2 * pad + 0.5:
        raise ValueError(f'text does not fit into the box {title!r}: needs {total:.0f}, has {h - 2 * pad}')
    top = y + (h - total) / 2
    for line in title_lines:
        c.text(x + w / 2, top + tsize * 0.575, line, tsize, bold=True, anchor='mm')
        top += tsize * 1.15
    for line in lines:
        c.text(x + w / 2, top + dsize * 0.575, line, dsize, anchor='mm')
        top += dsize * 1.15


def label(c: Canvas, x, y, text, size=18, anchor='mm', italic=False, fill=WHITE, bold=False) -> None:
    """Text on a white patch, so that a line behind it does not run through the letters."""
    lines = text.split('\n')
    widest = max(c.measure(line, size, bold, italic) for line in lines)
    height = len(lines) * size * 1.15
    left = x - widest / 2 if anchor[0] == 'm' else x if anchor[0] == 'l' else x - widest
    top = y - height / 2 if anchor[1] == 'm' else y if anchor[1] == 't' else y - height
    if fill:
        c.rect(left - 3, top - 1, widest + 6, height + 2, fill, None, 0)
    c.text(left + widest / 2, top + height / 2, text, size, bold=bold, italic=italic, anchor='mm')


def fit(c: Canvas, text: str, width: float, size=18, bold=False, italic=False) -> None:
    if c.measure(text, size, bold, italic) > width:
        raise ValueError(f'{text!r} is wider than {width}')


# ---- figure 1: the structure of the system -----------------------------------------------------------------------

def structure_system() -> Path:
    W, H = 1150, 1340
    c = Canvas(W, H)

    # The client.
    c.rect(15, 15, 1120, 395, BLUE, 'black', 2, 12)
    c.text(35, 38, 'Client: Vue 3 single-page application and PWA (in the browser)', 20, bold=True, anchor='lm')
    chain = [('Router', 'src/router: routes and access guard'), ('Views', 'src/views: 18 screens'),
             ('Pinia stores', 'src/stores: 8 stores'), ('API client', 'src/api: http.ts and 6 API modules')]
    ys = [62, 148, 234, 320]
    for (title, detail), y in zip(chain, ys):
        node(c, 50, y, 450, 56, title, detail)
    for y in ys[:-1]:
        c.arrow([(275, y + 56), (275, y + 86)], head_len=13, head_w=9)
    # The screens also call the API modules directly (stats, catalog search, reviews, reports, the reader).
    c.arrow([(50, 176), (30, 176), (30, 348), (50, 348)], head_len=13, head_w=9)
    # The screens use the building blocks.
    c.rect(560, 62, 555, 208, WHITE, 'black', 2, 8)
    c.text(837, 80, 'Building blocks of the screens', 19, bold=True, anchor='mm')
    blocks = [('Components', 'src/components: 45 .vue files'), ('Composables', 'src/composables: 4 files'),
              ('Utils', 'src/utils: readerDom, quoteCard, chart, format, social'), ('i18n', 'src/i18n: texts in Ukrainian')]
    for i, (title, detail) in enumerate(blocks):
        node(c, 572 + (i % 2) * 271, 98 + (i // 2) * 80, 255, 72, title, detail, fill=LIGHT)
    c.arrow([(500, 176), (560, 176)], head_len=13, head_w=9)
    label(c, 530, 158, 'uses')
    # PWA.
    c.rect(560, 290, 555, 104, WHITE, 'black', 2, 8)
    node(c, 572, 306, 225, 72, 'PWA modules', 'src/pwa: updates, install, online', fill=LIGHT)
    node(c, 877, 306, 226, 72, 'Service worker', 'src/sw: /sw.js, shell cache', fill=LIGHT)
    c.arrow([(797, 342), (877, 342)], head_len=13, head_w=9)
    label(c, 837, 324, 'registers', 18)

    # The web server.
    c.arrow([(275, 376), (275, 455)], head='both', head_len=13, head_w=9)
    label(c, 292, 433, 'HTTPS: JSON, session cookie, X-XSRF-TOKEN', anchor='lm')
    c.arrow([(990, 378), (990, 455)], head_len=13, head_w=9)
    label(c, 1005, 433, 'GET shell files', anchor='lm')
    node(c, 15, 455, 1120, 74, 'Web server: nginx 1.28 (container web); Caddy in front of it in production (HTTPS)',
         'serves the static files of the SPA; passes /api, /docs and /up to PHP-FPM', fill=GREY, tsize=19)

    # The server part.
    c.rect(15, 575, 1120, 584, GREEN, 'black', 2, 12)
    c.text(320, 600, 'Server part: Laravel 13 on PHP 8.4 (PHP-FPM, container app)', 20, bold=True, anchor='lm')
    c.arrow([(275, 529), (275, 635)], head='both', head_len=13, head_w=9)
    label(c, 292, 554, 'FastCGI: requests under /api', anchor='lm')
    rows = [
        ('Route and middleware', 'routes/api.php, social.php, reader.php, Fortify; session, auth:sanctum, CSRF, throttle', 74),
        ('Form request', 'authorize() with a policy, then rules()', 54),
        ('Controller', 'calls an action or a service, returns a resource', 70),
        ('Action', 'a domain operation, one transaction', 54),
        ('Service', 'EPUB import, Open Library, statistics', 54),
        ('Model', 'Eloquent models, relations, scopes', 54),
    ]
    ry = {}
    y = 635
    for title, detail, h in rows:
        node(c, 50, y, 450, h, title, detail)
        ry[title] = (y, h)
        y += h + 28
    keys = [r[0] for r in rows]
    for a, b in zip(keys, keys[1:]):
        c.arrow([(275, ry[a][0] + ry[a][1]), (275, ry[b][0])], head_len=13, head_w=9)
    fy, fh = ry['Form request']
    cy, ch = ry['Controller']
    ay, ah = ry['Action']
    sy, sh = ry['Service']
    my, mh = ry['Model']
    node(c, 640, fy, 475, fh, 'Policy', 'decides access; a foreign resource gives 404', fill=LIGHT)
    node(c, 640, cy, 475, ch, 'Resource', 'builds the JSON answer from a model', fill=LIGHT)
    node(c, 640, ay, 475, ah, 'Console commands (Artisan)', 'moderation:reports, :hide, :restore, :resolve', fill=LIGHT)
    c.arrow([(500, fy + fh / 2), (640, fy + fh / 2)], head_len=13, head_w=9)  # form request -> policy
    label(c, 570, fy + fh / 2 - 16, 'authorize', 18)
    c.polyline([(500, cy + 14), (570, cy + 14), (570, fy + fh / 2)], 2)  # controller -> policy, joins the line above
    c.arrow([(500, cy + ch - 16), (640, cy + ch - 16)], head_len=13, head_w=9)  # controller -> resource
    label(c, 570, cy + ch - 32, 'JSON', 18)
    c.arrow([(640, ay + ah / 2), (500, ay + ah / 2)], head_len=13, head_w=9)  # console -> action
    label(c, 570, ay + ah / 2 - 16, 'calls', 18)
    bottom = my + mh

    # The database and the external catalog.
    c.arrow([(275, bottom), (275, 1204)], head='both', head_len=13, head_w=9)
    label(c, 292, 1182, 'SQL (PDO)', anchor='lm')
    node(c, 50, 1204, 450, 86, 'MySQL 8.4 (container mysql)', 'the tables of the data model and the tables sessions, cache, cache_locks', fill=YELLOW)
    node(c, 640, 1204, 475, 86, 'Open Library (external service)', 'search API; the browser loads the cover pictures from its servers', fill=GREY)
    c.arrow([(500, sy + sh / 2), (877, sy + sh / 2), (877, 1204)], head='both', head_len=13, head_w=9)
    label(c, 892, my + mh / 2, 'HTTPS, JSON:\nGET /search.json', anchor='lm')

    c.text(15, 1318, 'An arrow points from the caller to the called part; a double arrow is a request and its answer.', 18, italic=True,
           anchor='lm')
    return c.save(FIGURES / 'structure_system.png')


# ---- figure 2: the modules of the server part --------------------------------------------------------------------

LAYER_FILL = {'Controllers': BLUE, 'Actions': GREEN, 'Services': YELLOW, 'Policies': RED, 'Commands': GREY,
              'Packages': LIGHT}

SERVER_TREE = [
    ('1.0', 'Manage account', [
        ('Controllers', ['MeController']),
        ('Actions', ['CreateNewUser', 'GenerateHandle']),
        ('Packages', ['Laravel Fortify', 'Laravel Sanctum']),
    ]),
    ('2.0', 'Manage library', [
        ('Controllers', ['CatalogSearchController', 'UserBookController']),
        ('Actions', ['AddBookToLibrary', 'UpdateUserBook', 'ChangeUserBookStatus']),
        ('Services', ['OpenLibraryClient']),
        ('Policies', ['BookPolicy', 'UserBookPolicy']),
    ]),
    ('3.0', 'Update progress', [
        ('Controllers', ['ReadingProgressController']),
        ('Actions', ['UpdateProgress', 'RecordReadingLog']),
    ]),
    ('4.0', 'Calculate statistics and goal', [
        ('Controllers', ['StatsController', 'GoalController']),
        ('Services', ['ReadingStatistics', 'GoalProgress']),
    ]),
    ('5.0', 'Manage quotes', [
        ('Controllers', ['QuoteController']),
        ('Actions', ['SaveQuote', 'PickQuoteOfTheDay']),
        ('Policies', ['QuotePolicy']),
    ]),
    ('6.0', 'Read books', [
        ('Controllers', ['BookTextController', 'BookChapterController', 'ReadingPositionController']),
        ('Actions', ['ImportBookText', 'DeleteBookText', 'SaveReadingPosition', 'ResolveQuoteAnchor']),
        ('Services', ['EpubImporter', 'EpubArchive', 'PackageReader', 'EpubBook', 'SafeXml', 'HtmlSanitizer', 'Text',
                      '+ 8 small classes']),
        ('Policies', ['BookTextPolicy']),
    ]),
    ('7.0', 'Share and discuss', [
        ('Controllers', ['FeedController', 'PostController', 'ProfileController', 'FollowController', 'LikeController',
                         'CommentController', 'BookReviewController']),
        ('Actions', ['PublishPost', 'DeletePost', 'FollowReader', 'LikePost', 'PostComments']),
        ('Policies', ['PostPolicy', 'CommentPolicy', 'UserPolicy']),
    ]),
    ('8.0', 'Moderate content', [
        ('Controllers', ['ReportController', 'BlockController']),
        ('Actions', ['ReportContent', 'ModerateContent', 'BlockReader']),
        ('Commands', ['ModerationReports', 'ModerationHide', 'ModerationRestore', 'ModerationResolve']),
    ]),
]


def structure_server() -> Path:
    W = 1750
    colw, gap, x0 = 209, 8, 11
    top = 215
    line_h = 18 * 1.15
    c_probe = Canvas(10, 10)

    def block_height(names):
        return 10 + (1 + len(names)) * line_h + 4

    tallest = max(sum(block_height(n) + 8 for _, n in blocks) for _, _, blocks in SERVER_TREE)
    bottom = top + tallest + 20
    H = int(bottom + 140)
    c = Canvas(W, H)

    # The root.
    rw = 640
    rx = (W - rw) / 2
    node(c, rx, 14, rw, 70, 'Bookshelf API', 'Laravel 13, PHP 8.4: backend/app, 130 PHP files', fill=BLUE, tsize=21)
    bus_y = 112
    c.polyline([(W / 2, 84), (W / 2, bus_y)], 2)
    centers = [x0 + i * (colw + gap) + colw / 2 for i in range(8)]
    c.polyline([(centers[0], bus_y), (centers[-1], bus_y)], 2)

    for i, (code, name, blocks) in enumerate(SERVER_TREE):
        x = x0 + i * (colw + gap)
        cx = centers[i]
        c.polyline([(cx, bus_y), (cx, 130)], 2)
        node(c, x, 130, colw, 68, f'{code} {name}', '', fill=WHITE, tsize=19, pad=4)
        # The line to the blocks is drawn first: the blocks cover it, and it shows in the gaps.
        total = sum(block_height(n) + 8 for _, n in blocks)
        c.polyline([(cx, 198), (cx, top + total - 8 - 4)], 2)
        y = top
        for layer, names in blocks:
            h = block_height(names)
            c.rect(x, y, colw, h, LAYER_FILL[layer], 'black', 1.6, 6)
            c.text(cx, y + 5 + line_h / 2, layer, 18, italic=True, anchor='mm')
            for k, nm in enumerate(names):
                fit(c_probe, nm, colw - 8)
                c.text(cx, y + 5 + line_h * (k + 1.5), nm, 18, anchor='mm')
            y += h + 8

    # The part that belongs to no single process.
    bar_y = bottom
    c.rect(11, bar_y, W - 22, 78, WHITE, 'black', 1.6, 6, dash=(9, 6))
    c.text(25, bar_y + 20, 'Shared by all subsystems:', 18, bold=True, anchor='lm')
    c.text(25, bar_y + 46,
           'routes (api.php, social.php, reader.php); bootstrap/app.php (middleware, rendering of errors); AppServiceProvider '
           '(rate limiters, morph map);', 18, anchor='lm')
    c.text(25, bar_y + 66,
           'FortifyServiceProvider; the abstract base class Controller.', 18, anchor='lm')
    # Legend of the colours.
    ly = bar_y + 112
    c.text(11, ly, 'Layer:', 18, bold=True, anchor='lm')
    lx = 90
    for layer in ['Controllers', 'Actions', 'Services', 'Policies', 'Commands', 'Packages']:
        c.rect(lx, ly - 13, 26, 26, LAYER_FILL[layer], 'black', 1.6, 4)
        c.text(lx + 36, ly, layer, 18, anchor='lm')
        lx += 36 + c.measure(layer, 18) + 34
    c.text(lx + 10, ly, 'Form requests, resources, models, enums and exceptions are not drawn: see the table.', 18, italic=True, anchor='lm')
    return c.save(FIGURES / 'structure_server.png')


# ---- figure 3: the modules of the client -------------------------------------------------------------------------

def _names(pattern: str, strip: tuple = ('.spec.ts',)) -> list:
    """Names (without the extension) of the files of the client that match the pattern; the tests are left out."""
    out = []
    for path in sorted(FRONT.glob(pattern)):
        if path.is_file() and not path.name.endswith('.spec.ts'):
            name = path.name
            for ext in ('.vue', '.ts', '.css'):
                if name.endswith(ext):
                    name = name[: -len(ext)]
                    break
            out.append(name)
    return out


def client_groups() -> list:
    """(layer, directory, number of files, extension, names) for every group of the source of the client."""
    groups = [
        ('Screens', 'src/router', '.ts', _names('router/*.ts')),
        ('Screens', 'src/views', '.vue', _names('views/*.vue')),
        ('Screens', 'src/components', '.vue', _names('components/*.vue')),
        ('Screens', 'src/components/reader', '.vue, .css', _names('components/reader/*')),
        ('State and data', 'src/stores', '.ts', _names('stores/*.ts')),
        ('State and data', 'src/api', '.ts', _names('api/*.ts')),
        ('State and data', 'src/composables', '.ts', _names('composables/*.ts')),
        ('State and data', 'src/types', '.ts', _names('types/*.ts')),
        ('Platform and support', 'src/pwa', '.ts', _names('pwa/*.ts')),
        ('Platform and support', 'src/sw', '.ts', _names('sw/*.ts')),
        ('Platform and support', 'src/i18n', '.ts', _names('i18n/*.ts')),
        ('Platform and support', 'src/utils', '.ts', _names('utils/*.ts')),
        ('Platform and support', 'src (root)', '.ts, .vue, .css', ['main', 'App', 'style', 'env.d']),
    ]
    return [(layer, d, ext, names) for layer, d, ext, names in groups]


def structure_client() -> Path:
    W = 1750
    groups = client_groups()
    c_probe = Canvas(10, 10)
    fx, fw = 800, 940
    row_gap = 6
    heights = []
    wrapped = []
    for _, d, ext, names in groups:
        lines = c_probe.wrap(', '.join(names), fw - 24, 18)
        wrapped.append(lines)
        heights.append(max(56, len(lines) * 18 * 1.15 + 16))
    total = sum(heights) + row_gap * (len(groups) - 1)
    top = 14
    H = int(top + total + 36)
    c = Canvas(W, H)

    # Rows.
    ys = []
    y = top
    for h in heights:
        ys.append(y)
        y += h + row_gap
    layer_rows = {}
    for i, (layer, *_rest) in enumerate(groups):
        layer_rows.setdefault(layer, []).append(i)

    dir_x, dir_w = 470, 290
    for i, (layer, d, ext, names) in enumerate(groups):
        y, h = ys[i], heights[i]
        node(c, dir_x, y, dir_w, h, d, f'{len(names)} files ({ext})', fill=WHITE, tsize=19, pad=4)
        c.polyline([(dir_x + dir_w, y + h / 2), (fx, y + h / 2)], 2)
        c.rect(fx, y, fw, h, LIGHT, 'black', 1.6, 6)
        ty = y + (h - len(wrapped[i]) * 18 * 1.15) / 2
        for line in wrapped[i]:
            c.text(fx + 12, ty + 18 * 0.575, line, 18, anchor='lm')
            ty += 18 * 1.15

    # Layers and the root.
    layer_x, layer_w = 240, 190
    fills = {'Screens': BLUE, 'State and data': GREEN, 'Platform and support': YELLOW}
    layer_boxes = []
    for layer, rows in layer_rows.items():
        y1 = ys[rows[0]]
        y2 = ys[rows[-1]] + heights[rows[-1]]
        node(c, layer_x, y1, layer_w, y2 - y1, layer, '', fill=fills[layer], tsize=20, pad=4)
        layer_boxes.append((y1, y2))
        bar_x = 450
        mids = [ys[r] + heights[r] / 2 for r in rows]
        c.polyline([(layer_x + layer_w, (y1 + y2) / 2), (bar_x, (y1 + y2) / 2)], 2)
        c.polyline([(bar_x, min(mids)), (bar_x, max(mids))], 2)
        for m in mids:
            c.polyline([(bar_x, m), (dir_x, m)], 2)
    root_h = 120
    root_y = top + total / 2 - root_h / 2
    root_w = 200
    node(c, 12, root_y, root_w, root_h, 'Bookshelf client', 'Vue 3, TypeScript, Vite: frontend/src', fill=BLUE, tsize=20, pad=4)
    bus_x = 226
    mids = [(a + b) / 2 for a, b in layer_boxes]
    c.polyline([(12 + root_w, root_y + root_h / 2), (bus_x, root_y + root_h / 2)], 2)
    c.polyline([(bus_x, min(mids)), (bus_x, max(mids))], 2)
    for m in mids:
        c.polyline([(bus_x, m), (layer_x, m)], 2)

    c.text(12, H - 18, 'File names are given without the extension. The 76 test files (*.spec.ts) and the 6 files of '
           'test data in src/test are not drawn.', 18, italic=True, anchor='lm')
    return c.save(FIGURES / 'structure_client.png')


if __name__ == '__main__':
    structure_system()
    structure_server()
    structure_client()
    print('figures written')
