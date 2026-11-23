"""The short technical design: the five things that the methodology asks for (refined algorithms, structural scheme,
functional scheme, structure charts of Constantine and of Jackson) and, in a few tables, everything else that the
technical design must contain. The extended version, with every list in full, is built from `content/`.

Used by build.py for `TechnicalDesign_Bookshelf.docx` and for Appendix A of `Zvit_LR3_Bookshelf.docx`.
"""

from __future__ import annotations

from content.s07_algorithms import _levels, code
from content.s10_constantine import QUALITY_HEADER, QUALITY_ROWS


def intro(d, ctx) -> None:
    d.h1('1 Introduction', page_break=True)
    d.p('This document is the technical design of the web application Bookshelf, version 2: a reading tracker with a '
        'community of readers and a built-in reader of EPUB books. It follows the technical specification and the '
        'preliminary design and describes the program that is implemented: the names of the modules, the routes, the '
        'tables and the codes are those of the repository. The document gives the five things that the methodology of '
        'the stage asks for (refined algorithms, a structural and a functional scheme, structure charts of Constantine and '
        'of Jackson) and, briefly, the other parts of a technical design: the main decisions, the coding, the input and '
        'output data, the user interface, the software, the technical means, the reliability and the cost. The full '
        'lists, with all their tables, are in the extended version of this document.')


def decisions(d, ctx) -> None:
    d.h1('2 Main technical decisions')
    d.p('The preliminary design fixed the decisions that affect the whole system (privacy by default, a post as a snapshot, '
        'a sanitized text of a book). The decisions about the structure of the program are in ' + ctx.ref('tab:s_decisions') + '.')
    ctx.table(d, 'tab:s_decisions', 'Main decisions of the technical design')
    d.table(
        ['Decision', 'Reason and consequence'],
        [
            ['A layered server part: route, middleware, controller, form request, action or service, policy, model, resource',
             'Every layer has one task, so the modules are weakly coupled and can be tested alone. A controller is thin: a form request, one action, a resource.'],
            ['One class for one use case (an action with the method `handle`)',
             'The logic of a use case, for example `UpdateProgress`, is in one place and is called by a controller and by another action.'],
            ['Access control in policies; a foreign resource is “not found”',
             'The rule “a reader sees only own data” is written once per resource and used by the form requests and the controllers.'],
            ['Business-rule errors are stable codes such as `library.invalid_transition`',
             'The API answers 422 with the code, and the client translates it, so the text of the interface is only on the client.'],
            ['Races are closed in the database',
             'A row lock (`lockForUpdate`) in the progress update, the reading position and the reports; unique indexes; one global lock for the import of an EPUB book.'],
            ['The EPUB file is read by the built-in tools of PHP and is not stored',
             '`ZipArchive` and `DOMDocument` need no new dependencies; the file is read chapter by chapter and only the sanitized text is stored.'],
            ['The PWA never caches the data of a reader',
             'The hand-written service worker caches only the shell of the application; requests to `/api` always go to the network.'],
        ],
        [4.2, 7.4],
    )


