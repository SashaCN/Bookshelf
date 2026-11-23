"""Section 5: the input data (documents) of the system."""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'tools'))

from docxkit import LANDSCAPE_TEXT_WIDTH  # noqa: E402

HEADER = ['Document (view of the client)', 'Request and server check', 'Fields: type and restrictions', 'Process',
          'Limit', 'Frequency']
WIDTHS = [2.1, 3.2, 5.6, 1.1, 1.7, 1.7]


def _table(d, rows: list) -> None:
    d.table(HEADER, rows, WIDTHS, total=LANDSCAPE_TEXT_WIDTH, align=['left', 'left', 'left', 'center', 'left', 'left'],
            size=18)


def _account(d, ctx) -> None:
    d.h3('5.2.1 Account and profile')
    d.p('The documents of the process 1.0 are listed in ' + ctx.ref('tab:input_account') + '. The first one is a service '
        'request that the client sends before any request that changes data.')
    ctx.table(d, 'tab:input_account', 'Input documents: account and profile')
    _table(d, [
        ['CSRF cookie (`src/api/http.ts`)', '`GET /api/sanctum/csrf-cookie`',
         'No fields. The answer sets the cookie `XSRF-TOKEN`; the client copies it into the header `X-XSRF-TOKEN` of every '
         'request that is not a `GET`.', '1.0', 'None',
         'Once per session, before the first request that changes data; again once after the answer 419.'],
        ['Registration (`RegisterView.vue`)', '`POST /api/auth/register`\nFortify, `CreateNewUser`',
         '`name`: string, required, up to 255 characters.\n`email`: string, required, a valid address, up to 255 characters, '
         'unique among readers; converted to lower case.\n`password`: string, required, at least 8 characters '
         '(`Password::min(8)`).\n`timezone`: optional, an IANA identifier (`timezone:all`); default `Europe/Kyiv`; the '
         'client sends the zone of the browser.\nThe handle is made by the server from the name.',
         '1.0', 'None', 'Once per reader.'],
        ['Sign-in (`LoginView.vue`)', '`POST /api/auth/login`\nFortify, `LoginRequest`',
         '`email`: string, required.\n`password`: string, required.\n`remember` is not sent by the client.',
         '1.0', '`login`: 5 attempts per minute for the pair e-mail and IP address', 'Once per session; a session lasts 30 days.'],
        ['Sign-out (`ProfileView.vue`)', '`POST /api/auth/logout`', 'No fields.', '1.0', 'None', 'Rare.'],
        ['Profile (`ProfileView.vue`)', '`PATCH /api/me`\n`UpdateProfileRequest`',
         'All fields are optional (`sometimes`), the client sends all four.\n`name`: string, required if sent, up to 255 '
         'characters.\n`timezone`: required if sent, an IANA identifier (`timezone:all`).\n`handle`: required if sent; '
         'trimmed and converted to lower case, then `^[a-z0-9_]{3,30}$`; unique among the other readers (codes '
         '`social.handle_invalid`, `social.handle_taken`).\n`bio`: may be empty, up to 280 characters.\nOther fields '
         '(for example `is_admin`) are ignored.',
         '1.0', 'None', 'Rare.'],
    ])


