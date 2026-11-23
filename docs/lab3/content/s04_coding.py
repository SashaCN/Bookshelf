"""Section 4: the systems of coding and classification that the system really uses."""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'tools'))

from docxkit import LANDSCAPE_TEXT_WIDTH as W  # noqa: E402


def _identifiers(d, ctx) -> None:
    d.h2('4.2 Identifiers')
    d.p('The identifiers of the objects, the names of the readers and the formats of the dates are given in '
        + ctx.ref('tab:coding_ids') + '. The identifiers of the database are numbers that the client never invents. '
        'The only identifier that a reader chooses is the handle.')
    ctx.table(d, 'tab:coding_ids', 'Identifiers and formats')
    d.table(
        ['Identifier', 'Where', 'Format and rules', 'How it is made'],
        [
            ['Record number `id`',
             'Tables `users`, `books`, `authors`, `user_books`, `reading_logs`, `quotes`, `reading_goals`, `posts`, '
             '`comments`, `reports`, `book_texts`, `book_chapters`',
             'Unsigned 64-bit integer, primary key. A number in JSON and a string of digits in a path. The routes of the '
             'built-in reader accept 1 to 18 digits (`[0-9]{1,18}`), the routes of the client accept `\\d+`.',
             'Assigned by MySQL (`AUTO_INCREMENT`). Other readers are not addressed by `id` but by the handle: '
             'the numeric `id` of another reader is never in an answer.'],
            ['Composite key',
             '`author_book` (`book_id`, `author_id`), `follows` (`follower_id`, `followed_id`), `likes` (`user_id`, '
             '`post_id`), `blocks` (`user_id`, `blocked_id`)',
             'The primary key is the pair of foreign keys; these tables have no `id`.',
             'A repeated pair is impossible, so one reader has one like per post and one follow per reader.'],
            ['Handle `users.handle`',
             'The public name “@handle” of a reader; the address `/u/{handle}` of the client and '
             '`/api/profiles/{handle}` of the API',
             '3 to 30 characters from `a-z`, `0-9` and `_`; unique; lower case only. The rule is `/^[a-z0-9_]{3,30}$/` '
             'in `UpdateProfileRequest` and the same check in the database (`users_handle_format`, `REGEXP_LIKE(..., '
             '\'c\')`, case-sensitive). The input is trimmed and lower-cased before the check. The sign “@” is not stored.',
             'Made at registration by `GenerateHandle`; the reader may change it in the profile.'],
            ['Generated handle',
             '`GenerateHandle::handle`',
             'The name is transliterated (`Str::slug($name, \'_\', \'uk\')`), everything except `a-z0-9_` is removed, '
             'the result is cut to 22 characters and stripped of `_` at the ends. A result shorter than 3 characters '
             'becomes `reader`. If the handle is taken, a random number is added: 4 digits (3 tries), 6 digits '
             '(3 tries), 8 digits (3 tries). The name is shortened so that the whole is at most 30 characters.',
             'The last resort is 8 random characters `a-z0-9`. If two registrations still get the same handle, '
             '`CreateNewUser` repeats the creation up to 3 times. The readers who registered before the community got '
             'their handles from the migration `add_community_columns_to_users_table` by the same rule, with the '
             'numbers 2, 3, ... as a suffix.'],
            ['E-mail `users.email`',
             'Sign-in name',
             'Up to 255 characters, a valid address (rule `email`), unique. Fortify converts it to lower case '
             '(`lowercase_usernames`) at registration and at sign-in.',
             'Entered by the reader. It is never in an answer about another reader.'],
            ['ISBN-13 `books.isbn_13`',
             'The catalog `books`',
             '`VARCHAR(13)`, 13 digits without hyphens (the pattern `^\\d{13}$`); the check digit is not verified. '
             'May be empty; not unique.',
             'Taken from the first value of the field `isbn` of the Open Library document that has 13 digits '
             '(`OpenLibraryClient::isbn13`). A book added by hand has no ISBN: the form has no such field.'],
            ['Work key of Open Library `books.openlibrary_work_key`',
             'The catalog `books`; the field `work_key` of the search result',
             '`VARCHAR(255)`, unique, may be empty. It is stored as `/works/OL<digits>W`, for example '
             '`/works/OL17930368W`. The input accepts the key with or without the prefix: `^(/works/)?OL\\d+W$`.',
             'Given by Open Library. `AddBookToLibrary::normalizeWorkKey` adds the prefix. A book with a key is shared '
             'by all readers; a book added by hand has no key.'],
            ['Time zone name `users.timezone`',
             'The account of a reader',
             'An identifier of the IANA database, for example `Europe/Kyiv`; `VARCHAR(64)`, default `Europe/Kyiv` '
             '(`User::DEFAULT_TIMEZONE`). The rule is `timezone:all`, that is, a value of '
             '`timezone_identifiers_list(DateTimeZone::ALL)`.',
             'The client sends `Intl.DateTimeFormat().resolvedOptions().timeZone` at registration and offers '
             '`Intl.supportedValuesOf(\'timeZone\')` in the profile.'],
            ['Date and time of an event',
             'The columns `created_at`, `updated_at`, `started_at`, `finished_at`, `hidden_at`, '
             '`position_updated_at`',
             'Stored and calculated in UTC (`config/app.php`: `timezone` is `UTC`). In JSON: ISO 8601 with the '
             'offset, for example `2026-10-21T09:15:00+00:00` (`toIso8601String()`).',
             'Set by the server. The client formats the dates for the screen.'],
            ['Calendar day of the reader',
             '`reading_logs.logged_on` (`DATE`), `today.date` and `data[].date` of the statistics, the parameters '
             '`from` and `to`',
             '`YYYY-MM-DD` in the time zone of the reader. The days are compared as text, because midnight in UTC is '
             'later than midnight in Kyiv. A finish date (UTC) is moved into the calendar of the reader by '
             '`ReadingStatistics::booksFinished`.',
             '`RecordReadingLog` writes `now($timezone)->toDateString()`.'],
            ['Year of a goal `reading_goals.year`',
             'The route `/api/goals/{year}` (digits only); `SMALLINT UNSIGNED`',
             'A whole number from 2000 to the current year plus one in the calendar of the reader '
             '(`SetGoalRequest`). One goal per reader and year (unique index `user_id`, `year`).',
             'Chosen by the client from the date of the server (`today.date`).'],
            ['Place in a book text',
             '`book_chapters.position`, `quotes.chapter_position`, `start_offset`, `end_offset`',
             'The chapter number is an integer from 0 in the order of the spine; the pair (`book_text_id`, `position`) is '
             'unique. The offsets count UTF-16 code units of the text of the chapter (the unit of a JavaScript string '
             'index), with `end_offset > start_offset` (`quotes_anchor_ordered`). The three columns are all set or all '
             'empty (`quotes_anchor_complete`).',
             'Made by the importer (chapters) and sent by the client (offsets); the server checks them in '
             '`ResolveQuoteAnchor`.'],
            ['Session',
             'The cookies `bookshelf-session` and `XSRF-TOKEN`; the table `sessions`',
             'Sanctum cookie session of the stateful mode, no tokens. The lifetime is 43 200 minutes (30 days) in both '
             'compose files. The session cookie is `HttpOnly` and `SameSite=lax`; in production it is `Secure` by default.',
             'Started by Laravel with the first request of the client (the CSRF cookie) and tied to the reader at sign-in or '
             'registration. The name of the cookie is the application name plus `-session`.'],
            ['Technical keys',
             'The cache and the browser',
             'Cache `openlibrary:search:<sha1 of "query|limit">` (24 hours); lock `epub-import` (120 s); cache of the '
             'service worker `bookshelf-shell-<version>`, where the version is the first 12 hexadecimal digits of the '
             'SHA-1 of the built files; the `localStorage` key `bookshelf.reader.settings`; the file of a quote card '
             '`bookshelf-quote-<id>.png`.',
             'Made by `OpenLibraryClient`, `ImportBookText`, `vite.sw.config.ts` and the client.'],
        ],
        [2.1, 2.6, 4.4, 3.3], size=18,
    )


