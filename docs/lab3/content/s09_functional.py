"""Section 9 of the technical design: the functional scheme (the interaction of the components with the flows of data,
the composition of the data, the files and devices; the symbols of GOST 19.701-90). The figures are drawn by
docs/lab3/diagrams/functional.py.
"""

from __future__ import annotations


def write(d, ctx) -> None:
    d.h1('9 Functional scheme')
    d.p(
        'A functional scheme shows the interaction of the components of the software, the flows of information between them, the composition '
        'of the data in the flows, and the files and devices that are used. It carries more information than a structural scheme. '
        'The schemes of this section use the symbols of GOST 19.701-90. The components are the classes and the modules that are named in '
        'Section 8; the data are the real names of the fields of the API and of the columns of the database.'
    )

    # ---- 9.1 -------------------------------------------------------------------------------------------------
    d.h2('9.1 Symbols')
    d.p(
        f'The symbols of the schemes are shown in {ctx.ref("fig:gost_legend")}. What each of them denotes in this project is given in '
        f'{ctx.ref("tab:gost_symbols")}. An arrow is a flow of data in the direction of the arrow; its label names the fields. A double arrow is a '
        'request and its answer, or reading and writing.'
    )
    ctx.figure(d, 'fig:gost_legend', 'gost_legend.png', 'Symbols of GOST 19.701-90 used in the functional schemes')
    ctx.table(d, 'tab:gost_symbols', 'Symbols of the functional schemes and their meaning in the project')
    d.table(
        ['Symbol', 'What it denotes in this project', 'Example'],
        [
            ['Processing', 'A program component: a class or a group of classes that transforms data.',
             'Views and components; `SaveQuote`; `ImportBookText`; API client'],
            ['Data', 'Data in transit between two components that are not stored.', '`ParsedChapter`: position, title, html, characters'],
            ['Stored data', 'Data on a medium of the browser or of the web server that is not the database: cookies, the local storage, the Cache Storage, '
             'the static files.', 'Cookies; `localStorage`; Cache Storage; static files of the SPA'],
            ['Internal memory', 'The memory of a running program, lost when the page is closed.', 'Pinia stores'],
            ['Direct access storage', 'The MySQL database with its tables.', 'MySQL 8.4, database `bookshelf`'],
            ['Document', 'Data made as a file or a printout.', 'The PNG file of a quote card; the output of the moderation commands'],
            ['Manual input', 'Data that a person enters or chooses.', 'Forms; the EPUB file; the selection of text; the commands of the moderator'],
            ['Display', 'Data that is shown on a screen.', 'Screens; Book page; Reading screen'],
        ],
        [20, 50, 30], size=20,
    )

    # ---- 9.2 -------------------------------------------------------------------------------------------------
    d.h2('9.2 Functional scheme of the system')
    d.p(
        f'{ctx.ref("fig:functional_system")} shows the components of the system and the flows between them. The client is on the left. In the middle are '
        'the eight server processes of the preliminary design (with the numbers 1.0 to 8.0), reached from the API client through the line of the '
        'HTTP requests; each process has its own arrow to the database on the right. The number of a process is the same as in the diagram of level 1 '
        'of the preliminary design.'
    )
    with ctx.landscape(d):
        ctx.figure(d, 'fig:functional_system', 'functional_system.png', 'Functional scheme of the system', landscape=True)
    d.p(
        f'All components of {ctx.ref("fig:functional_system")} are described in {ctx.ref("tab:functional_components")}. The letters R and W in the figure '
        'mean reading and writing; the codes D1 to D9 are the data stores of the preliminary design.'
    )
    ctx.table(d, 'tab:functional_components', 'Components of the functional scheme of the system')
    comp = [
        ['Forms and selection', 'Manual input', 'Text typed or chosen in the forms of the screens: credentials, search query, page, quote, post, comment, report; the '
         'selection of text in a chapter.'],
        ['EPUB file', 'Manual input', 'A `.epub` file chosen with the file chooser of `BookTextBlock`, up to 15 MB.'],
        ['Views and components', 'Processing', '`src/views` and `src/components`: draw the screens, check the input and call the stores and the API modules.'],
        ['Screens', 'Display', 'The screens of the library, the book page, quotes, statistics, the community and the reader.'],
        ['Pinia stores', 'Internal memory', 'Eight stores with the lists that were loaded (library, quotes, feed, posts, comments, profiles, blocks, the reader). '
         'They are lost when the page is closed and cleared at sign-in, sign-out and the end of the session.'],
        ['API client', 'Processing', '`http.ts` and six modules in `src/api`: build the request (JSON or form data), add `X-XSRF-TOKEN`, read the answer and the errors.'],
        ['Cookies', 'Stored data', 'The session cookie `bookshelf-session` (HttpOnly, SameSite Lax, secure in production) and `XSRF-TOKEN`; the browser sends them with each request.'],
        ['localStorage', 'Stored data', 'The key `bookshelf.reader.settings`: `fontSize`, `lineHeight`, `font`, `theme`. Nothing else is kept in the local storage.'],
        ['Quote card renderer', 'Processing', '`drawQuoteCard` in `quoteCard.ts`: draws the text, title, authors and page of a quote on a canvas of 1080 × 1350 pixels in one '
         'of three templates (light, dark, accent).'],
        ['Quote card image', 'Document', 'The file `bookshelf-quote-<id>.png`: given to the share sheet of the phone (`navigator.share`) or downloaded.'],
        ['Service worker', 'Processing', '`/sw.js` (`src/sw`): when a version is installed it fetches the built files into the cache; it answers the opening of a screen '
         'with `index.html` and the built files from the cache. `/api`, `/docs` and `/up` always go to the network.'],
        ['Cache Storage', 'Stored data', 'One cache `bookshelf-shell-<version>` with the built files; the caches of older versions are deleted when a version is activated.'],
        ['Static files of the SPA', 'Stored data', '`index.html`, `assets/*`, `sw.js`, the manifest and the icons, built by Vite and kept in the image of nginx (in production).'],
        ['2.0 Library and catalog', 'Processing', '`UserBookController`, `AddBookToLibrary`, `UpdateUserBook`, `OpenLibraryClient`. Stores: D2 and D3 (RW), the table `cache` (RW). '
         'Calls Open Library with `q`.'],
        ['3.0 Progress', 'Processing', '`ReadingProgressController`, `UpdateProgress`, `RecordReadingLog`. Stores: D3 (RW), D4 (W).'],
        ['6.0 Reading', 'Processing', '`BookTextController`, `ImportBookText`, `EpubImporter`, `SaveReadingPosition`. Stores: D5 and D7 (RW), the lock in `cache_locks`. '
         'Calls 3.0 with the page.'],
        ['5.0 Quotes', 'Processing', '`QuoteController`, `SaveQuote`, `PickQuoteOfTheDay`. Stores: D5 (RW), D3 (R). Calls `ResolveQuoteAnchor` of 6.0 with the anchor.'],
        ['4.0 Statistics and goal', 'Processing', '`StatsController`, `GoalController`, `ReadingStatistics`, `GoalProgress`. Stores: D3 and D4 (R), D6 (RW).'],
        ['1.0 Account', 'Processing', 'Fortify, Sanctum, `MeController`, `CreateNewUser`. Stores: D1 (RW) and the table `sessions` (RW).'],
        ['7.0 Community', 'Processing', '`FeedController`, `PostController`, `PublishPost`, `PostComments`, `LikePost`, `FollowReader`. Stores: D8 (RW); D1, D2, D3, D5, D9 (R).'],
        ['8.0 Moderation', 'Processing', '`ReportController`, `BlockController`, `ReportContent`, `ModerateContent`, `BlockReader`. Stores: D9 (RW), D8 (W).'],
        ['Console commands', 'Manual input', 'The moderator types `php artisan moderation:reports`, `moderation:hide {type} {id}`, `moderation:restore {type} {id}` or '
         '`moderation:resolve {report}` on the server.'],
        ['Console output', 'Document', 'The table of open reports (`moderation:reports`) and the messages of the other commands, printed in the terminal.'],
        ['MySQL, database `bookshelf`', 'Direct access storage', 'The tables of the stores D1 to D9 (the composition is in the next table) and the tables `sessions`, `cache` and `cache_locks`.'],
        ['Open Library', 'External service', '`GET /search.json` with `q`, `limit`, `fields`; the answer is a list of documents. It is not a symbol of the standard and is drawn as a grey box.'],
    ]
    d.table(['Component', 'Symbol', 'Description'], comp, [20, 14, 66], size=20)

    d.p(
        f'The composition of the stored data is given in {ctx.ref("tab:functional_stores")}. The fields are the columns of the migrations.'
    )
    ctx.table(d, 'tab:functional_stores', 'Composition of the data stores of the database')
    stores = [
        ['D1', '`users`', '`name`, `email`, `password` (hash), `timezone`, `handle`, `bio`, `is_admin`'],
        ['D2', '`books`, `authors`, `author_book`', '`title`, `subtitle`, `isbn_13`, `openlibrary_work_key`, `cover_url`, `page_count`, `published_year`, `subjects`, `source`, '
         '`created_by`; `name`; `book_id`, `author_id`, `position`'],
        ['D3', '`user_books`', '`user_id`, `book_id`, `status`, `current_page`, `total_pages`, `rating`, `started_at`, `finished_at`'],
        ['D4', '`reading_logs`', '`user_book_id`, `user_id`, `from_page`, `to_page`, `logged_on`'],
        ['D5', '`quotes`', '`user_book_id`, `user_id`, `type`, `content`, `note`, `page`, `is_favorite`, and the anchor: `book_text_id`, `chapter_position`, `start_offset`, `end_offset`'],
        ['D6', '`reading_goals`', '`user_id`, `year`, `target_books`'],
        ['D7', '`book_texts`, `book_chapters`', '`user_book_id`, `user_id`, `source_name`, `chapters_count`, `characters_count`, `chapter_position`, `chapter_progress`, `progress`, '
         '`position_updated_at`; `book_text_id`, `position`, `title`, `content` (html), `characters`'],
        ['D8', '`posts`, `likes`, `comments`, `follows`', '`user_id`, `book_id`, `type`, `content`, `comment`, `rating`, `page`, `likes_count`, `comments_count`, `hidden_at`; '
         '`user_id`, `post_id`; `post_id`, `user_id`, `content`, `hidden_at`; `follower_id`, `followed_id`'],
        ['D9', '`reports`, `blocks`', '`reporter_id`, `reportable_type`, `reportable_id`, `reason`, `note`, `status`; `user_id`, `blocked_id`'],
        ['-', '`sessions`', '`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`: the session store (`SESSION_DRIVER=database`, lifetime 43,200 minutes)'],
        ['-', '`cache`, `cache_locks`', '`key`, `value`, `expiration`; `key`, `owner`, `expiration`: the cache (`CACHE_STORE=database`) and its locks'],
    ]
    d.table(['Store', 'Tables', 'Data'], stores, [7, 25, 68], size=19)

    d.p('The stores of the scheme are the following. They are checked against the configuration of the project.')
    d.bullets([
        '**The cache and the sessions of the server are in MySQL.** `CACHE_STORE` and `SESSION_DRIVER` are `database` in `docker-compose.yml` and in '
        '`deploy/docker-compose.prod.yml`. The answers of Open Library (the key `openlibrary:search:` and a hash, 24 hours; a failure is not cached) '
        'and the lock `epub-import` are rows of `cache` and `cache_locks`; they are not in the memory of PHP. The queue is also `database`, but no job is '
        'dispatched.',
        '**Internal memory is on the client only.** The Pinia stores and the chapters that `ReaderView` has loaded (the current one and its neighbours) '
        'are lost with the page.',
        '**Stored data of the browser are three.** The cookies, the local storage with the settings of the reader and the Cache Storage of the service worker. '
        'No data of the reader (books, quotes, texts) is kept in the browser.',
        '**The documents are two.** The image of a quote card, which is made in the browser and never sent to the server, and the output of the moderation commands.',
    ])
    d.p('The flows of the scheme are the following.')
    d.bullets([
        '**Input and display.** The reader gives data to the views through forms. The views show the screens. The EPUB file and the selection of text '
        'are also input of the reader.',
        '**Requests.** The API client sends all requests through one line to the processes. The labels of the arrows of the processes give the fields of '
        f'the request and, after the arrow, of the answer. The routes with their fields are in {ctx.ref("tab:interfaces")}.',
        '**Calls between the processes.** `SaveQuote` (5.0) passes the anchor to `ResolveQuoteAnchor` (6.0); `SaveReadingPosition` (6.0) passes the '
        'page to `UpdateProgress` (3.0); `AddBookToLibrary` (2.0) calls `OpenLibraryClient`.',
        '**Access to the stores.** The arrows between the processes and the database give the stores and the kind of access. They agree with the use of the '
        'stores in the preliminary design; the stores that 6.0 reaches through 3.0 (D3 and D4) are not drawn from 6.0.',
        '**The application shell.** The service worker fetches the static files into the Cache Storage and answers from it; the data of the reader are '
        'never cached.',
    ])

    # ---- 9.3 -------------------------------------------------------------------------------------------------
    d.h2('9.3 Functional scheme of the built-in reading')
    d.p(
        f'{ctx.ref("fig:functional_reader")} shows the process 6.0 in detail: the upload of an EPUB file, its reading and cleaning, the storage of the '
        'chapters, the reading screen, the selection of text, the saving of a quote with an anchor and the saving of the reading position that raises '
        'the progress. The columns are the client, the server part and the database. The numbers in the circles are the numbers of the flows in the list below '
        'the figure. The line across the figure separates the upload from the reading.'
    )
    ctx.figure(d, 'fig:functional_reader', 'functional_reader.png', 'Functional scheme of the built-in reading')
    d.p(f'The flows of {ctx.ref("fig:functional_reader")} are the following.')
    d.numbered([
        'The reader chooses an `.epub` file on the book page. `BookTextBlock` turns a file over 15 MB away and sends the rest as form data: '
        '`POST /api/library/{id}/text`, the field `file`.',
        'The route (`auth:sanctum`, `throttle:20,60`) and `StoreBookTextRequest` (the policy `update` of `UserBookPolicy`, the rule: a file up to 15 MB) '
        'pass the path of the uploaded file and its name through `BookTextController::store` to `ImportBookText`.',
        '`ImportBookText` takes the lock `epub-import` in `cache_locks` (120 s) and waits for it up to 5 s. When another book is being imported, the '
        'answer is 503 `reader.busy` with `Retry-After: 5`.',
        '`ImportBookText` gives the path to `EpubImporter::open`. `EpubArchive` checks the size, the number of entries (2,000), the names of the entries, the '
        'unpacked size (60 MB), the entry `mimetype` and the encryption of the archive. `EpubImporter` refuses a book with DRM (`reader.drm`). `PackageReader` '
        'reads `container.xml`, the package, the manifest, the spine and the titles from the table of contents.',
        '`EpubBook` receives the archive, the list of the documents of the spine and the titles.',
        '`EpubBook::chapters` reads the documents one by one. `SafeXml::parse` reads a document without network and entities, and `HtmlSanitizer::sanitize` '
        'builds a new tree from text and the allowed elements without attributes. A document without text is skipped. The limits: 500 chapters, 2 MB of html '
        'in a chapter, 8,000,000 characters in a text.',
        'A chapter goes out as `ParsedChapter`: `position`, `title` (from the table of contents or the first heading), `html`, `characters` (UTF-16 code units). '
        'Chapters are taken one at a time, so the book is never in memory as a whole.',
        'In one transaction `ImportBookText` locks the library entry, calls `DeleteBookText` (the old text goes and the anchors of its quotes are cleared), '
        'checks the quota of the reader (30 texts, 40,000,000 characters), creates the row of `book_texts` and inserts every chapter into `book_chapters`.',
        'The row of `book_texts` and the rows of `book_chapters` are stored. The original file is not stored.',
        'The answer 201 is `BookTextResource`: `id`, `user_book_id`, `source_name`, `chapters_count`, `characters_count`, `position`, `progress`, '
        '`chapters` (`position`, `title`, `characters`).',
        'The library store sets `has_text` and `text_progress` of the entry. The quotes store clears the anchors of the book (`clearAnchors`).',
        'The book page shows the button "Read", which opens the route `/read/{id}`.',
        '`ReaderView` takes the entry from the library store or by `GET /api/library/{id}` and asks for the text: `GET /api/library/{id}/text`. '
        '`BookTextController::show` checks `UserBookPolicy`.',
        'The controller reads `book_texts` and the list of the chapters (`position`, `title`, `characters`). The answer carries the table of contents and the '
        'position (`chapter`, `chapter_progress`).',
        '`showChapter` opens the chapter where the reader stopped, or the chapter and the highlight that the address names when the reader comes from a quote.',
        '`GET /api/book-texts/{id}/chapters/{position}`; `BookChapterController::show` checks `BookTextPolicy`. The next chapter is asked for in advance and '
        'only the chapters next to the current one are kept.',
        'The controller reads `book_chapters.content` and the quotes of the chapter with their offsets (`start_offset`, `end_offset`).',
        'The answer is `BookChapterResource`: `position`, `title`, `html`, `characters`, `highlights` (`id`, `start`, `end`, `content`, `note`). '
        '`ReaderChapter` puts the html into the page (`v-html`) and `applyHighlights` marks the quotes.',
        'The reader selects text. `useTextSelection` waits 120 ms until the selection stops changing.',
        '`selectionOf` turns the selection into `SelectedText`: the text and the offsets `start` and `end` in UTF-16 code units of the text of the chapter, '
        'the same unit that the server uses. A selection over 2,000 characters is refused by the client.',
        '`ReaderView.saveQuote` sends `POST /api/library/{id}/quotes` with `content`, `note` and `anchor` (`chapter`, `start`, `end`). `QuoteController::store` '
        'calls `SaveQuote::create`, which calls `ResolveQuoteAnchor`.',
        '`ResolveQuoteAnchor` reads the chapter and checks the range (`end` over `start`, `end` within `characters`, at most 10,000 units) and that the text '
        'of the range equals the text of the quote without white space. The quote is stored with `book_text_id`, `chapter_position`, `start_offset` and '
        '`end_offset`. The answer is 201 `QuoteResource` with the anchor, and the client adds the highlight. If the answer is `reader.anchor_mismatch` or '
        '`reader.anchor_out_of_range`, the client saves the quote without the anchor.',
        '`usePositionSaver` sends `PUT /api/book-texts/{id}/position` with `chapter` and `chapter_progress` every 15 s, when the page is hidden and when the '
        'reader leaves (`keepalive`). The same position is not sent twice.',
        '`SaveReadingPosition` works in a transaction and locks the library entry. The progress is (the characters of the chapters before + `chapter_progress` '
        '× the characters of the chapter) / the characters of the text. The row of `book_texts` gets `chapter_position`, `chapter_progress`, `progress` and '
        '`position_updated_at`.',
        'When the book is not finished and has `total_pages`, the page is round(`progress` × `total_pages`). Only a page greater than `current_page` goes to '
        '`UpdateProgress`; the page is never lowered.',
        '`UpdateProgress` changes `user_books` (`current_page`, and `status` when the book was wanted or put aside) and `RecordReadingLog` '
        'writes `reading_logs` (`from_page`, `to_page`, `logged_on` in the time zone of the reader). So the log, the streak and the statistics work for the '
        'reading in the application as for the pages entered by hand.',
        'The answer is `BookTextResource` with `meta.user_book`, the entry with the new page. The library store replaces the entry.',
    ])
    d.p(
        f'The components of {ctx.ref("fig:functional_reader")} that are not described in the list are described in {ctx.ref("tab:reader_components")}.'
    )
    ctx.table(d, 'tab:reader_components', 'Components of the functional scheme of the built-in reading')
    rc = [
        ['EPUB file', 'Manual input', 'The file chosen by the reader; `BookTextBlock` checks its size.'],
        ['StoreBookTextRequest', 'Processing', 'The form request: the policy `update` of the library entry and the rule of the file.'],
        ['ImportBookText', 'Processing', 'The action of the import: the lock, the call of the importer and the transaction that stores the text.'],
        ['EpubImporter::open', 'Processing', 'Opens the archive within the limits and reads the package; returns `EpubBook`. The file is untrusted.'],
        ['EpubBook::chapters', 'Processing', 'A generator: reads, parses and cleans one chapter at a time and gives `ParsedChapter`.'],
        ['ParsedChapter', 'Data', 'A chapter ready to be stored: `position`, `title`, `html`, `characters`.'],
        ['Stores library and quotes', 'Processing', 'The Pinia stores of the client that take the new state of the entry and of the quotes.'],
        ['Book page', 'Display', 'The screen of a library entry with the block of the text and the button "Read".'],
        ['ReaderView', 'Processing', 'The full-screen reader (`/read/{id}`): loads the text, shows a chapter, saves quotes and the position.'],
        ['Reading screen', 'Display', 'The chapter with its highlights, the top bar and the sheets of the contents and of the settings.'],
        ['Text selection', 'Manual input', 'A selection of text by the reader inside a chapter.'],
        ['usePositionSaver', 'Processing', 'The composable that sends the reading position every 15 s, when the page is hidden and when it is left.'],
        ['BookTextController::show', 'Processing', 'Gives the table of contents and the position of the text of an entry.'],
        ['BookChapterController::show', 'Processing', 'Gives one chapter with the highlights of its quotes.'],
        ['SaveQuote', 'Processing', 'The action of saving a quote; with an anchor it calls `ResolveQuoteAnchor`.'],
        ['SaveReadingPosition', 'Processing', 'The action that stores the position, counts the progress and raises the page.'],
        ['UpdateProgress', 'Processing', 'The action of the process 3.0; here it is called with the page counted from the position.'],
        ['Library store', 'Processing', 'The Pinia store of the library, which keeps the entry with the page that the server has accepted.'],
        ['cache_locks', 'Direct access storage', 'The lock `epub-import`.'],
        ['book_texts, book_chapters', 'Direct access storage', 'The text of an entry, its position and its chapters (D7).'],
        ['quotes', 'Direct access storage', 'The quotes with the anchor (D5).'],
        ['user_books, reading_logs', 'Direct access storage', 'The entry of the library with its page and the reading log (D3 and D4).'],
    ]
    d.table(['Component', 'Symbol', 'Description'], rc, [26, 20, 54], size=20)

    # ---- 9.4 -------------------------------------------------------------------------------------------------
    d.h2('9.4 Interfaces between the modules')
    d.p(
        'The interface between the client and the server part is the HTTP API. All its routes are under `/api` and need a signed-in reader except the '
        'registration, the sign-in and the cookie of the CSRF token. The main routes are given in '
        f'{ctx.ref("tab:interfaces")}. The rules that are common to all routes are the following.'
    )
    d.bullets([
        'The data are JSON (`Accept: application/json`). The only exception is the upload of an EPUB file, which is form data.',
        'A write request carries the cookie of the session and the header `X-XSRF-TOKEN`. The token is given by `GET /api/sanctum/csrf-cookie`. After 419 the '
        'client asks for a new token and repeats the request once.',
        'An answer with one object is `{"data": {...}}`; a list is `{"data": [...]}`. The lists of quotes, the log, the feed, the profiles, the comments, the reviews '
        'and the blocks have 20 items on a page, the parameter `page`, and the keys `links` and `meta`.',
        'An error of validation is 422 with `{"message": "...", "errors": {"field": ["code"]}}`. A broken business rule has a stable code `<area>.<reason>` '
        '(for example `library.invalid_transition`, `reader.drm`, `social.review_exists`), and the client turns it into a text in Ukrainian.',
        'Other statuses: 401 no session; 404 not found or a foreign resource; 409 the book is already in the library (`library.already_added`); 419 the CSRF '
        'token; 429 the rate; 503 the catalog is not reachable (`catalog.unavailable`) or the import is busy (`reader.busy`).',
        'A request is limited in the rate per reader: the sign-in 5 a minute per email and address; the catalog search 30 a minute; the uploads of a text '
        '20 an hour; the reading position 120 a minute; posts 20 an hour; comments 60 an hour; likes and follows 120 a minute; reports 20 an hour; the search of '
        'readers 30 a minute; the other changes of the community 60 a minute.',
    ])
    ctx.table(d, 'tab:interfaces', 'Main routes of the HTTP API between the client and the server part')
    api = [
        ['`/api/sanctum/csrf-cookie`', 'GET', '-', 'The cookie `XSRF-TOKEN`.', '1.0'],
        ['`/api/auth/register`', 'POST', '`name`, `email`, `password`, `timezone`', '201; the session cookie.', '1.0'],
        ['`/api/auth/login`', 'POST', '`email`, `password`', '`{"two_factor": false}`; the session cookie.', '1.0'],
        ['`/api/auth/logout`', 'POST', '-', '204.', '1.0'],
        ['`/api/me`', 'GET, PATCH', 'PATCH: `name`, `timezone`, `handle`, `bio`', '`id`, `name`, `email`, `handle`, `bio`, `timezone`.', '1.0'],
        ['`/api/catalog/search`', 'GET', '`q` (2 to 100 characters)', 'A list: `work_key`, `title`, `subtitle`, `authors`, `cover_url`, `page_count`, '
         '`published_year`, `subjects`, `isbn_13`, `in_library`.', '2.0'],
        ['`/api/library`', 'GET, POST', 'GET: `status`. POST: `openlibrary_work_key` or `title`; `authors`, `published_year`, `total_pages`, `status`, '
         '`started_at`, `finished_at`', 'An entry: `id`, `status`, `allowed_statuses`, `current_page`, `total_pages`, `progress_percent`, `has_text`, '
         '`text_progress`, `rating`, `started_at`, `finished_at`, `book`. 409 if the book is there.', '2.0'],
        ['`/api/library/{id}`', 'GET, PATCH, DELETE', 'PATCH: `status`, `rating`, `total_pages`, `started_at`, `finished_at`', 'The entry; 204 for DELETE.', '2.0'],
        ['`/api/library/{id}/progress`', 'POST', '`page` (0 to 20,000)', 'The entry and `meta`: `pages`, `reached_end`, `streak`.', '3.0'],
        ['`/api/library/{id}/logs`', 'GET', '`page`', 'A list: `id`, `from_page`, `to_page`, `pages`, `logged_on`, `created_at`.', '3.0'],
        ['`/api/stats/summary`', 'GET', '-', '`today`, `streak`, `pace`, `month`, `year`, `forecasts`.', '4.0'],
        ['`/api/stats/daily`', 'GET', '`from`, `to` or `days` (1 to 366)', 'A list: `date`, `pages`; `meta`: `from`, `to`, `total_pages`.', '4.0'],
        ['`/api/goals/{year}`', 'GET, PUT, DELETE', 'PUT: `target_books` (1 to 1,000)', '`year`, `target_books`, `finished_books`, `expected_books`, `due_books`, '
         '`remaining_books`, `on_track`.', '4.0'],
        ['`/api/quotes`', 'GET', '`favorite`, `book`, `type`, `page`', 'A list: `id`, `type`, `content`, `note`, `page`, `is_favorite`, `anchor`, `created_at`, '
         '`user_book_id`, `book`.', '5.0'],
        ['`/api/quotes/daily`', 'GET', '`favorite`', 'A quote or `null`.', '5.0'],
        ['`/api/library/{id}/quotes`', 'POST', '`type`, `content` (up to 2,000), `note`, `page`, `is_favorite`, `anchor`: `chapter`, `start`, `end`',
         '201 and the quote. 422: `quotes.page_above_total`, `reader.no_text`, `reader.anchor_mismatch`, `reader.anchor_out_of_range`.', '5.0, 6.0'],
        ['`/api/quotes/{id}`', 'GET, PATCH, DELETE', 'PATCH: `type`, `content`, `note`, `page`, `is_favorite`', 'The quote; 204 for DELETE.', '5.0'],
        ['`/api/library/{id}/text`', 'POST, GET, DELETE', 'POST: form data, the field `file` (EPUB, up to 15 MB)',
         'The text: `id`, `user_book_id`, `source_name`, `chapters_count`, `characters_count`, `position`, `progress`, `chapters`. 422 `reader.*`; 503 `reader.busy`.', '6.0'],
        ['`/api/book-texts/{id}/chapters/{position}`', 'GET', '-', '`position`, `title`, `html`, `characters`, `highlights`.', '6.0'],
        ['`/api/book-texts/{id}/position`', 'PUT', '`chapter`, `chapter_progress` (0 to 1)', 'The text with the new position; `meta.user_book`: the entry.', '6.0, 3.0'],
        ['`/api/feed`', 'GET', '`scope` (`following` or `all`), `page`', 'A list of posts.', '7.0'],
        ['`/api/profiles`', 'GET', '`q` (2 to 50 characters)', 'A list: `handle`, `name`, `bio`, `is_me`, `is_following`.', '7.0'],
        ['`/api/profiles/{handle}`, `.../posts`', 'GET', '`page`', 'A profile: `handle`, `name`, `bio`, `posts_count`, `followers_count`, `following_count`, '
         '`is_following`, `is_me`, `is_blocked`; a list of posts.', '7.0'],
        ['`/api/profiles/{handle}/follow`', 'PUT, DELETE', '-', 'The profile.', '7.0'],
        ['`/api/posts`', 'POST', 'A quote: `type`, `quote_id`, `comment`. A review: `type`, `user_book_id`, `rating` (1 to 5), `content`',
         '201 and the post: `id`, `type`, `content`, `comment`, `rating`, `page`, `likes_count`, `comments_count`, `liked_by_me`, `is_mine`, `is_hidden`, `author`, `book`.', '7.0'],
        ['`/api/posts/{id}`', 'GET, PATCH, DELETE', 'PATCH: `comment`, `rating`, `content`', 'The post; 204 for DELETE.', '7.0'],
        ['`/api/posts/{id}/like`', 'PUT, DELETE', '-', 'The post with the exact counter.', '7.0'],
        ['`/api/posts/{id}/comments`', 'GET, POST', 'POST: `content` (up to 1,000)', 'A comment: `id`, `post_id`, `content`, `created_at`, `author`, `can_delete`.', '7.0'],
        ['`/api/comments/{id}`', 'DELETE', '-', '204.', '7.0'],
        ['`/api/books/{id}/reviews`', 'GET', '`page`', 'A list of reviews; `meta`: `my_review_id`, `reviews_count`, `average_rating`.', '7.0'],
        ['`/api/posts/{id}/report`, `/api/comments/{id}/report`', 'POST', '`reason` (`spam`, `abuse`, `other`), `note` (up to 500)', '201 and the report: `id`, `reason`, '
         '`status`, `created_at`.', '8.0'],
        ['`/api/blocks`, `/api/blocks/{handle}`', 'GET; PUT, DELETE', '-', 'A list of readers; the profile; 204.', '8.0'],
    ]
    d.table(['Route', 'Method', 'Data in', 'Data out', 'Process'], api, [20, 9, 25, 38, 8], size=18,
            align=['left', 'left', 'left', 'left', 'center'])

    d.p(
        'The modules of the server part call each other through the methods of the classes. The main interfaces are given in '
        f'{ctx.ref("tab:interfaces_internal")}. They are the interfaces that the structure charts of Section 10 and Section 11 use.'
    )
    ctx.table(d, 'tab:interfaces_internal', 'Main interfaces between the modules of the server part')
    inner = [
        ['`UpdateProgress::handle(UserBook, int $page)`', 'Controller, `SaveReadingPosition`', '`ProgressUpdate` (`userBook`, `log`). `ValidationException` with '
         '`library.progress_finished`, `library.page_above_total`.'],
        ['`RecordReadingLog::handle(UserBook, int $from, int $to)`', '`UpdateProgress`, `UpdateUserBook`', 'The new `ReadingLog`, or `null` when the page did not change.'],
        ['`ChangeUserBookStatus::apply(UserBook, BookStatus, ?Carbon, ?Carbon)`', '`UpdateProgress`, `UpdateUserBook`', 'Nothing; changes the model without saving it. '
         '`library.invalid_transition`, `library.total_pages_required`.'],
        ['`AddBookToLibrary::handle(User, array $data)`', '`UserBookController`', 'The new `UserBook`. `BookAlreadyInLibraryException` (409), `CatalogUnavailableException` (503).'],
        ['`OpenLibraryClient::search(string $query, int $limit)`', '`CatalogSearchController`', 'A list of `CatalogEntry`; cached for 24 hours. '
         '`CatalogUnavailableException`.'],
        ['`SaveQuote::create(UserBook, array $data)`', '`QuoteController`', 'The `Quote`. `quotes.page_above_total`; the anchor errors of the next row.'],
        ['`ResolveQuoteAnchor::handle(UserBook, array $anchor, string $content)`', '`SaveQuote`', 'The columns of the anchor: `book_text_id`, `chapter_position`, '
         '`start_offset`, `end_offset`. `reader.no_text`, `reader.anchor_out_of_range`, `reader.anchor_mismatch`.'],
        ['`ImportBookText::handle(UserBook, string $path, string $sourceName)`', '`BookTextController`', 'The `BookText` with its chapters. `ValidationException` '
         '`reader.*` on the field `file`; `ImportBusyException`.'],
        ['`EpubImporter::open(string $path)`', '`ImportBookText`', '`EpubBook`; `EpubImportException` with a `ReaderError`.'],
        ['`EpubBook::chapters()`', '`ImportBookText`', 'A generator of `ParsedChapter` (`position`, `title`, `html`, `characters`).'],
        ['`HtmlSanitizer::sanitize(DOMDocument)`', '`EpubBook`', '`SanitizedChapter` (`html`, `characters`, `heading`).'],
        ['`SaveReadingPosition::handle(BookText, int $chapter, float $chapterProgress)`', '`ReadingPositionController`', '`PositionSaved` (`text`, `userBook`). '
         '`reader.chapter_out_of_range`.'],
        ['`PublishPost::handle(User, array $data)`', '`PostController`', 'The `Post`, a snapshot of a quote or a review. `social.review_exists`.'],
        ['`ReportContent::handle(User, Post|Comment, array $data)`', '`ReportController`', 'The `Report`. The third open report calls `ModerateContent::hide`. '
         '`social.cannot_report_own`, `social.already_reported`.'],
        ['`ModerateContent::hide`, `restore`, `resolveReports` (Post|Comment)', '`ReportContent`, the commands `moderation:*`', 'A flag (changed or not), or the number of closed reports.'],
        ['`ReadingStatistics(User)`: `dailyPages`, `streak`, `pace`, `booksFinished`, `forecasts`', '`StatsController`, `GoalProgress`, `ReadingProgressController`',
         'Arrays and numbers for the time zone of the reader.'],
        ['`GoalProgress::handle(User, int $year)`', '`GoalController`', 'An array: `year`, `target_books`, `finished_books`, `expected_books`, `due_books`, '
         '`remaining_books`, `on_track`.'],
    ]
    d.table(['Interface', 'Called by', 'Result and errors'], inner, [38, 22, 40], size=19)
