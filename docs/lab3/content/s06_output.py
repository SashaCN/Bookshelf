"""Section 6: the output data (documents) of the system."""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'tools'))

from docxkit import LANDSCAPE_TEXT_WIDTH  # noqa: E402

HEADER = ['Document and resource', 'Request', 'Consumer', 'Composition', 'Format', 'Size, volume and frequency', 'Process']
WIDTHS = [1.8, 2.9, 1.6, 4.6, 1.1, 2.3, 1.1]

# The consumer of a document: the store or the view of the client that receives it (all documents go to the client).
CONSUMERS = {
    'Current reader': '`useAuthStore`; `ProfileView.vue`',
    'Result of sign-in, registration, sign-out': '`useAuthStore`',
    'Public profile': '`useProfilesStore`; `ReaderProfileView.vue`',
    'Reader in a list': '`CommunityView.vue`; `BlockedReadersView.vue`',
    'Library and book card': '`useLibraryStore`; the screens Reading, Library, Book',
    'Result of the catalog search': '`AddBookView.vue`',
    'Result of a progress update': '`useLibraryStore`; `ReadingCard.vue`, `BookView.vue`',
    'Reading journal': '`ReadingJournal.vue`',
    'Summary of the statistics': '`StatsView.vue`',
    'Pages per day': '`StatsView.vue`; `PagesChart.vue`',
    'Annual goal': '`GoalCard.vue`',
    'Quote': '`useQuotesStore`; the screens Quotes, Book, Reading',
    'Quote of the day': '`DailyQuoteCard.vue`',
    'Quote card': 'The reader; other applications through the menu “Share”',
    'Post': '`usePostsStore`; `PostView.vue`',
    'Feed and posts of a reader': '`useFeedStore`; `useProfilesStore`',
    'Reviews of a book': '`BookReviews.vue`',
    'Comment': '`useCommentsStore`; `CommentList.vue`',
    'Receipt of a report': '`ReportDialog.vue`',
    'Text of a book (table of contents)': '`ReaderView.vue`; `BookTextBlock.vue`',
    'Chapter': '`ReaderView.vue`; `ReaderChapter.vue`',
    'Result of saving the position': '`ReaderView.vue`; `usePositionSaver`',
}


# How often one reader receives the document in normal use (an estimate from the way the screen is used).
FREQUENCY = {
    'Current reader': 'When the app starts and after a save of the profile.',
    'Result of sign-in, registration, sign-out': 'Once per session.',
    'Public profile': 'On each opening of the page of a reader, and with each follow or block.',
    'Reader in a list': 'On each search; the list of blocked readers on each opening.',
    'Library and book card': 'The library once after sign-in (kept in the store until a retry); a card after each change of the entry.',
    'Result of the catalog search': 'On each search (a few while the reader types).',
    'Result of a progress update': 'On each update of the page.',
    'Reading journal': 'On each opening of a book that is started; the next page on request.',
    'Summary of the statistics': 'On each opening of the statistics screen.',
    'Pages per day': 'On each opening of the statistics screen and on each change of the period.',
    'Annual goal': 'On each opening of the statistics screen and on each change of the goal.',
    'Quote': 'On each opening of the list or of a book; the next page on request.',
    'Quote of the day': 'On each opening of the main screen.',
    'Quote card': 'On demand, for each card that is shared.',
    'Post': 'On each opening of a post, and with each like.',
    'Feed and posts of a reader': 'On each opening of the community screen or of a profile; the next page on request.',
    'Reviews of a book': 'On each opening of a book.',
    'Comment': 'On each opening of a post; the next page on request.',
    'Receipt of a report': 'Once per report.',
    'Text of a book (table of contents)': 'On each upload and on each opening of the reading screen.',
    'Chapter': 'On each chapter that is opened.',
    'Result of saving the position': 'Every 15 seconds while the reader reads (at most 4 per minute from the timer).',
}


def _table(d, rows: list) -> None:
    full = []
    for row in rows:
        name = row[0].split('\n')[0]
        size = row[4] + '\nFrequency: ' + FREQUENCY[name]
        full.append([row[0], row[1], CONSUMERS[name], row[2], row[3], size, row[5]])
    d.table(HEADER, full, WIDTHS, total=LANDSCAPE_TEXT_WIDTH,
            align=['left', 'left', 'left', 'left', 'left', 'left', 'center'], size=18)


