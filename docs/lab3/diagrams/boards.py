"""Puts the screenshots of `screenshots.py` (figures/shot_*.png) in phone frames, three in a row, with a caption under each.

    python3 docs/lab3/diagrams/boards.py
"""

from __future__ import annotations

import sys
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'docs' / 'tools'))

from diagram import Canvas  # noqa: E402

FIGURES = ROOT / 'docs' / 'lab3' / 'figures'

BOARDS = {
    'proto_account_library': [
        ('login', 'Sign-in\n(/login)'),
        ('home', 'Reading: the books being\nread (/)'),
        ('library', 'Library by status\n(/library)'),
    ],
    'proto_book_quote': [
        ('book', 'Book page: progress, EPUB\nupload (/library/:id)'),
        ('add_book', 'Search of the catalog\n(/library/add)'),
        ('quote_form', 'New quote\n(/quotes/new)'),
    ],
    'proto_quotes_stats': [
        ('quotes', 'Quotes\n(/quotes)'),
        ('quote_share', 'Quote card\n(/quotes/:id/share)'),
        ('stats', 'Statistics and goal\n(/stats)'),
    ],
    'proto_community': [
        ('community', 'Feed\n(/community)'),
        ('post', 'Post with comments\n(/community/posts/:id)'),
        ('compose', 'New post\n(/community/new)'),
    ],
    'proto_profiles': [
        ('reader_profile', 'Profile of another reader\n(/u/:handle)'),
        ('profile', 'Own profile\n(/profile)'),
        ('reader_settings', 'Reader: settings sheet\n(/read/:id)'),
    ],
    'proto_reader': [
        ('reader', 'Reading screen\n(/read/:id)'),
        ('reader_selection', 'Selection: “Save quote”\n(/read/:id)'),
        ('reader_contents', 'Contents sheet\n(/read/:id)'),
    ],
}

WIDTH, PHONE_W, GAP, MARGIN = 1100, 330, 25, 30
SHOT = (390, 844)


def draw_board(name: str, items: list) -> None:
    phone_h = round(PHONE_W * SHOT[1] / SHOT[0])
    label_h = 64
    height = MARGIN + phone_h + label_h + 10
    c = Canvas(WIDTH, height)
    out = c.out
    start = (WIDTH - (3 * PHONE_W + 2 * GAP)) / 2
    for i, (shot, label) in enumerate(items):
        x = start + i * (PHONE_W + GAP)
        c.text(x + PHONE_W / 2, MARGIN / 2 + phone_h + 40, label, 18, anchor='mm')
    final = c.img.resize((int(WIDTH * out), int(height * out)), Image.LANCZOS)
    draw = ImageDraw.Draw(final)
    for i, (shot, label) in enumerate(items):
        x = int((start + i * (PHONE_W + GAP)) * out)
        y = int(MARGIN / 2 * out)
        w, h = int(PHONE_W * out), int(phone_h * out)
        image = Image.open(FIGURES / f'shot_{shot}.png').convert('RGB').resize((w, h), Image.LANCZOS)
        mask = Image.new('L', (w, h), 0)
        ImageDraw.Draw(mask).rounded_rectangle((0, 0, w - 1, h - 1), radius=int(24 * out), fill=255)
        final.paste(image, (x, y), mask)
        draw.rounded_rectangle((x, y, x + w - 1, y + h - 1), radius=int(24 * out), outline='#444444', width=int(2.5 * out))
    final.save(FIGURES / f'{name}.png', dpi=(320, 320), optimize=True)
    print('  board', name, final.size)


if __name__ == '__main__':
    for board, items in BOARDS.items():
        draw_board(board, items)
