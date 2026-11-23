"""Section 12: user interface. The prototype of Bookshelf is the working application itself, so the section documents the
interface as it is in the code of the client (frontend/src). Figures: diagrams/ui.py."""

from __future__ import annotations

from docxkit import LANDSCAPE_TEXT_WIDTH

# Counted from frontend/src/**/*.spec.ts with a script (lines that start with `it(` or `test(`); 16 more declarations are
# tables (`it.each`, `test.each`) that run several cases each, so the number of cases that run is higher.
TESTS = 1181
SPEC_FILES = 76


def tokens(d, ctx) -> None:
    ctx.table(d, 'tab:ui_tokens', 'Colour tokens of the interface (frontend/src/style.css)')
    d.table(
        ['Token', 'Light', 'Dark', 'Use'],
        [
            ['`paper`', '#f3f5f8', '#0e121a', 'Background of the page'],
            ['`surface`', '#ffffff', '#161c27', 'Cards, fields, the tab bar'],
            ['`sunken`', '#e6eaf1', '#212938', 'Avatar, segmented control, chart days without reading'],
            ['`line`', '#d8dde6', '#2a3243', 'Borders and dividers'],
            ['`ink`', '#141b2b', '#e6e9f0', 'Text'],
            ['`ink-muted`', '#5b6479', '#a0a9bb', 'Secondary text, hints, unlit tabs'],
            ['`accent`', '#2d47c8', '#93a5ff', 'Filled buttons, links, progress bars, the lit tab'],
            ['`on-accent`', '#ffffff', '#0b1020', 'Text on the accent colour'],
            ['`danger`', '#b42318', '#f2837a', 'Errors and destructive buttons'],
            ['`danger-soft`', '#fbe2de', '#3a1a17', 'Background of error notices and of the offline banner'],
        ],
        [20, 14, 14, 52], size=22,
    )