def _account(d, ctx) -> None:
    d.h3('6.2.1 Account and readers')
    d.p('The documents of the process 1.0 and the descriptions of readers that the process 7.0 gives are listed in '
        + ctx.ref('tab:output_account') + '. All the documents of Section 6.2 are received by the client; the column '
        '“Consumer” names the store or the view that uses the document.')
    ctx.table(d, 'tab:output_account', 'Output documents: account and readers')
    _table(d, [
        ['Current reader\n`UserResource`', '`GET /api/me`\n`PATCH /api/me`',
         '`id`, `name`, `email`, `handle`, `bio`, `timezone`. The only document that has the e-mail and the time zone.',
         'JSON, `{"data": {...}}`', '0.3 KB (Latin sample), 1.0 KB (Cyrillic sample).',
         '1.0'],
        ['Result of sign-in, registration, sign-out', '`POST /api/auth/login`, `/register`, `/logout`',
         'Sign-in: `{"two_factor": false}` (status 200). Registration: an empty body (201). Sign-out: no body (204). The '
         'session cookie is set or cleared.', 'JSON or empty', 'A few bytes.', '1.0'],
        ['Public profile\n`ProfileResource`', '`GET /api/profiles/{handle}`; the answers of `PUT`, `DELETE '
         '/api/profiles/{handle}/follow` and of `PUT /api/blocks/{handle}`',
         '`handle`, `name`, `bio`, `posts_count`, `followers_count`, `following_count`, `is_following`, `is_me`, '
         '`is_blocked`. The counts leave out hidden posts and the readers who have a block with the viewer. No '
         'e-mail, no time zone, no numeric `id`.',
         'JSON, `{"data": {...}}`', '0.3 KB (Latin), 1.1 KB (Cyrillic).', '7.0'],
        ['Reader in a list\n`ReaderResource`', '`GET /api/profiles?q=`\n`GET /api/blocks`',
         '`handle`, `name`, `bio`, `is_me`, `is_following`. Search results are sorted by handle; the blocked readers, '
         'latest blocked first.', 'JSON, list with `links` and `meta`',
         '0.24 KB (Latin), 1.0 KB (Cyrillic) per reader; a page of 20: 5 KB and 21 KB.', '7.0, 8.0'],
    ])


def _library(d, ctx) -> None:
    d.h3('6.2.2 Library, progress and journal')
    d.p('The documents of the processes 2.0 and 3.0 are listed in ' + ctx.ref('tab:output_library') + '. The library is '
        'sent whole; the other lists are divided into pages (Section 6.3).')
    ctx.table(d, 'tab:output_library', 'Output documents: library, progress and journal')
    _table(d, [
        ['Library and book card\n`UserBookResource`, `BookResource`', '`GET /api/library?status=`\n`GET /api/library/{userBook}`; '
         'the answers of `POST`, `PATCH /api/library`',
         '`id`, `status`, `allowed_statuses`, `current_page`, `total_pages`, `progress_percent`, `has_text`, `text_progress`, '
         '`rating`, `started_at`, `finished_at`, `created_at`, `book`.\n`book`: `id`, `title`, `subtitle`, `authors` (the '
         'names), `isbn_13`, `cover_url`, `page_count`, `published_year`, `subjects`, `source`.\nThe list is sorted by '
         'the last change (`updated_at`, then `id`, newest first) and can be filtered by `status`.',
         'JSON, `{"data": [...]}` or `{"data": {...}}`',
         'An entry: 0.7 KB (Latin), 1.4 KB (Cyrillic). The whole library is sent: 30 entries are 20 KB or 42 KB, 100 entries '
         '68 KB or 141 KB.', '2.0'],
        ['Result of the catalog search', '`GET /api/catalog/search?q=`',
         '`data`: up to 20 items with `work_key`, `title`, `subtitle`, `authors`, `cover_url`, `page_count`, '
         '`published_year`, `subjects` (up to 12), `isbn_13`, `in_library` (the reader has the book already).',
         'JSON, `{"data": [...]}`', '0.5 KB or 1.7 KB per item; 20 items: 10 KB or 33 KB. The server keeps the answer of '
         'Open Library for 24 hours.', '2.0'],
        ['Result of a progress update', '`POST /api/library/{userBook}/progress`',
         '`data`: the library entry as above. `meta`: `pages` (pages gained, negative for a correction, 0 if the page did not '
         'change), `reached_end` (the bookmark is on the last page), `streak` (days in a row).',
         'JSON', 'About the size of an entry.', '3.0'],
        ['Reading journal\n`ReadingLogResource`', '`GET /api/library/{userBook}/logs?page=`',
         '`id`, `from_page`, `to_page`, `pages`, `logged_on` (the day of the reader, `YYYY-MM-DD`), `created_at`. Newest '
         'record first, 20 per page.', 'JSON, list with `links` and `meta`',
         '0.12 KB per record; a page of 20: 2.7 KB.', '3.0'],
    ])


