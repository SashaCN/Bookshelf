"""Section 10: structure charts of Constantine (the figures are drawn by docs/lab3/diagrams/constantine.py)."""

from __future__ import annotations

import sys
from pathlib import Path

try:
    from docxkit import LANDSCAPE_TEXT_WIDTH
except ImportError:  # the tools folder is put on the path by ctx.py; this is only for a direct import
    sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'tools'))
    from docxkit import LANDSCAPE_TEXT_WIDTH

SPEC_HEADER = ['Module', 'Purpose', 'Input parameters\n(name: type, range)', 'Output parameters\n(type, range)', 'Errors']
SPEC_WIDTHS_PORTRAIT = [3.0, 2.6, 3.3, 2.7, 3.0]
SPEC_WIDTHS_LANDSCAPE = [2.6, 3.0, 4.2, 3.4, 3.8]


def _spec_portrait(d, ctx, key: str, title: str, rows: list) -> None:
    ctx.table(d, key, title)
    d.table(SPEC_HEADER, rows, SPEC_WIDTHS_PORTRAIT, size=18)


def _spec_landscape(d, ctx, key: str, title: str, rows: list) -> None:
    ctx.table(d, key, title)
    d.table(SPEC_HEADER, rows, SPEC_WIDTHS_LANDSCAPE, total=LANDSCAPE_TEXT_WIDTH, size=20)


# ---- specifications -------------------------------------------------------------------------------------------------

PROGRESS_ROWS = [
    ['`ReadingProgressController::store`',
     'Moves the bookmark of a library entry to a page and answers with the entry and the facts for the reply.',
     '`request`: UpdateProgressRequest (the field `page`)\n`userBook`: UserBook, from the route `/library/{userBook}/progress`\n`updateProgress`: UpdateProgress (injected)',
     '`UserBookResource`, JSON, status 200, with `meta`:\n`pages`: int (negative for a correction)\n`reached_end`: bool\n`streak`: int, 0 or more',
     '401 for a guest; 404 for a foreign or missing entry; 422 from the form request and from `UpdateProgress`.'],
    ['`UpdateProgressRequest`\n(`authorize`, `rules`)',
     'Checks that the entry is the reader\'s (before the body is read) and validates the body.',
     '`userBook`: UserBook, from the route\n`page`: int, required, 0 to 20000',
     'The validated `page`: int, or a refusal.',
     '404 for a foreign entry; 422 when `page` is missing, is not an integer or is outside 0 to 20000.'],
    ['`UserBookPolicy::update`',
     'Allows the owner of the entry only. Everybody else gets “not found”, so the API does not confirm that a foreign entry exists.',
     '`user`: User\n`userBook`: UserBook',
     '`Response`: allow, or deny as “not found” (404).',
     'None; the refusal is the answer 404.'],
    ['`UpdateProgress::handle`',
     'Moves the bookmark in one transaction with a lock on the row, and writes the move to the reading log. A book that is wanted or put aside starts being read.',
     '`userBook`: UserBook\n`page`: int, 0 to `total_pages` when it is known (the request admits up to 20000)',
     '`ProgressUpdate`:\n`userBook`: UserBook with `book.authors`\n`log`: ?ReadingLog (null when the page did not change)\n`pages()`: int; `reachedEnd()`: bool',
     '`ValidationException`: `page` `library.progress_finished` (the book is finished), `page` `library.page_above_total`, and the errors of `apply`. 404 if the row was deleted meanwhile. The transaction is rolled back.'],
    ['`ChangeUserBookStatus::apply`',
     'Moves an entry to another status and keeps its dates and its page consistent. Changes the model in place and does not save it.',
     '`userBook`: UserBook\n`target`: BookStatus (`want`, `reading`, `finished`, `abandoned`)\n`startedAt`, `finishedAt`: ?Carbon, only for `want` to `finished`',
     'None (`void`). The model has the new `status`, `started_at`, `finished_at` and `current_page`.',
     '`ValidationException`: `status` `library.invalid_transition`; `total_pages` `library.total_pages_required` (a wanted book cannot start without a page count). Nothing happens when `target` equals the status.'],
    ['`RecordReadingLog::handle`',
     'Writes one move of the bookmark to the reading log, on the calendar day of the reader.',
     '`userBook`: UserBook\n`fromPage`: int, 0 or more\n`toPage`: int, 0 or more',
     '?ReadingLog: `from_page`, `to_page`, `logged_on` (a date in `users.timezone`, `Europe/Kyiv` when empty). Null when `fromPage` equals `toPage`.',
     'None of its own; a database error rolls the transaction back.'],
    ['`ReadingStatistics::streak`',
     'Counts the days in a row with pages read. A streak that ended yesterday is alive.',
     'None. The reader is given to the constructor: `user`: User.',
     'array: `current`: int, 0 or more; `longest`: int, 0 or more; `read_today`: bool',
     'None; a reader without a log gets zeros.'],
]