def screens(d, ctx) -> None:
    ctx.table(d, 'tab:screens', 'Screens of the client: routes, views, purpose and the calls of the server part')
    rows = [
        ['`/login`\n(`login`)', 'guest only',
         '`LoginView`\n`AuthLayout`, `FormField`',
         'Sign in (“Вхід”): email and password. A notice “Session expired. Sign in again.” is shown after `?expired=1`.',
         '`GET /api/sanctum/csrf-cookie` (once)\n`POST /api/auth/login`\n`GET /api/me`', '1.0'],
        ['`/register`\n(`register`)', 'guest only',
         '`RegisterView`\n`AuthLayout`, `FormField`',
         'Registration (“Реєстрація”): name, email, password of at least 8 characters; the time zone of the device is sent.',
         '`POST /api/auth/register`\n`GET /api/me`', '1.0'],
        ['`/`\n(`home`)', 'signed in',
         '`HomeView`\n`ReadingCard`, `PageJumpForm`, `ProgressBar`, `DailyQuoteCard`, `SkeletonList`, `EmptyState`',
         'Reading (“Читаю”): the books being read, the quick update of the page (“+10”, “+25”, “I’m on page…”), the quote of the day.',
         '`GET /api/library`\n`POST /api/library/{id}/progress`\n`PATCH /api/library/{id}` (finish)\n`GET /api/quotes/daily`', '3.0, 5.0'],
        ['`/library`\n(`library`)', 'signed in',
         '`LibraryView`\n`BookCard`, `EmptyState`, `SkeletonList`',
         'Library (“Бібліотека”): the books by status, four chips with the counts (“Хочу”, “Читаю”, “Прочитано”, “Відкладено”).',
         '`GET /api/library`', '2.0'],
        ['`/library/add`\n(`library-add`)', 'signed in',
         '`AddBookView`\n`BookCover`',
         'Add a book (“Додати книгу”): search in Open Library (from two characters, after a pause of 350 ms) or the form for a manual entry.',
         '`GET /api/catalog/search?q=`\n`POST /api/library`', '2.0'],
        ['`/library/:id`\n(`book`)', 'signed in',
         '`BookView`\n`ProgressBar`, `PageJumpForm`, `BookTextBlock`, `RatingStars`, `BookQuotes`, `BookReviews`, `ReadingJournal`',
         'Book card (“Книга”): status, progress, the block “Read in the app” (upload of an EPUB), pages, rating, quotes, reviews of readers, reading history, removal.',
         '`GET /api/library`, `GET /api/library/{id}`\n`PATCH`, `DELETE /api/library/{id}`\n`POST /api/library/{id}/progress`\n`GET /api/library/{id}/logs`\n`GET /api/quotes?book=`\n`GET /api/books/{book}/reviews`\n`POST`, `DELETE /api/library/{id}/text`',
         '2.0, 3.0, 5.0, 6.0, 7.0'],
        ['`/quotes`\n(`quotes`)', 'signed in',
         '`QuotesView`\n`QuoteCard`, `PublishQuoteButton`, `QuoteSource`',
         'Quotes (“Цитати”): the list with the filters “All” / “Favorites” and by book; favorite star, share, edit, open in text, publish, delete.',
         '`GET /api/library` (the filter by book)\n`GET /api/quotes?favorite=&book=&page=`\n`PATCH /api/quotes/{id}` (favorite)\n`DELETE /api/quotes/{id}`', '5.0'],
        ['`/quotes/new`\n(`quote-new`)', 'signed in',
         '`QuoteFormView`\n`SegmentedControl`',
         'New quote (“Нова цитата”): book, type (quote or insight), text, thought, page. Takes `?book=` and the text shared from another application (`?text=`).',
         '`GET /api/library`\n`POST /api/library/{id}/quotes`', '5.0'],
        ['`/quotes/:id/edit`\n(`quote-edit`)', 'signed in',
         '`QuoteFormView`',
         'Editing (“Редагування”) of a quote: the same form, the book cannot be changed.',
         '`GET /api/quotes/{id}` (not if the quote is in the store)\n`PATCH /api/quotes/{id}`', '5.0'],
        ['`/quotes/:id/share`\n(`quote-share`)', 'signed in',
         '`QuoteShareView`\n`SegmentedControl`, `drawQuoteCard` (Canvas)',
         'Quote card (“Картка цитати”): three templates, a picture of 1080 x 1350 px drawn by the browser, the system share sheet or a download.',
         '`GET /api/quotes/{id}` (not if the quote is in the store); the picture is drawn on the client', '5.0'],
        ['`/stats`\n(`stats`)', 'signed in',
         '`StatsView`\n`GoalCard`, `StatTile`, `PagesChart`, `EmptyState`',
         'Statistics (“Статистика”): the annual goal, streak, pace, pages of the month and the year, the chart of pages per day for 7, 30 or 90 days, the forecast of finishing.',
         '`GET /api/stats/summary`\n`GET /api/stats/daily?days=`\n`GET`, `PUT`, `DELETE /api/goals/{year}`', '4.0'],
        ['`/profile`\n(`profile`)', 'signed in',
         '`ProfileView`\n`InstallCard`, `ErrorNotice`',
         'Profile (“Профіль”): name, nickname (handle), about me, the time zone; links to the public profile and to the blocked readers; the card “Install”; log out.',
         '`PATCH /api/me`\n`POST /api/auth/logout`', '1.0'],
        ['`/community`\n(`community`)', 'signed in',
         '`CommunityView`\n`PostCard`, `ReaderListItem`, `ReportDialog`, `SegmentedControl`',
         'Community (“Спільнота”): the search of readers, the feed with the tabs “Following” (“Підписки”) and “All” (“Усі”), likes, the menu of a post.',
         '`GET /api/feed?scope=&page=`\n`GET /api/profiles?q=`\n`PUT`, `DELETE /api/posts/{id}/like`\n`DELETE /api/posts/{id}`\n`POST /api/posts/{id}/report`\n`GET /api/library` (links to books)', '7.0, 8.0'],
        ['`/community/new`\n(`community-new`)', 'signed in',
         '`ComposePostView`\n`RatingStars`, `SegmentedControl`',
         'New post (“Нова публікація”): a quote chosen from the own quotes with a thought, or a review (book, rating, text). Nothing is published until “Publish”.',
         '`GET /api/library`\n`GET /api/quotes`, `GET /api/quotes/{id}`\n`POST /api/posts`', '7.0'],
        ['`/community/posts/:id`\n(`community-post`)', 'signed in',
         '`PostView`\n`PostCard`, `PostEditForm`, `CommentForm`, `CommentList`, `ReportDialog`',
         'Post (“Публікація”) with the comments; the form of editing is shown by `?edit=1` for the author.',
         '`GET`, `PATCH`, `DELETE /api/posts/{id}`\n`GET`, `POST /api/posts/{id}/comments`\n`DELETE /api/comments/{id}`\n`POST /api/posts/{id}/report`, `POST /api/comments/{id}/report`', '7.0, 8.0'],
        ['`/u/:handle`\n(`reader`)', 'signed in',
         '`ReaderProfileView`\n`ReaderAvatar`, `StatTile`, `PostCard`',
         'Profile of a reader (“Читач”): nickname, about, numbers of posts, followers and followed, follow, block, posts of the reader.',
         '`GET /api/profiles/{handle}`\n`GET /api/profiles/{handle}/posts`\n`PUT`, `DELETE /api/profiles/{handle}/follow`\n`PUT`, `DELETE /api/blocks/{handle}`', '7.0, 8.0'],
        ['`/community/blocked`\n(`community-blocked`)', 'signed in',
         '`BlockedReadersView`\n`ReaderListItem`',
         'Blocked readers (“Заблоковані читачі”) with the button “Unblock”. The tab “Community” stays lit.',
         '`GET /api/blocks`\n`DELETE /api/blocks/{handle}`', '8.0'],
        ['`/read/:id`\n(`read`)', 'signed in; full screen',
         '`ReaderView`\n`ReaderTopBar`, `ReaderChapter`, `ReaderSelectionBar`, `ReaderContents`, `ReaderSettings`, `HighlightSheet`, `ReaderSheet`',
         'Reader (“Читанка”): a chapter of the uploaded book, the contents, the settings of the look, the selection of a quote, the sheet of a saved quote, the next and the previous chapter.',
         '`GET /api/library/{id}`\n`GET /api/library/{id}/text`\n`GET /api/book-texts/{id}/chapters/{n}`\n`PUT /api/book-texts/{id}/position`\n`POST /api/library/{id}/quotes` (with `anchor`)\n`DELETE /api/quotes/{id}`', '6.0, 5.0'],
        ['`/:pathMatch(.*)*`\n(`not-found`)', 'signed in',
         '`NotFoundView`\n`EmptyState`',
         'Not found (“Не знайдено”): inside the layout, so the tab bar stays; the button “To home”.',
         'none', '–'],
    ]
    d.table(['Route (name)', 'Access', 'View and main components', 'Purpose', 'Calls of the server part', 'Process'], rows,
            [17, 8, 24, 27, 33, 7], size=19, total=LANDSCAPE_TEXT_WIDTH)


