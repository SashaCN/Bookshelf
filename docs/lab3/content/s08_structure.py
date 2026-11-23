"""Section 8 of the technical design: the structural scheme (the composition of the software and the calls between
its parts). The figures are drawn by docs/lab3/diagrams/structure.py. The numbers of files and lines were counted on
21 October 2026 with `wc -l` on the files of backend/app, backend/routes, backend/bootstrap/app.php and frontend/src.
"""

from __future__ import annotations

COUNT_DATE = '21 October 2026'


def write(d, ctx) -> None:
    d.h1('8 Structural scheme')
    d.p(
        'A structural scheme shows the composition of the parts of the software and their interaction by control. '
        'This section describes the structure of Bookshelf as it is implemented: the names of the classes and the '
        'files are the real ones, taken from `backend/app`, `backend/routes` and `frontend/src`. The numbers of files '
        f'and lines were counted on {COUNT_DATE} with the command `wc -l`; test files are not counted in the modules.'
    )

    # ---- 8.1 -------------------------------------------------------------------------------------------------
    d.h2('8.1 Structure of the system')
    d.p(
        f'The parts of the system and the calls between them are shown in {ctx.ref("fig:structure_system")}. It '
        'refines Figure 1 of the preliminary design: the client is divided into groups of modules, and the server part '
        'into the layers that a request passes. An arrow points from the caller to the called part. A double arrow is a '
        'request over a network and its answer.'
    )
    ctx.figure(d, 'fig:structure_system', 'structure_system.png', 'Structure of the system: the parts and the calls between them')
    d.p(
        f'All parts of the figure are described in {ctx.ref("tab:structure_parts")}. The column "Calls" names the parts '
        'that the part uses.'
    )
    ctx.table(d, 'tab:structure_parts', 'Parts of the structure of the system')
    rows = [
        ['Router', '`src/router`', 'Maps the address to a screen. The guard `beforeEach` asks the server once who is signed in and sends guests to the '
         'sign-in form (`redirectFor` in `access.ts`).', 'Views, auth store'],
        ['Views', '`src/views`', '18 screens. A screen reads and changes data through a store or an API module and shows it with components.',
         'Stores, API client, building blocks'],
        ['Pinia stores', '`src/stores`', 'The state of the reader in the memory of the page: auth, library, quotes, feed, posts, comments, profiles, blocks. '
         'It is cleared when the reader signs in or out or the session ends.', 'API client'],
        ['API client', '`src/api`', '`http.ts` is the only place that sends requests to the server (`fetch`): JSON, the header `X-XSRF-TOKEN`, one retry after 419, a timeout of 20 s '
         '(120 s for a file). Six modules (auth, library, quotes, reader, social, stats) name the routes.', 'Web server'],
        ['Building blocks', '`src/components`, `composables`, `utils`, `i18n`', 'Components (45 `.vue` files), composables (position saver, text selection, '
         'reader settings, error messages), utilities (reader DOM, quote card, chart, formats) and the texts in Ukrainian.', 'Utils, i18n'],
        ['PWA modules', '`src/pwa`', 'Register the service worker in the production build, show the banner of a new version, keep the install prompt and '
         'the state online or offline.', 'Service worker'],
        ['Service worker', '`src/sw`', 'The script `/sw.js`: caches the files of the application shell, answers the opening of a screen and the requests '
         'of the built files from the cache. It never handles `/api`, `/docs` and `/up`.', 'Web server (static files)'],
        ['Web server', '`docker/web/nginx.conf`', 'nginx 1.28 in the container `web`: serves the SPA (`try_files` to `/index.html`) and passes `/api`, `/docs`, '
         '`/up` to PHP-FPM (`app:9000`). The limit of a body is 2 MB, and 25 MB for `/api/library/{id}/text`. In production Caddy in front of it '
         'gives HTTPS (`deploy/Caddyfile`).', 'Route and middleware'],
        ['Route and middleware', '`routes/api.php`, `social.php`, `reader.php`, `bootstrap/app.php`', 'Routes under `/api`. `auth:sanctum` guards all of them '
         'except sign-in and registration (Fortify, `/api/auth/*`). Sanctum in the stateful mode gives the session cookie and the CSRF check; `throttle` '
         'limits the rate.', 'Form request, controller'],
        ['Form request', '`app/Http/Requests`', '15 classes. `authorize()` calls a policy, then `rules()` checks the input. A failure is answered before the '
         'controller starts: 404 or 422.', 'Policy'],
        ['Controller', '`app/Http/Controllers`', '19 classes and the abstract `Controller`. A controller takes the checked input, calls an action or a service '
         '(or builds a read query with the scopes of a model) and returns a resource.', 'Action, service, model, policy, resource'],
        ['Policy', '`app/Policies`', '7 classes. A foreign resource, a hidden post or a blocked reader gives 404, so the API does not confirm that the '
         'resource exists.', 'Model'],
        ['Resource', '`app/Http/Resources`', '12 classes. Build the JSON of a model, with the fields that depend on the viewer (`is_mine`, `liked_by_me`, '
         '`is_following`).', '-'],
        ['Action', '`app/Actions`', '25 files. One domain operation, often in a transaction: `UpdateProgress`, `SaveQuote`, `ImportBookText`, `PublishPost`, '
         '`ReportContent`.', 'Service, model, other actions'],
        ['Service', '`app/Services`', '19 files. `Epub` (reading and cleaning of an EPUB file), `OpenLibrary` (client of the catalog) and `Stats` '
         '(statistics and goal).', 'Model, Open Library, MySQL (cache)'],
        ['Model', '`app/Models`', '12 Eloquent classes and the trait `VisibleToReaders`. The tables `author_book`, `follows`, `likes` and `blocks` have no '
         'model and are used through relations and `DB::table`.', 'MySQL'],
        ['Console commands', '`app/Console/Commands`', '`moderation:reports`, `moderation:hide`, `moderation:restore`, `moderation:resolve`. They run on the '
         'server with `php artisan` and do not pass the web server, the routes or the middleware.', 'Action `ModerateContent`, model'],
        ['MySQL', 'container `mysql`, 8.4', 'The 16 tables of the data model (stores D1 to D9 of the preliminary design) and the framework tables `sessions`, '
         '`cache` and `cache_locks`. The tables `password_reset_tokens`, `jobs`, `job_batches` and `failed_jobs` are created by the migrations, but no '
         'code uses them.', '-'],
        ['Open Library', 'external service', 'The search API `/search.json`. Only `OpenLibraryClient` calls it. The browser loads the cover pictures from '
         'the address kept in `books.cover_url`.', '-'],
    ]
    d.table(['Part', 'Place in the code', 'Role', 'Calls'], rows, [11, 17, 54, 18], size=20)

    d.p('A request of the reader passes the parts in the following order.')
    d.numbered([
        'A view calls a store or an API module. `http.ts` sends the request with the session cookie. Before a write it makes sure that the cookie '
        '`XSRF-TOKEN` exists (`GET /api/sanctum/csrf-cookie`) and repeats its value in the header `X-XSRF-TOKEN`.',
        'nginx passes the request to PHP-FPM. The middleware reads the session from the table `sessions`, checks the CSRF token and the user '
        '(`auth:sanctum`) and applies the limit of the rate.',
        'The form request calls the policy (`authorize()`) and then checks the input (`rules()`).',
        'The controller calls an action or a service. An action that changes several tables does it in one transaction.',
        'The models read and write MySQL.',
        'The controller returns a resource, which is the JSON answer. A failure has the status 401 (no session), 404 (not found or foreign), 419 (CSRF), '
        '422 (validation or a business rule with a code such as `library.total_pages_required`), 429 (the rate) or 503 (the catalog is not reachable, '
        'the import is busy). The client turns the code into a text in Ukrainian (`useApiErrorMessage`).',
    ])

    # ---- 8.2 -------------------------------------------------------------------------------------------------
    d.h2('8.2 Hierarchy of the modules of the server part')
    d.p(
        f'The modules of the server part are grouped into eight subsystems. They correspond to the processes 1.0 to 8.0 of the preliminary design. '
        f'{ctx.ref("fig:structure_server")} shows the controllers, actions, services, policies and console commands of every subsystem by class name. '
        'Form requests, resources, models, enums and exceptions are listed in '
        f'{ctx.ref("tab:modules_server")}.'
    )
    with ctx.landscape(d):
        ctx.figure(d, 'fig:structure_server', 'structure_server.png', 'Hierarchy of the modules of the server part', landscape=True)
    d.p(
        f'{ctx.ref("tab:modules_server")} lists every module group with its classes, layer, purpose and size. The size is the number of files and of '
        f'lines, counted on {COUNT_DATE}. `backend/app` has 130 files and 7,439 lines; with the four route files and `bootstrap/app.php` the server '
        'part has 135 files and 7,593 lines. The backend has 46 test files with 8,865 lines in `backend/tests`; they are not counted in the table.'
    )
    ctx.table(d, 'tab:modules_server', 'Modules of the server part')
    srv = [
        ['all', 'Route, middleware, providers',
         '`routes/api.php`, `social.php`, `reader.php`, `console.php`; `bootstrap/app.php`; `AppServiceProvider`; `FortifyServiceProvider`; `Controller` (abstract)',
         'Routes, trusted proxies, Sanctum stateful API, JSON errors for `/api`; the limits of the rate (catalog, posts, comments, likes, follows, reports, search, '
         'social writes, reader position); the morph map (`post`, `comment`) of the reports.', '8 / 241'],
        ['1.0', 'Controller, form request, resource',
         '`MeController`; `UpdateProfileRequest`; `UserResource`',
         'Shows and changes the profile of the reader: name, time zone, handle, bio.', '3 / 124'],
        ['1.0', 'Action',
         '`CreateNewUser`, `GenerateHandle`, `PasswordValidationRules` (trait), `ResetUserPassword`',
         'Registration (Fortify): checks the data and makes the first handle from the name. Sign-in and sign-out are the routes of Fortify. '
         '`ResetUserPassword` is not connected: only registration is enabled in `config/fortify.php`.', '4 / 185'],
        ['1.0', 'Model', '`User`', 'The reader; relations to the library, quotes, posts, follows and blocks; scopes of blocking.', '1 / 176'],
        ['2.0', 'Controller, form request, resource, policy',
         '`CatalogSearchController`, `UserBookController`; `CatalogSearchRequest`, `StoreUserBookRequest`, `UpdateUserBookRequest`; `BookResource`, '
         '`UserBookResource`; `BookPolicy`, `UserBookPolicy`',
         'Search in the catalog, list, add, change and delete an entry of the library. A foreign entry gives 404; `BookPolicy` hides a book entered by hand from other readers.',
         '9 / 325'],
        ['2.0', 'Action, service',
         '`AddBookToLibrary`, `UpdateUserBook`, `ChangeUserBookStatus`; `OpenLibraryClient`, `CatalogEntry`',
         'Adding a book from the catalog or by hand; changing status, rating and page count by the rules of the transitions; search in Open Library '
         'with a cache of 24 hours.', '5 / 507'],
        ['2.0', 'Model, enum, exception',
         '`Book`, `Author`, `UserBook`; `BookSource`, `BookStatus`; `BookAlreadyInLibraryException`, `CatalogUnavailableException`',
         'The shared catalog, the authors and the library entries; the allowed transitions of the status; the answers 409 and 503.', '7 / 280'],
        ['3.0', 'Controller, form request, resource',
         '`ReadingProgressController`; `UpdateProgressRequest`; `ReadingLogResource`', 'Moves the bookmark to a page; lists the reading log of a book.', '3 / 103'],
        ['3.0', 'Action',
         '`UpdateProgress`, `RecordReadingLog`, `ProgressUpdate`',
         'Moves the page under a lock of the row, starts or resumes a book, writes the reading log. `ProgressUpdate` is the result object.', '3 / 125'],
        ['3.0', 'Model', '`ReadingLog`', 'One record for each move of the page (`from_page`, `to_page`, `logged_on`).', '1 / 60'],
        ['4.0', 'Controller, form request',
         '`StatsController`, `GoalController`; `SetGoalRequest`', 'Statistics (summary, pages per day) and the annual goal.', '3 / 149'],
        ['4.0', 'Service',
         '`ReadingStatistics`, `GoalProgress`',
         'Pages per day, streak, pace, forecast, books finished; the goal of a year against the days passed. All in the time zone of the reader.', '2 / 271'],
        ['4.0', 'Model', '`ReadingGoal`', 'The goal "N books" of a year.', '1 / 36'],
        ['5.0', 'Controller, form request, resource, policy',
         '`QuoteController`; `StoreQuoteRequest`, `UpdateQuoteRequest`; `QuoteResource`; `QuotePolicy`',
         'List, show, add, change and delete quotes; the quote of the day.', '5 / 237'],
        ['5.0', 'Action', '`SaveQuote`, `PickQuoteOfTheDay`',
         'Saves a quote and checks its page against the book; calls `ResolveQuoteAnchor` for a quote selected in the text; picks the quote of the day by the date.',
         '2 / 93'],
        ['5.0', 'Model, enum', '`Quote`; `QuoteType`', 'A quote or an insight, with the anchor in a book text (`book_text_id`, `chapter_position`, `start_offset`, `end_offset`).', '2 / 109'],
        ['6.0', 'Controller, form request, resource, policy',
         '`BookTextController`, `BookChapterController`, `ReadingPositionController`; `StoreBookTextRequest`, `SaveReadingPositionRequest`; '
         '`BookTextResource`, `BookChapterResource`; `BookTextPolicy`',
         'Upload, show and delete the text of a book; one chapter with its highlights; save the reading position.', '8 / 325'],
        ['6.0', 'Action',
         '`ImportBookText`, `DeleteBookText`, `SaveReadingPosition`, `ResolveQuoteAnchor`, `PositionSaved`',
         'Import under a lock (one book at a time) and in one transaction; deleting a text and clearing the anchors; progress of the text and the page '
         'that follows it; checking of the anchor of a quote. `PositionSaved` is the result object.', '5 / 356'],
        ['6.0', 'Service (EPUB)',
         '`EpubImporter`, `EpubArchive`, `PackageReader`, `EpubBook`, `SafeXml`, `HtmlSanitizer`, `Text`; support classes `EpubLimits`, `EpubPackage`, '
         '`EpubPaths`, `ParsedChapter`, `SanitizedChapter`, `ElementNode`, `TextNode`, `EpubImportException`',
         'Opens the archive within the limits, reads the package (spine, titles), parses every XML document safely and builds a new tree of allowed elements '
         'without attributes. `Text` counts the characters in UTF-16 units.', '15 / 1,752'],
        ['6.0', 'Model, enum, exception', '`BookText`, `BookChapter`; `ReaderError`; `ImportBusyException`',
         'The text of an entry and its chapters; the stable codes `reader.*`; the answer 503 with `Retry-After`.', '4 / 240'],
        ['7.0', 'Controller, form request, resource, policy',
         '`FeedController`, `PostController`, `ProfileController`, `FollowController`, `LikeController`, `CommentController`, `BookReviewController`; '
         '`StorePostRequest`, `UpdatePostRequest`, `StoreCommentRequest`, `SearchReadersRequest`; `PostResource`, `CommentResource`, `ProfileResource`, '
         '`ReaderResource`; `PostPolicy`, `CommentPolicy`, `UserPolicy`',
         'Feed, profiles, search of readers, posts, follows, likes, comments, reviews of a book. Hidden content and readers with a block are not shown.',
         '18 / 748'],
        ['7.0', 'Action', '`PublishPost`, `DeletePost`, `FollowReader`, `LikePost`, `PostComments`',
         'Publishing a snapshot of a quote or a review; deleting a post with its reports; idempotent follow and like; the counters in the same transaction.', '5 / 233'],
        ['7.0', 'Model, trait, enum', '`Post`, `Comment`; `VisibleToReaders`; `PostType`',
         'Posts and comments; the scope `visibleTo` (hidden content, blocks); the type of a post.', '4 / 210'],
        ['8.0', 'Controller, form request, resource',
         '`ReportController`, `BlockController`; `StoreReportRequest`; `ReportResource`', 'Reports on posts and comments; the list of blocks, block and unblock.', '4 / 148'],
        ['8.0', 'Action', '`ReportContent`, `ModerateContent`, `BlockReader`',
         'One report per reader and object; the third open report hides the object; hide, restore and resolve; a block cuts the follows of both readers.', '3 / 180'],
        ['8.0', 'Console command',
         '`ModerationReports`, `ModerationHide`, `ModerationRestore`, `ModerationResolve`; traits `FindsReportedContent`, `PrintsUserText`',
         'The moderator works on the server with `php artisan moderation:*`. The commands print the text of readers in a safe form.', '6 / 284'],
        ['8.0', 'Model, enum', '`Report`; `ReportReason`, `ReportStatus`, `ReportableType`', 'A report on a post or a comment, with a reason and a status.', '4 / 96'],
        ['', '**Total**', '', '', '**135 / 7,593**'],
    ]
    shade = {i for i, row in enumerate(srv) if row[0] in ('all', '2.0', '4.0', '6.0', '8.0')}
    d.table(['Process', 'Layer', 'Classes', 'Purpose', 'Files / lines'], srv, [7, 13, 34, 37, 9], size=19,
            align=['center', 'left', 'left', 'left', 'center'], shade=shade)

    # ---- 8.3 -------------------------------------------------------------------------------------------------
    d.h2('8.3 Hierarchy of the modules of the client')
    d.p(
        f'The client is the folder `frontend/src`. {ctx.ref("fig:structure_client")} shows its groups of files and their names. The groups are '
        'combined into three layers: the screens, the state and the data, and the platform with the support modules. '
        f'The purpose of every group is given in {ctx.ref("tab:modules_client")}.'
    )
    with ctx.landscape(d):
        ctx.figure(d, 'fig:structure_client', 'structure_client.png', 'Hierarchy of the modules of the client', landscape=True)
    d.p(
        f'{ctx.ref("tab:modules_client")} gives the number of files and lines of every group, counted on {COUNT_DATE}. The client has 112 files and '
        '10,754 lines. It also has 76 test files (`*.spec.ts`, 13,751 lines) and 6 files of test data in `src/test`; they are not counted.'
    )
    ctx.table(d, 'tab:modules_client', 'Modules of the client')
    cli = [
        ['Screens', '`src/router`', '`index.ts` (routes and the guard), `access.ts` (`redirectFor`), `reader.ts` (the route `/read/:id`), `social.ts` (the routes of the community).',
         '4 / 175'],
        ['Screens', '`src/views`',
         '18 screens. Account: `LoginView`, `RegisterView`, `ProfileView`. Library: `HomeView`, `LibraryView`, `AddBookView`, `BookView`. Quotes: `QuotesView`, '
         '`QuoteFormView`, `QuoteShareView`. Statistics: `StatsView`. Reading: `ReaderView`. Community: `CommunityView`, `ComposePostView`, `PostView`, '
         '`ReaderProfileView`, `BlockedReadersView`. `NotFoundView`.', '18 / 3,535'],
        ['Screens', '`src/components`',
         '37 components of the screens: layout and navigation (`AppLayout`, `AuthLayout`, `TabBar`, `AppBanners`), cards and lists (`BookCard`, `ReadingCard`, '
         '`QuoteCard`, `PostCard`), forms and dialogs (`CommentForm`, `PostEditForm`, `ReportDialog`, `PageJumpForm`), statistics (`GoalCard`, `PagesChart`, '
         '`StatTile`) and states (`BootScreen`, `OfflineGate`, `CrashNotice`, `ErrorNotice`, `EmptyState`, `SkeletonList`).', '37 / 2,244'],
        ['Screens', '`src/components/reader`',
         'The reader: `BookTextBlock` (upload and removal of a text, on the book page), `ReaderChapter` (the html of a chapter and its highlights), `ReaderTopBar`, '
         '`ReaderContents`, `ReaderSettings`, `ReaderSelectionBar`, `HighlightSheet`, `ReaderSheet` (a modal sheet); `reader.css`.', '9 / 847'],
        ['State and data', '`src/stores`',
         'Pinia stores `auth`, `library`, `quotes`, `feed`, `posts`, `comments`, `profiles`, `blocks`; `social.ts` clears all the community stores.', '9 / 1,051'],
        ['State and data', '`src/api`',
         '`http.ts` (requests, CSRF, errors, timeouts), `errors.ts` (codes of business rules), the modules `auth`, `library` (library and catalog), `quotes`, '
         '`reader`, `social`, `stats`.', '8 / 322'],
        ['State and data', '`src/composables`',
         '`usePositionSaver` (sends the reading position every 15 s), `useTextSelection`, `useReaderSettings` (the key `bookshelf.reader.settings` of the local '
         'storage), `useApiErrorMessage`.', '4 / 332'],
        ['State and data', '`src/types`', '`api.ts`, `reader.ts`, `social.ts`: the types of the data that the API gives.', '3 / 429'],
        ['Platform and support', '`src/pwa`',
         '`updates.ts` (registers `/sw.js` in the production build, the banner of a new version), `install.ts` (the install prompt), `online.ts`.', '3 / 173'],
        ['Platform and support', '`src/sw`',
         '`sw.ts` (the entry), `worker.ts` (install, activate and fetch of the service worker), `strategy.ts` (what is cached and the name of the cache), `globals.d.ts`.',
         '4 / 151'],
        ['Platform and support', '`src/i18n`',
         '`index.ts` (vue-i18n, locale `uk`, the plural rules of Ukrainian), `uk.ts`, `reader.uk.ts`, `social.uk.ts`: the texts of the interface.', '4 / 647'],
        ['Platform and support', '`src/utils`',
         '`readerDom.ts` (offsets of the text, selections and highlights), `quoteCard.ts` (draws the quote card on a canvas), `chart.ts`, `format.ts`, `social.ts`.',
         '5 / 717'],
        ['Platform and support', '`src` (root)',
         '`main.ts` (creates the application, Pinia, router and i18n, registers the service worker, reacts to an ended session), `App.vue`, `style.css` '
         '(Tailwind CSS), `env.d.ts`.', '4 / 131'],
        ['', '**Total**', '', '**112 / 10,754**'],
    ]
    shade = {i for i, row in enumerate(cli) if row[0] == 'State and data'}
    d.table(['Layer', 'Group', 'Files', 'Files / lines'], cli, [12, 17, 59, 12], size=20, align=['left', 'left', 'left', 'center'], shade=shade)

    # ---- 8.4 -------------------------------------------------------------------------------------------------
    d.h2('8.4 Principles of the decomposition')
    d.p('The decomposition follows the rules below. Each of them is checked in the code.')
    d.bullets([
        '**One role for a layer.** A controller does not hold domain rules: it calls an action or a service. The classes of `app/Actions` and '
        '`app/Services` do not know the HTTP request. A few read-only controllers (`FeedController`, `ProfileController`, `BookReviewController`) build their '
        'queries with the scopes of the models.',
        '**Data are isolated by policies.** `UserBookPolicy`, `QuotePolicy` and `BookTextPolicy` allow only the owner; `PostPolicy`, `CommentPolicy` and '
        '`UserPolicy` hide the content of a blocked reader and moderated content. Every policy answers a foreign resource with 404 (`denyAsNotFound`). '
        'The policy is called in `authorize()` of the form request, before validation, or by `Gate::authorize` in the controller. Lists start from the '
        'relations of the signed-in reader (`$request->user()->userBooks()`, `->quotes()`) or use the scope `visibleTo`.',
        '**Input is checked in form requests.** All 15 form requests hold the rules of the fields. A broken business rule is a `ValidationException` with a '
        'stable code `<area>.<reason>`; the client translates the code.',
        '**One operation, one transaction.** An action that changes several rows uses `DB::transaction`. `UpdateProgress`, `SaveReadingPosition`, '
        '`ImportBookText` and `ReportContent` lock the row they depend on (`lockForUpdate`), so two simultaneous requests do not break the progress, the '
        'counters or the third-report rule.',
        '**An uploaded file is not trusted.** The EPUB file is read only by `ZipArchive` and `DOMDocument` (`SafeXml`), without network and entities; '
        '`HtmlSanitizer` builds a new tree, so the stored chapters are safe to show. The file itself is not stored. Only one import runs at a time.',
        '**The client does not call the Open Library API.** The search goes through `/api/catalog/search`, so the server limits the rate (30 a minute) and '
        'caches the answers. The browser loads only the cover pictures from the addresses stored in `books.cover_url` (the Content-Security-Policy of nginx '
        'allows `img-src https:`).',
        '**The client keeps no data of the reader on the device** except the session cookie and the settings of the reader (local storage). The service '
        'worker caches only the application shell; `/api` is never answered from a cache.',
        '**The dependencies of the client go one way.** Views use stores, components and API modules; stores use API modules; API modules import only '
        '`http.ts` and types; `utils` import at most types. No store imports a component or a view.',
    ])