def coding_and_data(d, ctx) -> None:
    d.h1('3 Coding, input data and output data')
    d.h2('3.1 Systems of coding and classification')
    d.p('The codes that the system uses are in ' + ctx.ref('tab:s_coding') + '.')
    ctx.table(d, 'tab:s_coding', 'Systems of coding and classification')
    d.table(
        ['Kind', 'Code and format', 'Meaning'],
        [
            ['Identifier', 'Integer, assigned by the database', 'A reader, a book, a library entry, a quote, a post, a comment.'],
            ['Handle of a reader', '`^[a-z0-9_]{3,30}$`', 'The public name of a reader (`@olena_reads`), unique.'],
            ['Status of a book', '`want`, `reading`, `finished`, `abandoned`', 'The transitions are fixed in `BookStatus::allowedTransitions`.'],
            ['Type of a post, of a quote', '`quote`, `review`; `quote`, `insight`', 'What the reader publishes or saves.'],
            ['Reason of a report', '`spam`, `abuse`, `other`', 'Three open reports from different readers hide the content.'],
            ['Code of an error', '`<area>.<reason>`, areas `library`, `catalog`, `quotes`, `goals`, `social`, `reader`', 'A rule of the business is broken; the answer is 422 (409 or 503 in two cases).'],
            ['Codes of the design documents', '`F-1.1` to `F-9.7`; processes `1.0` to `8.0`; stores `D1` to `D9`', 'Functions, processes and data stores of the technical specification and the preliminary design.'],
        ],
        [2.4, 4.4, 4.8],
    )
    d.h2('3.2 Input data')
    d.p('The main input documents are in ' + ctx.ref('tab:s_input') + '. Every request is checked by a form request before '
        'the controller runs, and every limit is repeated by the server.')
    ctx.table(d, 'tab:s_input', 'Main input documents')
    d.table(
        ['Document', 'Route', 'Fields and limits', 'Process'],
        [
            ['Registration', 'POST `/api/auth/register`', 'name up to 255 characters, e-mail, password of 8 characters or more, time zone', '1.0'],
            ['Add a book', 'POST `/api/library`', 'title up to 255 characters or a key of Open Library; up to 5 authors; pages from 1 to 20 000; status', '2.0'],
            ['Progress', 'POST `/api/library/{id}/progress`', 'page from 0 to the number of pages of the book', '3.0'],
            ['Quote', 'POST `/api/library/{id}/quotes`', 'text up to 2000 characters; note up to 2000; page; anchor (chapter, start, end) when selected in the text', '5.0, 6.0'],
            ['EPUB file', 'POST `/api/library/{id}/text`', 'a file up to 15 MB; 60 MB unpacked; 2000 entries; 500 chapters; 20 uploads an hour', '6.0'],
            ['Post, comment, report', 'POST `/api/posts`, `.../comments`, `.../report`', 'a note of a quote post up to 2000 characters; a comment under a post up to 1000; a rating from 1 to 5; a report has a reason and a note up to 500', '7.0, 8.0'],
        ],
        [2.2, 3.6, 5.0, 1.2],
    )
    d.h2('3.3 Output data')
    d.p('The main output documents are in ' + ctx.ref('tab:s_output') + '. The server sends JSON and never a text for the '
        'reader: the interface is composed on the client.')
    ctx.table(d, 'tab:s_output', 'Main output documents')
    d.table(
        ['Document', 'Route', 'Content'],
        [
            ['Library and book card', 'GET `/api/library`', 'the book, its authors, the status, the current page, the number of pages, the rating'],
            ['Answer to a progress update', 'POST `/api/library/{id}/progress`', 'the entry, the pages gained, the mark “reached the end”, the current streak'],
            ['Statistics', 'GET `/api/stats/summary`, `/api/stats/daily`', 'pages per day, streak, pace, forecast of finishing, annual goal'],
            ['Feed and posts', 'GET `/api/feed`', 'twenty posts a page with the author, the likes and the number of comments the viewer may see'],
            ['Chapter of a book', 'GET `/api/book-texts/{id}/chapters/{n}`', 'the title, the sanitized HTML, the number of characters, the highlights'],
            ['Quote card', 'drawn in the browser', 'a PNG picture of 1080 by 1350 pixels'],
            ['Output of the moderation', 'Artisan commands `moderation:*`', 'a table of open reports on the console of the server'],
        ],
        [3.0, 4.2, 4.4],
    )