def _stats(d, ctx) -> None:
    d.h3('6.2.3 Statistics, goal and quotes')
    d.p('The documents of the processes 4.0 and 5.0 are listed in ' + ctx.ref('tab:output_stats_quotes') + '. The numbers '
        'of the statistics are calculated at the moment of the request from the reading log, in the calendar of the '
        'reader (Section 7.4), and are not stored.')
    ctx.table(d, 'tab:output_stats_quotes', 'Output documents: statistics, goal and quotes')
    _table(d, [
        ['Summary of the statistics', '`GET /api/stats/summary`\n`StatsController::summary`',
         '`today`: `date`, `pages`.\n`streak`: `current`, `longest`, `read_today`.\n`pace`: pages per day over the last 14 '
         'days, rounded to one decimal.\n`month` and `year`: `pages`, `books_finished`.\n`forecasts`: for each book that is '
         'being read and has a page count: `user_book_id`, `title`, `current_page`, `total_pages`, `pages_per_day`, '
         '`days_left`, `finish_on` (empty when there is no pace or the date is later than two years).',
         'JSON, `{"data": {...}}`', '0.5 KB (Latin) or 0.8 KB (Cyrillic) with two forecasts.', '4.0'],
        ['Pages per day', '`GET /api/stats/daily`\n`StatsController::daily`',
         '`data`: for every day of the period (also a day with no reading) `date` and `pages` (the net pages of the day, '
         'not below 0). `meta`: `from`, `to`, `total_pages`.', 'JSON',
         'About 35 bytes per day: 30 days are 1.1 KB, 90 days 3.0 KB; at most 366 days.', '4.0'],
        ['Annual goal\n`GoalProgress`', '`GET`, `PUT /api/goals/{year}`',
         '`year`, `target_books`, `finished_books`, `expected_books` (one decimal), `due_books`, `remaining_books`, `on_track`. '
         'Without a goal: `target_books`, `expected_books`, `due_books`, `remaining_books` and `on_track` are empty and '
         '`finished_books` is still counted.', 'JSON, `{"data": {...}}`', 'About 0.13 KB.', '4.0'],
        ['Quote\n`QuoteResource`', '`GET /api/quotes?favorite=&book=&type=&page=`\n`GET /api/quotes/{quote}`; the answers of '
         '`POST /api/library/{userBook}/quotes`, `PATCH /api/quotes/{quote}`',
         '`id`, `type`, `content`, `note`, `page`, `is_favorite`, `anchor` (`book_text_id`, `chapter`, `start`, `end`, or '
         'empty for a quote typed in), `created_at`, `user_book_id`, `book` (`title`, `authors`, `cover_url`).\nNewest '
         'first, 20 per page.', 'JSON, list with `links` and `meta`, or one object',
         'A quote of 200 characters with a note of 100: 0.6 KB (Latin), 2.4 KB (Cyrillic); a page of 20: 12 KB and 47 KB.', '5.0'],
        ['Quote of the day', '`GET /api/quotes/daily?favorite=`', 'One quote as above, or `{"data": null}` when the reader '
         'has none. The same quote all day of the reader and another one the next day.', 'JSON', 'One quote.', '5.0'],
        ['Quote card', 'Made by the client', 'An image of a quote, see Section 6.4.', 'PNG', '1080 × 1350 pixels.', '5.0'],
    ])