def _enumerations(d, ctx) -> None:
    d.h2('4.3 Enumerations and the scale of the rating')
    d.p('A closed list of values is an enumeration (a class in `app/Enums`). The value is a short English word and is '
        'stored as it is. ' + ctx.ref('tab:coding_enums') + ' lists all of them. A value is checked in the form request '
        '(`Rule::enum` or `Rule::in`) and, where the table has a CHECK constraint, in the database too. The client shows '
        'the Ukrainian name of a value from its dictionary, so the names of the values are never shown to the reader.')
    ctx.table(d, 'tab:coding_enums', 'Enumerations')
    d.table(
        ['Enumeration', 'Values', 'Stored in', 'Checked by', 'Notes'],
        [
            ['`BookStatus`', '`want`, `reading`, `finished`, `abandoned`',
             '`user_books.status`, `VARCHAR(16)`, default `want`',
             '`Rule::enum` in `StoreUserBookRequest`, `UpdateUserBookRequest`, `UserBookController::index`; the cast of '
             '`UserBook`',
             'Wanted, being read, finished, put aside. The rules of the changes are in ' + ctx.ref('tab:coding_transitions') + '.'],
            ['`BookSource`', '`openlibrary`, `manual`', '`books.source`, `VARCHAR(16)`, not empty',
             'The cast of `Book`; set by `AddBookToLibrary`',
             'A book of the catalog or a book that a reader entered. A manual book is visible only to its creator '
             '(`BookPolicy`).'],
            ['`PostType`', '`quote`, `review`', '`posts.type`, `VARCHAR(16)`',
             'CHECK `posts_type_known`; `Rule::enum` in `StorePostRequest`; `UpdatePostRequest` chooses the rules by the type',
             'A quote post has a text and no rating (`posts_quote_has_content`); a review has a rating from 1 to 5 '
             '(`posts_rating_matches_type`).'],
            ['`QuoteType`', '`quote`, `insight`', '`quotes.type`, `VARCHAR(16)`, default `quote`',
             'CHECK `quotes_type_known`; `Rule::enum` in the requests and in `QuoteController::index`',
             'A passage from a book or a thought of the reader.'],
            ['`ReportReason`', '`spam`, `abuse`, `other`', '`reports.reason`, `VARCHAR(16)`',
             'CHECK `reports_reason_known`; `Rule::enum` in `StoreReportRequest`', 'The reason of a complaint.'],
            ['`ReportStatus`', '`open`, `resolved`', '`reports.status`, `VARCHAR(16)`, default `open`',
             'CHECK `reports_status_known`',
             'Only open reports count towards the hiding (`ReportContent::HIDE_THRESHOLD` is 3).'],
            ['`ReportableType`', '`post`, `comment`', '`reports.reportable_type`, `VARCHAR(16)`; no foreign key',
             'CHECK `reports_type_known`; the morph map in `AppServiceProvider`; the argument `type` of the commands '
             '`moderation:hide` and `moderation:restore`',
             'The kind of the object of a report. The value is stored instead of the name of a class.'],
            ['`ReaderError`', '17 codes `reader.*`', 'Not stored; sent in the answers', 'The enumeration itself',
             'See ' + ctx.ref('tab:coding_errors') + '.'],
            ['Scope of the feed', '`following`, `all`', 'Not stored; the query parameter `scope`, default `following`',
             '`Rule::in` in `FeedController`', 'The tabs “Following” and “All”.'],
            ['Rating', 'Integers 1 to 5',
             '`user_books.rating` and `posts.rating`, `TINYINT UNSIGNED`; an empty value of `user_books.rating` means '
             '“no rating”',
             'Rule `between:1,5`; CHECK `user_books_rating_range` and `posts_rating_matches_type`',
             'A scale of five stars. The client clears a rating when the same star is pressed again. A review '
             'cannot be without a rating.'],
            ['Roles', 'Visitor, Reader, Moderator, Administrator',
             'Visitor: no session. Reader: a signed-in user (`auth:sanctum`). Moderator: the flag `users.is_admin`, '
             '`BOOLEAN`, default false',
             'The flag is not in the fillable fields: it cannot be set through the API. No code reads it',
             'The commands `moderation:*` are available to whoever can start `php artisan` on the server, so in the '
             'code the role Moderator is the access to the console.'],
            ['Codes of the client', 'Card templates `light`, `dark`, `accent`; reader themes `auto`, `light`, `sepia`, '
             '`dark`; reader fonts `serif`, `sans`; periods of the statistics 7, 30, 90 days',
             'Only the settings of the reader are stored, in the browser (`localStorage`)',
             'TypeScript types `CardTemplate`, `ReaderTheme`, `ReaderFont`', 'Not sent to the server.'],
        ],
        [1.8, 2.6, 2.8, 3.2, 3.6], total=W, size=18,
    )

    d.h3('Transitions between the statuses of a book')
    d.p('The allowed transitions are the method `BookStatus::allowedTransitions` and are repeated in every answer about '
        'a library entry as the field `allowed_statuses`, so the client does not copy the rules. The effect of each '
        'transition is made by `ChangeUserBookStatus` and is given in ' + ctx.ref('tab:coding_transitions')
        + '. A page update of a book that is wanted or put aside makes the transition to “reading” by itself.')
    ctx.table(d, 'tab:coding_transitions', 'Transitions between the statuses of a book')
    d.table(
        ['From', 'To', 'Condition and effect', 'Error'],
        [
            ['`want`', '`reading`', 'The page count must be known. `started_at` is set to now.', '`library.total_pages_required`'],
            ['`want`', '`finished`', 'The book was read earlier. `started_at` and `finished_at` come from the request '
             '(`finished_at` is now by default); `current_page` becomes `total_pages` (0 if unknown); no log record is made.', ''],
            ['`reading`', '`finished`', '`finished_at` is now; `current_page` becomes `total_pages` when it is known; '
             'the pages added are written to the reading log (`UpdateUserBook`).', ''],
            ['`reading`', '`abandoned`', 'Nothing else changes; the progress is kept.', ''],
            ['`abandoned`', '`reading`', '`finished_at` is cleared; the page and `started_at` are kept.', ''],
            ['`finished`', '`reading`', '`finished_at` is cleared (a correction of a mistake).', ''],
            ['Any other pair', '', 'Refused. The same status again changes nothing.', '`library.invalid_transition`'],
        ],
        [1.5, 1.5, 6.5, 3.0], total=W, size=18,
    )