IMPORT_ROWS = [
    ['`BookTextController::store`',
     'Takes the uploaded EPUB for a library entry, gives it to the importer and answers with the stored text.',
     '`request`: StoreBookTextRequest (the field `file`)\n`userBook`: UserBook, from the route `/library/{userBook}/text`\n`importText`: ImportBookText (injected)',
     '`BookTextResource` with the table of contents, JSON, status 201.',
     '401; 404 for a foreign entry; 422 with a `reader.*` code on `file`; 429 after 20 uploads in 60 minutes; 503 `reader.busy` with `Retry-After: 5`.'],
    ['`StoreBookTextRequest`',
     'Checks the owner of the entry and the size of the upload.',
     '`userBook`: UserBook, from the route\n`file`: uploaded file, required, up to 15360 KB (15 MB)',
     'The validated request, or a refusal.',
     '404 for a foreign entry; 422 `reader.no_file`, `reader.too_large`.'],
    ['`ImportBookText::handle`',
     'Reads the EPUB and stores its text as the text of the entry, instead of the old one, in one transaction. One book is read at a time on the server (a lock in the cache).',
     '`userBook`: UserBook\n`path`: string, the uploaded file\n`sourceName`: string, up to 255 characters',
     '`BookText` with its table of contents (`chapters_count`, `characters_count`, position 0).',
     '`ImportBusyException` when the lock `epub-import` is not got in 5 s. `ValidationException` on `file`: `reader.not_epub`, `too_large`, `too_many_entries`, `too_many_chapters`, `chapter_too_large`, `text_too_large`, `unsafe_archive`, `drm`, `empty`, `invalid`, `quota_exceeded` (30 texts or 40 000 000 characters per reader).'],
    ['`ImportBusyException`',
     'Says that another book is being read, so the client must try again.',
     'None.',
     'JSON `{"message": "reader.busy"}`, status 503, header `Retry-After: 5`.',
     'none'],
    ['`EpubImporter::open`\n(subsystem)',
     'Checks the archive and reads the package, without the database. The structure is in ' + '{epub}.',
     '`path`: string',
     '`EpubBook`: the open archive, the package, the sanitizer.',
     '`EpubImportException` with the code of the part that refused.'],
    ['`DeleteBookText::handle`',
     'Removes the text of the entry. The quotes stay; their anchors are cleared.',
     '`userBook`: UserBook',
     'bool: true when there was a text.',
     'None; the transaction is rolled back on a database error.'],
    ['`EpubBook::chapters`\n(subsystem, generator)',
     'Gives the chapters in reading order one at a time. A document that is missing, unreadable or without text is skipped.',
     'None; the state is the open book.',
     'Generator of `ParsedChapter`: `position`: int, 0 to 499; `title`: ?string, up to 200 characters; `html`: string, up to 2 MB; `characters`: int, 1 or more (UTF-16 units).',
     '`EpubImportException`: `reader.too_many_chapters` (more than 500), `reader.text_too_large` (more than 8 000 000 characters), `reader.chapter_too_large`, `reader.invalid`.'],
    ['`BookChapter::insert`\n(query builder)',
     'Stores one chapter without making a model, so that the text of the book is not kept in memory.',
     'array: `book_text_id`: int, `position`: int, `title`: ?string, `content`: string, `characters`: int',
     'bool.',
     'A database error rolls the whole import back.'],
]

EPUB_ROWS = [
    ['`EpubArchive::open`',
     'Opens the ZIP file, looks at every entry once (number, names, encryption, sizes) and checks the entry `mimetype`.',
     '`path`: string, a file of 22 bytes to 15 MB\n`limits`: EpubLimits',
     '`EpubArchive`.',
     '`reader.not_epub`, `too_large` (the file, or more than 60 MB unpacked), `too_many_entries` (more than 2000), `unsafe_archive` (an absolute name or `..`), `drm` (an encrypted entry), `invalid`.'],
    ['`EpubImporter::assertNotEncrypted`',
     'Refuses a book under DRM. The file `META-INF/encryption.xml` is read only if it exists; the obfuscation of fonts is allowed.',
     '`archive`: EpubArchive',
     'None (`void`).',
     '`reader.invalid` (the file is not XML); `reader.drm` (an `EncryptionMethod` that is not the obfuscation of fonts).'],
    ['`PackageReader::read`\n(subsystem)',
     'Finds the package (`content.opf`), its manifest and spine, and the titles of the chapters from the table of contents (`nav` or NCX).',
     '`archive`: EpubArchive',
     '`EpubPackage`: `spine`: list of archive paths, each once; `titles`: array path => title, up to 200 characters.',
     '`reader.invalid` (no package, manifest or spine; more than 5000 items); `reader.too_many_chapters` (more than 5000 entries of the table of contents).'],
    ['`EpubArchive::read`',
     'Reads one entry into memory, never more than the caller and the limits of the archive allow.',
     '`name`: string, the name of an entry\n`maxBytes`: int, 1 or more\n`tooBig`: ReaderError, what to report for a big entry (default `reader.too_large`)',
     '?string: the content; null when there is no such entry.',
     '`tooBig` (the entry is over `maxBytes`); `reader.too_large` (more than 60 MB read in all); `reader.invalid` (cannot be read, or is bigger than it says).'],
    ['`SafeXml::parse`',
     'Turns the text of an XML document into a tree, without trusting it: UTF-8 only, no entities, no network.',
     '`xml`: string',
     '?DOMDocument; null when nothing can be read.',
     '`reader.invalid`: a NUL character, an entity declaration, a DOCTYPE with an internal subset, an encoding that is not accepted.'],
    ['`EpubPaths::resolve`',
     'Turns a reference found in a file (`../text/ch1.xhtml#a`) into a path inside the archive.',
     '`baseDirectory`: string, a directory of the archive or an empty string\n`reference`: string',
     '?string: the path; null for a URL, an absolute path, an empty reference or one that climbs out of the archive.',
     'None.'],
    ['`HtmlSanitizer::sanitize`\n(subsystem)',
     'Builds a new, safe tree from a chapter: text and a short list of elements without attributes. White space is collapsed so that the offsets of quotes are stable.',
     '`document`: DOMDocument',
     '`SanitizedChapter`: `html`: string, up to 2 MB; `characters`: int, 0 or more (UTF-16 units); `heading`: ?string, up to 200 characters.',
     '`reader.chapter_too_large`: the HTML is over 2 MB, the text is over 4 MB before the cleaning, or the document has more than 250 000 elements.'],
    ['`Text::ofHtml`, `Text::length`, `Text::collapse`',
     'The one definition of the text of a chapter: HTML without tags; its length in UTF-16 code units; white space reduced to single spaces.',
     '`html`: string; `utf8`: string; `text`: string',
     'string; int, 0 or more; string.',
     'None.'],
]

