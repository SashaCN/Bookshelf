"""Section 11: structure charts of Jackson (the figures are drawn by docs/lab3/diagrams/jackson.py)."""

from __future__ import annotations

CODE_SIZE = 19  # half-points: the pseudocode lines are up to 76 characters, the width of the page


def _map_table(d, ctx, key: str, title: str, rows: list, shade: set | None = None) -> None:
    """The table of the correspondence between the components of the data and of the program."""
    ctx.table(d, key, title)
    d.table(['Component of the data', 'Component of the program', 'Operations, condition and code'], rows,
            [3.0, 3.4, 6.4], size=22, shade=shade)


def write(d, ctx) -> None:
    d.h1('11 Structure charts of Jackson')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('11.1 The method in this project')
    d.p('The structure charts of Constantine (Section 10) show how the modules call each other. A structure chart of '
        'Jackson goes one level lower. It shows the inside of one module, its algorithm, as a tree. The method rests on '
        'one rule: **the structure of a program follows the structure of the data that the program reads and writes.** '
        'A program that reads a list of days has a loop over the days. A program whose result is one of three answers '
        'has a choice of three branches.')
    d.p('Every chart of this section was made in the steps of the method of Jackson, in a short form:')
    d.numbered([
        'draw the structure of the input data and of the output data, each as a tree;',
        'join the two trees: the components of the input and of the output that correspond to each other (one chapter '
        'is made from one document) give one component of the program;',
        'put the operations of the program into the components that they belong to, and write the condition of every '
        'selection and of every iteration;',
        'write the program text in structured pseudocode with the same constructs, so that the text and the chart say '
        'the same.',
    ])
    d.p('A chart is built of four components: an operation, a sequence, a selection and an iteration ('
        + ctx.ref('fig:jk_legend') + '). A sequence has no mark and is read from left to right. As in the methodology, '
        'the alternatives of a selection are marked with a small circle (o) and the repeated box of an iteration with an '
        'asterisk (*). The same notation draws the data: a data component is a box, its parts are in sequence, its '
        'alternatives are marked “o” and a repeated part is marked “*”.')
    ctx.figure(d, 'fig:jk_legend', 'jk_legend.png',
               'Components of the structure charts of Jackson and the conventions of this document')
    d.p('The real code has limits, errors and calls, which the four components do not cover. These conventions are '
        'added, and they are the same in all the charts:')
    d.bullets([
        'An alternative that ends the program with an error is filled with light red, and its text starts with “Stop”. '
        'In the code it is an exception (`ValidationException`, `EpubImportException`).',
        'An alternative that does nothing is the box “nothing” or “skip”.',
        'A box with a line on each side is a procedure block of the methodology: a procedure that is drawn as a chart of '
        'its own, or is written under the chart.',
        'A chart that would be too wide is split. The sub-tree is drawn as a separate chart, the usual device of '
        'Jackson, and its root has the name of the box that it replaces.',
        'A check that is made once, before or after the main loop, and only stops the program (for example the number of '
        'texts of a reader) belongs to the operation next to it. The table of the program lists it with the code of '
        'its error. A check inside a loop, a check that gives an alternative result and every choice that depends on '
        'the data are drawn. The errors that a called module raises inside an operation (the limits of the archive, '
        'the checks of the XML) are listed in the table and are not drawn.',
        'The boxes are named in plain words, and the table of every program gives the names of the code. The title of a '
        'chart names the method (`Class::method`) that it describes.',
    ])
    d.p('Three programs are drawn (' + ctx.ref('tab:jk_programs') + '). They refine the specifications of the preliminary '
        'design for the processes 6.0, 4.0 and 8.0 and the algorithms of Sections 7.5, 7.4 and 7.10. They are chosen '
        'because their algorithms are made of loops and choices, which a chart of Jackson shows well.')
    ctx.table(d, 'tab:jk_programs', 'Programs drawn as charts of Jackson')
    d.table(
        ['No.', 'Program', 'Methods', 'What the chart shows'],
        [
            ['1', 'Reading the chapters of an EPUB book (11.2)',
             '`EpubBook::chapters`, `ImportBookText::import`',
             'An iteration over the documents of the spine; a selection of three kinds of document; the limits that '
             'stop the program inside the loop.'],
            ['2', 'Counting the reading streak (11.3)', '`ReadingStatistics::streak`',
             'Two iterations over the same days, one forward and one backward; a selection inside each of them.'],
            ['3', 'Report and automatic hiding (11.4)',
             '`ReportContent::handle`, `ModerateContent::hide`, `ModerationReports::handle`',
             'A chain of selections that follows the alternative results; an iteration over the open reports.'],
        ],
        [0.7, 3.6, 4.0, 5.2], size=22,
    )

    # ------------------------------------------------------------------------------------------------------------
    d.h2('11.2 Reading the chapters of an EPUB book')
    d.p('This program is the core of the import of a book (process 6.0, Section 7.5). It turns the spine of an uploaded '
        'EPUB file into the stored text. It is made of two programs that work in turns. `EpubBook::chapters` is a '
        'generator: it produces one chapter and waits. The loop in `ImportBookText::import` stores that chapter and asks '
        'for the next one. Only one chapter exists at a time, so a book is never in memory as a whole. The stream of '
        '`ParsedChapter` objects joins the two programs. In the method of Jackson two programs joined by a stream are the '
        'usual answer to a clash of the structures of the data; here the reason is memory, and each program has its own '
        'chart.')
    d.p('The data are drawn in the panels (a) and (b) of ' + ctx.ref('fig:jk_import') + '. The input is the spine of '
        'the book: the documents of the package in reading order, each of them once (`EpubPackage::spine`). A document is '
        'of one of three kinds: it is missing from the archive or is not XML; it is XML without text (a cover, a page of '
        'pictures); it is XML with text. The output is the stored text: one `BookText` row and the `BookChapter` rows in '
        'reading order. Only a document of the third kind gives a chapter. This correspondence fixes the structure: both '
        'programs are loops, the first over the documents and the second over the chapters, and the choice of the kind '
        'of a document is inside the first loop.')
    ctx.figure(d, 'fig:jk_import', 'jk_import.png',
               'Reading the chapters of an EPUB book: data (a, b) and charts of Jackson (c, d, e)')
    d.bullets([
        'Chart (c) is `EpubBook::chapters`. For every document of the spine it reads and cleans up the document and then '
        'chooses: skip a document that is missing or is not XML, skip a document that has no text, or hand over the '
        'chapter.',
        'Chart (d) is the procedure “Hand over the chapter”. The chapter passes the limits of `EpubLimits`: at most '
        '500 chapters and at most 8 000 000 characters in the text (`maxChapters`, `maxTextCharacters`). A limit that is '
        'passed stops the program. Otherwise the chapter is yielded to the caller and the position grows by one.',
        'Chart (e) is `ImportBookText::import`. It opens the book, prepares the text, takes the chapters one by one from '
        'the generator and finishes the text. In the loop every chapter is inserted and counted, and the quota of the '
        'reader is checked: at most 40 000 000 characters in all of the texts of the reader (`maxReaderCharacters`).',
        'Two operations of chart (e) contain a check that is made once and only stops the program: “Prepare the text” '
        '(a reader keeps at most 30 texts) and “Finish the text” (a book without chapters is refused). They are listed in '
        + ctx.ref('tab:jk_import_map') + ' and are not drawn as selections.',
    ])
    d.p('The program texts below match the charts line by line. `Stop(code)` ends the program with an error, and the '
        'transaction of the import is rolled back.')
    d.code([
        'Program EpubBook::chapters                                    -- chart (c)',
        '  position := 0',
        '  characters := 0',
        '  Loop-while the spine has a next document',
        '    path := the next document of the spine',
        '    chapter := read, parse and clean up the document path',
        '    If chapter = none Then',
        '      skip                          -- no entry in the archive, or not XML',
        '    Else If chapter.characters = 0 Then',
        '      skip                          -- a cover, a page of pictures',
        '    Else',
        '      Hand over the chapter',
        '    End-if',
        '  End-loop',
        'End-program',
        '',
        'Procedure Hand over the chapter                               -- chart (d)',
        '  If position >= limits.maxChapters Then',
        '    Stop("reader.too_many_chapters")',
        '  Else If characters + chapter.characters > limits.maxTextCharacters Then',
        '    Stop("reader.text_too_large")',
        '  Else',
        '    characters := characters + chapter.characters',
        '  End-if',
        '  Yield ParsedChapter(position, title, chapter.html, chapter.characters)',
        '  position := position + 1',
        'End-procedure',
        '',
        'Program ImportBookText::import                                -- chart (e)',
        '  Open the book                 -- may stop: the error of the file',
        '  Prepare the text              -- may stop: "reader.quota_exceeded"',
        '  chapters := 0',
        '  characters := 0',
        '  Loop-while EpubBook::chapters() gives a chapter',
        '    Insert the BookChapter row of the chapter',
        '    chapters := chapters + 1',
        '    characters := characters + chapter.characters',
        '    If kept + characters > limits.maxReaderCharacters Then',
        '      Stop("reader.quota_exceeded")',
        '    Else',
        '      nothing',
        '    End-if',
        '  End-loop',
        '  Finish the text               -- may stop: "reader.empty" (no chapters)',
        'End-program',
    ], size=CODE_SIZE)
    d.p('The components of the data and of the program correspond as shown in ' + ctx.ref('tab:jk_import_map') + '. The '
        'rows follow the charts (c), (d) and (e).')
    _map_table(d, ctx, 'tab:jk_import_map', 'Components of the data and of the program: reading the chapters', [
        ['Spine of the book (`EpubPackage::spine`)', '`Produce the chapters of a book`',
         'Sets `position` and `characters` to 0. `EpubBook::chapters`.'],
        ['Document, each item of the spine *', '`Document of the spine` *',
         'Condition: the spine has a next document (`foreach ($this->package->spine as $path)`).'],
        ['Document of any kind', '`Read and clean up the document`',
         '`EpubArchive::read` (at most `maxDocumentBytes`, 10 MB), `SafeXml::parse` and, when an XML document came out, '
         '`HtmlSanitizer::sanitize`. The errors of these modules (`reader.chapter_too_large`, `reader.too_large`, '
         '`reader.invalid`) stop the program and are not drawn.'],
        ['Missing or not XML', '`No document: skip`',
         'Condition: `$document === null` (no entry in the archive, or `SafeXml::parse` could read nothing). Nothing is '
         'produced.'],
        ['XML without text', '`No text: skip`', 'Condition: `$chapter->characters === 0`. Nothing is produced.'],
        ['XML with text', '`Chapter: hand over`', 'The procedure of chart (d).'],
        ['The text as a whole', '`Check the limits`',
         'First alternative: `$position >= $this->limits->maxChapters` (500), error `reader.too_many_chapters`. Second: '
         '`$characters` with this chapter is more than `maxTextCharacters` (8 000 000), error `reader.text_too_large`. '
         'Otherwise `$characters += $chapter->characters`.'],
        ['Chapter of the stream (`ParsedChapter`)', '`Yield the chapter`, `Move to the next position`',
         '`yield new ParsedChapter($position, title, html, characters)`. The title is the one of the table of contents '
         '(`$this->package->titles[$path]`) or, when there is none, the first heading of the chapter. Then `$position++`.'],
        ['Stored text', '`Import the text of a book`',
         '`ImportBookText::import`, inside one transaction. It is called by `ImportBookText::handle` under the lock '
         '`epub-import` of the cache.'],
        ['Uploaded file', '`Open the book`',
         '`EpubImporter::open` checks the archive, the encryption and the package. Errors: `reader.not_epub`, '
         '`reader.too_large`, `reader.too_many_entries`, `reader.unsafe_archive`, `reader.drm`, `reader.invalid`.'],
        ['`BookText` row', '`Prepare the text`, `Finish the text`',
         'Prepare: lock of the library entry (`lockForUpdate`), `DeleteBookText::handle`, the check of the number of '
         'texts of the reader (`maxReaderTexts`, 30; error `reader.quota_exceeded`), the characters of the other texts '
         '(`$kept`), the row with 0 chapters. Finish: `$chapters === 0` gives `reader.empty`; otherwise `chapters_count` '
         'and `characters_count` are saved.'],
        ['`BookChapter` *', '`Chapter` *', 'Condition: the generator `EpubBook::chapters()` gives a chapter (`foreach`).'],
        ['`BookChapter` row', '`Insert the chapter row`',
         '`BookChapter::query()->insert([...])`, a plain insert, so that a model does not keep the chapter in memory.'],
        ['', '`Count the chapter`', '`$chapters++`, `$characters += $chapter->characters`.'],
        ['Quota of the reader', '`Check the quota`', 'Condition: `$kept + $characters > $this->limits->maxReaderCharacters` '
         '(40 000 000), error `reader.quota_exceeded`; otherwise nothing.'],
    ])
    d.p('The code is in `backend/app/Services/Epub/EpubBook.php` (`EpubBook::chapters`) and in '
        '`backend/app/Actions/Reader/ImportBookText.php` (`ImportBookText::import`, called by `ImportBookText::handle`). '
        'The structure chart of Constantine of the same import is ' + ctx.ref('fig:cc_import') + '.')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('11.3 Counting the reading streak')
    d.p('The reading streak is the number of days in a row on which the reader read (process 4.0, Section 7.4). '
        '`ReadingStatistics::streak` returns three values: `current`, `longest` and `read_today`. `StatsController` '
        'returns all three in the statistics, and `ReadingProgressController` uses `current` in the answer to a '
        'progress update.')
    d.p('The data are drawn in the panels (a) and (b) of ' + ctx.ref('fig:jk_streak') + '. The input is the list of the '
        'reading days and the current day of the reader. A reading day is a date `logged_on` of the reading log on which '
        'the net number of pages is above zero; the dates come in ascending order. The current day is taken in the time '
        'zone of the reader (`ReadingStatistics::today`). The output is the three values.')
    d.p('The input has no runs in it: it is a plain list of days. A run of consecutive days is found only by comparing '
        'every day with the previous one, so the first loop has a selection inside it. The current streak is at the end '
        'of the list and depends on today, so it is counted by a second loop. The loop walks back from today, or from '
        'yesterday when today is not a reading day (a streak that ended yesterday is still alive), while the day is a '
        'reading day.')
    ctx.figure(d, 'fig:jk_streak', 'jk_streak.png',
               'Counting the reading streak: data (a, b) and charts of Jackson (c, d, e)')
    d.bullets([
        'Chart (c) is `ReadingStatistics::streak`: get the reading days, find the longest run, find the current streak '
        'and return the three values. The two middle boxes are procedures, drawn in (d) and (e).',
        'Chart (d) is an iteration over the reading days. A selection counts the run: the day follows the previous one '
        'and the run grows, or it is the first day or comes after a gap and a new run starts. Then the longest run is '
        'updated.',
        'Chart (e) first chooses the day to start from: today, if it is a reading day, otherwise yesterday. Then an '
        'iteration counts the day and steps one day back, for as long as the day is a reading day.',
    ])
    d.p('The program texts match the charts.')
    d.code([
        'Program ReadingStatistics::streak                             -- chart (c)',
        '  days := the reading days of the reader, in date order',
        '  Find the longest run',
        '  Find the current streak',
        '  Return (current, longest, read_today)   -- read_today: today was read',
        'End-program',
        '',
        'Procedure Find the longest run                                -- chart (d)',
        '  longest := 0',
        '  run := 0',
        '  previous := none',
        '  Loop-while days has a next reading day',
        '    day := the next reading day',
        '    If previous <> none And previous + 1 day = day Then',
        '      run := run + 1',
        '    Else',
        '      run := 1',
        '    End-if',
        '    longest := max(longest, run)',
        '    previous := day',
        '  End-loop',
        'End-procedure',
        '',
        'Procedure Find the current streak                             -- chart (e)',
        '  today := the current day of the reader',
        '  If today is a reading day Then',
        '    cursor := today',
        '  Else',
        '    cursor := today - 1 day',
        '  End-if',
        '  current := 0',
        '  Loop-while cursor is a reading day',
        '    current := current + 1',
        '    cursor := cursor - 1 day',
        '  End-loop',
        'End-procedure',
    ], size=CODE_SIZE)
    d.p('The components of the data and of the program correspond as shown in ' + ctx.ref('tab:jk_streak_map') + '.')
    _map_table(d, ctx, 'tab:jk_streak_map', 'Components of the data and of the program: counting the reading streak', [
        ['Reading days, in date order', '`Get the reading days`',
         'A query of `ReadingLog` for the reader: group by `logged_on`, keep the groups with '
         '`SUM(to_page - from_page) > 0` (the pages are cast to a signed type), order by `logged_on`, take the dates.'],
        ['Reading day *', '`Reading day` * (chart d)',
         'Condition: the list has a next day (`foreach ($readingDays as $day)`).'],
        ['Run of consecutive days (it is not in the data)',
         '`Count the run`: `Follows the previous day: extend the run`, `First day or after a gap: start a new run`',
         'Condition of the first alternative: `$previous !== null` and the day after `$previous` is `$day`. Then '
         '`$run + 1`, otherwise `1`.'],
        ['`longest`', '`Update the longest run, remember the day`', '`$longest = max($longest, $run)`, `$previous = $day`.'],
        ['Today', '`Choose the first day`: `Today is a reading day: start today`, `Not read today: start yesterday`',
         '`ReadingStatistics::today`. Condition: `isset($read[$today->toDateString()])`, where `$read` is the set of the '
         'reading days (`array_flip`).'],
        ['`current`', '`Count the day and step back` *',
         'Condition: the day of the cursor is a reading day. `$current++`, and the cursor goes one day back.'],
        ['`read_today`', '`Return the three values`',
         'The array `current`, `longest`, `read_today`; `read_today` is `isset($read[$today->toDateString()])`.'],
    ])
    d.p('The code is in `backend/app/Services/Stats/ReadingStatistics.php` (`ReadingStatistics::streak`).')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('11.4 Report and automatic hiding')
    d.p('A reader can report a post or a comment of another reader. The third open report hides the content from '
        'everybody except its author (process 8.0, Section 7.10). Two programs are drawn: `ReportContent::handle`, a '
        'chain of choices, and `ModerationReports::handle`, the command `moderation:reports` that lists the open reports '
        'for the moderator, a loop. The procedure `ModerateContent::hide` is drawn as a chart too, because the program '
        'of the report calls it.')
    d.p('The data of the first program are drawn in the panels (a) and (b) of ' + ctx.ref('fig:jk_report') + '. The request '
        'has the reporter, the content (a `Post` or a `Comment`), a reason and a note; the program also reads the '
        'reports that exist about the content. The result is one of four: a refusal because the content is the own '
        'content of the reporter, a refusal because the reporter has already reported it, the stored report, or the '
        'stored report and the hidden content. The output has alternatives, so the program is a selection. Its first '
        'branches are the two refusals; the branch of a new report is a sequence that ends with one more selection, the '
        'decision to hide.')
    d.p('The list of the moderator is the opposite case: its input is a list, so the program is a loop. Its output is '
        'the rows of a console table and a line with the total. The state of the content (deleted, hidden or visible) is '
        'a selection inside the row.')
    ctx.figure(d, 'fig:jk_report', 'jk_report.png',
               'Report and automatic hiding: data (a, b) and charts of Jackson (c, d, e)')
    d.bullets([
        'Chart (c) is `ReportContent::handle`. The two refusals are the alternatives that stop the program. A new '
        'report locks the content, creates the report, counts the open reports and hides the content when the '
        'threshold (`HIDE_THRESHOLD`, 3) is reached.',
        'Chart (d) is `ModerateContent::hide`. It sets `hidden_at` only when it is empty, so a content that is hidden '
        'already is not changed. For a comment that was hidden just now it also decrements the counter `comments_count` '
        'of the post.',
        'Chart (e) is `ModerationReports::handle`. It prints a note when there are no open reports; otherwise it '
        'builds a row for every report, chooses the state of its content and prints the table with the total.',
    ])
    d.p('The command `moderation:resolve` is not drawn. Its structure is that of `ReportContent::handle`: a chain of '
        'checks, each of which ends the command (the id is a whole number, the report exists, it is not resolved yet), '
        'and then the resolution of the report with a warning when the content is still hidden.')
    d.p('The program texts match the charts.')
    d.code([
        'Program ReportContent::handle                                 -- chart (c)',
        '  If content.user_id = reporter.id Then',
        '    Stop("social.cannot_report_own")',
        '  Else If the reporter has a report about the content Then',
        '    Stop("social.already_reported")',
        '  Else',
        '    Lock the row of the content',
        '    report := create the Report (reporter, content, reason, note)',
        '    open := the number of the open reports about the content',
        '    If open >= HIDE_THRESHOLD Then',
        '      ModerateContent::hide(content)',
        '    Else',
        '      nothing',
        '    End-if',
        '  End-if                                  -- the result is report',
        'End-program',
        '',
        'Procedure ModerateContent::hide(content)                      -- chart (d)',
        '  changed := set hidden_at := now() where hidden_at is empty',
        '  If changed > 0 And content is a Comment Then',
        '    decrement comments_count of its post, if it is above 0',
        '  Else',
        '    nothing',
        '  End-if',
        '  Reload the content                      -- the result is changed > 0',
        'End-procedure',
        '',
        'Program ModerationReports::handle                             -- chart (e)',
        '  reports := the open reports with their reporters and contents, by id',
        '  If reports is empty Then',
        '    Print "There are no open reports."',
        '  Else',
        '    Loop-while reports has a next report',
        '      report := the next report',
        '      cells := id, about, reason, by, note, text, reported',
        '      If the content is not a Post or a Comment Then',
        '        state := "deleted"',
        '      Else If the content is hidden Then',
        '        state := "hidden"',
        '      Else',
        '        state := "visible"',
        '      End-if                          -- the row: the cells and the state',
        '    End-loop',
        '    Print the table and the number of the open reports',
        '  End-if',
        'End-program',
    ], size=CODE_SIZE)
    d.p('The components of the data and of the program correspond as shown in ' + ctx.ref('tab:jk_report_map') + '.')
    _map_table(d, ctx, 'tab:jk_report_map', 'Components of the data and of the program: report and automatic hiding', [
        ['Request: reporter, content, reason, note', '`Report content`',
         '`ReportContent::handle($reporter, $content, $data)`. The result of the program is the `Report`.'],
        ['Refusal: own content', '`Stop: own content`',
         'Condition: `$content->user_id === $reporter->id`. Error `social.cannot_report_own` (`ValidationException`, '
         'field `reason`).'],
        ['Refusal: already reported (the reports of the content)', '`Stop: already reported`',
         'Condition: a report of the reporter about the content exists (`$content->reports()->where(...)->first()`). The '
         'same error comes when the unique index refuses the insert (`UniqueConstraintViolationException`): the same '
         'reader twice at the same moment. Error `social.already_reported`.'],
        ['Report stored', '`New report`: `Lock the content`, `Create the report`, `Count the open reports`',
         'All inside `DB::transaction`. Lock: `lockForUpdate` on the row of the content, so that the reports of one '
         'object are counted one at a time. Create: `Report::query()->create([...])`, status `open`. Count: the reports '
         'of the object with status `open`; there is one per reader, because of the unique index.'],
        ['Report stored, content hidden', '`Hide at the threshold`: `Threshold reached: hide the content`',
         'Condition: `$open >= ReportContent::HIDE_THRESHOLD` (3). Calls the procedure of chart (d).'],
        ['Report stored, content visible', '`Below it: nothing`', 'Condition: fewer than 3 open reports.'],
        ['Content: `Post` or `Comment`', '`Correct the comment counter`',
         'Condition of the first alternative: the update changed a row and the content is a `Comment`; then '
         '`posts.comments_count` is decremented, if it is above 0.'],
        ['Content', '`Mark the content hidden`, `Reload the content`',
         'Mark: `UPDATE ... SET hidden_at = now() WHERE id = ... AND hidden_at IS NULL` in a transaction; the number of '
         'changed rows is `$changed`. Reload: `$content->refresh()`; the result is `$changed > 0`.'],
        ['Open reports, oldest first', '`List the open reports`: `No open reports: print a note`, `Open reports`',
         'Query: `Report` with status `open`, with `reporter` and `reportable`, ordered by `id`. Condition of the first '
         'alternative: the list is empty.'],
        ['Report *', '`Report row` *', 'Condition: the list has a next report (`map` over the reports).'],
        ['Row of the table', '`Take the cells of the report`',
         'Id, about (the type and the id of the content), reason, the handle of the reporter, note, text of the content '
         'and time of the report. The text of a reader is cleaned and cut by `PrintsUserText::safe`.'],
        ['State of the content', '`Take the state of the content`: `deleted`, `hidden`, `visible`',
         'Conditions in this order: the content is not a `Post` or a `Comment` (deleted); `isHidden()` (hidden); '
         'otherwise visible.'],
        ['Console output', '`Print the table and the total`',
         '`$this->table(...)`, then the number of the open reports and the next commands (`moderation:hide`, '
         '`moderation:restore`, `moderation:resolve`).'],
    ])
    d.p('The code is in `backend/app/Actions/Social/ReportContent.php` (`ReportContent::handle`), '
        '`backend/app/Actions/Social/ModerateContent.php` (`ModerateContent::hide`, which the command `moderation:hide` '
        'uses too) and `backend/app/Console/Commands/ModerationReports.php` (`ModerationReports::handle`).')

    # ------------------------------------------------------------------------------------------------------------
    d.h2('11.5 Charts of Jackson and charts of Constantine')
    d.p('The charts of Jackson and the charts of Constantine describe the same code from two sides. The charts of '
        'Constantine show the structure of the modules and the coupling between them. The charts of Jackson show the '
        'logic inside one module as a tree of the data that it processes. ' + ctx.ref('tab:jk_compare') + ' gives the '
        'differences.')
    ctx.table(d, 'tab:jk_compare', 'Charts of Constantine and charts of Jackson')
    d.table(
        ['Question', 'Charts of Constantine (Section 10)', 'Charts of Jackson (Section 11)'],
        [
            ['What is drawn', 'Modules and the calls between them.',
             'The parts of one program: operations, sequences, selections and iterations.'],
            ['What a line means', 'A call: the upper module calls the lower one.',
             '“Consists of”: the parts of a component are under it.'],
            ['Level of the design', 'The structure of the software: which modules exist and who calls whom.',
             'The inside of one module: the order of its operations, its loops and its choices.'],
            ['Where the shape comes from', 'The decomposition of the functions into modules.',
             'The structure of the input data and of the output data.'],
            ['What it lets us judge', 'Coupling by data, by stamp and by control, and the cohesion of a module ('
             + ctx.ref('tab:cc_quality') + ').',
             'Whether the program matches its data; where a loop, a choice or a limit is.'],
            ['What it does not show', 'The order of the operations and the conditions inside a module.',
             'Which other modules are called and what is passed to them: a call is only an operation in a box.'],
            ['Example in this project', 'In ' + ctx.ref('fig:cc_import') + ' `ImportBookText::handle` calls '
             '`EpubBook::chapters` as a coroutine in a loop, and `BookChapter::insert` in a loop.',
             'In ' + ctx.ref('fig:jk_import') + ' the loop over the documents of a book, its three kinds of document '
             'and its limits.'],
        ],
        [2.4, 5.2, 5.2], size=22,
    )
    d.p('The method of Warnier and Orr is related to the method of Jackson. Both build the structure of a program from '
        'the structure of data, and both use sequence, repetition and alternation. They differ in two points. Warnier and '
        'Orr start from the output data and work backwards to the input that is needed to produce it. Jackson draws the '
        'structure of the input and the structure of the output and joins them. A Warnier-Orr diagram is also drawn '
        'differently: as nested braces that read from left to right, the whole at the left and its parts at the right, '
        'and not as a tree of boxes that reads from top to bottom. Only the charts of Jackson are drawn in this '
        'document, as the task asks.')
    d.p('Together the two kinds of charts cover the structural design: the charts of Constantine give the modules and '
        'their coupling, and the charts of Jackson give the algorithm inside the modules that matter most.')