def _community(d, ctx) -> None:
    d.h3('6.2.4 Community')
    d.p('The documents of the processes 7.0 and 8.0 are listed in ' + ctx.ref('tab:output_community') + '. In every list '
        'the hidden posts and comments and the content of the readers who have a block with the viewer are left out; '
        'the author sees the own hidden content with `is_hidden` set.')
    ctx.table(d, 'tab:output_community', 'Output documents: community')
    _table(d, [
        ['Post\n`PostResource`', '`GET /api/posts/{post}`; the answers of `POST /api/posts`, `PATCH /api/posts/{post}` and of `PUT`, '
         '`DELETE /api/posts/{post}/like`',
         '`id`, `type`, `content`, `comment`, `rating`, `page`, `likes_count`, `comments_count` (without the comments that '
         'the viewer cannot see), `liked_by_me`, `is_mine`, `is_hidden`, `created_at`, `updated_at`, `author` (`handle`, '
         '`name`), `book` (`id`, `title`, `authors`, `cover_url`). `DELETE ... /like` answers `{"data": null}` if the viewer may '
         'no longer see the post.', 'JSON, `{"data": {...}}`',
         'A post with a quote of 200 characters and a comment of 100: 0.8 KB (Latin), 2.6 KB (Cyrillic).', '7.0'],
        ['Feed and posts of a reader', '`GET /api/feed?scope=&page=`\n`GET /api/profiles/{handle}/posts`',
         'A list of posts as above, newest first (by `id`), 20 per page. `scope` is `following` (the posts of the followed '
         'readers and the own ones) or `all`.', 'JSON, list with `links` and `meta`', 'A page of 20: 16 KB (Latin), 52 KB (Cyrillic).', '7.0'],
        ['Reviews of a book', '`GET /api/books/{book}/reviews?page=`',
         'Posts of the type `review` about the book of the catalog, newest first, 20 per page, and `meta`: `my_review_id`, '
         '`reviews_count`, `average_rating` (one decimal; empty if there are no reviews). Hidden reviews and those of '
         'blocked readers are not counted.', 'JSON, list with `links` and `meta`', 'As the feed.', '7.0'],
        ['Comment\n`CommentResource`', '`GET /api/posts/{post}/comments?page=`; the answer of `POST`',
         '`id`, `post_id`, `content`, `created_at`, `author` (`handle`, `name`), `is_mine`, `can_delete` (the author of the '
         'comment or of the post), `is_hidden`. Newest first, 20 per page.', 'JSON, list with `links` and `meta`',
         'A comment of 150 characters: 0.3 KB (Latin), 1.2 KB (Cyrillic); a page of 20: 7 KB and 24 KB.', '7.0'],
        ['Receipt of a report\n`ReportResource`', '`POST /api/posts/{post}/report`\n`POST /api/comments/{comment}/report`',
         '`id`, `reason`, `status`, `created_at` (status 201). The reporter does not get the note back and the author of the '
         'content does not learn who reported.', 'JSON, `{"data": {...}}`', 'About 0.09 KB.', '8.0'],
    ])


def _reader(d, ctx) -> None:
    d.h3('6.2.5 Chapters and text')
    d.p('The documents of the process 6.0 are listed in ' + ctx.ref('tab:output_reader') + '. Only the owner of the text '
        'receives them. The text is sanitized when it is imported, so the client inserts the HTML of a chapter into the '
        'page as it is.')
    ctx.table(d, 'tab:output_reader', 'Output documents: built-in reader')
    _table(d, [
        ['Text of a book (table of contents)\n`BookTextResource`', '`POST /api/library/{userBook}/text` (status 201)\n'
         '`GET /api/library/{userBook}/text`',
         '`id`, `user_book_id`, `source_name` (the name of the uploaded file, without a path, up to 255 characters), '
         '`chapters_count`, `characters_count`, `position` (`chapter`, `chapter_progress`, `updated_at`), `progress` (from 0 '
         'to 1), `chapters`: for each chapter `position`, `title`, `characters`. No text of the chapters.',
         'JSON, `{"data": {...}}`', 'With 40 chapters: 2.9 KB (Latin), 6.9 KB (Cyrillic); with the largest number of '
         'chapters (500): 33 KB or 83 KB.', '6.0'],
        ['Chapter\n`BookChapterResource`', '`GET /api/book-texts/{bookText}/chapters/{position}`',
         '`position`, `title`, `html` (the sanitized chapter: elements of the allow-list without any attribute), '
         '`characters`, `highlights`: for each quote anchored in the chapter `id`, `start`, `end`, `content`, `note` (sorted '
         'by `start_offset`, then `id`).', 'JSON, `{"data": {...}}`',
         'The HTML of a chapter is at most 2 MB. The technical specification expects a typical chapter of up to 200 KB of '
         'text. A Cyrillic letter is written as six bytes in JSON, so the answer is large before the compression by Caddy '
         '(gzip or zstd).', '6.0'],
        ['Result of saving the position', '`PUT /api/book-texts/{bookText}/position`',
         '`data`: the text as above without `chapters`. `meta.user_book`: the library entry as it is now (its `current_page` '
         'may have moved on with the reader).', 'JSON', '0.9 KB (Latin), 1.7 KB (Cyrillic).', '6.0'],
    ])