def algorithms(d, ctx) -> None:
    d.h1('4 Refined algorithms')
    d.p('The algorithms are refined from the top down, by the method of stepwise refinement. Level 0 states the task in '
        'one sentence. Level 1 splits it into steps and gives the module of each step. Level 2 is the structured '
        'algorithm in pseudocode, with the checks, the locks, the limits and the codes of the errors. Two of the '
        'algorithms of the preliminary design are given here: the update of progress and the import of an EPUB book. '
        'The extended version has all nine.')
    d.h2('4.1 Update of reading progress')
    d.p('The update is one transaction that locks the row of the book, so two quick taps do not interleave; the reading '
        'position of the built-in reader uses the same module and the same lock. The refinement is in '
        + ctx.ref('tab:s_alg_progress') + '.')
    _levels(d, ctx, 'tab:s_alg_progress', 'Refinement of the update of reading progress',
            'Move the bookmark of a book of the reader to the page p and write the change into the reading log.',
            [('Open a transaction and lock the row of the book.', '`UpdateProgress::handle`'),
             ('Refuse the update of a finished book.', '`UpdateProgress::handle`'),
             ('If the page differs from the current one, move the bookmark (starting the reading if the book was only wanted or put aside).', '`UpdateProgress::moveTo`, `ChangeUserBookStatus::apply`'),
             ('Write a record of the move into the log (nothing is written when the page did not change).', '`RecordReadingLog::handle`'),
             ('Answer with the book, the pages gained, the mark “reached the end” and the current streak.', '`ReadingProgressController::store`, `ReadingStatistics::streak`')])
    code(d, [
        'FUNCTION update_progress(entry, p)                       -- UpdateProgress::handle',
        '  BEGIN TRANSACTION',
        '    e <- SELECT user_book WHERE id = entry.id FOR UPDATE',
        '    IF e.status = finished THEN FAIL(page: "library.progress_finished")',
        '    from <- e.current_page',
        '    IF p <> from THEN move_to(e, p)',
        '    log <- record_log(e, from, p)',
        '  COMMIT',
        '  RETURN (e, log)',
        '',
        'PROCEDURE move_to(e, p)                                  -- UpdateProgress::moveTo',
        '  IF e.status <> reading THEN change_status(e, reading)',
        '  IF e.total_pages IS NOT NULL AND p > e.total_pages THEN FAIL(page: "library.page_above_total")',
        '  e.current_page <- p; SAVE e',
        '',
        'FUNCTION record_log(e, from, to)                         -- RecordReadingLog::handle',
        '  IF from = to THEN RETURN null',
        '  RETURN INSERT reading_log(user_id, user_book_id, from_page, to_page, logged_on = today in zone)',
    ])
    d.h2('4.2 Import of an EPUB book')
    d.p('The uploaded file belongs to a stranger, so it is checked before it is read, and it is read chapter by chapter: '
        'each chapter is stored before the next one is parsed, so the memory does not grow with the size of the book. '
        'Only one book is read at a time on the whole server (a lock of the cache), because the server has 1 GB of '
        'memory. The import is one transaction: either the whole book is stored or the book keeps its previous text. '
        'The refinement is in ' + ctx.ref('tab:s_alg_import') + '.')
    _levels(d, ctx, 'tab:s_alg_import', 'Refinement of the import of an EPUB book',
            'Turn the uploaded EPUB file into the sanitized text of the chapters of a library book, replacing the text it had.',
            [('Check the request: the book is the reader’s, the file is present and not larger than 15 MB.', '`StoreBookTextRequest`'),
             ('Take the lock of the import, or answer “busy”.', '`ImportBookText::handle`'),
             ('Check the archive (size, ZIP, names of entries, encryption, unpacked size, “mimetype”) and refuse DRM.', '`EpubArchive::open`, `EpubImporter::open`'),
             ('Read the package: the root file, the manifest, the order of the documents, the titles.', '`PackageReader::read`'),
             ('Delete the previous text and check the quota of the reader.', '`DeleteBookText::handle`'),
             ('For every document: read it, parse it safely, sanitize it, skip it if it has no text, store the chapter.', '`EpubBook::chapters`, `SafeXml::parse`, `HtmlSanitizer::sanitize`'),
             ('Save the counters, release the lock, answer 201.', '`ImportBookText::import`')])
    code(d, [
        'FUNCTION import_book(entry, file)                        -- ImportBookText::handle',
        '  GET LOCK "epub-import" (waits 5 s); IF not taken THEN FAIL(503, "reader.busy")',
        '  book <- open_epub(file)                                 -- may FAIL with a code reader.*',
        '  BEGIN TRANSACTION',
        '    lock the row of entry; delete the previous text',
        '    IF the reader has 30 texts THEN FAIL("reader.quota_exceeded")',
        '    FOR EACH chapter IN book.chapters()                   -- one chapter in memory at a time',
        '      INSERT book_chapter(...); characters <- characters + chapter.characters',
        '      IF characters of the reader > 40 000 000 THEN FAIL("reader.quota_exceeded")',
        '    IF there is no chapter THEN FAIL("reader.empty")',
        '    UPDATE text SET chapters_count, characters_count',
        '  COMMIT; RELEASE LOCK; RETURN text',
        '',
        'GENERATOR chapters(book)                                 -- EpubBook::chapters',
        '  FOR EACH document IN the spine of the book',
        '    doc <- parse_safely(read(document, at most 10 MB))     -- no entities, no network',
        '    IF doc = null THEN CONTINUE',
        '    chapter <- sanitize(doc)          -- a new tree from an allow-list, without attributes',
        '    IF chapter has no text THEN CONTINUE',
        '    IF more than 500 chapters THEN FAIL("reader.too_many_chapters")',
        '    IF more than 8 000 000 characters THEN FAIL("reader.text_too_large")',
        '    YIELD chapter',
    ])