def _errors(d, ctx) -> None:
    d.h2('4.4 Codes of errors and HTTP statuses')
    d.p('A broken business rule is reported by a stable code of the form `<area>.<reason>`. The areas are `library`, '
        '`catalog`, `quotes`, `goals`, `social`, `reader` and `stats`. An action throws a validation error whose '
        'message is the code, for example `ValidationException::withMessages([\'page\' => [\'library.page_above_total\']])`, '
        'so the answer is 422 and the code is in the list of the field. A few codes have their own status (409 and 503). '
        'The client (`apiErrorCode` in `src/api/errors.ts`) takes the first message that has the form of a code, and '
        '`useApiErrorMessage` shows the Ukrainian text `apiErrors.<code>`. A code without a text, a status 429 and a '
        'missing network have general texts.')
    d.p('All the codes are given in ' + ctx.ref('tab:coding_errors') + ' (36 codes). The column “Field” is the key in the '
        'object `errors` of the answer.')
    ctx.table(d, 'tab:coding_errors', 'Codes of business-rule errors')
    rows = [
        ['`library.already_added`', '409', '(body)', '`AddBookToLibrary`, `BookAlreadyInLibraryException`',
         'The book is already in the library. The body also has `user_book_id`; the client opens that entry.'],
        ['`library.invalid_transition`', '422', '`status`', '`ChangeUserBookStatus::apply`',
         'The change of the status is not allowed.'],
        ['`library.total_pages_required`', '422', '`total_pages`', '`ChangeUserBookStatus`',
         'A book without a page count cannot be started.'],
        ['`library.total_pages_below_current`', '422', '`total_pages`', '`UpdateUserBook::setTotalPages`',
         'The page count is less than the current page.'],
        ['`library.progress_finished`', '422', '`page`', '`UpdateProgress::handle`',
         'The progress of a finished book cannot be changed.'],
        ['`library.page_above_total`', '422', '`page`', '`UpdateProgress::moveTo`',
         'The page is greater than the page count.'],
        ['`quotes.page_above_total`', '422', '`page`', '`SaveQuote::assertPageFitsBook`',
         'The page of a quote is greater than the page count of the reader’s edition.'],
        ['`goals.year_out_of_range`', '422', '`year`', '`SetGoalRequest::prepareForValidation`',
         'The year is before 2000 or after the next year.'],
        ['`catalog.not_found`', '422', '`openlibrary_work_key`', '`AddBookToLibrary`',
         'Open Library has no work with this key.'],
        ['`catalog.unavailable`', '503', '(body)', '`CatalogUnavailableException`',
         'Open Library is not reachable or answered with an error. The client offers manual entry.'],
        ['`social.review_exists`', '422', '`user_book_id`', '`PublishPost`',
         'The reader has reviewed this book already (also on a clash of two requests: unique index).'],
        ['`social.cannot_follow_self`', '422', '`handle`', '`FollowReader::follow`', 'A reader cannot follow oneself.'],
        ['`social.user_blocked`', '422', '`handle`', '`FollowReader::follow`',
         'The reader has blocked the target and has to unblock first.'],
        ['`social.cannot_block_self`', '422', '`handle`', '`BlockReader::block`', 'A reader cannot block oneself.'],
        ['`social.already_reported`', '422', '`reason`', '`ReportContent`',
         'One report per reader and object (also by the unique index).'],
        ['`social.cannot_report_own`', '422', '`reason`', '`ReportContent`', 'A reader cannot report own content.'],
        ['`social.handle_taken`', '422', '`handle`', '`UpdateProfileRequest`, `MeController::update`',
         'The handle belongs to another reader (the second place is a clash of two requests).'],
        ['`social.handle_invalid`', '422', '`handle`', '`UpdateProfileRequest`',
         'The handle is not 3 to 30 characters of `a-z`, `0-9`, `_`.'],
        ['`reader.no_file`', '422', '`file`', '`StoreBookTextRequest`',
         'No file in the request, or it did not arrive whole.'],
        ['`reader.too_large`', '422', '`file`', '`StoreBookTextRequest`, `EpubArchive`',
         'The file is over 15 MB, or it unpacks to more than 60 MB.'],
        ['`reader.not_epub`', '422', '`file`', '`EpubArchive`',
         'Shorter than 22 bytes, not a ZIP archive, or no entry `mimetype` with `application/epub+zip`.'],
        ['`reader.too_many_entries`', '422', '`file`', '`EpubArchive`', 'More than 2000 entries in the archive.'],
        ['`reader.unsafe_archive`', '422', '`file`', '`EpubArchive`, `EpubPaths::isSafeEntryName`',
         'An entry name is absolute or has a segment `..`.'],
        ['`reader.drm`', '422', '`file`', '`EpubArchive`, `EpubImporter`',
         'An encrypted entry, or `META-INF/encryption.xml` with an algorithm that is not a font obfuscation.'],
        ['`reader.invalid`', '422', '`file`', '`PackageReader`, `SafeXml`, `EpubArchive`',
         'A damaged archive; no package, manifest or spine; XML with entities or an unknown encoding.'],
        ['`reader.too_many_chapters`', '422', '`file`', '`EpubBook`, `PackageReader`',
         'More than 500 chapters, or more than 5000 lines of the table of contents.'],
        ['`reader.chapter_too_large`', '422', '`file`', '`HtmlSanitizer`, `EpubBook`',
         'A chapter is over 2 MB of cleaned HTML, or has more than 250 000 elements.'],
        ['`reader.text_too_large`', '422', '`file`', '`EpubBook`', 'All chapters together are over 8 000 000 characters.'],
        ['`reader.quota_exceeded`', '422', '`file`', '`ImportBookText`',
         'The reader would keep more than 30 texts or more than 40 000 000 characters.'],
        ['`reader.empty`', '422', '`file`', '`ImportBookText`', 'No chapter with text is left.'],
        ['`reader.busy`', '503', '(body)', '`ImportBusyException`',
         'Another book is being read. The header `Retry-After` is 5 (seconds).'],
        ['`reader.no_text`', '422', '`anchor`', '`ResolveQuoteAnchor`', 'A quote with an anchor for a book without a text.'],
        ['`reader.anchor_out_of_range`', '422', '`anchor`', '`ResolveQuoteAnchor`',
         'No such chapter, or `end` is not after `start`, or `end` is beyond the chapter, or the range is over 10 000.'],
        ['`reader.anchor_mismatch`', '422', '`anchor`', '`ResolveQuoteAnchor`',
         'The text at the anchor is not the text of the quote (white space is ignored).'],
        ['`reader.chapter_out_of_range`', '422', '`chapter`', '`SaveReadingPosition`',
         'The text has no chapter with this number.'],
        ['`stats.range_too_long`', '422', '`from`', '`StatsController::daily`',
         'The period is longer than 365 days. The client has no text for it and never asks for such a period.'],
    ]
    assert len(rows) == 36
    d.table(['Code', 'HTTP', 'Field', 'Raised by', 'Meaning'], rows, [3.1, 1.0, 2.0, 3.4, 4.9], total=W,
            align=['left', 'center', 'left', 'left', 'left'], size=18)

    d.p('The other errors are standard answers of the framework or of the web server. The statuses that the system '
        'uses are given in ' + ctx.ref('tab:coding_http') + '. The form of the body is described in Section 6.')
    ctx.table(d, 'tab:coding_http', 'Use of HTTP statuses')
    d.table(
        ['Status', 'Where it comes from', 'Used for'],
        [
            ['200', 'The application', 'A read, and a change of an existing object (`PATCH /api/me`, `PUT /api/goals/{year}`, a follow, a like, a block); sign-in.'],
            ['201', 'The application', 'A new object: registration (empty body), a library entry, a quote, a post, a comment, a report, the text of a book.'],
            ['204', 'The application', 'A deletion that returns nothing (a library entry, a quote, a post, a comment, a goal, a block, a text); sign-out; the CSRF cookie.'],
            ['401', 'Sanctum', 'No valid session on a route of the group `auth:sanctum`. For `GET /api/me` it means “not signed in”.'],
            ['404', 'Policies and routing', 'The object does not exist, belongs to another reader or is hidden from the viewer (`denyAsNotFound`); a path that does not match the constraint of the route.'],
            ['409', 'The application', 'Only `library.already_added`.'],
            ['413', 'nginx', 'The body is over 2 MB (25 MB for the upload of a book). The client reads it as `reader.too_large` on an upload.'],
            ['419', 'Laravel', 'The CSRF token does not match. The client takes a new cookie and repeats the request once.'],
            ['422', 'The application', 'A rule of the form request, or a business-rule code of ' + ctx.ref('tab:coding_errors') + '.'],
            ['429', 'The rate limiter', 'A named limiter is exceeded (see ' + ctx.ref('tab:input_limiters') + '). The header `Retry-After` is set.'],
            ['500', 'Laravel', 'An unexpected exception. In production the body is `{"message": "Server Error"}`.'],
            ['503', 'The application', '`catalog.unavailable` and `reader.busy`.'],
            ['502, 504', 'nginx or Caddy', 'The PHP container does not answer. At start the client treats every status from 500 as “the server cannot be reached”.'],
        ],
        [1.2, 2.4, 8.4], total=W, align=['center', 'left', 'left'], size=18,
    )