def _library(d, ctx) -> None:
    d.h3('5.2.2 Library')
    d.p('The documents of the process 2.0, with the answer of the external catalog that the server receives, are '
        'listed in ' + ctx.ref('tab:input_library') + '. The change of a library entry is one route with three '
        'forms on the screen of the book.')
    ctx.table(d, 'tab:input_library', 'Input documents: library')
    _table(d, [
        ['Search in the catalog (`AddBookView.vue`)', '`GET /api/catalog/search?q=`\n`CatalogSearchRequest`',
         '`q`: string, required, 2 to 100 characters. The client sends it 350 ms after the last key and only when the '
         'trimmed text has 2 or more characters.',
         '2.0', '`catalog`: 30 per minute per reader', 'On each search; several requests while the reader types.'],
        ['Add from the catalog (`AddBookView.vue`)', '`POST /api/library`\n`StoreUserBookRequest`',
         '`openlibrary_work_key`: string, required when there is no `title`, pattern `^(/works/)?OL\\d+W$`.\n'
         'The optional fields `total_pages`, `status`, `started_at`, `finished_at` are as in the next row; the form of the '
         'client does not send them.',
         '2.0', 'None', 'Once per book.'],
        ['Add by hand (`AddBookView.vue`, manual mode)', '`POST /api/library`\n`StoreUserBookRequest`',
         '`title`: string, required when there is no work key, up to 255 characters; not allowed together with a work key.\n'
         '`authors`: a list of up to 5 strings of up to 255 characters; the client splits a line at commas.\n'
         '`published_year`: integer from 1 to the next year.\n`total_pages`: integer from 1 to 20 000.\n`status`: '
         '`BookStatus`. `started_at`, `finished_at`: dates not later than now; `finished_at` not before `started_at`.',
         '2.0', 'None', 'Rare (books missing in the catalog).'],
        ['Change of the status (buttons in `BookView.vue` and `ReadingCard.vue`)', '`PATCH /api/library/{userBook}`\n'
         '`UpdateUserBookRequest`; `UpdateUserBook`',
         '`status`: `BookStatus`; the client offers only the values of `allowed_statuses`.\nFor the transition '
         '`want` to `finished`: `started_at`, `finished_at` (dates not later than now; `finished_at` not before '
         '`started_at`).',
         '2.0', 'None', 'A few times per book.'],
        ['Rating (`RatingStars.vue` in `BookView.vue`)', '`PATCH /api/library/{userBook}`',
         '`rating`: integer from 1 to 5, or empty to clear the rating.', '2.0', 'None', 'Once per book.'],
        ['Page count of the edition (form in `BookView.vue`)', '`PATCH /api/library/{userBook}`',
         '`total_pages`: integer from 1 to 20 000. It cannot be less than the current page unless the book is finished '
         '(`library.total_pages_below_current`). The client sets the minimum to the current page.',
         '2.0', 'None', 'Rare.'],
        ['Removal of a book (`BookView.vue`)', '`DELETE /api/library/{userBook}`',
         'No fields. The client asks for a confirmation first.', '2.0', 'None', 'Rare.'],
        ['Answer of Open Library (the server, `OpenLibraryClient`)',
         '`GET {OPENLIBRARY_BASE_URL}/search.json`',
         'Request: `q` (the query in lower case without extra spaces, or `key:/works/...` for one work), `limit` (20; 1 '
         'for one work), `fields` (`key`, `title`, `subtitle`, `author_name`, `author_key`, `first_publish_year`, '
         '`cover_i`, `number_of_pages_median`, `subject`, `isbn`).\nAnswer: the list `docs`. A document is used only if '
         'its `key` starts with `/works/` and its `title` is not empty. The server keeps up to 12 subjects and the first '
         '13-digit ISBN, and builds the address of the cover from `cover_i`.\nUp to 2 attempts, 150 ms apart, 8 s each.',
         '2.0', 'The `catalog` limit above; an answer is cached for 24 hours',
         'One per search that is not in the cache.'],
    ])