def _pages(d, ctx) -> None:
    d.h2('6.3 Lists and pagination')
    d.p('A list is divided into pages with `simplePaginate(20)`: the page has 20 items and the server does not count the '
        'total. The answer has `data`, `links` (`first`, `last` which is always empty, `prev`, `next`) and `meta` '
        '(`current_page`, `current_page_url`, `from`, `path`, `per_page`, `to`). The client asks for `?page=N` and goes on '
        'while `links.next` is not empty (the button “Show more”). The lists are given in ' + ctx.ref('tab:output_pages')
        + '.')
    ctx.table(d, 'tab:output_pages', 'Lists, their order and the size of a page')
    d.table(
        ['List', 'Route', 'Page', 'Order', 'Controller'],
        [
            ['Library', '`GET /api/library`', 'No pages: all entries', 'Last change first (`updated_at`, `id`)', '`UserBookController::index`'],
            ['Catalog search', '`GET /api/catalog/search`', 'Up to 20 items, no pages', 'As Open Library gave them', '`CatalogSearchController`'],
            ['Reading journal', '`GET /api/library/{userBook}/logs`', '20', '`id` descending', '`ReadingProgressController::index`'],
            ['Quotes', '`GET /api/quotes`', '20', '`id` descending', '`QuoteController::index`'],
            ['Feed', '`GET /api/feed`', '20', '`posts.id` descending', '`FeedController`'],
            ['Posts of a reader', '`GET /api/profiles/{handle}/posts`', '20', '`posts.id` descending', '`ProfileController::posts`'],
            ['Comments of a post', '`GET /api/posts/{post}/comments`', '20', '`comments.id` descending', '`CommentController::index`'],
            ['Reviews of a book', '`GET /api/books/{book}/reviews`', '20', '`posts.id` descending', '`BookReviewController::index`'],
            ['Search of readers', '`GET /api/profiles`', '20', '`handle` ascending', '`ProfileController::index`'],
            ['Blocked readers', '`GET /api/blocks`', '20', 'The time of the block, then `users.id`, descending', '`BlockController::index`'],
            ['Days of the chart', '`GET /api/stats/daily`', 'All days of the period (at most 366)', 'Date ascending', '`StatsController::daily`'],
            ['Open reports (console)', '`moderation:reports`', 'All open reports', '`id` ascending (the oldest first)', '`ModerationReports`'],
        ],
        [2.2, 3.8, 2.4, 2.8, 4.0], size=20,
    )