def transitions(d, ctx) -> None:
    ctx.table(d, 'tab:transitions', 'Transitions between the screens')
    rows = [
        ['Router guard', 'no session, route needs sign-in', '`/login`'],
        ['Any screen', 'a request is answered 401 while the app is open', '`/login?expired=1`'],
        ['`/login`', '“Register” (“Зареєструватися”)', '`/register`'],
        ['`/register`', '“Sign in” (“Увійти”)', '`/login`'],
        ['`/login`, `/register`', 'success', '`/`'],
        ['`/profile`', '“Log out” (“Вийти”)', '`/login`'],
        ['Any header', 'round avatar', '`/profile`'],
        ['Tab bar', 'five tabs', '`/`, `/library`, `/quotes`, `/stats`, `/community`'],
        ['`/`', 'card of a book', '`/library/:id`'],
        ['`/`', '“Read” (only if the text is uploaded)', '`/read/:id`'],
        ['`/`', '“Share” under the quote of the day', '`/quotes/:id/share`'],
        ['`/library`', '“Add book”', '`/library/add`'],
        ['`/`, `/quotes/new`, `/community/new`', '“To library” (no books yet)', '`/library`'],
        ['Page “Not found”, `CrashNotice`', '“To home”', '`/`'],
        ['`/library`', 'card of a book', '`/library/:id`'],
        ['`/library/add`', '“Add”, or the manual form (also when the answer is 409: the existing entry)', '`/library/:id`'],
        ['`/library/:id`', '“Continue reading” / “Read”', '`/read/:id`'],
        ['`/library/:id`', '“All quotes”; “Add quote”', '`/quotes?book=`; `/quotes/new?book=`'],
        ['`/library/:id`', '“Write a review”; “My review”', '`/community/new?book=`; `/community/posts/:id`'],
        ['`/library/:id`', '“Remove from library” and the confirmation', '`/library`'],
        ['`/quotes`', '“Add”; “Share”; “Edit”', '`/quotes/new`; `/quotes/:id/share`; `/quotes/:id/edit`'],
        ['`/quotes`', '“Open in text” (a quote selected in the text)', '`/read/:id?chapter=&highlight=`'],
        ['`/quotes`', '“Publish”', '`/community/new?quote=`'],
        ['`/quotes/new`, `/quotes/:id/edit`', '“Save”, “Cancel”', 'the previous screen, or `/quotes`'],
        ['`/stats`', 'a row of the forecast', '`/library/:id`'],
        ['`/community`', '“Write”', '`/community/new`'],
        ['`/community`', 'name of an author; a result of the search', '`/u/:handle`'],
        ['`/community`', 'counter of comments of a post', '`/community/posts/:id`'],
        ['`/community/new`', '“Publish”', '`/community/posts/:id`'],
        ['`/community/new`', '“To quotes” (no quotes yet)', '`/quotes`'],
        ['`/community/posts/:id`', 'the post is deleted by its author', '`/community`'],
        ['`/u/:handle`', '“Edit profile” (own profile)', '`/profile`'],
        ['`/profile`', '“My public profile”; “Blocked readers”', '`/u/:handle`; `/community/blocked`'],
        ['`/read/:id`', 'back arrow; “To book” when there is no text', '`/library/:id`'],
        ['`/read/:id`', '“Open quote” in the sheet of a saved quote', '`/quotes/:id/edit`'],
    ]
    d.table(['From', 'Control', 'To'], rows, [24, 40, 36], size=20)