def schemes(d, ctx) -> None:
    d.h1('5 Structural scheme')
    d.p('A structural scheme shows the composition of the parts of the software and their interaction by control. The '
        'structure of Bookshelf is in ' + ctx.ref('fig:s_structure') + ': the client, the web server and the layers '
        'that a request passes in the server part. An arrow points from the caller to the called part.')
    ctx.figure(d, 'fig:s_structure', 'structure_system.png', 'Structure of the system: the parts and the calls between them')
    d.p('The layers are in ' + ctx.ref('tab:s_layers') + '. The hierarchy of all the modules (controllers, actions, '
        'services, policies, console commands) is in the extended version of the document.')
    ctx.table(d, 'tab:s_layers', 'Layers of the server part')
    d.table(
        ['Layer', 'Task', 'Examples'],
        [
            ['Route, middleware', 'Session, CSRF, access to signed-in readers, limits of the rate', '`routes/api.php`, `social.php`, `reader.php`'],
            ['Form request', 'Checks who may send the request and what it contains', '`UpdateProgressRequest`, `StoreBookTextRequest`'],
            ['Controller', 'Calls one action or service and returns a resource', '`ReadingProgressController`, `BookTextController`'],
            ['Action', 'One use case, often in a transaction', '`UpdateProgress`, `SaveQuote`, `ImportBookText`, `ReportContent`'],
            ['Service', 'A technical capability', '`EpubImporter`, `ReadingStatistics`, `OpenLibraryClient`'],
            ['Policy', 'Access to a resource; a foreign one is “not found”', '`UserBookPolicy`, `PostPolicy`'],
            ['Model, resource', 'The tables and the JSON of an answer', '`UserBook`, `Quote`, `UserBookResource`'],
        ],
        [2.4, 5.0, 4.2],
    )

    d.h1('6 Functional scheme')
    d.p('A functional scheme shows the interaction of the components with the flows of information, the composition of '
        'the data in the flows and the files and the devices that are used, in the symbols of GOST 19.701-90. The symbols '
        'that are used are in ' + ctx.ref('tab:s_symbols') + ', the scheme is ' + ctx.ref('fig:s_functional') + '.')
    ctx.table(d, 'tab:s_symbols', 'Symbols of the functional scheme (GOST 19.701-90)')
    d.table(
        ['Symbol', 'Meaning in the scheme', 'Example'],
        [
            ['Processing (a rectangle)', 'A process or a component', 'a process of the server part, the views of the client'],
            ['Manual input', 'Data typed or chosen by the reader', 'forms; the EPUB file chosen on the page of a book'],
            ['Display', 'Data shown on the screen', 'lists, cards, charts'],
            ['Document', 'Data that leave as a file', 'the picture of a quote card; the output of the moderation'],
            ['Stored data', 'Data kept without naming the device', 'the cache of the service worker, `localStorage`, cookies'],
            ['Direct access storage', 'A database on a disk', 'MySQL 8.4 with the stores D1 to D9'],
        ],
        [3.2, 4.2, 4.2],
    )
    with ctx.landscape(d):
        d.p('The scheme shows the client with its views, stores and API client on the left and the eight processes of the '
            'server part with the stores of data on the right.', keep_next=True, line=240)
        ctx.figure(d, 'fig:s_functional', 'functional_system.png', 'Functional scheme of the system', landscape=True)