def _design_codes(d, ctx) -> None:
    d.h2('4.5 Codes of the design documents')
    d.p('The design documents use the systems of codes of ' + ctx.ref('tab:coding_docs')
        + '. The codes of functions, processes, stores and works are the same in the technical specification, the '
        'preliminary design and this document.')
    ctx.table(d, 'tab:coding_docs', 'Codes of the design documents')
    d.table(
        ['System', 'Form', 'Content'],
        [
            ['Functions', '`F-<group>.<number>`, for example F-5.3',
             '48 functions of the technical specification in nine groups: F-1 Account (5), F-2 Library (7), F-3 Statuses '
             '(1), F-4 Progress (4), F-5 Quotes (6), F-6 Statistics and goal (5), F-7 PWA (2), F-8 Community (11), '
             'F-9 Built-in reading (7). The groups F-1 to F-7 are the personal tracker; F-8 and F-9 are new in '
             'version 2. F-1.4 is extended with the handle and the bio.'],
            ['Processes', '`<number>.0`, 1.0 to 8.0',
             '1.0 Manage account, 2.0 Manage library, 3.0 Update progress, 4.0 Calculate statistics and goal, 5.0 Manage '
             'quotes, 6.0 Read books, 7.0 Share and discuss, 8.0 Moderate content. The processes 6.0 to 8.0 are new.'],
            ['Data stores', '`D<number>`, D1 to D9',
             'D1 Users, D2 Books and authors, D3 User books, D4 Reading log, D5 Quotes, D6 Reading goals, D7 Book texts, '
             'D8 Posts and social data, D9 Reports and blocks. A store is one or several tables of the database.'],
            ['Works of the network schedule', 'A letter, A to R (18 works)',
             'A Technical specification, B Preliminary design, C Skeleton, Docker, CI, D Authentication, E Library server and '
             'Open Library, F Library screens, G First deployment, H Progress server and log, I Progress screen, J Quotes '
             'and quote card, K Statistics and annual goal, N Community: server, O Community: screens, P Moderation and '
             'abuse protection, Q Built-in reading: EPUB import, R Built-in reading: screens, quote selection, progress, '
             'L PWA and polishing, M Acceptance and documentation. The letters N to R were added in version 2; in the '
             'schedule they stand between K and L. The names are those of the report on laboratory work 2.'],
            ['Theme of the project', '`BS-2026`', 'The code of the theme of the technical specification, version 2.'],
            ['Keys of this document', '`tab:<name>`, `fig:<name>`',
             'Internal keys of the tables and figures; the number is given by the build and is used in the text by reference.'],
        ],
        [2.4, 3.0, 8.0], size=20,
    )
    d.p('The links between the codes are given in ' + ctx.ref('tab:coding_trace') + '. They are made by the names of '
        'the groups of functions, of the processes and of the works. The group F-7 is not a process of the data flow '
        'diagram: it is done by the client alone.')
    ctx.table(d, 'tab:coding_trace', 'Links between the codes of functions, processes, stores and works')
    d.table(
        ['Process', 'Functions', 'Stores (R read, W write)', 'Works'],
        [
            ['1.0 Manage account', 'F-1.1 to F-1.5', 'D1 RW', 'D'],
            ['2.0 Manage library', 'F-2.1 to F-2.7, F-3.1', 'D2 RW, D3 RW', 'E, F'],
            ['3.0 Update progress', 'F-4.1 to F-4.4', 'D3 RW, D4 W', 'H, I'],
            ['4.0 Calculate statistics and goal', 'F-6.1 to F-6.5', 'D3 R, D4 R, D6 RW', 'K'],
            ['5.0 Manage quotes', 'F-5.1 to F-5.6', 'D3 R, D5 RW', 'J'],
            ['6.0 Read books', 'F-9.1 to F-9.7', 'D3 RW, D4 W, D5 RW, D7 RW', 'Q, R'],
            ['7.0 Share and discuss', 'F-8.1 to F-8.9', 'D1 R, D2 R, D3 R, D5 R, D8 RW, D9 R', 'N, O'],
            ['8.0 Moderate content', 'F-8.10, F-8.11', 'D8 W, D9 RW', 'P'],
            ['(client only)', 'F-7.1, F-7.2', '', 'L'],
        ],
        [3.4, 3.0, 4.6, 1.4], size=20,
    )