def states(d, ctx) -> None:
    ctx.table(d, 'tab:states', 'States of the screens')
    rows = [
        ['Start of the application (`App.vue`)',
         '`BootScreen`: the logo and the name, a pulse, until the first answer of `GET /api/me`.',
         '–',
         'No answer, or the answer 5xx: `OfflineGate` with the button “Retry”. An error inside a screen: `CrashNotice` with “Reload the page” and “To home”.'],
        ['Every screen of the layout',
         '–',
         '–',
         '`AppBanners`: “No internet. Changes will not be saved until the connection returns.” while the browser is offline; “A new version of the application is available.” with the button “Update”.'],
        ['Sign in',
         'The button is disabled while the request goes.',
         '–',
         '422: “Wrong email or password.” 429: “Too many attempts. Try again in a minute.” No network: “No connection to the server…”. Other: the general message. After a lost session: “Session expired. Sign in again.”'],
        ['Registration',
         'The button is disabled while the request goes.',
         '–',
         'Under the field: the email is taken; the password is shorter than 8 characters (also the attribute `minlength`); the message of the server for the name. Fields are `required`.'],
        ['Reading',
         '`SkeletonList`, three cards.',
         '“Nothing is being read now”, the button “To library”. The quote of the day is simply absent when there are no quotes or the request fails.',
         'The notice with “Retry”. A failed update of the page: the notice in the card and the page goes back. A page outside 0..total: “Enter a page number from 0 to N.” under the field, “Save” is disabled.'],
        ['Library',
         '`SkeletonList`, three cards.',
         'One text for each status (for example “The list is empty” with the button “Add book” for “Want”).',
         'The notice with “Retry”.'],
        ['Add a book',
         '“Searching…” (`role="status"`); nothing is searched before two characters.',
         '“Nothing found.” The link “Add manually” is always under the results.',
         '`catalog.unavailable`: “The Open Library catalog is unavailable now. Add the book manually.” `library.already_added` is not an error for the reader: the existing book is opened.'],
        ['Book card',
         'Two tall skeletons. The quotes, the reviews and the journal load apart and fail apart.',
         'Journal: “No entries yet…”. Quotes: “There are no quotes in this book yet.” Reviews: “No reviews of this book yet.”',
         '404: “This book is not in your library.” Errors of the status, of the number of pages and of the rating: the notice over the card. “Start reading” is disabled until the number of pages is known (hint “To start reading, enter the number of pages.”). Deleting asks for confirmation in place.'],
        ['Upload of a book (on the book card)',
         'The button says “Uploading and parsing the book…” and is busy (`aria-busy`).',
         'No text: the hint, “Upload EPUB” and the note about privacy.',
         'A file over 15 MB is turned away before it is sent; 413 is read the same way; the codes `reader.*` give the notice.'],
        ['Quotes',
         '`SkeletonList`.',
         '“No quotes yet”, the button “Add quote”. With a filter: “Nothing found” and “Reset filters”.',
         'The notice with “Retry”; the error of “Show more” is shown under the list and does not remove it.'],
        ['Quote form',
         '`SkeletonList` while the library and the quote load.',
         'No books: “To save a quote, first add a book to your library.” and “To library”.',
         '404: “This record is not in your quotes.” 422 under the fields: the text (1 to 2000 characters), the thought (up to 2000), the page (1 to 20000, `quotes.page_above_total`). The counter “n of 2000”.'],
        ['Quote card',
         'One tall skeleton.',
         '–',
         '404 as above. “Could not prepare the picture. Try again.” Closing the share sheet is not an error.'],
        ['Statistics',
         'One tall skeleton.',
         'No goal: “Set a goal…” and the field “How many books a year?”. No books in reading: “No books being read” for the forecast; a row without data says “Not enough data for a forecast yet”.',
         'The notice with “Retry”; the chart and the goal have their own notices. Goal: “Enter a whole number from 1 to 1000.”; `goals.year_out_of_range`.'],
        ['Profile',
         'The button is disabled while the request goes.',
         '“Saved.” (`role="status"`) is the result, not an error.',
         '422 under the fields: `social.handle_taken`, `social.handle_invalid`; the about text longer than 280 characters.'],
        ['Community',
         '`SkeletonList`; the search has its own.',
         '“Following”: “The feed is empty for now” and “Show all”. “All”: “Nobody has published anything yet” and “Write”. Search: “Nobody found”.',
         'The notice with “Retry”; “Show more” has its own. A search of one character: “Enter at least two characters.”'],
        ['New post',
         '`SkeletonList` while the library and the quotes load.',
         'No quotes: “You have no quotes yet. Save a quote to publish it.” and “To quotes”. No books: “To write a review, first add a book to your library.”',
         'Before sending: “Choose a quote from your list.”, “Choose a book from your library.”, “Give a rating from 1 to 5.”. After: `social.review_exists` and the limits of the text.'],
        ['Post, reader profile',
         'A skeleton; the comments load apart.',
         'No comments: “No comments yet. Write the first.” No posts of a reader: “No posts yet”.',
         '404: “This post does not exist or is not available to you.” / “There is no such reader, or their profile is not available to you.” A hidden post or comment has the mark “Hidden by moderation”. A comment that the server refuses: “Enter a comment of at most 1000 characters.”'],
        ['Report dialog',
         'The button “Send report” says “Sending…”.',
         '–',
         'No reason chosen: “Choose a reason for the report.” `social.already_reported`. Success: “Thank you! The report was passed to the moderators.” and “Close”.'],
        ['Reader',
         '`SkeletonList` of two tall cards; while another chapter loads, the text stays and the buttons of the chapters are disabled.',
         'No text: “There is no uploaded text for this book. Upload an EPUB file on the book page.” and “To book”.',
         'The notice with “Retry” for the book and for a chapter; 404 of the book. Selection: “The quote is too long: up to 2000 characters can be saved.”, “Saving…”, the message “Quote saved.” for 4 s, or “Quote saved, but it could not be highlighted in the text.” A failed save of the position is not shown and is repeated.'],
    ]
    d.table(['Screen', 'Loading', 'Empty', 'Error and validation'], rows, [13, 20, 25, 42], size=19, total=LANDSCAPE_TEXT_WIDTH)