def _progress_quotes(d, ctx) -> None:
    d.h3('5.2.3 Progress, quotes, statistics and goal')
    d.p('The documents of the processes 3.0, 4.0 and 5.0 are listed in ' + ctx.ref('tab:input_progress_quotes')
        + '. The queries of the statistics and of the lists are also input documents: their parameters are checked.')
    ctx.table(d, 'tab:input_progress_quotes', 'Input documents: progress, quotes, statistics and goal')
    _table(d, [
        ['Page of progress (`PageJumpForm.vue`; buttons “+10” and “+25” in `ReadingCard.vue`)',
         '`POST /api/library/{userBook}/progress`\n`UpdateProgressRequest`; `UpdateProgress`',
         '`page`: integer, required, from 0 to 20 000. The upper bound is the page count of the book, checked by the action '
         '(`library.page_above_total`). A finished book is refused (`library.progress_finished`). The client allows '
         '0 to the page count; a quick button adds 10 or 25 to the current page and cuts the result to that range.',
         '3.0', 'None', 'Once or several times per reading session; one request per tap.'],
        ['New quote (`QuoteFormView.vue`; the route can have `?book=`)',
         '`POST /api/library/{userBook}/quotes`\n`StoreQuoteRequest`; `SaveQuote`',
         '`type`: `quote` or `insight`, default `quote`.\n`content`: string, required, up to 2000 characters.\n'
         '`note`: may be empty, up to 2000 characters.\n`page`: integer from 1 to 20 000, may be empty; not above the page '
         'count of the entry (`quotes.page_above_total`).\n`is_favorite`: boolean, optional.\n`anchor`: only from the '
         'built-in reader, see ' + ctx.ref('tab:input_reader') + '.',
         '5.0', 'None', 'Once per quote.'],
        ['Text shared from another application (PWA share target)',
         '`GET /quotes/new?title=&text=` (a route of the client; no request to the server until the quote is saved)',
         '`text` is trimmed and cut to 2000 characters, and fills `content`. `title` is declared in the manifest but not used.',
         '5.0', 'None', 'Rare.'],
        ['Editing of a quote (`QuoteFormView.vue`, route `/quotes/{id}/edit`)',
         '`PATCH /api/quotes/{quote}`\n`UpdateQuoteRequest`; `SaveQuote`',
         'The fields of the new quote without `anchor`, each optional (`sometimes`); `content` cannot be empty if sent.',
         '5.0', 'None', 'Rare.'],
        ['Favorite mark (the star in `QuoteCard.vue`)', '`PATCH /api/quotes/{quote}`', '`is_favorite`: boolean.',
         '5.0', 'None', 'Occasional.'],
        ['Removal of a quote (`QuoteCard.vue`; a highlight in `ReaderView.vue`)', '`DELETE /api/quotes/{quote}`', 'No fields. The client asks for a confirmation first.', '5.0', 'None', 'Rare.'],
        ['List of quotes (`QuotesView.vue`, `BookQuotes.vue`)', '`GET /api/quotes?favorite=&book=&type=&page=`',
         '`favorite`: boolean. `book`: integer, the id of a library entry. `type`: `QuoteType`. `page`: integer from 1 '
         '(20 quotes per page).', '5.0', 'None', 'On each opening of the list or of a book.'],
        ['Quote of the day (`DailyQuoteCard.vue`)', '`GET /api/quotes/daily?favorite=`', '`favorite`: boolean, optional.',
         '5.0', 'None', 'On each opening of the main screen.'],
        ['Period of the chart (`StatsView.vue`)', '`GET /api/stats/daily?days=`',
         'The client sends `days` = 7, 30 or 90. The server accepts `days` (integer from 1 to 366) or `from` and `to` '
         '(format `Y-m-d`, `to` not before `from`). Without parameters: 30 days. A period over 365 days is refused '
         '(`stats.range_too_long`).', '4.0', 'None', 'On each opening of the screen and on each change of the period.'],
        ['Statistics and goal of the year (`StatsView.vue`)', '`GET /api/stats/summary`\n`GET /api/goals/{year}`',
         'No fields. `year` is a whole number in the path; the client takes it from the date of the server '
         '(`today.date`).', '4.0', 'None', 'On each opening of the screen.'],
        ['Annual goal (`GoalCard.vue`)', '`PUT /api/goals/{year}`\n`SetGoalRequest`',
         '`target_books`: integer, required, from 1 to 1000.\n`year` in the path: from 2000 to the next year in the '
         'calendar of the reader (`goals.year_out_of_range`). A goal of the year is replaced.',
         '4.0', 'None', 'Once a year.'],
        ['Removal of a goal (`GoalCard.vue`)', '`DELETE /api/goals/{year}`', 'No fields.', '4.0', 'None', 'Rare.'],
    ])