def _classification(d, ctx) -> None:
    d.h2('4.6 Classification of the data')
    d.p('The data is classified in two ways: by who may see it and by how long it lives and what is deleted with it.')
    d.h3('Classification by visibility')
    d.p('Nothing is public to guests: every route of the API except the sign-in, the registration, the CSRF cookie, '
        'the documentation of the API and the health check `/up` is in the group `auth:sanctum`. Among the signed-in readers the data has the classes of '
        + ctx.ref('tab:coding_visibility') + '. A reader never learns that an object of the class 1 or 3 of another '
        'reader exists: the answer is 404.')
    ctx.table(d, 'tab:coding_visibility', 'Classes of the data by visibility')
    d.table(
        ['Class', 'Data', 'Who sees it', 'How it is enforced'],
        [
            ['1 Private to the reader',
             'The library (`user_books`), the reading log, the quotes, the goals, the book texts and chapters, the '
             'statistics; the e-mail and the time zone',
             'Only the owner',
             '`UserBookPolicy`, `QuotePolicy`, `BookTextPolicy` give 404 to others; every query starts from the relation of '
             'the current user; the form request checks the owner before the body (`authorize`). The resources for other '
             'readers (`ProfileResource`, `ReaderResource`, `PostResource`) have no field for the e-mail or the time zone.'],
            ['2 Public to signed-in readers',
             'The handle, the name, the bio and the counters of a reader; published posts (quote snapshots and reviews), '
             'comments, the counters of likes; the title, authors and cover of the book of a post',
             'Every signed-in reader, except the pair of readers with a block',
             '`VisibleToReaders::scopeVisibleTo` and `User::excludeBlocked` remove the content of a reader who has blocked '
             'the viewer or is blocked by the viewer; `PostPolicy`, `CommentPolicy`, `UserPolicy::viewProfile`.'],
            ['3 Hidden',
             'A post or a comment with `hidden_at` set (three open reports, or `moderation:hide`)',
             'Only its author, who gets `is_hidden: true`',
             'The scope `visibleTo`; the counter `comments_count` of the post does not count hidden comments.'],
            ['4 Moderation only',
             'The reports with the note and the identity of the reporter',
             'The moderator on the console',
             'There is no route that lists reports. The reporter gets only a receipt (`ReportResource`: `id`, `reason`, '
             '`status`, `created_at`); the author of the content learns nothing.'],
            ['5 Shared catalog',
             'The books and authors of Open Library',
             'Every signed-in reader (through a post, a review list or the own library)',
             '`BookPolicy::view`: a book with the source `manual` is visible only to its creator.'],
            ['6 Never in an answer',
             'The hash of the password, `remember_token`, `is_admin`, the sessions, the numeric `id` of another reader',
             'Nobody through the API',
             'The attribute `Hidden` of `User`; the fields are not in the resources.'],
        ],
        [2.0, 4.0, 2.4, 5.0], size=20,
    )

    d.h3('Classification by lifetime and ownership')
    d.p('Every record belongs to a reader or to the shared catalog. What is deleted together with a record is fixed by '
        'the rules `ON DELETE` of the migrations and, where a table has no foreign key (a report points to a post or to a '
        'comment), by the actions. The result is in ' + ctx.ref('tab:coding_lifetime') + '.')
    ctx.table(d, 'tab:coding_lifetime', 'Lifetime of the data and what is deleted with it')
    d.table(
        ['Data', 'Created by', 'Deleted by', 'Deleted together with it', 'Survives'],
        [
            ['Account (`users`)', '`CreateNewUser`', 'No route and no command; the rules below are those of the database',
             'Library entries, logs, quotes, goals, texts, posts, comments, likes, follows and blocks of the reader, and '
             'the reports made by the reader (`ON DELETE CASCADE`)',
             'Reports about the posts and comments of the reader (no foreign key; the console shows them as “deleted”); a '
             'manual book created by the reader (`created_by` becomes empty)'],
            ['Library entry (`user_books`)', '`AddBookToLibrary`', '`UserBookController::destroy` (F-2.7)',
             'Reading log, quotes and book text with its chapters (`ON DELETE CASCADE`)',
             'The posts of the reader (they point to the catalog, not to the entry); the catalog book'],
            ['Catalog book (`books`, `author_book`)',
             'The first entry of the book; a hand-entered book by its reader',
             'An Open Library book: never. A manual book: with its last library entry (same transaction)',
             'The links to the authors; the library entries and the posts about the book (`ON DELETE CASCADE`), with their likes and comments',
             'The authors (`authors`) are never deleted; the reports about the deleted posts stay'],
            ['Reading log (`reading_logs`)', '`RecordReadingLog`', 'Only with the entry or the account',
             '', 'Never edited: a correction is a new record with a negative change'],
            ['Quote (`quotes`)', '`SaveQuote`', 'The reader (`QuoteController::destroy`), or with the entry',
             '', 'The posts made from the quote (a post is a snapshot)'],
            ['Book text (`book_texts`, `book_chapters`)', '`ImportBookText`',
             'The reader (`DeleteBookText`); replaced by a new upload in one transaction; or with the entry',
             'The chapters (`ON DELETE CASCADE`)',
             'The quotes: `DeleteBookText` clears their anchor (`book_text_id`, `chapter_position`, `start_offset`, '
             '`end_offset`). The original file is never stored'],
            ['Post (`posts`)', '`PublishPost`', 'The author (`DeletePost`)',
             'Likes and comments (`ON DELETE CASCADE`); the reports about the post and its comments (`DeletePost`)',
             'The quote or the library entry it was made from'],
            ['Comment (`comments`)', '`PostComments::add`', 'Its author or the author of the post (`PostComments::remove`)',
             'The reports about it (`PostComments::remove`)', ''],
            ['Like, follow, block', '`LikePost`, `FollowReader`, `BlockReader`', 'The same reader, or the account',
             'A block also deletes the follows between the two readers in both directions', ''],
            ['Report (`reports`)', '`ReportContent`', 'With its post or comment',
             '', 'Kept after it is resolved (`status` becomes `resolved`) and counts no more towards the hiding'],
            ['Session (`sessions`)', 'Laravel, with the first request of the client', 'Expires after 30 days', '', ''],
            ['Cache entries', '`OpenLibraryClient`, `ImportBookText`', 'Expire: a search answer after 24 hours, the import lock after 120 s', '', ''],
        ],
        [2.4, 2.0, 2.8, 3.6, 3.2], size=18,
    )