QUOTE_ROWS = [
    ['`QuoteController::store`',
     'Saves a quote or an insight for a library entry.',
     '`request`: StoreQuoteRequest\n`userBook`: UserBook, from the route `/library/{userBook}/quotes`\n`saveQuote`: SaveQuote (injected)',
     '`QuoteResource`, JSON, status 201.',
     '401; 404 for a foreign entry; 422 from the form request and from `SaveQuote`.'],
    ['`StoreQuoteRequest`',
     'Checks the owner of the entry and validates the fields.',
     '`type`: QuoteType (`quote`, `insight`), optional\n`content`: string, 1 to 2000 characters\n`note`: ?string, up to 2000\n`page`: ?int, 1 to 20000\n`is_favorite`: bool\n`anchor`: ?array: `chapter` 0 to 100000, `start` 0 to 100 000 000, `end` above `start`, up to 100 000 000',
     'The validated array, or a refusal.',
     '404 for a foreign entry; 422 for a field outside its range.'],
    ['`SaveQuote::create`',
     'Checks the page against the page count, resolves the anchor when there is one, and saves the quote.',
     '`userBook`: UserBook\n`data`: array of the fields above; `anchor` is optional',
     '`Quote` with `userBook.book.authors`.',
     '`ValidationException`: `page` `quotes.page_above_total`; on `anchor` the errors of `ResolveQuoteAnchor`.'],
    ['`ResolveQuoteAnchor::handle`',
     'Checks that the text of the book has the quote at the place the client says, and turns the place into the columns of the quote.',
     '`userBook`: UserBook\n`anchor`: array: `chapter`, `start`, `end`: int\n`content`: string, the text of the quote',
     'array: `book_text_id`, `chapter_position`, `start_offset`, `end_offset`: int.',
     '`ValidationException` on `anchor`: `reader.no_text`; `reader.anchor_out_of_range` (no such chapter, `end` not above `start`, `end` over the length of the chapter, or a range of more than 10 000 units); `reader.anchor_mismatch` (the text there is not the quote, white space aside).'],
    ['`Text::ofHtml`',
     'The text of a chapter: its HTML without tags, with `&lt;`, `&gt;`, `&amp;` turned back.',
     '`html`: string',
     'string.',
     'None.'],
    ['`Text::slice`',
     '`length` UTF-16 code units of a text from the unit `start`.',
     '`utf8`: string\n`start`: int, 0 or more\n`length`: int, 1 or more',
     '?string; null when the range is outside the text, is empty or cuts a character in two.',
     'None.'],
    ['`Text::skeleton`',
     'The text without any white space, to compare two selections of one passage.',
     '`text`: string',
     'string.',
     'None.'],
]

POSITION_ROWS = [
    ['`ReadingPositionController::update`',
     'Saves where the reader is. The page of the entry moves on with the reader, never back.',
     '`request`: SaveReadingPositionRequest\n`bookText`: BookText, from the route `/book-texts/{bookText}/position`\n`savePosition`: SaveReadingPosition (injected)',
     '`BookTextResource`, with `meta.user_book`: UserBookResource.',
     '401; 404 for a foreign text; 422; 429 after 120 saves in a minute.'],
    ['`SaveReadingPositionRequest`',
     'Checks the owner of the text and validates the position.',
     '`bookText`: BookText, from the route\n`chapter`: int, 0 to 100000\n`chapter_progress`: number, 0 to 1',
     'The validated fields, or a refusal.',
     '404 for a foreign text; 422 for a field outside its range.'],
    ['`SaveReadingPosition::handle`',
     'Takes the lock of the entry, saves the place and the share of the whole text, and raises the page when the reader got further.',
     '`text`: BookText\n`chapter`: int, 0 or more\n`chapterProgress`: float, 0 to 1',
     '`PositionSaved`: `text`: BookText (`chapter_position`, `chapter_progress`, `progress`, `position_updated_at`); `userBook`: UserBook.',
     '`ValidationException`: `chapter` `reader.chapter_out_of_range`. The transaction is rolled back.'],
    ['`SaveReadingPosition::progressAt`\n(private)',
     'The share of the whole text before the place: (characters of the chapters before + the part of this chapter) / all characters.',
     '`text`: BookText\n`chapter`: int\n`chapterProgress`: float',
     'float, 0 to 1; 0.0 when the text has no characters.',
     '`reader.chapter_out_of_range`.'],
    ['`SaveReadingPosition::raisePage`\n(private)',
     'Calls the progress update with the page that corresponds to the share, when it is above the current page.',
     '`entry`: UserBook\n`progress`: float, 0 to 1',
     'UserBook. It is returned unchanged for a finished book, an unknown page count, or a page not above the current one.',
     'The errors of `UpdateProgress::handle`.'],
]