def _community(d, ctx) -> None:
    d.h3('5.2.4 Community')
    d.p('The documents of the processes 7.0 and 8.0 are listed in ' + ctx.ref('tab:input_community') + '. The routes '
        'that contain `{handle}` find the reader by the column `users.handle`.')
    ctx.table(d, 'tab:input_community', 'Input documents: community')
    _table(d, [
        ['Search of readers (`CommunityView.vue`)', '`GET /api/profiles?q=&page=`\n`SearchReadersRequest`',
         '`q`: string, required; spaces and a leading “@” are removed first; then 2 to 50 characters. The signs `%`, `_`, '
         '`\\` are escaped. It finds the beginning of a handle, of a name or of a word of a name. The client waits for '
         '2 characters.', '7.0', '`search`: 30 per minute per reader', 'On each search.'],
        ['Choice of the feed (`CommunityView.vue`)', '`GET /api/feed?scope=&page=`\n`FeedController`',
         '`scope`: `following` or `all`, default `following`. `page`: integer from 1 (20 posts per page).',
         '7.0', 'None', 'On each opening and on each change of the tab.'],
        ['Publication of a quote (`ComposePostView.vue`)', '`POST /api/posts`\n`StorePostRequest`; `PublishPost`',
         '`type`: `quote`.\n`quote_id`: integer, required, a quote of the reader (another quote “does not exist”: '
         '422).\n`comment`: may be empty, up to 2000 characters.',
         '7.0', '`posts`: 20 per hour', 'Rare.'],
        ['Publication of a review (`ComposePostView.vue`)', '`POST /api/posts`\n`StorePostRequest`; `PublishPost`',
         '`type`: `review`.\n`user_book_id`: integer, required, a library entry of the reader.\n`rating`: integer from 1 '
         'to 5, required.\n`content`: may be empty, up to 5000 characters.\nOne review per reader and book '
         '(`social.review_exists`).', '7.0', '`posts`: 20 per hour', 'Once per book.'],
        ['Editing of a post (`PostEditForm.vue` in `PostView.vue`)', '`PATCH /api/posts/{post}`\n`UpdatePostRequest`',
         'A quote post: `comment`, may be empty, up to 2000 characters. A review: `rating` (1 to 5), `content` (up to 5000 '
         'characters). The fields of the other type are ignored. The text of a quote post cannot be changed. Only the '
         'author.', '7.0', '`social-writes`: 60 per minute', 'Rare.'],
        ['Removal of a post (`PostCard.vue`)', '`DELETE /api/posts/{post}`', 'No fields. Only the author. The client asks for a confirmation first.', '7.0',
         '`social-writes`', 'Rare.'],
        ['Like and its removal (`PostCard.vue`)', '`PUT` and `DELETE /api/posts/{post}/like`', 'No fields. Repeated '
         'requests change nothing.', '7.0', '`likes`: 120 per minute', 'On each tap.'],
        ['Comment (`CommentForm.vue`)', '`POST /api/posts/{post}/comments`\n`StoreCommentRequest`',
         '`content`: string, required, 1 to 1000 characters (plain text).', '7.0', '`comments`: 60 per hour',
         'On each comment.'],
        ['Removal of a comment (`CommentList.vue`)', '`DELETE /api/comments/{comment}`',
         'No fields. The author of the comment or of the post. The client asks for a confirmation first.', '7.0', '`social-writes`', 'Rare.'],
        ['Report (`ReportDialog.vue` in `PostCard.vue` and `CommentList.vue`)',
         '`POST /api/posts/{post}/report`\n`POST /api/comments/{comment}/report`\n`StoreReportRequest`; `ReportContent`',
         '`reason`: `spam`, `abuse` or `other`, required.\n`note`: may be empty, up to 500 characters.\nOne report per '
         'reader and object; a report on own content is refused. The third open report from different readers hides the '
         'object.', '8.0', '`reports`: 20 per hour', 'Rare.'],
        ['Follow and unfollow (`ReaderProfileView.vue`)', '`PUT` and `DELETE /api/profiles/{handle}/follow`',
         'No fields. A reader cannot follow oneself or a reader who is blocked by the follower.', '7.0',
         '`follows`: 120 per minute', 'Occasional.'],
        ['Block and unblock (`ReaderProfileView.vue`, `BlockedReadersView.vue`)', '`PUT` and `DELETE /api/blocks/{handle}`',
         'No fields. A block also deletes the follows between the two readers.', '8.0', '`social-writes`', 'Rare.'],
    ])