def _card(d, ctx) -> None:
    d.h2('6.4 Quote card')
    d.p('The quote card (the function F-5.6) is an image that the client draws on a canvas from the data of one quote. '
        'The server takes no part, and nothing is sent to it. The parameters of the image are given in '
        + ctx.ref('tab:output_card') + '.')
    ctx.table(d, 'tab:output_card', 'Parameters of the quote card')
    d.table(
        ['Parameter', 'Value'],
        [
            ['Module', '`src/utils/quoteCard.ts` (`drawQuoteCard`), screen `QuoteShareView.vue`'],
            ['Size', '1080 × 1350 pixels (`CARD_WIDTH`, `CARD_HEIGHT`), the ratio 4:5; 1 458 000 pixels'],
            ['Format', 'PNG (`canvas.toBlob(..., \'image/png\')`); the file is named `bookshelf-quote-<id>.png`'],
            ['Templates', '`light`, `dark`, `accent`: fixed colours of the background, the text, the secondary text, the rule '
                          'and the quotation mark, independent of the theme of the app'],
            ['Content', 'The text of the quote with an opening quotation mark; a rule; the title of the book; the authors and '
                        'the page (the label “с. 42”); the name “Bookshelf” at the bottom'],
            ['Text', 'The quote is set in a serif face from 76 px down to 26 px (a step of 2 px) in the box 888 × 680 px '
                     '(the margins are 96 px, the box is between 260 and 940 px from the top). If it does not fit at 26 px, '
                     'the last line ends with “…”. The title is bold sans-serif from 48 to 32 px in a box 116 px high; the '
                     'authors and the page from 36 to 24 px; the name of the app is 30 px'],
            ['Delivery', 'If the browser can share files (`navigator.canShare`), the system menu “Share” opens '
                         '(`navigator.share`); otherwise the file is downloaded through a temporary link that is revoked '
                         'after 10 s. Closing the menu is not an error'],
            ['Size of the file', 'Not fixed: it depends on the text. The bitmap is 1 458 000 × 4 bytes = 5.8 MB before the '
                                 'PNG compression; the size of the file was not measured'],
            ['Frequency', 'On demand, once for each card that the reader shares'],
        ],
        [2.6, 9.4], size=20,
    )


def _screens(d, ctx) -> None:
    d.h2('6.5 Screens of the application')
    d.p('The screens are the output that the reader sees. Their layout and states are described in Section 12 ('
        + ctx.ref('tab:screens') + ' and ' + ctx.ref('fig:ui_map') + '). ' + ctx.ref('tab:output_screens')
        + ' links every screen with the output documents of Section 6.2 that fill it.')
    ctx.table(d, 'tab:output_screens', 'Screens and the documents that they show')
    d.table(
        ['Screen', 'Route', 'View', 'Documents shown'],
        [
            ['Sign-in, registration', '`/login`, `/register`', '`LoginView.vue`, `RegisterView.vue`', 'Only messages of errors'],
            ['Reading', '`/`', '`HomeView.vue`', 'Library (the books being read), quote of the day'],
            ['Library', '`/library`', '`LibraryView.vue`', 'Library (tabs by status)'],
            ['Adding a book', '`/library/add`', '`AddBookView.vue`', 'Result of the catalog search'],
            ['Book', '`/library/{id}`', '`BookView.vue`', 'Book card, reading journal, quotes of the book, reviews of the book, text of the book (progress of reading)'],
            ['Quotes', '`/quotes`', '`QuotesView.vue`', 'Quotes'],
            ['New and edited quote', '`/quotes/new`, `/quotes/{id}/edit`', '`QuoteFormView.vue`', 'Quote, library (the choice of a book)'],
            ['Quote card', '`/quotes/{id}/share`', '`QuoteShareView.vue`', 'Quote, quote card (PNG)'],
            ['Statistics', '`/stats`', '`StatsView.vue`', 'Summary, pages per day, annual goal'],
            ['Profile', '`/profile`', '`ProfileView.vue`', 'Current reader'],
            ['Community', '`/community`', '`CommunityView.vue`', 'Feed, reader in a list'],
            ['New post', '`/community/new`', '`ComposePostView.vue`', 'Quotes, library (the choice of a quote or a book)'],
            ['Post', '`/community/posts/{id}`', '`PostView.vue`', 'Post, comments'],
            ['Reader', '`/u/{handle}`', '`ReaderProfileView.vue`', 'Public profile, posts of the reader'],
            ['Blocked readers', '`/community/blocked`', '`BlockedReadersView.vue`', 'Reader in a list'],
            ['Reading screen', '`/read/{id}`', '`ReaderView.vue`', 'Library entry, text of the book, chapter, result of saving the position'],
            ['Not found', 'Any other path', '`NotFoundView.vue`', '(none)'],
        ],
        [2.4, 3.2, 3.0, 5.4], size=20,
    )
    d.p('Three more outputs are not routes: the full screen “No connection” (`OfflineGate.vue`), which the client shows '
        'when the server cannot be reached at start, and two banners of `AppBanners.vue`: “No internet” (the browser '
        'reports that it is offline) and “A new version is available” (the service worker has a new version).')