STATS_ROWS = [
    ['`StatsController::summary`',
     'Gives everything the statistics screen needs at once.',
     'The signed-in reader.',
     'JSON `data`: `today` {`date`, `pages`}; `streak`; `pace`: float, one decimal; `month` and `year` {`pages`, `books_finished`}; `forecasts`: list.',
     '401.'],
    ['`StatsController::daily`',
     'Gives the pages per day for a chart.',
     '`from`: date `Y-m-d`, optional\n`to`: date `Y-m-d`, optional, not before `from`\n`days`: int, 1 to 366, optional (30 by default)',
     'JSON: `data`: list of {`date`, `pages`}; `meta`: {`from`, `to`, `total_pages`}.',
     '401; 422 for a wrong date or `days`; 422 `stats.range_too_long` for more than 365 days.'],
    ['`ReadingStatistics::today`',
     'The current calendar day of the reader.',
     'None.',
     'CarbonImmutable: midnight in the time zone of the reader.',
     'None.'],
    ['`ReadingStatistics::dailyPages`',
     'Pages read on each day of a range, every day present. The pages of a day are the net sum of its log entries.',
     '`from`: CarbonInterface\n`to`: CarbonInterface',
     'array: day `Y-m-d` => pages, int, 0 or more.',
     'None; an empty array when `from` is after `to`.'],
    ['`ReadingStatistics::pagesBetween`',
     'The sum of the pages of a range.',
     '`from`: CarbonInterface\n`to`: CarbonInterface',
     'int, 0 or more.',
     'None.'],
    ['`ReadingStatistics::streak`',
     'The same module as in {progress}.',
     'None (the reader is in the constructor).',
     'array: `current`, `longest`: int; `read_today`: bool.',
     'None.'],
    ['`ReadingStatistics::pace`',
     'Pages per day over the last 14 days, today included; days without reading count.',
     'None.',
     'float, 0 or more.',
     'None.'],
    ['`ReadingStatistics::booksFinished`',
     'The books finished on the days of a range, by the finish date.',
     '`from`: CarbonInterface\n`to`: CarbonInterface',
     'int, 0 or more.',
     'None.'],
    ['`ReadingStatistics::forecasts`',
     'The date of finishing for every book that is being read. A book has its own pace after reading on 3 different days of the last 14; before that the pace of the reader is used.',
     'None.',
     'list of arrays: `user_book_id`, `title`, `current_page`, `total_pages`; `pages_per_day`: ?float; `days_left`: ?int, 0 to 730; `finish_on`: ?string `Y-m-d`.',
     'None; the fields are null when there is no pace or the date is more than 730 days away.'],
    ['`GoalController::show`\n(and `update`)',
     'Gives the goal of a year with the progress. `update` first stores the goal (`SetGoalRequest`) and then answers in the same way.',
     '`request`: Request\n`year`: int, from the route (digits only)\n`progress`: GoalProgress (injected)\n`update`: `target_books`: int, 1 to 1000; `year` from 2000 to next year',
     'JSON `data`: the array of `GoalProgress::handle`.',
     '401; 404 for a year that is not a number; 422 `goals.year_out_of_range`.'],
    ['`GoalProgress::handle`',
     'Compares the books finished in a year with the books that should be finished by today, spread evenly over the days.',
     '`user`: User\n`year`: int',
     'array: `year`; `target_books`: ?int; `finished_books`: int; `expected_books`: ?float; `due_books`: ?int; `remaining_books`: ?int; `on_track`: ?bool.',
     'None; the fields of the goal are null for a year without a goal, `on_track` is null for a future year.'],
]