def _reader(d, ctx) -> None:
    d.h3('5.2.5 Built-in reader')
    d.p('The documents of the process 6.0 are listed in ' + ctx.ref('tab:input_reader') + '. The limits of the uploaded '
        'file are given in ' + ctx.ref('tab:input_epub') + '.')
    ctx.table(d, 'tab:input_reader', 'Input documents: built-in reader')
    _table(d, [
        ['EPUB file (`BookTextBlock.vue`)', '`POST /api/library/{userBook}/text`\n`multipart/form-data`\n'
         '`StoreBookTextRequest`; `ImportBookText`',
         '`file`: a file, required, up to 15 360 KB (15 MB). The format is judged by the content, not by the name or the '
         'type. The client allows `.epub` and `application/epub+zip`, refuses a file over 15 MB before sending, and waits '
         'for the answer up to 120 s. The file is not stored: only the cleaned text is.',
         '6.0', '20 uploads per hour per reader (`throttle:20,60`); one import at a time on the server',
         'Once per book.'],
        ['Removal of the text (`BookTextBlock.vue`)', '`DELETE /api/library/{userBook}/text`', 'No fields.', '6.0',
         'None', 'Rare.'],
        ['Opening of a chapter (`ReaderView.vue`)', '`GET /api/book-texts/{bookText}/chapters/{position}`',
         '`bookText`: 1 to 18 digits. `position`: 1 to 6 digits.', '6.0', 'None', 'On each chapter that is opened.'],
        ['Reading position (`usePositionSaver`, `ReaderView.vue`)', '`PUT /api/book-texts/{bookText}/position`\n'
         '`SaveReadingPositionRequest`; `SaveReadingPosition`',
         '`chapter`: integer from 0 to 100 000; the text must have this chapter (`reader.chapter_out_of_range`).\n'
         '`chapter_progress`: a number from 0 to 1.\nA position that was sent already is not sent again.',
         '6.0', '`reader-position`: 120 per minute',
         'Every 15 seconds while the reader reads, when the page is hidden and when it is left (4 per minute at most '
         'from the timer).'],
        ['Quote selected in the text (`ReaderView.vue`, `ReaderSelectionBar.vue`)',
         '`POST /api/library/{userBook}/quotes`\n`StoreQuoteRequest`; `ResolveQuoteAnchor`',
         'The fields of the quote (`content` up to 2000 characters: the client refuses a longer selection; `note` up to '
         '2000) and `anchor`; if it is present all three parts are required:\n`anchor.chapter`: integer from 0 to '
         '100 000.\n`anchor.start`: integer from 0 to 100 000 000.\n`anchor.end`: integer greater than `start`, up to '
         '100 000 000.\nThe server also checks that the chapter exists, that `end` is not beyond the chapter, that '
         '`end - start` is at most 10 000, and that the text at the place equals the quote up to white space.',
         '6.0', 'None', 'Once per saved quote.'],
        ['Settings of the reader (`ReaderSettings.vue`)', 'No request: `localStorage`, key `bookshelf.reader.settings`',
         '`fontSize`: from 14 to 30, step 1, default 18.\n`lineHeight`: from 1.3 to 2.1, step 0.1, default 1.6.\n'
         '`font`: `serif` or `sans`, default `serif`.\n`theme`: `auto`, `light`, `sepia` or `dark`, default `auto`.',
         '6.0', 'None', 'Rare.'],
    ])