def _moderation(d, ctx) -> None:
    d.h2('6.6 Output of the moderation commands')
    d.p('The moderator works on the server with four Artisan commands (the process 8.0). They write to the terminal. '
        'Their output is given in ' + ctx.ref('tab:output_moderation') + '. A command ends with the exit code 0 on '
        'success and 1 when it could not find or accept its argument.')
    ctx.table(d, 'tab:output_moderation', 'Output of the moderation commands')
    d.table(
        ['Command', 'Arguments', 'Output', 'Errors (exit code 1)'],
        [
            ['`moderation:reports`', 'None',
             'A table of the open reports, the oldest first, with the columns Report (`id`), About (“post 12” or “comment 7”), '
             'Reason, By (“@handle”, at most 30 characters), Note (at most 40), State (`visible`, `hidden` or `deleted`), Text '
             '(the post or the comment, at most 50), Reported (the time of the report in UTC, `YYYY-MM-DD HH:MM:SS`). Then '
             'the line “N open reports. Next: moderation:hide, moderation:restore or moderation:resolve.” Without reports: '
             '“There are no open reports.” (exit code 0).', '(none)'],
            ['`moderation:hide`', '`type` (`post` or `comment`), `id`',
             '“Hidden.” or “It was hidden already.”, then “N open reports closed.”', 'Unknown type; an id that is not a whole '
             'number; no such object'],
            ['`moderation:restore`', '`type`, `id`',
             '“Restored.” or “It was not hidden.”, then “N open reports closed.”', 'The same'],
            ['`moderation:resolve`', '`report` (the id of a report)',
             '“Resolved.” or “It was resolved already.” If it was the last open report of an object that is still hidden, a '
             'warning follows with the command that shows the object again.', 'An id that is not a whole number; no such report'],
        ],
        [3.0, 2.6, 5.0, 3.4], size=20,
    )
    d.p('The texts that readers wrote (a note, a post, a handle) are printed through `PrintsUserText::safe`, because a '
        'terminal obeys the characters that it receives. The method does the following in this order:')
    d.numbered([
        'It replaces invalid UTF-8 (`mb_scrub`).',
        'It removes the escape sequences of the terminal: the sequences of colours and of moves of the cursor, and the '
        'commands that set the title of the window.',
        'It replaces the other control characters (C0 and C1), the characters of zero width and the characters that change '
        'the direction of the text or hide it (U+200B to U+200F, U+202A to U+202E, U+2060 to U+2069, U+FEFF) by a space.',
        'It collapses the white space, so the text is on one line, and cuts it to the limit of the column (`Str::limit`).',
        'It escapes the markup of the console (`OutputFormatter::escape`), so a note such as `<error>` is printed as it was '
        'written.',
    ])
    d.p('The identifier that is not a number is also printed through this method, cut to 30 characters.')