QUALITY_HEADER = ['Module', 'Coupling', 'Cohesion', 'Justification']
QUALITY_ROWS = [
    ['`ReadingProgressController::store`', 'Stamp: the request, `UserBook`, `ProgressUpdate`, `User`, the streak array.', 'Sequential',
     'The result of the action feeds the reply. The streak is a second source for the same reply.'],
    ['`UpdateProgressRequest`', 'Stamp: the request object to the controller; `User` and `UserBook` to the policy.', 'Functional',
     'One task: let the request through or refuse it.'],
    ['`UserBookPolicy::update`', 'Stamp: `User`, `UserBook`; only two ids are used.', 'Functional',
     'One question: is the entry the reader\'s. The signature is fixed by the Gate of Laravel.'],
    ['`UpdateProgress::handle`', 'Stamp: `UserBook`. Data: `page`.', 'Sequential',
     'Lock, refuse a finished book, move, write the log: every step uses the result of the one before. Two callers.'],
    ['`ChangeUserBookStatus::apply`', 'Stamp: `UserBook`, changed in place. Control: `BookStatus` target.', 'Logical (weak)',
     'One entry for four transitions; `target` chosen by the caller selects the branch. All of them change the same model and share `canMoveTo`.'],
    ['`RecordReadingLog::handle`', 'Stamp: `UserBook`. Data: two pages.', 'Functional',
     'One task: write one record. The check “page not changed, no record” belongs to the task.'],
    ['`ReadingStatistics` (seven public methods)', 'Stamp: `User` in the constructor, dates, arrays. Data: pages, counts.', 'Informational',
     'Every method reads the log of one reader. `forecasts` is the longest (about 60 lines) and mixes the choice of books, the pace of each and the date.'],
    ['`StatsController::summary`', 'Stamp: dates and arrays. Data: counts.', 'Informational',
     'Six calls on the same log; no result passes from one call to the next.'],
    ['`GoalProgress::handle`', 'Stamp: `User` in, array out.', 'Functional',
     'One task: compare the finished books with the goal. It creates its own `ReadingStatistics`, so the dependency is not in the signature.'],
    ['`ImportBookText::handle`', 'Stamp: `UserBook`, `EpubBook`. Data: `path`, `sourceName`.', 'Sequential, with procedural parts',
     'Lock, open, replace, store, count. It also holds the rules of the quota. The private `import` is about 70 lines.'],
    ['`EpubImporter::open`', 'Stamp: `EpubArchive`, `EpubPackage`, `EpubBook`. Data: `path`.', 'Sequential',
     'Open the archive, refuse DRM, read the package, assemble the book.'],
    ['`EpubArchive::read`', 'Data: `name`, `maxBytes`. Control: `ReaderError`.', 'Functional',
     'One task: read one entry within the limits. The class as a whole is informational (one archive and the count of bytes read).'],
    ['`PackageReader::read`', 'Stamp: `EpubArchive` in, `EpubPackage` out.', 'Sequential',
     'Container, package, manifest, spine, titles: each step uses the result of the one before. 332 lines, 12 methods.'],
    ['`SafeXml::parse`', 'Data: `xml`. Stamp: `DOMDocument` out.', 'Sequential',
     'Convert to UTF-8, refuse entities, parse. It has no state and three callers.'],
    ['`HtmlSanitizer::sanitize`', 'Stamp: `DOMDocument` in, `SanitizedChapter` out.', 'Sequential (the class is large)',
     'Copy by the allow-list, tidy the text and the tables, write the HTML, measure it. 591 lines, 23 methods and the state in fields; three concerns in one class.'],
    ['`Text` (five functions)', 'Data only: strings and integers.', 'Informational',
     'The functions work on one representation of text (UTF-16 code units). The best coupling in the system.'],
    ['`SaveQuote::create`', 'Stamp: `UserBook`, the array of fields.', 'Sequential',
     'Check the page, resolve the anchor, create the quote. With `update` the class is informational.'],
    ['`ResolveQuoteAnchor::handle`', 'Stamp: `UserBook`, the anchor array. Data: `content`.', 'Functional',
     'One task: check where the quote is and give its columns.'],
    ['`SaveReadingPosition::handle`', 'Stamp: `BookText`, `UserBook`, `PositionSaved`. Data: `chapter`, `chapterProgress`.', 'Sequential',
     'Lock, calculate the progress, save the position, raise the page. The private `progressAt` has data coupling only.'],
]