def _limits(d, ctx) -> None:
    d.h2('5.3 Limits of the EPUB upload')
    d.p('An uploaded file is not trusted. The limits of ' + ctx.ref('tab:input_epub') + ' are the defaults of the class '
        '`EpubLimits`. The size of the file and of the unpacked content are checked from the headers of the archive '
        'before anything is read, and again while the entries are read, so an archive with a false header or an '
        'extreme compression ratio is refused before it uses the memory.')
    ctx.table(d, 'tab:input_epub', 'Limits of the upload of an EPUB file')
    d.table(
        ['Limit', 'Value', 'Where', 'Code on excess'],
        [
            ['Size of the uploaded file', '15 MB (15 × 1024 × 1024 bytes)', '`EpubLimits::$maxFileBytes`; the rule `max:15360` of `StoreBookTextRequest`', '`reader.too_large`'],
            ['Unpacked content of all entries', '60 MB', '`$maxUnpackedBytes`', '`reader.too_large`'],
            ['Entries in the archive', '2000', '`$maxEntries`', '`reader.too_many_entries`'],
            ['Chapters', '500 (an empty chapter is skipped and is not counted)', '`$maxChapters`', '`reader.too_many_chapters`'],
            ['Cleaned HTML of one chapter', '2 MB', '`$maxChapterHtmlBytes`', '`reader.chapter_too_large`'],
            ['One XML or XHTML document read into memory', '10 MB', '`$maxDocumentBytes`', '`reader.chapter_too_large` for a chapter, `reader.invalid` for the package'],
            ['Elements of a chapter that are looked at', '250 000', '`HtmlSanitizer::MAX_ELEMENTS`', '`reader.chapter_too_large`'],
            ['Nesting', 'Input over 300 levels: only the text is kept. Output over 32 levels: the tags are dropped, the content is kept', '`HtmlSanitizer`', '(none)'],
            ['Text of one book', '8 000 000 characters (UTF-16 code units)', '`$maxTextCharacters`', '`reader.text_too_large`'],
            ['Items of the manifest and of the spine; lines of the table of contents', '5000 each', '`$maxManifestItems`, `$maxTocEntries`', '`reader.invalid`; `reader.too_many_chapters`'],
            ['Texts of one reader', '30 texts and 40 000 000 characters in all', '`$maxReaderTexts`, `$maxReaderCharacters`', '`reader.quota_exceeded`'],
            ['Wait for the turn of the import', '5 s; the lock lives 120 s', '`$importLockWait`, `Cache::lock(\'epub-import\')`', '`reader.busy` (503)'],
            ['Body of the request', '25 MB for `/api/library/{id}/text`, 2 MB elsewhere', '`docker/web/nginx.conf`, `docker/nginx/dev.conf`', 'Status 413 from nginx'],
            ['Limits of PHP', '`upload_max_filesize` 20M, `post_max_size` 22M, `memory_limit` 256M', '`docker/php/prod.ini`, `dev.ini`', '`reader.too_large` or `reader.no_file`'],
            ['Time', 'nginx waits 60 s for PHP; the client waits 120 s for the upload and 20 s for other requests', '`fastcgi_read_timeout`, `UPLOAD_TIMEOUT_MS`, `TIMEOUT_MS`', 'The client shows “no connection”'],
        ],
        [2.6, 3.2, 3.2, 3.4], size=20,
    )


