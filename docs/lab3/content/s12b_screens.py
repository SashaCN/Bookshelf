"""Subsection 12.6: screenshots of the working application (the prototype). The pictures are made by
diagrams/screenshots.py and diagrams/boards.py."""

from __future__ import annotations

BOARDS = [
    ('proto_account_library', 'fig:proto_account_library', 'Screens of the prototype: sign-in, the books being read, the library',
     'Sign-in, the screen “Reading” with the books that are being read (the buttons “+10” and “+25” and the page '
     'field update the progress, see Section 7.2) and the library with the filter by status.'),
    ('proto_book_quote', 'fig:proto_book_quote', 'Screens of the prototype: a book, the search of the catalog, a new quote',
     'The page of a book with the progress and the block that uploads an EPUB book for reading in the application; the '
     'search of the catalog (Open Library) with the link to add a book by hand; the form of a new quote.'),
    ('proto_quotes_stats', 'fig:proto_quotes_stats', 'Screens of the prototype: quotes, the quote card, statistics',
     'The list of quotes with the quote of the day; the quote card that the browser draws and the reader shares; the '
     'statistics with the annual goal, the streak, the pace and the chart of the pages per day (Section 7.4).'),
    ('proto_community', 'fig:proto_community', 'Screens of the prototype: the feed, a post with comments, a new post',
     'The feed with the tabs “Following” and “All”, the search of readers and the buttons of likes and comments; a post '
     'with its comments and the link to report; the form that publishes a quote or a review.'),
    ('proto_profiles', 'fig:proto_profiles', 'Screens of the prototype: a profile of a reader, the own profile, the settings of reading',
     'The profile of another reader with the numbers of posts, followers and followed readers, the button of the follow '
     'and the link to block; the own profile; the sheet with the settings of the reading screen (the size of the font, '
     'the spacing, the typeface and the theme).'),
    ('proto_reader', 'fig:proto_reader', 'Screens of the prototype: the reading screen, the selection of a quote, the contents',
     'The reading screen with a chapter of the book that was uploaded; a selected fragment with the buttons “Save quote”, '
     '“Add a thought” and “Cancel” (the algorithm is in Section 7.7); the sheet with the contents of the book.'),
]


def write(d, ctx) -> None:
    d.h2('12.6 Screens of the working prototype')
    d.p('The prototype of Bookshelf is the working application itself, so the screens are given as they are in it. They '
        'were taken by a script (`docs/lab3/diagrams/screenshots.py`) from the application that runs in the development '
        'containers, in a browser window of the size of a phone (390 by 844 CSS pixels) and in the light theme; the '
        'application also has a dark theme that follows the setting of the device. The script fills the application with '
        'demo data through its own API (three demo readers, five books, quotes, an EPUB book, posts, likes and comments) '
        'and removes the demo readers afterwards. The interface is in Ukrainian; the captions under the screens give the '
        'meaning in English and the route of the screen. The screens are in ' + ', '.join(ctx.ref(key) for _, key, _, _ in BOARDS)
        + '.')
    for name, key, title, text in BOARDS:
        d.p(text)
        ctx.figure(d, key, f'{name}.png', title)