def _errors(d, ctx) -> None:
    d.h2('6.7 Error output')
    d.p('Every error of the API is JSON, because `bootstrap/app.php` asks for a JSON answer to every path `api/*` and to '
        'every request that expects JSON. The bodies are given in ' + ctx.ref('tab:output_errors') + '. The field '
        '`message` is a text of the framework for the errors that it raises and a code (Section 4.4) for the business '
        'rules. The answer of a business rule is a validation answer (422) with the code both in `message` and in the '
        'list of the field. The client reads the codes and not the texts.')
    ctx.table(d, 'tab:output_errors', 'Form of the error answers')
    d.table(
        ['Situation', 'Status', 'Body', 'Source'],
        [
            ['A rule of the form request is broken', '422',
             '`{"message": "The content field is required.", "errors": {"content": ["The content field is required."]}}`. '
             '`message` is the first text; with more errors the framework adds “(and N more errors)”.', 'Form request'],
            ['A business rule is broken', '422',
             '`{"message": "library.page_above_total", "errors": {"page": ["library.page_above_total"]}}`', 'Action'],
            ['The book is in the library already', '409', '`{"message": "library.already_added", "user_book_id": 17}`',
             '`BookAlreadyInLibraryException`'],
            ['Open Library is not available', '503', '`{"message": "catalog.unavailable"}`', '`CatalogUnavailableException`'],
            ['Another book is being imported', '503', '`{"message": "reader.busy"}` and the header `Retry-After: 5`', '`ImportBusyException`'],
            ['The period of the chart is too long', '422',
             '`{"message": "The range is too long.", "errors": {"from": ["stats.range_too_long"]}}`. The only answer in which '
             '`message` is a sentence next to a code.', '`StatsController::daily`'],
            ['Wrong e-mail or password', '422',
             '`{"message": "These credentials do not match our records.", "errors": {"email": ["These credentials do not match '
             'our records."]}}`', 'Fortify'],
            ['No valid session', '401', '`{"message": "Unauthenticated."}`', 'Sanctum'],
            ['No such object, or not the viewer’s', '404', '`{"message": "Not Found"}` for a denial of a policy; for an '
             'object that is not in the database, the text of the framework about the model', 'Policies, routing'],
            ['The CSRF token does not match', '419', '`{"message": "CSRF token mismatch."}`', 'Laravel'],
            ['Too many requests', '429', '`{"message": "Too Many Attempts."}` and the headers `Retry-After`, `X-RateLimit-Limit`, '
             '`X-RateLimit-Remaining`', 'Rate limiter'],
            ['An unexpected error', '500', '`{"message": "Server Error"}`. With `APP_DEBUG=true` (development) the body also has '
             '`exception`, `file`, `line` and `trace`; production sets it to false.', 'Laravel'],
            ['The web server answers', '413, 502, 504', 'Not JSON: the page of nginx or Caddy. The client treats the status '
             'only.', 'nginx, Caddy'],
        ],
        [2.8, 1.3, 6.2, 3.1], align=['left', 'center', 'left', 'left'], size=20,
    )
    d.p('The client turns every failure into one message: a code with a text in the dictionary, else the text for 429, '
        'else the text for a lost connection, else the general text (`useApiErrorMessage`). The reader never sees the '
        'text of the server and never sees a technical detail.')


def write(d, ctx) -> None:
    d.h1('6 Output data (documents)')

    d.h2('6.1 General rules of the output')
    d.p('The server part gives data to the client only as JSON; the other outputs are the pictures and the screens that '
        'the client makes itself, and the text of the console commands of the moderator. The rules of the JSON answers are '
        'the same for all the resources of `app/Http/Resources`:')
    d.bullets([
        'The encoding is UTF-8, the object has the key `data`; additional information is in `meta`. The fields are only '
        'those that the resource names, so nothing else of a model can leak into an answer.',
        'A moment is a string of ISO 8601 with the offset (`2026-10-21T09:15:00+00:00`). A calendar day of the reader is a '
        'string `YYYY-MM-DD`. See Section 4.2.',
        'A hidden post or comment and the content of a reader who has a block with the viewer are not output; the author '
        'sees the own hidden content with the field `is_hidden`.',
        'Another reader is described only by the `handle`, the `name`, the `bio` and the counters. The e-mail, the time zone '
        'and the numeric `id` of another reader are never output.',
        'The enumerations are output as their values (`reading`, `quote`), see Section 4.3.',
    ])
    d.p('The sizes in the tables are estimates. They were obtained by writing a sample answer with the real field names '
        'and counting its bytes. The texts of the sample are a title of 30 characters, a name of 15, an author of 20, a '
        'quote of 200, a note or a comment of 100, a comment of 150 and a bio of 140 characters; the sample is given '
        'twice, with Latin letters and with Cyrillic letters. The PHP encoder writes a Cyrillic letter as `\\uXXXX`, six '
        'bytes, and a slash as `\\/`, so a Ukrainian answer with long texts is up to three times larger. The sizes are before '
        'the compression; Caddy compresses the answers (`encode zstd gzip`). The frequency is how often one reader '
        'receives the document in normal use; it is an estimate from the way the screen is used, not a measurement. The '
        'volume of the whole system is in ' + ctx.ref('tab:quantities') + '.')

    d.h2('6.2 Output documents')
    d.p('The documents are grouped by the area of the application. The tables are printed on landscape pages. The '
        'column “Document and resource” gives the name of the document and the class of `app/Http/Resources` that '
        'builds it; the column “Process” is the process of the preliminary design that is the source.')
    with ctx.landscape(d):
        _account(d, ctx)
        _library(d, ctx)
        _stats(d, ctx)
        _community(d, ctx)
        _reader(d, ctx)

    _pages(d, ctx)
    _card(d, ctx)
    _screens(d, ctx)
    _moderation(d, ctx)
    _errors(d, ctx)