def _validation(d, ctx) -> None:
    d.h2('5.4 Validation of the input data')
    d.p('Every input is checked at two levels.')
    d.bullets([
        '**The client** checks what it can check without the server: the attributes of the fields (`required`, `minlength`, '
        '`maxlength`, `min`, `max`, `type`), the counters of characters, and the conditions that disable a button. '
        'This level only saves a request and explains a mistake early. It is not trusted.',
        '**The server** checks everything again, regardless of the client. The form requests in `app/Http/Requests` and '
        '`CreateNewUser` check the form of the data; the actions check what depends on the state (the page count of the '
        'book, the status, the text of the book); the database checks the rest with unique indexes and CHECK '
        'constraints. A form request that works on a given object also checks the owner first (`authorize`), so a foreign '
        'object is “not found” whatever the body is.',
    ])
    d.p(ctx.ref('tab:input_validation') + ' compares the two levels for the main fields.')
    ctx.table(d, 'tab:input_validation', 'Checks of the main fields at the two levels')
    d.table(
        ['Field', 'Client check', 'Server rule'],
        [
            ['Name', '`maxlength` 255 in the profile', '`required|string|max:255`'],
            ['E-mail', '`type="email"`', '`required|string|email|max:255|unique:users`'],
            ['Password', '`minlength` 8 at registration', '`required|string|Password::min(8)`'],
            ['Handle', '`required`, `minlength` 3, `maxlength` 30', '`^[a-z0-9_]{3,30}$` after trim and lower case; unique'],
            ['Bio', '`maxlength` 280 with a counter', '`max:280`'],
            ['Search in the catalog', 'At least 2 characters after trimming; 350 ms pause', '`required|min:2|max:100`'],
            ['Title of a book by hand', '`maxlength` 255', '`max:255`'],
            ['Page count, quote page', '`type="number"`, `min` 1, `max` 20 000 (the page count of the book for a quote)', '`integer|min:1|max:20000`; the action compares with the page count'],
            ['Year of publication', '`min` 1, `max` the next year', '`integer|min:1|max:` the next year'],
            ['Page of progress', 'An integer from 0 to the page count; the button is disabled otherwise', '`integer|min:0|max:20000`; the action compares with the page count'],
            ['Quote text, note', '`maxlength` 2000 with a counter', '`max:2000`'],
            ['Goal', '`min` 1, `max` 1000', '`integer|min:1|max:1000`'],
            ['Comment of a quote post', '`maxlength` 2000', '`max:2000`'],
            ['Text of a review', '`maxlength` 5000', '`max:5000`'],
            ['Rating', 'Only the stars 1 to 5 can be chosen', '`between:1,5`'],
            ['Comment', '`maxlength` 1000; an empty comment is not sent', '`required|string|max:1000`'],
            ['Note of a report', '`maxlength` 500; the reason must be chosen', '`max:500`; `Rule::enum(ReportReason::class)`'],
            ['Search of readers', 'At least 2 characters, `maxlength` 50', '`min:2|max:50` after removing the “@”'],
            ['EPUB file', '`accept`; a size over 15 MB is refused', '`required|file|max:15360`, then the checks of the archive'],
            ['Selection in the text', 'At most 2000 characters; the button is disabled otherwise', '`max:2000` and the checks of the anchor'],
        ],
        [2.8, 4.6, 4.6], size=20,
    )
    d.p('A rule of the form request gives a standard English text of the framework. The client does not show it: it '
        'shows the text of its own dictionary for the field. The exceptions are the rules whose message is a code '
        '(the handle and the file of a book). Every error of a business rule is a translatable code (Section 4.4): the '
        'server sends the code, and the client chooses the text of the interface from `src/i18n`. The 422 answer has '
        'the form described in Section 6.7.')
    d.p('The limits of the rate of requests are set by the named limiters of `AppServiceProvider` and '
        '`FortifyServiceProvider`. They are listed in ' + ctx.ref('tab:input_limiters') + '. A limiter counts the requests '
        'of one signed-in reader (the sign-in counts the pair of the e-mail and the IP address). The server trusts the '
        'proxies in front of it (`trustProxies(at: \'*\')`), so the address is the one of the client and not of Caddy. '
        'The routes without a limiter are the reads, the registration, the sign-out and the changes of the library, '
        'the progress, the quotes and the goals.')
    ctx.table(d, 'tab:input_limiters', 'Limits of the rate of requests')
    d.table(
        ['Limiter', 'Rule', 'Routes', 'Defined in'],
        [
            ['`login`', '5 per minute for the key “e-mail in lower case | IP address”', '`POST /api/auth/login`', '`FortifyServiceProvider`'],
            ['`catalog`', '30 per minute', '`GET /api/catalog/search`', '`AppServiceProvider`'],
            ['`search`', '30 per minute', '`GET /api/profiles`', '`AppServiceProvider`'],
            ['`posts`', '20 per hour', '`POST /api/posts`', '`AppServiceProvider`'],
            ['`comments`', '60 per hour', '`POST /api/posts/{post}/comments`', '`AppServiceProvider`'],
            ['`likes`', '120 per minute', '`PUT`, `DELETE /api/posts/{post}/like`', '`AppServiceProvider`'],
            ['`follows`', '120 per minute', '`PUT`, `DELETE /api/profiles/{handle}/follow`', '`AppServiceProvider`'],
            ['`reports`', '20 per hour', '`POST /api/posts/{post}/report`, `POST /api/comments/{comment}/report`', '`AppServiceProvider`'],
            ['`social-writes`', '60 per minute', '`PATCH`, `DELETE /api/posts/{post}`; `DELETE /api/comments/{comment}`; `PUT`, `DELETE /api/blocks/{handle}`', '`AppServiceProvider`'],
            ['`reader-position`', '120 per minute', '`PUT /api/book-texts/{bookText}/position`', '`AppServiceProvider`'],
            ['`throttle:20,60`', '20 per 60 minutes', '`POST /api/library/{userBook}/text`', '`routes/reader.php`'],
        ],
        [2.2, 3.4, 4.8, 3.6], size=20,
    )


