"""Section 7: the refined algorithms (the algorithms of section 7 of the preliminary design, refined step by step)."""

from __future__ import annotations


LIMIT = 84  # characters of a line of pseudocode that fit a code box


def _fit(lines: list) -> list:
    """Keeps the lines of the pseudocode inside the box: a comment that does not fit after its line goes on a line of its
    own above it, and a code line that is still too long is wrapped with a deeper indent."""
    out: list = []
    for line in lines:
        stripped = line.lstrip(' ')
        indent = len(line) - len(stripped)
        code, comment = stripped, ''
        marker = stripped.find('  -- ')
        if marker > 0:
            code, comment = stripped[:marker].rstrip(), stripped[marker:].strip()
        elif stripped.startswith('-- '):
            code, comment = '', stripped
        if comment and not code:
            # A comment that was aligned far to the right: put it under the line it belongs to.
            previous = len(out[-1]) - len(out[-1].lstrip(' ')) if out else 0
            out.append(' ' * (previous + 2 if indent > 20 else indent) + comment)
            continue
        if comment and indent + len(code) + 2 + len(comment) <= LIMIT:
            out.append(' ' * indent + code + '  ' + comment)
            continue
        if comment:
            out.append(' ' * indent + comment)
        current, depth = '', indent
        for word in code.split(' '):
            trial = word if not current else current + ' ' + word
            if current and depth + len(trial) > LIMIT:
                out.append(' ' * depth + current)
                current, depth = word, indent + 4
            else:
                current = trial
        out.append(' ' * depth + current)
    return out


def code(d, lines: list) -> None:
    d.code(_fit(lines))


def _levels(d, ctx, key: str, title: str, level0: str, steps: list) -> None:
    """The table of levels 0 and 1 of an algorithm: the statement and the steps with the module of each step."""
    ctx.table(d, key, title)
    rows = [['0', level0, '']]
    for number, (step, module) in enumerate(steps, 1):
        rows.append([f'1.{number}', step, module])
    d.table(['Step', 'Content', 'Module'], rows, [1.1, 5.0, 4.9], size=22)