def write(d, ctx) -> None:
    refs = {'progress': ctx.ref('fig:cc_progress'), 'epub': ctx.ref('fig:cc_epub')}

    def spec(rows: list) -> list:
        """Puts the references into the cells that carry a mark like {epub}."""
        return [[cell.replace('{epub}', refs['epub']).replace('{progress}', refs['progress']) for cell in row]
                for row in rows]

    d.h1('10 Structure charts of Constantine')
    d.p('A structure chart of Constantine shows the modules of a program, the calls between them, the data that pass with '
        'each call and the data areas that the modules use. The charts of this section are drawn from the code of the '
        'server part, not from a plan: the modules are classes and methods with their real names, and the parameters are '
        'those of their signatures. The server part is shown by layers in ' + ctx.ref('fig:structure_server') + ' and '
        + ctx.ref('tab:modules_server') + '; the charts refine it down to methods. The client is not drawn: it calls the API, '
        'and the controller of the API is the top of every chart.')
    d.p('Four processes are drawn, because together they hold every kind of structure that the system has: a short chain '
        'of actions (the update of progress, process 3.0), a long chain of services with a loop and a coroutine (the '
        'import of an EPUB book, process 6.0), a check against stored data (the quote selected in the text, processes 5.0 '
        'and 6.0) and a set of calculations on one log (statistics and goal, process 4.0). The reading position, which '
        'raises the page, has a small chart of its own. The other processes (the library, the account, the community and '
        'the moderation) have a shorter structure of the same layers: a controller, a form request, an action and the '
        'models; they are not drawn.')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('10.1 Notation')
    d.p('The notation is that of the methodology and is shown in ' + ctx.ref('fig:cc_legend') + '. A module is a '
        'rectangle, a subsystem has two vertical lines on each side, a library two horizontal lines, a data area is an '
        'ellipse. A call is an arrow from the caller to the called module; a diamond at the caller makes it a conditional '
        'call, a circle a call in a loop, a bar across the line a coroutine. A coupling is a thin arrow next to the call '
        'with a circle at the tail: empty for coupling by data and by stamp, filled for coupling by control.')
    ctx.figure(d, 'fig:cc_legend', 'cc_legend.png', 'Notation of the structure charts of Constantine')
    d.p('A chart is read from the top. The top module is called by the framework (a method of a controller), each arrow '
        'leads to a module that it calls, and the modules of one caller are called from left to right. The arrows next to a '
        'call show what goes down as parameters and what comes back as the result; a label in lower case is an elementary '
        'parameter, a label with the name of a class is a composite one.')
    d.p('The three kinds of coupling are used as the methodology defines them:')
    d.bullets([
        '**By data**: the modules exchange elementary parameters. ~~Example:~~ `RecordReadingLog::handle` gets two integers, '
        '`fromPage` and `toPage`, besides the model.',
        '**By stamp**: one module sends a composite object, here a model (`UserBook`, `BookText`), an array or a value '
        'object (`ProgressUpdate`). The callee may use only a part of it.',
        '**By control**: one module sends a value that steers the logic of the other. ~~Example:~~ `EpubArchive::read` gets '
        '`ReaderError $tooBig`, which decides what error code the callee raises.',
    ])
    d.p('The standard symbols do not show five things, so five marks are added: the fill of a vertex shows its layer of '
        'the server part; a dotted arrow shows the access of a module to a data area and its direction; a bar at the left '
        'edge of a vertex marks a module or an area that is drawn a second time; a dashed frame groups the methods of one '
        'class; a vertex with a dashed outline is a module of another chart. A module that is drawn again keeps its calls '
        'at its first appearance only.')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('10.2 Update of reading progress (process 3.0)')
    d.p('The chart of the update of progress is given in ' + ctx.ref('fig:cc_progress') + '. Its top module is '
        '`ReadingProgressController::store`. Before its body runs, the framework builds `UpdateProgressRequest`, which asks '
        '`UserBookPolicy::update` whether the entry is the reader\'s; a foreign entry gets 404. The controller then calls '
        '`UpdateProgress::handle` and, to make the reply, `ReadingStatistics::streak` of the process 4.0.')
    d.p('`UpdateProgress::handle` works in one transaction. It calls `ChangeUserBookStatus::apply` only when the page '
        'changes and the book is not yet being read. It always calls `RecordReadingLog::handle`; that module does nothing '
        'when the page did not change, so the condition is inside it. `UpdateProgress` has a second caller, '
        '`SaveReadingPosition` (' + ctx.ref('fig:cc_position') + '); `ChangeUserBookStatus::apply` and '
        '`RecordReadingLog::handle` have a second caller too, `UpdateUserBook` (the change of the status of a book), '
        'which is not drawn.')
    d.p('Three data areas are used: `user_books` (D3), which is locked, read and written; `reading_logs` (D4), which '
        'is written and is read by the streak; and `users` (D1), from which `RecordReadingLog::handle` reads the time zone '
        'for the day of the record. The preliminary design lists no access to D1 for the process 3.0; the access is a '
        'read of one column. The specifications of the modules are given in ' + ctx.ref('tab:cc_spec_progress') + '.')
    ctx.figure(d, 'fig:cc_progress', 'cc_progress.png', 'Structure chart of Constantine: update of reading progress')
    _spec_portrait(d, ctx, 'tab:cc_spec_progress', 'Specifications of the modules of the update of progress', spec(PROGRESS_ROWS))

    # ------------------------------------------------------------------------------------------------------------
    d.h2('10.3 Import of an EPUB book (process 6.0)')
    d.p('The import is the longest chain of calls in the system (' + ctx.ref('fig:cc_import') + '). '
        '`BookTextController::store` calls `ImportBookText::handle`, which first takes a lock in the cache (`epub-import`) '
        'so that only one book is read at a time on the server. If the lock is not got in 5 s, `ImportBusyException` is '
        'raised (a conditional call) and the client is told to try again.')
    d.p('Then `EpubImporter::open` checks the archive and reads the package. In one transaction the importer locks the '
        'row of the entry, calls `DeleteBookText::handle` for the old text, checks the quota of the reader, creates the '
        'text, and goes through the chapters. `EpubBook::chapters` is a generator: it reads, parses and cleans the next '
        'document and hands over one chapter; the importer stores it with `BookChapter::insert` and asks for the next. '
        'This is a coroutine called in a loop, and it keeps one chapter in memory. `EpubImporter::open` and '
        '`EpubBook::chapters` are subsystems, drawn in full in ' + ctx.ref('fig:cc_epub') + '.')
    d.p('Not drawn: `StoreBookTextRequest` checks the owner through `UserBookPolicy::update`, as in the chart of the '
        'progress update; `BookTextController::sourceName` cleans the name of the file; `EpubBook::close` is called in '
        'the `finally` block and closes the archive. The specifications are given in ' + ctx.ref('tab:cc_spec_import')
        + '. The table of the subsystems is ' + ctx.ref('tab:cc_spec_epub') + '.')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:cc_import', 'cc_import.png', 'Structure chart of Constantine: import of an EPUB book',
                   landscape=True)
        _spec_landscape(d, ctx, 'tab:cc_spec_import', 'Specifications of the modules of the import of an EPUB book',
                        spec(IMPORT_ROWS))
    d.p('The two subsystems are drawn in full in ' + ctx.ref('fig:cc_epub') + '. `EpubImporter::open` makes three calls '
        'in a row: `EpubArchive::open` checks the file, `EpubImporter::assertNotEncrypted` refuses a book under DRM, and '
        '`PackageReader::read` finds the spine and the titles. The call of `assertNotEncrypted` is always made; only its '
        'reading of `META-INF/encryption.xml` is conditional, because the file may be absent. `EpubBook::chapters` goes '
        'through the documents of the spine in a loop: `EpubArchive::read`, `SafeXml::parse` and `HtmlSanitizer::sanitize`.')
    d.p('`EpubArchive::read`, `SafeXml::parse` and the functions of `Text` are libraries with several callers. Each is '
        'drawn once for the first subsystem and again, with a bar, where the second one needs it. `PackageReader::read` '
        'and `HtmlSanitizer::sanitize` are subsystems whose inner structure (12 and 23 methods) is not drawn. All the '
        'modules of the service read their limits from `EpubLimits`, a read-only object that is not drawn. The '
        'specifications of the modules are given in ' + ctx.ref('tab:cc_spec_epub') + '.')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:cc_epub', 'cc_epub.png', 'Structure chart of Constantine: the Epub service', landscape=True)
        _spec_landscape(d, ctx, 'tab:cc_spec_epub', 'Specifications of the modules of the Epub service',
                        spec(EPUB_ROWS))

    # ------------------------------------------------------------------------------------------------------------
    d.h2('10.4 Saving a quote selected in the text (processes 5.0 and 6.0)')
    d.p('The chart of the saving of a quote is given in ' + ctx.ref('fig:cc_quote') + '. `QuoteController::store` calls '
        '`SaveQuote::create`. That module calls `ResolveQuoteAnchor::handle` only when the request has an anchor, that is, '
        'when the quote was selected in the text of the book; a quote that was typed in has no anchor. '
        '`ResolveQuoteAnchor::handle` reads the text of the entry and the chapter, cuts the selected range with '
        '`Text::slice` from the text that `Text::ofHtml` gives, and compares the two texts without white space '
        '(`Text::skeleton`, called twice). The server does not trust the client: the range must hold the text of the quote.')
    d.p('The quote is written to `quotes` (D5); the text and the chapter are read from `book_texts` and `book_chapters` '
        '(D7). The access is checked by `UserBookPolicy::update` in the form request, and a missing text, a wrong range '
        'and a mismatch give the codes `reader.no_text`, `reader.anchor_out_of_range` and `reader.anchor_mismatch`. The '
        'specifications are given in ' + ctx.ref('tab:cc_spec_quote') + '.')
    ctx.figure(d, 'fig:cc_quote', 'cc_quote.png', 'Structure chart of Constantine: saving a quote selected in the text')
    _spec_portrait(d, ctx, 'tab:cc_spec_quote', 'Specifications of the modules of the saving of a quote', spec(QUOTE_ROWS))
    d.p('The reading position (process 6.0) is a second way in which the text of a book works with the rest of the '
        'system, so it has a chart of its own (' + ctx.ref('fig:cc_position') + '). `SaveReadingPosition::handle` takes '
        'the lock of the row of the entry, asks `progressAt` for the share of the whole text before the place, saves the '
        'place in `book_texts`, and asks `raisePage` to raise the page of the entry. `raisePage` calls '
        '`UpdateProgress::handle`, the module of ' + ctx.ref('fig:cc_progress') + ', only when the page is above the '
        'current one, the book is not finished and its page count is known. A subsystem symbol is used for '
        '`UpdateProgress::handle`, because its structure is drawn in that figure. ' + ctx.ref('tab:cc_spec_position')
        + ' gives the specifications.')
    ctx.figure(d, 'fig:cc_position', 'cc_position.png', 'Structure chart of Constantine: saving the reading position')
    _spec_portrait(d, ctx, 'tab:cc_spec_position', 'Specifications of the modules of the saving of the reading position',
                   spec(POSITION_ROWS))

    # ------------------------------------------------------------------------------------------------------------
    d.h2('10.5 Reading statistics and annual goal (process 4.0)')
    d.p('The chart of the statistics is given in ' + ctx.ref('fig:cc_stats') + '. The methods of `ReadingStatistics` '
        'are drawn as library modules inside a frame: they are functions of one class that several modules call. '
        '`StatsController::summary` calls six of them in a row, from left to right: `today`, `pagesBetween` (three times: '
        'for today, for the month and for the year), `streak`, `pace`, `booksFinished` (twice: for the month and for the '
        'year) and `forecasts`. Inside the class `pagesBetween` calls `dailyPages`, `pace` calls `pagesBetween`, and '
        '`forecasts` calls `pace`; `streak`, `pace` and `forecasts` also call `today`. The calls of `today` from inside the '
        'class are not drawn; the other inner calls are drawn once, and a method that is called a second time is drawn '
        'with a bar.')
    d.p('The controllers create the class with `new ReadingStatistics($request->user())`, so the reader is the only '
        'constructor parameter and the state of the object. `StatsController::daily` calls `today` only when the '
        'request has no `to`, and `dailyPages` always. `GoalController::show` calls `GoalProgress::handle`, which calls '
        '`today` and `booksFinished` and reads `reading_goals` (D6). The data areas are `reading_logs` (D4), `user_books` '
        '(D3) and `reading_goals` (D6). The specifications are given in ' + ctx.ref('tab:cc_spec_stats') + '.')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:cc_stats', 'cc_stats.png', 'Structure chart of Constantine: reading statistics and annual goal',
                   landscape=True)
        _spec_landscape(d, ctx, 'tab:cc_spec_stats', 'Specifications of the modules of the statistics and the goal',
                        spec(STATS_ROWS))

    # ------------------------------------------------------------------------------------------------------------
    d.h2('10.6 Evaluation of the quality of the design')
    d.p('The quality of the design is judged by two criteria of the methodology: the coupling of the modules should be '
        'weak, and every module should do one task. The levels of coupling, from the best, are data, stamp and control. '
        'The levels of cohesion, from the best, are functional, sequential, informational, procedural, temporal, logical '
        'and coincidental. ' + ctx.ref('tab:cc_quality') + ' gives both for the modules of the charts, with the reason '
        'for each estimate. The estimates are made by reading the code; they are a judgement, not a measurement.')
    ctx.table(d, 'tab:cc_quality', 'Coupling and cohesion of the modules of the charts')
    d.table(QUALITY_HEADER, QUALITY_ROWS, [3.0, 3.6, 1.9, 5.5], size=18)
    d.p('The table shows that the system has no coincidental, temporal or procedural-only modules. Three places are weaker '
        'than the rest: `ReadingStatistics` (informational), `ChangeUserBookStatus::apply` (logical) and the large class '
        '`HtmlSanitizer`. The coupling by stamp is where a model or an array is passed and only a part of it is used. '
        'The places where it could be reduced, and what the change would cost, are these:')
    d.bullets([
        '**The streak in the reply.** `ReadingProgressController::store` takes one number, `current`, out of the array of '
        '`streak()`. A method `currentStreak(): int` would give coupling by data. The cost is a second public method that '
        'shares the query of the reading days with `streak()` (a private method would be extracted), and the controller '
        'still needs all three values on the statistics screen. The gain is small, so the array is kept.',
        '**The pace is calculated twice.** `StatsController::summary` calls `pace()` and `forecasts()`, and `forecasts()` '
        'calls `pace()` again, so the query of the last 14 days runs twice. By reading the code, a call of `summary` makes '
        'up to 11 queries (an estimate: one for today, the streak, the pace, two for the month, two for the year, and four '
        'for the forecasts), one of them repeated. As in the example of the methodology, a module can be moved to the '
        'next level: `summary` calls `pace()` once and passes the number to `forecasts(float $pace)`. The coupling becomes '
        'data and the repeated query disappears. The cost is the same as in the methodology: `forecasts()` can no longer '
        'be called alone, and the caller must keep the order and recalculate the pace if the data change.',
        '**The control by the status.** `ChangeUserBookStatus::apply(UserBook, BookStatus)` is coupled by control. Public '
        'methods `startReading`, `finish`, `resume` and `markReadEarlier` would remove it. The cost is that the check of '
        'the allowed transition (`canMoveTo`) must be repeated in each of them or called by each, and that the callers '
        '(`UpdateUserBook`, `UpdateProgress`) must choose the method, so the choice that is now in one `match` moves into '
        'two places.',
        '**The model in `RecordReadingLog::handle`.** The module needs the ids of the entry and of the reader and the '
        'time zone, so it could take five integers and strings instead of the model. The cost is five parameters, which '
        'is worse for the reader of the code than one model, and the caller would load `userBook->user` itself. The model is kept.',
    ])
    good = sum(1 for row in QUALITY_ROWS if row[2].startswith(('Functional', 'Sequential')))
    d.p('Conclusions:')
    d.numbered([
        'Most calls are coupled by stamp: a model, an array or a value object travels between the layers. Coupling by data '
        'is found at the bottom of the charts (`Text`, `EpubPaths::resolve`, `SafeXml::parse`), where it is the best. '
        'There are two couplings by control (`BookStatus` in `ChangeUserBookStatus::apply`, `ReaderError` in '
        '`EpubArchive::read`); both pass an enumeration value, not a flag that switches a long algorithm.',
        f'{good} of the {len(QUALITY_ROWS)} modules of ' + ctx.ref('tab:cc_quality') + ' are functional or sequential. The weak '
        'places are `ReadingStatistics` and `StatsController::summary` (informational), `ChangeUserBookStatus::apply` '
        '(logical), and the size of `HtmlSanitizer` (591 lines), `PackageReader` (332 lines) and `ImportBookText::import` '
        '(about 70 lines).',
        'The structure is shallow: three levels for the progress, five for the import. The widest module is '
        '`StatsController::summary` with six calls. The modules that are used by several callers are written once: '
        '`UpdateProgress` (two callers), `ChangeUserBookStatus::apply` and `RecordReadingLog::handle` (two each), '
        '`SafeXml::parse` and `EpubArchive::read` (three each).',
        'The process 6.0 reaches the stores of other processes in two places, as Table 11 of the preliminary design shows: '
        '`SaveReadingPosition` raises the page through `UpdateProgress` (D3 and D4), and `DeleteBookText` clears the anchors '
        'in `quotes` (D5). In both places the access is through one module with one task, and the second is a deliberate '
        'step: the database clears only `book_text_id` when a text is deleted.',
        'No change of the design is needed. If the system grows, the changes are, in the order of cost, `currentStreak`, '
        'passing the pace to `forecasts`, and the split of `HtmlSanitizer`. The last one is the most expensive: the '
        'offsets of the saved quotes depend on the exact output of the sanitizer, so a change of it needs a test of the '
        'output and may need a new import of the stored texts.',
    ])
    d.p('The order in which the modules of the charts are implemented follows the methodology: the top modules first, then '
        'the modules that they call. In the repository the work went by slices of the product, and the order of the slices '
        'is the order of the list below; the modules of one slice were written from the controller down to the models, as '
        'Section 17 describes. The order inside one commit is not recorded, so only the order of the slices and of the '
        'changes to existing modules can be checked in the history.')
    d.numbered([
        'The library (stage 1): `ChangeUserBookStatus` with `UpdateUserBook`. It is the one module of the charts that is '
        'older than the process that calls it most, `UpdateProgress`.',
        'The update of progress (stage 2): `ReadingProgressController`, `UpdateProgressRequest`, `UpdateProgress`, '
        '`RecordReadingLog`.',
        'The quotes (stage 3): `QuoteController`, `StoreQuoteRequest`, `SaveQuote`.',
        'The statistics and the goal (stage 4): `StatsController`, `GoalController`, `ReadingStatistics`, `GoalProgress`. '
        'The call of `ReadingStatistics::streak` was added to `ReadingProgressController` in the same stage, after the '
        'controller was written.',
        'The reader (stage 7): `BookTextController`, `ImportBookText`, `EpubImporter` with `EpubArchive`, `PackageReader`, '
        '`EpubBook`, `HtmlSanitizer` and the libraries `SafeXml`, `EpubPaths`, `Text`; then `ResolveQuoteAnchor`, which '
        'was added to the existing `SaveQuote`, and `SaveReadingPosition` with `ReadingPositionController`, which call '
        'the existing `UpdateProgress`.',
    ])