def write(d, ctx) -> None:
    d.h1('5 Input data (documents)')

    d.h2('5.1 Composition of the input data')
    d.p('The input data of Bookshelf is the forms of the client, the requests that the client sends in the background '
        '(the reading position, the choice of a list), the file with a book, the settings that the reader keeps in the '
        'browser and the answer of the external catalog Open Library. The data of the reader enters the system only '
        'through the API, and every request passes the layers of the server part: the route with its limiter, the form '
        'request, the action or the service, and the policy.')
    d.p('Each input document is a row in one of the tables of Section 5.2. The columns are:')
    d.bullets([
        '**Document (view of the client)**: the form or the screen and the file of the client in `src/views` or '
        '`src/components` where it is entered.',
        '**Request and server check**: the method and the route of `routes/api.php`, `routes/social.php`, '
        '`routes/reader.php` or Fortify, the form request, and the action that does the work.',
        '**Fields**: the real name, the type and the restrictions of the rules of the form request.',
        '**Process**: the process of the preliminary design that receives the document (1.0 to 8.0).',
        '**Limit**: the limit of the rate of requests, if there is one (see ' + ctx.ref('tab:input_limiters') + ').',
        '**Frequency**: how often one reader sends the document in normal use. It is an estimate from the way the '
        'screen is used, not a measurement. The volumes of the whole system are given in ' + ctx.ref('tab:quantities')
        + '. A frequency that the code fixes (a timer, a limit) is stated as such.',
    ])
    d.p('The routes are written with the prefix `/api`. Only the request for the CSRF cookie, the registration and the '
        'sign-in are open to a visitor; every other document needs the session of a reader.')

    d.h2('5.2 Input documents')
    d.p('The documents are grouped by the area of the application. The tables are printed on landscape pages.')
    with ctx.landscape(d):
        _account(d, ctx)
        _library(d, ctx)
        _progress_quotes(d, ctx)
        _community(d, ctx)
        _reader(d, ctx)

    _limits(d, ctx)
    _validation(d, ctx)