def constantine(d, ctx) -> None:
    d.h1('7 Structure charts of Constantine')
    d.p('A structure chart of Constantine shows the modules of a program, the calls between them and the data that pass '
        'with each call. The notation is in ' + ctx.ref('fig:s_cc_legend') + ': a module is a rectangle, a subsystem has two '
        'vertical lines on each side, a library two horizontal lines, a data area is an ellipse. A diamond at the caller '
        'is a conditional call, a circle a call in a loop. The couplings are thin arrows next to the call: an empty circle '
        'at the tail is coupling by data or by stamp, a filled one is coupling by control. The charts are drawn from the '
        'code, with the real parameters.')
    ctx.figure(d, 'fig:s_cc_legend', 'cc_legend.png', 'Notation of the structure charts of Constantine')
    d.h2('7.1 Update of reading progress')
    d.p('The chart is in ' + ctx.ref('fig:s_cc_progress') + ' and the specifications of its modules in '
        + ctx.ref('tab:s_cc_progress') + '. `ChangeUserBookStatus::apply` is called only when the page changes and the book '
        'is not yet being read; `RecordReadingLog::handle` is always called and does nothing when the page did not change.')
    ctx.figure(d, 'fig:s_cc_progress', 'cc_progress.png', 'Structure chart of Constantine: update of reading progress')
    ctx.table(d, 'tab:s_cc_progress', 'Specifications of the modules of the update of progress')
    d.table(
        ['Module', 'Purpose', 'Input', 'Output and errors'],
        [
            ['`ReadingProgressController::store`', 'Moves the bookmark, builds the reply', '`UpdateProgressRequest` (page), `UserBook`', '`UserBookResource` with the pages gained, `reached_end`, the streak; 404, 422'],
            ['`UpdateProgress::handle`', 'Locks the entry, moves the page', '`UserBook`, `page` (int, 0 to the number of pages)', '`ProgressUpdate`; `library.progress_finished`, `library.page_above_total`'],
            ['`ChangeUserBookStatus::apply`', 'Changes the status of a book', '`UserBook`, `BookStatus`, two optional dates', 'nothing (the model is changed); `library.invalid_transition`'],
            ['`RecordReadingLog::handle`', 'Writes one record of the log', '`UserBook`, `from` and `to` (int)', '`ReadingLog` or `null` when the page did not change'],
        ],
        [3.4, 2.8, 3.2, 3.6], size=20,
    )
    d.h2('7.2 Import of an EPUB book')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:s_cc_import', 'cc_import.png', 'Structure chart of Constantine: import of an EPUB book', landscape=True)
    d.p('The chart is in ' + ctx.ref('fig:s_cc_import') + '. `EpubBook::chapters` is a generator and is called in a loop: it '
        'gives one chapter, which is stored before the next one is parsed. The specifications are in '
        + ctx.ref('tab:s_cc_import') + '.')
    ctx.table(d, 'tab:s_cc_import', 'Specifications of the modules of the import')
    d.table(
        ['Module', 'Purpose', 'Input', 'Output and errors'],
        [
            ['`ImportBookText::handle`', 'Replaces the text of a library book by the chapters of an EPUB file', '`UserBook`, `path` (string), `sourceName`', '`BookText`; `reader.*` on the field `file`; `ImportBusyException`'],
            ['`EpubImporter::open`', 'Checks the archive, refuses DRM, reads the package', '`path`', '`EpubBook`; `EpubImportException` with a `ReaderError`'],
            ['`EpubBook::chapters`', 'Gives the chapters that have text, one at a time', 'none (the opened book)', 'a generator of `ParsedChapter` (position, title, html, characters)'],
            ['`SafeXml::parse`', 'Parses an XML document without entities and network', '`xml` (string)', '`DOMDocument` or `null`'],
            ['`HtmlSanitizer::sanitize`', 'Builds a new tree from an allow-list, without attributes', '`DOMDocument`', '`SanitizedChapter` (html, characters, heading)'],
        ],
        [3.0, 3.8, 3.0, 3.8], size=20,
    )
    d.h2('7.3 Evaluation of the design')
    d.p('Coupling and cohesion of the main modules are in ' + ctx.ref('tab:s_cc_quality') + '. The estimates are made by '
        'reading the code; they are a judgement, not a measurement.')
    wanted = {'`UpdateProgress::handle`', '`ChangeUserBookStatus::apply`', '`RecordReadingLog::handle`',
              '`ReadingStatistics` (seven public methods)', '`ImportBookText::handle`', '`HtmlSanitizer::sanitize`',
              '`ResolveQuoteAnchor::handle`'}
    rows = [row for row in QUALITY_ROWS if row[0] in wanted]
    ctx.table(d, 'tab:s_cc_quality', 'Coupling and cohesion of the main modules')
    d.table(QUALITY_HEADER, rows, [3.0, 3.6, 1.9, 5.5], size=18)
    d.numbered([
        'No module has coincidental or temporal cohesion; most are functional or sequential.',
        'Most calls pass a model or an array (coupling by stamp); coupling by data is at the bottom of the charts. Two couplings are by control, both by an enumeration value.',
        'The weak places are `ReadingStatistics` (informational), `ChangeUserBookStatus::apply` (logical) and the size of `HtmlSanitizer`; no change of the design is needed.',
    ])