def messages(d, ctx) -> None:
    ctx.table(d, 'tab:messages', 'Texts that are used by many screens (frontend/src/i18n/uk.ts)')
    d.table(
        ['Key', 'Meaning', 'Text of the interface'],
        [
            ['`offline.title`, `offline.text`', 'The server cannot be reached (`OfflineGate`)',
             '“Немає з’єднання”. “Застосунок не може зв’язатися із сервером. Перевірте інтернет і спробуйте ще раз.”'],
            ['`banners.offline`', 'The browser reports no network',
             '“Немає інтернету. Зміни не збережуться, доки зв’язок не з’явиться.”'],
            ['`banners.update`, `banners.updateAction`', 'A new version waits for the reader',
             '“Доступна нова версія застосунку.” Button: “Оновити”.'],
            ['`crash.title`, `crash.text`', 'An error while a screen is drawn (`CrashNotice`)',
             '“Щось пішло не так”. “Спробуйте оновити сторінку. Ваші дані на сервері не постраждали.”'],
            ['`apiErrors.generic`', 'Any other failure of a request',
             '“Не вдалося виконати запит. Спробуйте ще раз.”'],
            ['`apiErrors.offline`', 'The request got no answer (`NetworkError`)',
             '“Немає зв’язку із сервером. Перевірте інтернет і спробуйте ще раз.”'],
            ['`apiErrors.tooManyRequests`', 'The answer 429 (rate limit)', '“Забагато запитів. Спробуйте за хвилину.”'],
            ['`auth.sessionExpired`', 'The session ended while the application was open', '“Сесія закінчилась. Увійдіть ще раз.”'],
            ['`progress.pageInvalid`', 'The page is outside the book', '“Введіть номер сторінки від 0 до {total}.”'],
            ['`progress.reachedEnd`', 'The bookmark is on the last page', '“Дочитали до кінця! Позначити книгу прочитаною?”'],
        ],
        [26, 28, 46], size=20,
    )


def checks(d, ctx) -> None:
    ctx.table(d, 'tab:ui_checks', 'Checks of the interface')
    d.table(
        ['Check', 'How it is run', 'State'],
        [
            ['Tests of components, views, stores, the router and the service worker (Vitest, jsdom)',
             '`make test-frontend` (`npm run test`)',
             f'{TESTS} tests in {SPEC_FILES} files; all passed in a full run on 21 October 2026. Run on every pull request.'],
            ['Style and mistakes in the code (ESLint, `--max-warnings 0`)',
             '`make lint` (`npm run lint`)', 'Run on every pull request.'],
            ['Types of the client (`vue-tsc -b`)',
             '`make lint` (`npm run typecheck`)', 'Run on every pull request.'],
            ['Production build of the client and of the service worker',
             '`make build` (`npm run build`)', 'Run on every pull request (job `frontend` of `.github/workflows/ci.yml`).'],
            ['Install, offline start and update of the PWA on real phones (Android Chrome, iPhone Safari)',
             'By hand, the checklist of `docs/pwa.md`', 'Not done yet: no item of the checklist is marked.'],
            ['Native selection of text and taps on highlights in the reader on iOS and Android',
             'By hand', 'Not done yet (`docs/mvp-plan.md`, status of stage 7).'],
            ['Lighthouse, a screen reader, a test with readers',
             '–', 'Not done; not a part of the project checks.'],
        ],
        [38, 26, 36], size=20,
    )