def write(d, ctx) -> None:
    d.h1('4 Systems of coding and classification')

    d.h2('4.1 Principles')
    d.p('Bookshelf uses three kinds of codes. Identifiers name the objects of the database. Enumerations fix the states '
        'and the types of the objects. Codes of errors tell the client which business rule was broken. The design '
        'documents have codes of their own.')
    d.p('The codes follow a few rules:')
    d.bullets([
        'A code is written with lower-case Latin letters, digits and the underscore; a code of an error has the form '
        '`<area>.<reason>`.',
        'A code is stable. It is stored or sent as it is and is never reused for another meaning.',
        'The server does not send texts for the reader. The client translates the codes with the dictionaries '
        '`src/i18n/uk.ts`, `social.uk.ts` and `reader.uk.ts`, so another language needs no change of the server.',
        'A list of values is checked twice: by the form request and, where the table has a CHECK constraint, by '
        'the database, so no error of the application can store an impossible value.',
    ])
    d.p('The sections that follow give the identifiers (4.2), the enumerations (4.3), the codes of errors (4.4), the '
        'codes of the design documents (4.5) and the classification of the data (4.6).')

    _identifiers(d, ctx)
    with ctx.landscape(d):
        _enumerations(d, ctx)
        _errors(d, ctx)
    _design_codes(d, ctx)
    _classification(d, ctx)