def jackson(d, ctx) -> None:
    d.h1('8 Structure charts of Jackson')
    d.p('The structure of a program is derived from the structure of the data that it processes. A chart is a tree of '
        'blocks: an operation is a box, a sequence is the boxes under a parent from left to right, a selection marks every '
        'alternative with a circle, an iteration marks the repeated box with an asterisk (' + ctx.ref('fig:s_jk_legend') + '). '
        'Two programs are given: the reading of the chapters of an EPUB book and the counting of the reading streak.')
    ctx.figure(d, 'fig:s_jk_legend', 'jk_legend.png', 'The components of the structure charts of Jackson')
    d.h2('8.1 Reading the chapters of an EPUB book')
    ctx.figure(d, 'fig:s_jk_import', 'jk_import.png', 'Structure charts of Jackson: reading the chapters of an EPUB book')
    d.p('The input data are the documents of the spine of the book, and the output data are the chapters that are stored '
        '(' + ctx.ref('fig:s_jk_import') + '). The program is an iteration over the documents with a selection of what '
        'to do with each: skip a document that is missing or is not XML, skip one without text, or hand over a chapter after '
        'the limits are checked. It is `EpubBook::chapters` and the loop of `ImportBookText::import`.')
    d.h2('8.2 Counting the reading streak')
    d.p('The input data are the days on which something was read, in order; the output data are the longest streak, the '
        'current streak and the mark that the reader has read today (' + ctx.ref('fig:s_jk_streak') + '). The program has two '
        'iterations: over the days, with a selection (the day follows the previous one or starts a new run), and back from '
        'today. It is `ReadingStatistics::streak`.')
    ctx.figure(d, 'fig:s_jk_streak', 'jk_streak.png', 'Structure charts of Jackson: counting the reading streak')


def interface(d, ctx) -> None:
    d.h1('9 User interface and prototype')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:s_ui_map', 'ui_map.png', 'Map of the screens and the transitions', landscape=True)
    d.p('The interface is made for a phone and is installed as a progressive web application: a bottom bar of five tabs '
        '(Reading, Library, Quotes, Statistics, Community), one task on a screen, the states “loading”, “empty”, “error” and '
        '“no connection” made of the same few components, and a reading screen with its own controls. The text of '
        'the interface is in Ukrainian. The map of the screens is ' + ctx.ref('fig:s_ui_map') + '.')
    d.p('The prototype of Bookshelf is the working application. Its real screens, taken from the application that runs in '
        'the development containers, are in ' + ctx.ref('fig:s_ui_screens') + ' (the reading screen, the selection of a '
        'quote and the contents of a book) and in ' + ctx.ref('fig:s_ui_stats') + ' (the quotes, the quote card and the statistics).')
    ctx.figure(d, 'fig:s_ui_screens', 'proto_reader.png', 'Screens of the prototype: the reading screen, the selection of a quote, the contents')
    ctx.figure(d, 'fig:s_ui_stats', 'proto_quotes_stats.png', 'Screens of the prototype: quotes, the quote card, statistics')