def write(d, ctx) -> None:
    d.h1('12 User interface')
    d.p('This section describes the interface of Bookshelf: its principles, the map of the screens, the screens as wireframes, '
        'the messages and states that a reader sees, and the prototype. The title of the work includes the design of the user '
        'interface and a prototype of the software. The prototype of Bookshelf is the working application itself, the client in '
        '`frontend/` together with the server part in `backend/`, so the section documents the interface as it was designed and '
        'implemented. Every fact comes from the code of the client: the router (`frontend/src/router/`), the views, the '
        'components and the dictionaries of texts (`frontend/src/i18n/`).')
    d.p('The interface is in Ukrainian. In the figures and the tables the labels are given in English; the Ukrainian text is '
        'added in quotation marks where it matters, for example the tab “Reading” (“Читаю”).')

    # ------------------------------------------------------------------------------------------------------------------
    d.h2('12.1 Principles of the interface')
    d.p('The interface follows the points below. Each of them is visible in the code that is named next to it.')
    d.bullets([
        '**Mobile first.** The client is a PWA that is used on a phone (`manifest.webmanifest`: `display: standalone`, '
        '`orientation: portrait`). The content is one column of at most 28 rem (`max-w-md`), which is centred on a wide screen; '
        'there is no separate desktop layout, and the breakpoints of Tailwind are not changed (one class uses one: `sm:items-center` '
        'of the report dialog). `viewport-fit=cover` and `env(safe-area-inset-*)` keep the tab bar and the bars of the reader away '
        'from the home gesture bar. Double tap does not zoom (`touch-action: manipulation`).',
        '**A bottom tab bar with five tabs:** “Reading” (“Читаю”), “Library” (“Бібліотека”), “Quotes” (“Цитати”), “Statistics” '
        '(“Статистика”) and “Community” (“Спільнота”). The tab of the section stays lit on its sub-screens (the field `meta.tab` of '
        'the route). The profile is opened by the round avatar in the header of every screen; a sub-screen has a back arrow in the '
        'same header (`PageHeader.vue`). The plan of the product (`docs/mvp-plan.md`, section 8) names four tabs; the fifth was '
        'added with the community.',
        '**One task per screen.** A screen has one title and one purpose; a form with more than two fields is a screen of its own '
        '(a new quote, a new post, adding a book). The main action of a block is a filled button, the other actions are outlined. '
        'Deleting a book, a quote, a post, a comment, an uploaded text or a highlight, and blocking a reader, ask for confirmation in '
        'the same place with a red button; the application never uses the dialogs of the browser.',
        '**Touch targets.** Buttons, fields and icon buttons are made at least 44 px high (`min-h-11`, `size-11`; 127 places). The '
        'exceptions are the chips and the small buttons of a header or a chart (`min-h-10`, 40 px), the round avatar (`size-10`), the '
        'stars of a rating (`size-9`, 36 px), the button of the update banner (`min-h-9`), the text links (“All quotes”, “My review”, '
        'the title of a book under a quote) and the button “Retry” inside a notice. Numeric fields have `inputmode="numeric"`, the '
        'search field `enterkeyhint="search"`, the fields of the account have `autocomplete`.',
        '**Fast feedback.** Three changes are shown at once and the request follows in the background: the page of a book that is being read, '
        'the star of a favorite quote and the heart of a post; if the server refuses, the old value comes back. The requests for one '
        'book go in a queue, and taps in a row on “+10” add up to one note with the pages gained and, from two days on, the streak; '
        'the note stays 3.5 s. Every other change waits for the answer of the server, and most buttons are disabled meanwhile.',
        '**Offline.** The service worker caches only the application shell, never the data of a reader (`docs/pwa.md`), so without '
        'a network the application opens but has no data. `AppBanners` shows the strip “No internet…” while the browser is offline; '
        '`OfflineGate` replaces the interface when the first question of the application (`GET /api/me`) gets no answer or a 5xx '
        'answer, and the reader is not sent to the sign-in form because “not signed in” would be a guess; `BootScreen` is shown '
        'until that answer comes. A request without an answer fails after 20 s (120 s for the upload of a book). A new version '
        'is announced by a banner with the button “Update” and is applied only when the reader agrees.',
        '**The reader has controls of its own.** It is a full screen outside the layout with the tab bar. The top bar has the back '
        'arrow, the contents and the button “Aa”; the size of the font (14 to 30 px), the line spacing (1.3 to 2.1), the typeface '
        '(serif or sans-serif) and the theme (“As app”, “Light”, “Sepia”, “Dark”) are kept on the device (`localStorage`, key '
        '`bookshelf.reader.settings`) and apply to the reader only. The position is sent every 15 s, when the page is hidden and '
        'when the reader leaves. The text is selected by the browser itself; the bar “Save quote” appears over the bottom edge.',
        '**Language.** Only Ukrainian: the texts are in `i18n/uk.ts`, `i18n/social.uk.ts` and `i18n/reader.uk.ts` (vue-i18n, the '
        'locale and the fallback are `uk`). A counted noun has three forms (1 день, 2 дні, 5 днів; `ukrainianPlural`), dates and '
        'numbers are formatted with `Intl` for `uk-UA`. The server part sends codes of errors and never texts, so a second language '
        'would need only one more dictionary (Section 12.4).',
        '**Themes.** The light and the dark theme follow the setting of the phone (`prefers-color-scheme`); the application has no '
        'switch, only the reader has its own theme. The colours are the tokens of ' + ctx.ref('tab:ui_tokens') + '. Titles and '
        'quotes are set in a serif face (`font-display`: Iowan Old Style, Palatino Linotype, Georgia), the rest in the system '
        'sans-serif face. The quote card is a picture that leaves the application, so its templates “Light”, “Dark” and “Accent” have '
        'fixed colours.',
    ])
    d.p('The accessibility features that are really in the code are these:')
    d.bullets([
        'the page has `lang="uk"` and the title of every screen (“screen · Bookshelf”, set by the router);',
        'every field has a `label`; an invalid field has `aria-invalid` and `aria-describedby` that names its message;',
        'errors are `role="alert"`, states and results are `role="status"`, the feedback of the progress is `aria-live="polite"`; '
        'the lit tab has `aria-current="page"`, toggles have `aria-pressed`, progress bars have `role="progressbar"` with the value;',
        'the report dialog and the sheets of the reader are `role="dialog"` with `aria-modal`; they take the focus, keep Tab inside, '
        'close on Escape and give the focus back (tests: `ReportDialog.spec.ts`, `ReaderSheet.spec.ts`); the menu of a post has '
        '`aria-expanded` and closes on Escape;',
        'a highlight in the text of the reader is a focusable button (`role="button"`, `tabindex="0"`, a name) that opens with Enter or Space;',
        'decorative icons are `aria-hidden`; the skeleton and the counters of likes have texts for screen readers (`sr-only`); '
        'the pulse of the skeleton and of the splash screen, the progress bar and the flash of a highlight stop with the setting '
        '“reduce motion”.',
    ])
    d.p('No automated accessibility checker (axe, Lighthouse) and no screen reader were used on the interface; the list above '
        'comes from reading the code and from the tests of the focus. This is stated again in Section 12.5.')
    tokens(d, ctx)

    # ------------------------------------------------------------------------------------------------------------------
    d.h2('12.2 Navigation')
    d.p('The map of the screens is in ' + ctx.ref('fig:ui_map') + '. `App.vue` chooses first what to show: `OfflineGate` when the '
        'server cannot be reached, `BootScreen` while the first answer is awaited, the router view otherwise. The guard of the '
        'router (`router/index.ts` and `redirectFor` in `router/access.ts`) sends a visitor who is not signed in to `/login` from every '
        'route that needs a session, and a signed-in reader from `/login` and `/register` to `/`. The field `requiresAuth` is set on the '
        'layout route and on `/read/:id`, so it also covers the page “Not found”. While the server cannot be reached nobody is '
        'redirected. A visitor has the two screens of `guestOnly`, and the moderator has no screen at all, as in the preliminary '
        'design. The preliminary design also lists the recovery of a password among the actions of a visitor; the client has no screen '
        'for it, and the server part enables only the feature `registration` of Fortify (`config/fortify.php`), so the recovery of a '
        'password is not a part of the prototype.')
    d.p('The profile, the list of blocked readers and the page “Not found” are outside the five tabs; the list of blocked readers '
        'is opened from the profile but keeps the tab “Community” lit. The screens of the layout have the tab bar; the reader '
        'has none.')
    d.p('Every route is listed in ' + ctx.ref('tab:screens') + ': the view and its main components, the purpose, the calls of the '
        'server part and the process of the preliminary design that the screen serves. The calls are taken from the files in '
        '`frontend/src/api/` and from the stores that call them.')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:ui_map', 'ui_map.png', 'Map of the screens and the transitions between them', landscape=True)
        screens(d, ctx)
    d.p('The links between the screens are listed in ' + ctx.ref('tab:transitions') + '. Three screens keep their state in the '
        'address, so that the back button returns to the same list: the library (`?status=`), the quotes (`?favorite=` and '
        '`?book=`) and the community (`?scope=` and `?q=`). The address `/read/:id?chapter=&highlight=` is only the way in from '
        'a quote; the reader replaces it by `/read/:id` as soon as the chapter is shown. The title of the browser tab is set from the '
        'route by `router.afterEach`.')
    transitions(d, ctx)

    # ------------------------------------------------------------------------------------------------------------------
    d.h2('12.3 Wireframes of the main screens')
    d.p('The wireframes are low-fidelity drawings of phone screens. They were made from the views, so they keep the order of '
        'the blocks and the buttons that a screen really has; lists are shown with one or two items, and the text that comes from '
        'the data is shown as grey bars. A screen that is longer than a phone is drawn in parts or is cut at the bottom edge, like a '
        'scrolled screen. A block with a dashed outline appears only in some cases (a hint, an error, a confirmation); a red text is '
        'a destructive action; a button with a thick outline is the chosen option; a dark pill is the chosen chip. The labels '
        'are the English meaning of the Ukrainian ones; the Ukrainian text of the main controls is in ' + ctx.ref('tab:screens') +
        ' and in Section 12.4.')
    d.p('Twenty-one screens, sheets and dialogs are drawn on 24 phones in six figures: ' + ctx.ref('fig:ui_main') + ' (sign in, “Reading”, the library, adding a '
        'book), ' + ctx.ref('fig:ui_progress') + ' (the book card and the update of the progress), ' + ctx.ref('fig:ui_stats') + ' (statistics, '
        'quotes, the quote card), ' + ctx.ref('fig:ui_reader') + ' (the reader), ' + ctx.ref('fig:ui_community') + ' (the community) and '
        + ctx.ref('fig:ui_forms') + ' (forms and dialogs). The states of the screens are drawn in ' + ctx.ref('fig:ui_states') +
        ' in Section 12.4. The screens of the working application, taken from the prototype itself, are in Section 12.6.')
    leads = [
        ('fig:ui_main', 'ui_main.png', 'Wireframes: sign in, Reading, Library, adding a book',
         'Sign in; the screen “Reading” with two cards and the quote of the day; the library with the chips of the statuses (the fourth chip '
         'goes beyond the screen: the row scrolls); the search of a book in Open Library.'),
        ('fig:ui_progress', 'ui_progress.png', 'Wireframes: the book card (three parts) and the update of the progress on the screen “Reading”',
         'The book card is longer than a screen, so it is drawn in three parts. The fourth phone shows the states of a card on '
         'the screen “Reading”: after a tap, an invalid page and the end of the book.'),
        ('fig:ui_stats', 'ui_stats.png', 'Wireframes: statistics (two parts), quotes and the quote card',
         'Statistics in two parts (the goal and the tiles; the chart and the forecasts), the list of quotes with a typed quote and a '
         'quote selected in the text, and the quote card with the choice of the template.'),
        ('fig:ui_reader', 'ui_reader.png', 'Wireframes: the reader, the selection of a quote, the contents and the settings',
         'The reader has no tab bar. Grey bars are the text of a chapter, yellow bars are saved quotes, blue bars are the selection; '
         'the sheets “Contents” and “Appearance” cover the lower part of the screen.'),
        ('fig:ui_community', 'ui_community.png', 'Wireframes: the feed, a post with comments, the profile of a reader and a new post',
         'The feed with the search, the tabs “Following” and “All” and two posts; a post with the form of a comment; the profile of '
         'a reader; the form of a new post with a quote chosen.'),
        ('fig:ui_forms', 'ui_forms.png', 'Wireframes: the form of a quote, the profile, the sheet of a saved quote and the dialog of a report',
         'The form of a quote with an error under the field; the first part of the profile; the sheet of a saved quote with the dashed '
         'confirmation of deleting; the dialog of a report over the feed.'),
    ]
    with ctx.landscape(d):
        for key, name, title, lead in leads:
            d.p(lead, keep_next=True, line=240)
            ctx.figure(d, key, name, title, landscape=True)
    d.p('The wireframes do not repeat what only changes a block of a screen. These variants are described in the tables: the '
        'registration form (the same layout as the sign-in form with the field “Name” and the hint “At least 8 characters”); the manual '
        'form of adding a book (“Title”, “Authors”, “Pages”, “Year of publication”); the book card of a book without a text '
        '(the hint, “Upload EPUB” and the note about privacy instead of the progress of the text); the card of statistics without '
        'a goal; the second part of the profile (the card “Install”, which is shown only where the browser can install the application '
        'or on an iPhone, and the button “Log out”); the list of blocked readers; the form of editing a post.')

    # ------------------------------------------------------------------------------------------------------------------
    d.h2('12.4 Dialogue with the user')
    d.p('Every screen that loads data goes through the same states: loading, the content, an empty list, an error. They are made '
        'of a few shared components, which are named in ' + ctx.ref('fig:ui_states') + ': `SkeletonList` (placeholders in the shape of '
        'the cards that are coming, with a text “Loading…” for screen readers), `EmptyState` (a title, a sentence and, where it helps, the '
        'button of the next step), `ErrorNotice` (a red notice with “Retry” when repeating makes sense), `AppBanners` and `OfflineGate`. '
        'The states of every screen are listed in ' + ctx.ref('tab:states') + '.')
    with ctx.landscape(d):
        d.p('The same components are used on every screen: the loading of the library, an empty list of quotes, a failed request '
            'under the banners of the application, and the screen without a connection.', keep_next=True, line=240)
        ctx.figure(d, 'fig:ui_states', 'ui_states.png',
                   'States of the screens: loading, an empty list, an error with the banners, no connection', landscape=True)
        states(d, ctx)
    d.h3('Messages')
    d.p('The server part sends no texts for the reader. A business rule is a stable code `<area>.<reason>`, for example '
        '`library.total_pages_required`, either as the message of the answer or as the message of a field in a 422 answer. '
        '`apiErrorCode` in `api/errors.ts` takes the first code that matches `^(library|catalog|quotes|goals|social|reader)\\.[a-z_]+$`; '
        '`useApiErrorMessage` turns a failure into a text in this order: no answer (`NetworkError`) gives `apiErrors.offline`; a code '
        'with a text in the dictionary gives that text (`apiErrors.<code>`); the answer 429 gives `apiErrors.tooManyRequests`; '
        'anything else gives `apiErrors.generic`. The codes are in ' + ctx.ref('tab:coding_errors') + ', the texts that many screens '
        'share are in ' + ctx.ref('tab:messages') + '.')
    messages(d, ctx)
    d.h3('Validation of forms')
    d.p('Most rules are checked by the server part (the form requests) and the client shows the result next to the field. The '
        'client checks only what it can check at once and what saves a request:')
    d.bullets([
        'the attributes of the fields: `required`, `minlength` of the password, `maxlength`, `min` and `max` of the numbers, the counters '
        '“n of 2000” under the long texts;',
        'the forms of one number (the page of a book, the goal of the year) compute the validity as the reader types: an invalid '
        'value sets `aria-invalid`, shows the sentence under the field and disables “Save” (the page form also while the value '
        'is the same as before);',
        'the form of a new post checks that a quote or a book and a rating are chosen before it sends anything; the file of a book is '
        'refused before the upload when it is over 15 MB;',
        'a 422 answer is shown under the field it names (`QuoteFormView`, `ComposePostView`, `ProfileView`, `RegisterView`, '
        '`CommentForm`): a known code gives its sentence, any other message gives the fixed sentence of that field; the field '
        'is linked to its message with `aria-describedby`;',
        'a 401 answer in the middle of the work (`onUnauthorized` in `main.ts`) ends the session on the client and opens `/login` with '
        'the notice “Session expired”; the questions “who is signed in?” and the sign-in itself are not counted. A 419 answer '
        '(an old CSRF token) is repeated once with a fresh token without bothering the reader.',
    ])

    # ------------------------------------------------------------------------------------------------------------------
    d.h2('12.5 Prototype and verification')
    d.p('The prototype of Bookshelf is the working application. The client (Vue 3, TypeScript, Tailwind CSS) and the server part '
        '(Laravel) run in Docker containers. The whole stack starts on a developer’s computer with `make up` and opens at '
        '`http://localhost:8000` (`README.md`); `make fresh` fills the development database with a demo reader. The same images '
        'are built by GitHub Actions and rolled out to the virtual machine in Azure behind Caddy with HTTPS (`docs/deploy.md`). '
        'The repository documents give no public address of the application: the domain is set in the variable `DEPLOY_DOMAIN` '
        'of the repository and the rollout is skipped until the variable `DEPLOY_ENABLED` is set. Therefore this document '
        'gives no address either. The screens of the running application are shown in Section 12.6.')
    d.p('The interface was checked in the ways of ' + ctx.ref('tab:ui_checks') + '. The tests of the client are component tests with '
        '`@vue/test-utils` in jsdom; for example, they check that the tab bar lights the right tab on every route, that the '
        'sheets of the reader and the report dialog keep the focus, that a failed request gives the text of its code, and that '
        'the guard sends a guest to the sign-in form. The number of tests is the result of a full run of the suite on '
        '21 October 2026. The definition of done of the project is that `make lint` and `make test` are green (`CLAUDE.md`), '
        'and the same checks run in CI.')
    checks(d, ctx)
    d.p('Three kinds of checks are still missing. The end-to-end check on real phones (installation, the start without a network, the '
        'update banner, the share sheet of the quote card, the safe area of the tab bar) is a list of boxes in `docs/pwa.md` that '
        'nobody has marked yet; the service worker does not run in Vitest, and the browser panel that was used in development could '
        'not register it. The native selection of text and the taps on highlights in the reader were not tried on iOS and Android '
        '(`docs/mvp-plan.md`, stage 7). No Lighthouse run, no screen reader and no test with readers have been done, so the '
        'accessibility of Section 12.1 and the ease of use are not confirmed by a measurement. These items are the first things '
        'to do before the application is shown to readers.')