def write(d, ctx) -> None:
    d.h1('7 Refined algorithms')

    d.h2('7.1 Method of refinement')
    d.p('The algorithms are refined from the top down, by the method of stepwise refinement. The starting point is the '
        'specification of a process in Section 7 of the preliminary design. It is refined in three levels. '
        'Level 0 states the task of the process in one sentence. Level 1 splits the task into steps and attaches every '
        'step to the module that performs it, so the steps become the calls of the structure charts of Sections 10 and 11. '
        'Level 2 is the structured algorithm of the modules in pseudocode, with the checks, the locks, the limits and '
        'the codes of the errors that the preliminary design left out. Only the three structures of structured '
        'programming are used: sequence, selection and iteration. The errors are the codes of Section 4 (a `FAIL` '
        'statement ends the algorithm with such a code and rolls back the transaction, if one is open).')
    d.p('Nine algorithms are refined, one for each specification of the preliminary design, and the sanitizing of a '
        'chapter, which the preliminary design only mentions, is described separately. The modules are named as in the '
        'code. ' + ctx.ref('tab:alg_list') + ' lists them.')
    ctx.table(d, 'tab:alg_list', 'Refined algorithms and their modules')
    d.table(
        ['Section', 'Algorithm', 'Process', 'Main modules'],
        [
            ['7.2', 'Update of reading progress', '3.0', '`ReadingProgressController`, `UpdateProgress`, `RecordReadingLog`'],
            ['7.3', 'Change of the status of a book', '2.0, 3.0', '`ChangeUserBookStatus`'],
            ['7.4', 'Reading streak, pace, forecast and annual goal', '4.0', '`ReadingStatistics`, `GoalProgress`'],
            ['7.5', 'Import of an EPUB book', '6.0', '`BookTextController`, `ImportBookText`, `EpubImporter`, `EpubArchive`, `PackageReader`, `EpubBook`'],
            ['7.6', 'Sanitizing of a chapter', '6.0', '`SafeXml`, `HtmlSanitizer`, `Text`'],
            ['7.7', 'Saving a quote selected in the text', '5.0, 6.0', '`QuoteController`, `SaveQuote`, `ResolveQuoteAnchor`'],
            ['7.8', 'Reading position and progress', '6.0', '`ReadingPositionController`, `SaveReadingPosition`, `UpdateProgress`'],
            ['7.9', 'Feed', '7.0', '`FeedController`, `VisibleToReaders`'],
            ['7.10', 'Reports and automatic hiding', '8.0', '`ReportController`, `ReportContent`, `ModerateContent`'],
        ],
        [1.7, 4.2, 1.5, 5.6], size=22,
    )

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.2 Update of reading progress')
    d.p('The reader moves the bookmark of a book to a page. The change is written to the reading log, because the log is '
        'the only source of the statistics. Taps arrive in quick succession (the buttons “+1”, “+10”), so the update is '
        'one transaction that locks the row of the book: the second tap starts from the page where the first ended. The '
        'refinement is given in ' + ctx.ref('tab:alg_progress') + ', and the algorithm of level 2 follows it.')
    _levels(d, ctx, 'tab:alg_progress', 'Refinement of the update of reading progress',
            'Move the bookmark of a book of the reader to the page p and write the change into the reading log.',
            [('Open a transaction and lock the row of the book.', '`UpdateProgress::handle`'),
             ('Refuse the update of a finished book.', '`UpdateProgress::handle`'),
             ('If the page differs from the current one, move the bookmark (starting the reading if the book was only wanted or put aside).', '`UpdateProgress::moveTo`, `ChangeUserBookStatus::apply`'),
             ('Write a record of the move into the log (nothing is written when the page did not change).', '`RecordReadingLog::handle`'),
             ('Answer with the book, the pages gained, the mark “reached the end” and the current streak.', '`ReadingProgressController::store`, `ProgressUpdate`, `ReadingStatistics::streak`')])
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
        '  IF e.status <> reading THEN change_status(e, reading)   -- see 7.3',
        '  IF e.total_pages IS NOT NULL AND p > e.total_pages THEN',
        '    FAIL(page: "library.page_above_total")',
        '  e.current_page <- p',
        '  SAVE e',
        '',
        'FUNCTION record_log(e, from, to)                         -- RecordReadingLog::handle',
        '  IF from = to THEN RETURN null',
        '  zone <- e.user.timezone, or the default zone of the system',
        '  RETURN INSERT reading_log(user_id, user_book_id, from_page, to_page, logged_on = today in zone)',
        '',
        'reply: pages <- to - from (or 0 without a log record);',
        '       reached_end <- e.total_pages IS NOT NULL AND e.current_page = e.total_pages;',
        '       streak <- streak(user).current                    -- 7.4',
    ])
    d.p('The refinement made three decisions that the preliminary design left open. The calendar day of a record is the day '
        'in the time zone of the reader, not of the server. A page that is lower than the current one is allowed (it is a '
        'correction) and is recorded with a negative number of pages. The range of the page is checked after the status '
        'is changed, because the change to “reading” already requires the page count of the book to be known.')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.3 Change of the status of a book')
    d.p('A book is in one of four statuses. The allowed transitions are fixed in the method '
        '`BookStatus::allowedTransitions`. The method `ChangeUserBookStatus::apply` changes the status in memory and keeps '
        'the dates and the page consistent, and the caller saves the model. The effects of the transitions are in '
        + ctx.ref('tab:alg_status') + '.')
    ctx.table(d, 'tab:alg_status', 'Transitions of the status of a book and their effects')
    d.table(
        ['From', 'To', 'Effect', 'Error'],
        [
            ['want', 'reading', 'started_at = now; the page count of the book must be known', '`library.total_pages_required`'],
            ['want', 'finished', 'started_at and finished_at are taken from the request (a book read earlier; finished_at = now by default); current_page = total_pages (or 0 if unknown)', ''],
            ['reading', 'finished', 'finished_at = now; current_page = total_pages (when known)', ''],
            ['reading', 'abandoned', 'only the status changes', ''],
            ['abandoned', 'reading', 'finished_at = null; the reader continues from the page where they stopped', ''],
            ['finished', 'reading', 'finished_at = null (a correction)', ''],
            ['any other pair', '', 'none', '`library.invalid_transition`'],
        ],
        [2.0, 2.0, 5.0, 2.8], size=22,
    )
    code(d, [
        'PROCEDURE change_status(e, target, started_at = null, finished_at = null)   -- ChangeUserBookStatus::apply',
        '  cur <- e.status',
        '  IF cur = target THEN RETURN',
        '  IF target NOT IN allowed_transitions(cur) THEN FAIL(status: "library.invalid_transition")',
        '  SELECT',
        '    CASE cur = want AND target = reading:',
        '      IF e.total_pages IS EMPTY THEN FAIL(total_pages: "library.total_pages_required")',
        '      e.started_at <- now',
        '    CASE cur = want AND target = finished:',
        '      e.started_at <- started_at; e.finished_at <- finished_at OR now',
        '      e.current_page <- e.total_pages OR 0',
        '    CASE target = finished:',
        '      e.finished_at <- now; e.current_page <- e.total_pages OR e.current_page',
        '    CASE target = reading:',
        '      e.finished_at <- null',
        '  e.status <- target',
    ])

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.4 Reading streak, pace, forecast and annual goal')
    d.p('All the figures are calculated from the reading log in the calendar of the reader. The module `ReadingStatistics` '
        'works for one reader; `GoalProgress` uses it for the annual goal. The calendar days are compared as text '
        '(YYYY-MM-DD) and not as moments, because between two moments in the reader’s time zone a day can be 23 or '
        '25 hours long. The columns of the pages are unsigned, so they are cast to a signed type before they are '
        'subtracted. The refinement is in ' + ctx.ref('tab:alg_stats') + '.')
    _levels(d, ctx, 'tab:alg_stats', 'Refinement of the calculation of the statistics',
            'Calculate from the reading log the pages per day, the streak, the pace, the forecast of finishing the books being read and the status of the annual goal.',
            [('Find the current day of the reader.', '`ReadingStatistics::today`'),
             ('Sum the net pages of every day of the range; a day never counts below zero.', '`ReadingStatistics::dailyPages`, `pagesBetween`'),
             ('Find the days with a positive sum, find the longest run of consecutive days, then count the current run back from today (or yesterday).', '`ReadingStatistics::streak`'),
             ('Divide the pages of the last 14 days (today included) by 14.', '`ReadingStatistics::pace`'),
             ('For each book being read choose its own pace or the common one and calculate the days left.', '`ReadingStatistics::forecasts`'),
             ('Count the books finished in the year and compare them with the part of the goal that is due by today.', '`GoalProgress::handle`, `ReadingStatistics::booksFinished`')])
    code(d, [
        'FUNCTION streak(user)                                    -- ReadingStatistics::streak',
        '  days <- the dates logged_on of user\'s log, grouped by date, with SUM(to_page - from_page) > 0, ascending',
        '  longest <- 0; run <- 0; previous <- null',
        '  FOR EACH day IN days',
        '    IF previous <> null AND previous + 1 day = day THEN run <- run + 1 ELSE run <- 1',
        '    longest <- MAX(longest, run); previous <- day',
        '  today <- today(user.timezone)',
        '  cursor <- IF today IN days THEN today ELSE today - 1 day    -- a streak that ended yesterday is alive',
        '  current <- 0',
        '  WHILE cursor IN days DO current <- current + 1; cursor <- cursor - 1 day',
        '  RETURN (current, longest, read_today = today IN days)',
        '',
        'FUNCTION forecast(book, overall_pace, today)             -- ReadingStatistics::forecasts, one book',
        '  days <- the days of the last 14 days on which the net pages of the book are > 0',
        '  pace <- overall_pace',
        '  IF COUNT(days) >= 3 THEN',
        '    since <- MAX(date of book.started_at in the reader\'s zone, today - 13 days)',
        '    pace <- SUM(pages of days) / (days_between(since, today) + 1)',
        '  remaining <- book.total_pages - book.current_page',
        '  left <- IF remaining <= 0 THEN 0 ELSE IF pace > 0 THEN CEIL(remaining / pace) ELSE unknown',
        '  IF left > 730 THEN left <- unknown                       -- further than two years is noise',
        '  RETURN (pace rounded to 0.1, left, today + left days)',
        '',
        'FUNCTION goal_progress(user, year)                       -- GoalProgress::handle',
        '  finished <- books of user with status finished and finished_at in the year (reader\'s calendar)',
        '  target <- the goal of the user for the year; IF none THEN RETURN (finished, all else unknown)',
        '  IF year = this year THEN due <- INT(target * day_of_year / days_in_year)',
        '  ELSE IF year < this year THEN due <- target ELSE due <- 0',
        '  RETURN (finished, due, remaining = MAX(0, target - finished),',
        '          on_track = IF year > this year THEN unknown ELSE finished >= due)',
    ])
    d.p('The goal is compared with whole books: being 0.02 of a book short on the second day of the year does not mean '
        'being behind, so `due` is rounded down.')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.5 Import of an EPUB book')
    d.p('The uploaded file belongs to a stranger, so it is checked before it is read, and it is read chapter by chapter, '
        'each chapter stored before the next is parsed, so the memory does not grow with the size of the book. Only one '
        'book is read at a time on the whole server (a lock of the cache, waited for 5 seconds), because reading a hostile '
        'book can take hundreds of megabytes, and the server has 1 GB of memory. The import is one transaction: either the '
        'whole book is stored or the book keeps its previous text. The limits are those of `EpubLimits` (file 15 MB, '
        '60 MB unpacked, 2000 entries, 10 MB of an XML document, 500 chapters, 2 MB of the HTML of a chapter, '
        '8 million characters of a text, 30 texts and 40 million characters per reader). The refinement is in '
        + ctx.ref('tab:alg_import') + '.')
    _levels(d, ctx, 'tab:alg_import', 'Refinement of the import of an EPUB book',
            'Turn the uploaded EPUB file into the sanitized text of the chapters of a library book, replacing the text it had.',
            [('Check the request: the book belongs to the reader (otherwise “not found”), the file is present and not larger than 15 MB; whether it is an EPUB is decided from its content, not from its name.', '`StoreBookTextRequest`, `UserBookPolicy`'),
             ('Take the lock of the import or answer “busy”.', '`ImportBookText::handle`'),
             ('Check the archive: size, ZIP format, number and names of entries, encryption, unpacked size, “mimetype”.', '`EpubArchive::open`'),
             ('Refuse a book with DRM.', '`EpubImporter::assertNotEncrypted`'),
             ('Read the package: the root file, the manifest, the order of the documents (spine), the titles.', '`PackageReader::read`'),
             ('Delete the previous text and check the quota of the reader.', '`DeleteBookText::handle`, `ImportBookText::import`'),
             ('For every document of the spine: read it, parse it, sanitize it, skip it if it has no text, store the chapter.', '`EpubBook::chapters`, `SafeXml::parse`, `HtmlSanitizer::sanitize`'),
             ('Save the counters of the text, release the lock, answer 201.', '`ImportBookText::import`, `BookTextResource`')])
    code(d, [
        'FUNCTION import_book(entry, file)                        -- ImportBookText::handle and ::import',
        '  GET LOCK "epub-import" (lifetime 120 s), waiting 5 s; IF not taken THEN FAIL(503, "reader.busy")',
        '  book <- open_epub(file)                                 -- may FAIL with a reader.* code',
        '  BEGIN TRANSACTION',
        '    lock the row of entry FOR UPDATE',
        '    delete_text(entry)                                    -- the anchors of its quotes are cleared',
        '    IF COUNT(texts of the reader) >= 30 THEN FAIL("reader.quota_exceeded")',
        '    kept <- SUM(characters of the other texts of the reader)',
        '    text <- INSERT book_text(entry, user, source_name, 0 chapters, 0 characters)',
        '    n <- 0; characters <- 0',
        '    FOR EACH chapter IN book.chapters()                   -- a generator: one chapter in memory',
        '      INSERT book_chapter(text, chapter.position, chapter.title, chapter.html, chapter.characters)',
        '      n <- n + 1; characters <- characters + chapter.characters',
        '      IF kept + characters > 40 000 000 THEN FAIL("reader.quota_exceeded")',
        '    IF n = 0 THEN FAIL("reader.empty")',
        '    UPDATE text SET chapters_count = n, characters_count = characters',
        '  COMMIT',
        '  close the archive; RELEASE LOCK',
        '  RETURN text',
        '',
        'FUNCTION open_epub(file)                                 -- EpubImporter::open, EpubArchive::open',
        '  IF size(file) < 22 THEN FAIL("reader.not_epub")',
        '  IF size(file) > 15 MB THEN FAIL("reader.too_large")',
        '  zip <- open file read-only; IF not a ZIP THEN FAIL("reader.not_epub")',
        '  IF zip.entries > 2000 THEN FAIL("reader.too_many_entries")',
        '  total <- 0',
        '  FOR EACH entry IN zip',
        '    IF the name is absolute, has ".." or a drive THEN FAIL("reader.unsafe_archive")',
        '    IF the entry is encrypted THEN FAIL("reader.drm")',
        '    total <- total + entry.size; IF total > 60 MB THEN FAIL("reader.too_large")',
        '  IF entry "mimetype" <> "application/epub+zip" THEN FAIL("reader.not_epub")',
        '  IF "META-INF/encryption.xml" exists THEN',
        '    FOR EACH method IN it: IF algorithm NOT IN (font obfuscation of IDPF, of Adobe) THEN FAIL("reader.drm")',
        '  package <- read_package(zip)',
        '  RETURN (zip, package)',
        '',
        'FUNCTION read_package(zip)                               -- PackageReader::read',
        '  root <- the "full-path" of the first rootfile of "META-INF/container.xml" that exists in the archive',
        '  IF none THEN FAIL("reader.invalid")',
        '  opf <- parse(root)                                      -- through SafeXml',
        '  manifest <- items of the manifest (id -> path, type, properties), at most 5000',
        '  spine <- the itemrefs of the spine, in order, that are documents, without repeats (at most 5000)',
        '  titles <- the titles of the table of contents (navigation document or NCX), at most 5000 entries',
        '  RETURN (spine, titles)',
        '',
        'GENERATOR chapters(book)                                 -- EpubBook::chapters',
        '  position <- 0; characters <- 0',
        '  FOR EACH path IN book.spine',
        '    xml <- read(path, at most 10 MB, else "reader.chapter_too_large")',
        '    doc <- parse_safely(xml)                              -- 7.6; null when nothing can be read',
        '    IF doc = null THEN CONTINUE',
        '    chapter <- sanitize(doc)                              -- 7.6',
        '    IF chapter.characters = 0 THEN CONTINUE               -- a cover, a page of pictures',
        '    IF position >= 500 THEN FAIL("reader.too_many_chapters")',
        '    characters <- characters + chapter.characters',
        '    IF characters > 8 000 000 THEN FAIL("reader.text_too_large")',
        '    YIELD (position, title(path) OR chapter.heading, chapter.html, chapter.characters)',
        '    position <- position + 1',
    ])
    d.p('Two decisions are worth noting. First, a document of the spine that is missing, is not XML or has no text is '
        'skipped and does not fail the import: real books contain such documents. Second, the lock is released in '
        'a `finally` block, and its lifetime (120 seconds) is longer than any import can take, so a process that died does '
        'not block the others for ever.')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.6 Sanitizing of a chapter')
    d.p('A chapter is not “cleaned”: a new tree is built from the parsed one, and only the elements of an allow-list '
        'are copied, without a single attribute. What is not copied cannot be harmful. This makes the HTML safe to '
        'insert into a page, and it makes the offsets of the quotes stable, because the text of the chapter is exactly '
        'the text of the stored HTML without its tags. The refinement is in ' + ctx.ref('tab:alg_sanitize') + '.')
    _levels(d, ctx, 'tab:alg_sanitize', 'Refinement of the parsing and sanitizing of a chapter',
            'Turn an XHTML document of a book into a safe HTML fragment with its text length and its first heading.',
            [('Prepare the XML text: convert it to UTF-8 (from a short list of accepted encodings), refuse NUL bytes, entity declarations and a DOCTYPE with an internal subset, remove the DOCTYPE, replace the named entities.', '`SafeXml::prepare`'),
             ('Parse with no network, no external entities and no entity substitution, recovering what can be read of a document that is not quite well-formed.', '`SafeXml::parse`'),
             ('Walk the tree from the root and copy it into a new tree: drop the elements of the drop list and of foreign namespaces with their content, rename the elements of the allow-list, unwrap the others (only their text is kept).', '`HtmlSanitizer::copyElement`, `copyChildren`'),
             ('Collapse the white space outside of `pre`, mend the structure of tables (rows and cells), remove empty elements.', '`HtmlSanitizer::finish`, `flattenCells`'),
             ('Write the new tree as HTML with the text escaped, check its size, count the characters of its text, find the first heading.', '`HtmlSanitizer::write`, `Text::length`, `Text::ofHtml`')])
    code(d, [
        'FUNCTION sanitize(doc)                                   -- HtmlSanitizer::sanitize',
        '  root <- new element; stack <- [root]; elements <- 0',
        '  copy_element(doc.documentElement, depth = 0)',
        '  finish(root); flatten_cells(root)',
        '  html <- write(root)',
        '  IF size(html) > 2 MB THEN FAIL("reader.chapter_too_large")',
        '  RETURN (html, length_utf16(text_of(html)), first_heading(root))',
        '',
        'PROCEDURE copy_element(e, depth)',
        '  elements <- elements + 1; IF elements > 250 000 OR depth > 300 THEN FAIL("reader.chapter_too_large")',
        '  IF e.name IN drop_list OR e.namespace IN (SVG, MathML) THEN RETURN      -- with everything inside',
        '  tag <- allow_list[e.name]                                 -- for example section -> div, cite -> em',
        '  IF tag exists AND depth of output < 32 THEN',
        '    open a new element `tag` WITHOUT ATTRIBUTES in the new tree',
        '  FOR EACH child IN e.children',
        '    IF child is text THEN add_text(child.text)               -- white space collapsed except inside pre',
        '    ELSE IF child is an element THEN copy_element(child, depth + 1)',
        '                                                             -- comments and instructions are ignored',
        '  IF an element was opened THEN close it',
    ])

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.7 Saving a quote selected in the text')
    d.p('The reader selects a fragment in the reading screen and saves it as a quote. The browser sends the text of the '
        'quote and the anchor: the number of the chapter and the start and the end of the fragment in the text of the '
        'chapter, counted in UTF-16 code units (the unit of a JavaScript string index, so the browser and the server '
        'count the same). The server does not trust the anchor: it takes the fragment out of the stored chapter and '
        'compares it with the text of the quote, white space aside, because a browser puts a line break between two '
        'paragraphs where the text of the chapter has none. The refinement is in ' + ctx.ref('tab:alg_quote') + '.')
    _levels(d, ctx, 'tab:alg_quote', 'Refinement of saving a quote selected in the text',
            'Save a fragment selected in the text of a book as a quote of the reader, anchored to its place.',
            [('Validate the fields of the quote and of the anchor; check that the book belongs to the reader.', '`StoreQuoteRequest`, `UserBookPolicy`'),
             ('Check that the page of the quote does not exceed the page count of the book.', '`SaveQuote::assertPageFitsBook`'),
             ('If an anchor is given, check it against the stored chapter.', '`ResolveQuoteAnchor::handle`'),
             ('Create the quote with the columns of the anchor.', '`SaveQuote::create`'),
             ('Answer 201 with the quote.', '`QuoteController::store`, `QuoteResource`')])
    code(d, [
        'FUNCTION save_quote(entry, data)                         -- SaveQuote::create',
        '  IF data.page IS NOT NULL AND entry.total_pages IS NOT NULL AND data.page > entry.total_pages THEN',
        '    FAIL(page: "quotes.page_above_total")',
        '  IF data.anchor IS NOT NULL THEN data <- data + resolve_anchor(entry, data.anchor, data.content)',
        '  RETURN INSERT quote(data, user_id = entry.user_id)',
        '',
        'FUNCTION resolve_anchor(entry, a, content)               -- ResolveQuoteAnchor::handle',
        '  text <- entry.book_text; IF text IS NULL THEN FAIL(anchor: "reader.no_text")',
        '  chapter <- the chapter of text with position = a.chapter',
        '  IF chapter IS NULL OR a.end <= a.start OR a.end > chapter.characters OR a.end - a.start > 10 000 THEN',
        '    FAIL(anchor: "reader.anchor_out_of_range")',
        '  selected <- slice(text_of(chapter.content), a.start, a.end - a.start)  -- null if it cuts a character in two',
        '  skeleton <- IF selected IS NULL THEN "" ELSE remove_white_space(selected)',
        '  IF skeleton = "" OR skeleton <> remove_white_space(content) THEN FAIL(anchor: "reader.anchor_mismatch")',
        '  RETURN (book_text_id = text.id, chapter_position = a.chapter, start_offset = a.start, end_offset = a.end)',
    ])

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.8 Reading position and progress')
    d.p('While the reader reads, the client sends the place in the book from time to time (at most 120 times a minute, '
        'a limit of the route). The server remembers the place, calculates how far into the whole text it is, and raises '
        'the page of the library book if the reader got further than it says. It never lowers the page and never touches '
        'a finished book. The page goes through `UpdateProgress`, so the log, the streak and the statistics keep working. '
        'The refinement is in ' + ctx.ref('tab:alg_position') + '.')
    _levels(d, ctx, 'tab:alg_position', 'Refinement of saving the reading position',
            'Remember the place of the reader in a text and move the page of the book forward when the place is further.',
            [('Validate the chapter and the part of the chapter (from 0 to 1).', '`SaveReadingPositionRequest`'),
             ('Open a transaction and lock the row of the library book (the same lock as the progress update takes).', '`SaveReadingPosition::handle`'),
             ('Calculate the progress in the whole text.', '`SaveReadingPosition::progressAt`'),
             ('Save the position, the progress and the time.', '`SaveReadingPosition::handle`'),
             ('Raise the page if the calculated page is higher than the current one.', '`SaveReadingPosition::raisePage`, `UpdateProgress::handle`')])
    code(d, [
        'FUNCTION save_position(text, chapter, part)              -- SaveReadingPosition::handle',
        '  BEGIN TRANSACTION',
        '    e <- SELECT user_book WHERE id = text.user_book_id FOR UPDATE',
        '    progress <- progress_at(text, chapter, part)',
        '    UPDATE text SET chapter_position = chapter, chapter_progress = part, progress = progress, position_updated_at = now',
        '    e <- raise_page(e, progress)',
        '  COMMIT',
        '  RETURN (text, e)',
        '',
        'FUNCTION progress_at(text, chapter, part)',
        '  characters <- the characters of the chapter with this position; IF none THEN FAIL(chapter: "reader.chapter_out_of_range")',
        '  IF text.characters_count < 1 THEN RETURN 0',
        '  before <- SUM(characters) of the chapters with a lower position',
        '  RETURN MIN(1, MAX(0, (before + part * characters) / text.characters_count))',
        '',
        'FUNCTION raise_page(e, progress)',
        '  IF e.status = finished OR e.total_pages IS EMPTY THEN RETURN e',
        '  page <- ROUND(progress * e.total_pages)',
        '  IF page <= e.current_page THEN RETURN e',
        '  RETURN update_progress(e, page).book                    -- 7.2',
    ])

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.9 Feed')
    d.p('The feed shows the posts that the reader may see. The tab “Following” has the posts of the readers that the '
        'reader follows and their own; the tab “All” has all posts. A post is visible if it is not hidden (or if the viewer '
        'is its author) and its author is not blocked by the viewer or does not block the viewer. The posts are newest '
        'first, twenty on a page, with the like of the viewer and the number of comments the viewer may see. The refinement is '
        'in ' + ctx.ref('tab:alg_feed') + '.')
    _levels(d, ctx, 'tab:alg_feed', 'Refinement of the feed',
            'Give the reader a page of posts of the followed readers (or of all readers) that the reader may see.',
            [('Validate the scope (“following” by default, or “all”).', '`FeedController::__invoke`'),
             ('Keep the posts that are not hidden or are the viewer’s own, and drop the posts of blocked readers in both directions.', '`VisibleToReaders::scopeVisibleTo`, `User::excludeBlocked`'),
             ('For the scope “following” keep only the viewer’s posts and the posts of the followed readers.', '`FeedController::__invoke`'),
             ('Add the viewer’s state (liked or not, the number of comments the viewer may see), order by the newest, take a page of 20.', '`Post::scopeWithViewerState`, `simplePaginate`')])
    code(d, [
        'FUNCTION feed(viewer, scope = "following", page)         -- FeedController::__invoke',
        '  posts <- all posts',
        '  KEEP posts WHERE hidden_at IS NULL OR author = viewer',
        '  REMOVE posts WHERE author blocked viewer OR viewer blocked author',
        '  IF scope = "following" THEN KEEP posts WHERE author = viewer OR author IN followed_by(viewer)',
        '  ADD to each post: liked_by_me, the number of comments visible to the viewer',
        '  ORDER BY id DESC',
        '  RETURN page of 20 posts, with a link to the next page if there is one',
    ])

    # ------------------------------------------------------------------------------------------------------------
    d.h2('7.10 Reports and automatic hiding')
    d.p('A reader can report a post or a comment of another reader. The third open report from different readers hides '
        'the content from everybody except its author, and a moderator decides what happens next with Artisan commands. '
        'The reports of one object go through the lock of its row one at a time, so the count always includes the '
        'report that came before, and the third report always hides. A unique index allows one report of an object per '
        'reader, so the number of open reports is the number of readers. The refinement is in '
        + ctx.ref('tab:alg_report') + '.')
    _levels(d, ctx, 'tab:alg_report', 'Refinement of the report and the automatic hiding',
            'Record a report on a post or a comment and hide the content when three different readers have reported it.',
            [('Check that the reader may see the content (otherwise “not found”); validate the reason (one of the allowed reasons) and the note (up to 500 characters); apply the rate limit (20 reports an hour).', '`StoreReportRequest`, the limiter `reports`'),
             ('Refuse a report on the reader’s own content and a second report of the same reader.', '`ReportContent::handle`'),
             ('Lock the row of the content, record the report, count the open reports.', '`ReportContent::handle`'),
             ('At the threshold (3) hide the content; for a comment correct the counter of comments of the post.', '`ModerateContent::hide`'),
             ('The moderator reviews the reports, hides, restores or resolves.', '`ModerationReports`, `ModerationHide`, `ModerationRestore`, `ModerationResolve`')])
    code(d, [
        'FUNCTION report(reporter, content, reason, note)         -- ReportContent::handle',
        '  IF content.author = reporter THEN FAIL(reason: "social.cannot_report_own")',
        '  IF a report of reporter on content exists THEN FAIL(reason: "social.already_reported")',
        '  BEGIN TRANSACTION',
        '    lock the row of content FOR UPDATE',
        '    report <- INSERT report(reporter, content, reason, note, status = open)',
        '                                       -- a duplicate by the unique index: FAIL("social.already_reported")',
        '    open <- COUNT(reports of content with status = open)',
        '    IF open >= 3 THEN hide(content)',
        '  COMMIT',
        '  RETURN report',
        '',
        'FUNCTION hide(content)                                   -- ModerateContent::hide',
        '  BEGIN TRANSACTION',
        '    changed <- UPDATE content SET hidden_at = now WHERE id = content.id AND hidden_at IS NULL',
        '    IF changed > 0 AND content is a comment THEN decrement comments_count of its post (not below 0)',
        '  COMMIT',
        '  RETURN changed > 0',
    ])
    d.p('The refined algorithms are the basis of the structure charts: every module named in the tables above is a vertex '
        'of a chart in Section 10, and the loops and the selections of the pseudocode are the iteration and the selection '
        'of the charts of Section 11.')