def environment(d, ctx) -> None:
    d.h1('10 Software, technical means, reliability and cost')
    d.h2('10.1 Software')
    d.p('The software is listed in ' + ctx.ref('tab:s_software') + ' (the versions are those of the lock files).')
    ctx.table(d, 'tab:s_software', 'Software and DBMS')
    d.table(
        ['Part', 'Software', 'Version'],
        [
            ['Server part', 'PHP, Laravel, Sanctum, Fortify', '8.4; 13.34; 4.3; 1.40'],
            ['DBMS', 'MySQL (InnoDB)', '8.4'],
            ['Client', 'Vue, TypeScript, Vue Router, Pinia, vue-i18n, Tailwind CSS', '3.5; 6.0; 5.3; 4.0; 11.4; 4.3'],
            ['Build', 'Vite, Node.js, npm', '8.3; 22'],
            ['Web servers', 'nginx, Caddy (HTTPS)', '1.28; 2'],
            ['Tests and checks', 'Pest, PHPUnit, Larastan, Pint; Vitest, ESLint, vue-tsc', '4.7; 12.5; 3.12; 1.32; 5.0; 10.12; 3.3'],
            ['Delivery', 'Docker Compose, GitHub Actions, GitHub Container Registry', '-'],
        ],
        [2.4, 6.6, 2.6],
    )
    d.h2('10.2 Technical means')
    d.p('The production system runs on one virtual machine in Azure: Standard B2ats_v2 with 2 vCPU, 1 GB of memory, a disk '
        'of 30 GB and Ubuntu Server 24.04. Four containers of one Docker Compose project run on it: `caddy`, `web` (nginx), '
        '`app` (PHP-FPM, six workers) and `mysql` (a buffer of 64 MB). A reader needs a phone or a computer with a modern '
        'browser (Chrome and Edge 111, Safari 16.4, Firefox 128 or newer; a screen of 360 pixels or more). The development '
        'machine needs only Docker.')
    d.h2('10.3 Reliability')
    d.p('The design estimate of the reliability is an expert one (the probability P and the impact I, from 1 to 5). The '
        'main risks are in ' + ctx.ref('tab:s_risks') + '.')
    ctx.table(d, 'tab:s_risks', 'Main risks and the recommended actions')
    d.table(
        ['Risk', 'P', 'I', 'P x I', 'Existing protection', 'Recommended action'],
        [
            ['Backups of the database', '3', '5', '15', 'A script makes a dump (run by a cron line that is set by hand) and keeps the dumps of 14 days', 'Copy the dumps off the server; try a restore and write down the steps'],
            ['Disk of 30 GB', '3', '4', '12', 'A quota of texts per reader; old dumps are deleted', 'A quota for the whole server; delete the expired rows of the cache'],
            ['End of the free hosting', '3', '4', '12', 'The images and the compose file run on any server with Docker', 'Decide where the system lives after 12 months'],
            ['One virtual machine', '2', '5', '10', 'Containers restart by themselves; the address is static', 'Accept for a study project'],
            ['Memory of 1 GB', '3', '3', '9', 'A trimmed MySQL; six PHP workers; one import at a time; a swap of 2 GB', 'Measure after a week of use; move to 4 GB if the swap grows'],
            ['No monitoring', '4', '2', '8', 'A check of `/up` at the end of a deployment', 'An external check that sends a message'],
        ],
        [2.8, 0.5, 0.5, 0.9, 3.8, 3.6], align=['left', 'center', 'center', 'center', 'left', 'left'], size=20,
    )
    d.p('The strong parts are the rules of the data (checked in the form request, in the action and in the database), '
        'the isolation of the data of readers and the import of a book, which is built against hostile input. The client and '
        'the server part have 1181 and 1053 automated tests; all of them passed in a full run on 21 October 2026.')
    d.h2('10.4 Cost')
    d.p('The equipment and the services are free for a study project: the virtual machine is in the free offer of Azure '
        'for Students (750 hours a month for 12 months, with a credit of $100), the address is an `sslip.io` name, and '
        'all the software is free or open source. The labour is the 18 works of the network schedule: 103 working days '
        'in total, with the critical path of 57 working days, for a team of three.')


def differences(d, ctx) -> None:
    d.h1('11 Differences between the program and the earlier documents')
    d.p('The technical design was written from the code. In a few places the code differs from the technical '
        'specification and the preliminary design (' + ctx.ref('tab:s_diff') + ').')
    ctx.table(d, 'tab:s_diff', 'Differences between the implementation and the earlier documents')
    d.table(
        ['Subject', 'Earlier document', 'Implementation'],
        [
            ['Password recovery (F-1.3)', 'Listed among the functions of the account', 'Not implemented: only the registration feature of Fortify is on'],
            ['Removing a book added by hand', 'Published posts stay (F-2.7)', 'The book leaves the catalog with the last entry, and the posts about it are deleted by the cascade'],
            ['Backups', 'On a separate medium', 'The dumps lie on the same disk; the copy off the server is made by hand'],
            ['Length of the fields', 'A name up to 100 and a quote up to 5000 characters', 'A name up to 255 and a quote up to 2000 characters'],
            ['Types of the API on the client', 'Generated from the OpenAPI contract', 'Written by hand in `src/types/api.ts`'],
            ['Role of a moderator', 'Given by the flag `is_admin`', 'The flag exists but nothing reads it; the commands run for whoever has access to the server'],
        ],
        [3.0, 4.0, 4.6],
    )


def write(d, ctx) -> None:
    intro(d, ctx)
    decisions(d, ctx)
    coding_and_data(d, ctx)
    algorithms(d, ctx)
    schemes(d, ctx)
    constantine(d, ctx)
    jackson(d, ctx)
    interface(d, ctx)
    environment(d, ctx)
    differences(d, ctx)
