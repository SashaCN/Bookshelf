"""The appendices of the report on laboratory work 4: the listing, the interface and the four guides."""

from __future__ import annotations

from listing import line_count, lines_of

LISTING = [
    ('backend/app/Actions/Library/UpdateProgress.php', 1, None,
     'The update of the reading progress: one transaction, a lock of the row, a move of the bookmark.'),
    ('backend/app/Actions/Library/RecordReadingLog.php', 1, None,
     'The record of a move of the bookmark in the reading log, in the calendar of the reader.'),
    ('backend/app/Actions/Reader/ResolveQuoteAnchor.php', 1, None,
     'The check of the place in the text from which a quote was selected.'),
    ('backend/app/Services/Epub/EpubBook.php', 1, None,
     'The chapters of an uploaded EPUB book, one at a time (a generator).'),
    ('frontend/src/composables/useTextSelection.ts', 1, None,
     'The selection of text in the reading screen (the client).'),
    ('backend/tests/Feature/Library/ProgressTest.php', 1, 41,
     'The start of the tests of the progress update (an excerpt).'),
]


def listing(d, ctx) -> None:
    d.h1('Appendix A. Program listing', page_break=True)
    d.p('The program is in the repository of the project (the folders `backend` and `frontend`). This appendix gives '
        'the text of the main modules, the same ones that the charts of the technical design show. Every file is given '
        'as it is in the repository, with the numbers of its lines; a line that is too long for the page is continued '
        'on the next line. The comments of the code are in English, as the rules of the project require.')
    for number, (path, start, end, note) in enumerate(LISTING, 1):
        total = line_count(path)
        shown = f'lines {start}-{end} of {total}' if end else f'{total} lines'
        d.h2(f'A.{number} {path} ({shown})')
        d.p(note, keep_next=True)
        d.code(lines_of(path, start, end), size=16)


def interface(d, ctx) -> None:
    d.h1('Appendix B. User interface')
    d.p('The interface of the program is made for a phone and is installed on it as a progressive web application. '
        'There is a bottom bar of five tabs (Reading, Library, Quotes, Statistics, Community), one task on a screen, and '
        'the same few components for the states “loading”, “empty”, “error” and “no connection”. The text of the '
        'interface is in Ukrainian. The screens below were taken from the running program in a window of the size of a '
        'phone (390 by 844 pixels); the captions give the meaning of each screen in English and its route. The map of the '
        'screens and the wireframes are in the technical design.')
    for key, name, title in [
        ('fig:ui1', 'proto_account_library.png', 'Sign-in, the books being read, the library'),
        ('fig:ui2', 'proto_book_quote.png', 'A book with the progress and the upload of an EPUB book, the search of the catalog, a new quote'),
        ('fig:ui3', 'proto_quotes_stats.png', 'The quotes, the quote card, the statistics and the goal'),
        ('fig:ui4', 'proto_community.png', 'The feed, a post with comments, a new post'),
        ('fig:ui5', 'proto_reader.png', 'The reading screen, the selection of a quote, the contents of a book'),
    ]:
        ctx.figure(d, key, name, f'Screens of the program: {title}', max_w=3_450_000)


def user_guide(d, ctx) -> None:
    d.h1('Appendix C. User guide', page_break=True)
    d.h2('C.1 Purpose, limits and requirements')
    d.p('Bookshelf is a reading tracker for a phone. A reader keeps a library of books, updates the page that they have '
        'reached, saves quotes and thoughts, sees the statistics and the annual goal, reads their own EPUB books in the '
        'application and, if they wish, publishes quotes and reviews for other readers. Nothing is public until the '
        'reader presses “Publish” (“Опублікувати”). The program is not a shop and does not sell or give books.')
    d.p('The reader needs a phone or a computer with a modern browser (Chrome or Edge 111 or newer, Safari 16.4 or newer, '
        'Firefox 128 or newer), the Internet and a screen of 360 pixels or more. An EPUB book can be up to 15 MB and '
        'must not be protected by DRM.')

    d.h2('C.2 Sign-up, sign-in and installation')
    d.numbered([
        'Open the address of the program. On the first visit press “Зареєструватися” (Register).',
        'Enter the name, the e-mail and a password of at least 8 characters and press “Створити акаунт” (Create an account). '
        'The program remembers the time zone of the device: the reading of a day is counted by it.',
        'Next time press “Увійти” (Sign in). The session lasts 30 days.',
        'To install the program on the phone open the screen “Профіль” (Profile). On Android press “Встановити” (Install). '
        'On an iPhone open the program in Safari, press “Поділитися” (Share) and “На початковий екран” (Add to Home Screen).',
    ])

    d.h2('C.3 Adding a book')
    d.numbered([
        'Open the tab “Бібліотека” (Library) and press “Додати книгу” (Add a book).',
        'Type the title or the author (from two characters). The program searches the catalog Open Library. Press '
        '“Додати” (Add) at the right book.',
        'If the book is not found, press “Додати вручну” (Add by hand) and fill in the title, the authors (separated by '
        'commas), the number of pages and the year, then press “Додати в бібліотеку” (Add to the library).',
        'The book has one of four statuses: “Хочу” (Want), “Читаю” (Reading), “Прочитано” (Finished) and “Відкладено” '
        '(Put aside). The buttons on the page of the book change the status; to start reading, the number of pages must '
        'be known.',
    ])

    d.h2('C.4 Reading progress')
    d.p('The screen “Читаю” (Reading) shows the books that you are reading. Press “+10” or “+25”, or type the number in the '
        'field “Я на сторінці…” (I am on page…). The program answers with the number of pages that this move was worth and '
        'the number of days in a row that you have read. The page can be corrected downwards. When you reach the last '
        'page, the program offers “Позначити прочитаною” (Mark as finished). A finished book does not accept a new page: '
        'return it to “Читаю” first. The history of the moves is on the page of the book, in “Історія читання”.')

    d.h2('C.5 Quotes and thoughts')
    d.numbered([
        'Open the tab “Цитати” (Quotes) and press “Додати” (Add). Choose the book and the type (“Цитата” or “Інсайт”), '
        'type the text (up to 2000 characters), your thought and the page.',
        'The star puts a quote among the favorites. The filters “Усі”, “Улюблені” and the choice of a book narrow the list.',
        '“Поділитися” (Share) makes a card with the quote in one of three templates (light, dark, accent) that you can '
        'send to another application or save as a picture.',
        'You can also select text in another application and share it to Bookshelf: the form of a new quote opens with '
        'the text.',
    ])

    d.h2('C.6 Statistics and the annual goal')
    d.p('The tab “Статистика” (Statistics) shows the goal for the year (the number of books), the streak of days, the pace '
        '(pages a day over the last 14 days), the pages of this month and of this year, the chart of the pages a day for 7, '
        '30 or 90 days, and the forecast “Коли дочитаю” (When I will finish) for every book that you are reading. To set a '
        'goal, type the number of books and press “Зберегти”. The program says whether you are on schedule: it compares '
        'the finished books with the part of the goal that is due by today.')

    d.h2('C.7 Reading a book in the program')
    d.numbered([
        'On the page of a book find the block “Читати в застосунку” (Read in the app) and press “Завантажити EPUB” '
        '(Upload EPUB). The program checks the file, keeps only its text and says whether the book was accepted. '
        'The file itself is not kept.',
        'Press “Читати” (Read). Use “Зміст” (Contents) to go to a chapter and “Налаштування вигляду” (Settings) to change '
        'the size of the font, the spacing, the typeface and the theme.',
        'To save a quote, select a fragment of the text: the bar “Зберегти цитату” (Save the quote), “Додати думку” '
        '(Add a thought), “Скасувати” (Cancel) appears. The saved quote is highlighted in the text, and from the list of '
        'the quotes you can come back to its place.',
        'The place where you stopped is saved by itself. If you have got further than the page of the book says, the '
        'page moves forward; it never moves back.',
    ])

    d.h2('C.8 Community')
    d.numbered([
        'Open the tab “Спільнота” (Community). The feed has two tabs: “Підписки” (Following) and “Усі” (All).',
        'To publish a quote, press “Опублікувати” (Publish) at the quote. To write a review, choose a book of your library, '
        'a rating from 1 to 5 and a text. A post keeps a copy of the text; changing or deleting your own quote does '
        'not change the post. There is one review of a book for a reader.',
        'A post can be liked and commented (a comment is up to 1000 characters). Another reader can be found by the '
        'nickname or the name, followed (“Підписатися”) or blocked (“Заблокувати”): a blocked reader and you do not '
        'see the posts of each other.',
        '“Поскаржитись” (Report) at a post or a comment sends a report with a reason. Three reports from different '
        'readers hide the post until a moderator looks at it; the author still sees it.',
    ])
    ctx.table(d, 'tab:limits', 'Limits of the actions of a reader')
    d.table(
        ['Action', 'Limit'],
        [
            ['New posts', '20 an hour'],
            ['Comments', '60 an hour'],
            ['Likes and follows', '120 a minute'],
            ['Reports', '20 an hour'],
            ['Search of books and of readers', '30 a minute'],
            ['Upload of an EPUB book', '20 an hour; 30 books and 40 million characters in all'],
        ],
        [5.0, 6.0],
    )

    d.h2('C.9 Messages of the program')
    d.p('The messages are in Ukrainian. ' + ctx.ref('tab:messages') + ' gives the main ones and what to do.')
    ctx.table(d, 'tab:messages', 'Messages of the program and the actions of the reader')
    d.table(
        ['Message (meaning)', 'What to do'],
        [
            ['“Немає з’єднання” (no connection with the server)', 'Check the Internet and press “Повторити” (Retry).'],
            ['“Сесія закінчилась. Увійдіть ще раз.” (the session has ended)', 'Sign in again.'],
            ['“Забагато запитів. Спробуйте за хвилину.” (too many requests)', 'Wait a minute.'],
            ['“Каталог Open Library зараз недоступний.” (the catalog is unavailable)', 'Add the book by hand.'],
            ['“Книга вже прочитана…” (a finished book does not accept progress)', 'Return the book to “Читаю”.'],
            ['“У книзі немає такої сторінки.” (the page is above the number of pages)', 'Type a page from 0 to the number of pages.'],
            ['An EPUB book is refused (not an EPUB, protected, too large, empty)', 'Use another file; the message names the reason.'],
        ],
        [6.0, 5.0],
    )


def system_guide(d, ctx) -> None:
    d.h1('Appendix D. System programmer’s guide')
    d.h2('D.1 Purpose and composition')
    d.p('This guide is for the person who installs, configures and checks the program for a concrete use: on a '
        'developer’s computer and on a server. The program is a set of containers of one Docker Compose project: '
        '`web` (nginx, the files of the client and a proxy to the API), `app` (PHP-FPM and the Laravel application), '
        '`mysql` (MySQL 8.4) and, in production, `caddy` (HTTPS).')

    d.h2('D.2 Requirements')
    ctx.table(d, 'tab:requirements', 'Requirements for the installation')
    d.table(
        ['Place', 'Requirements'],
        [
            ['Developer’s computer', 'Docker with Compose; about 2 GB of free disk; the ports 8000 (the program) and 3308 (MySQL). Nothing else: PHP, Node and MySQL run in the containers.'],
            ['Server', 'Ubuntu Server 24.04 (x64), 2 vCPU, 1 GB of memory (a swap of 2 GB is created by the script), a disk of 30 GB, a static address, the ports 22, 80 and 443 open.'],
            ['Domain', 'A name that points to the server (a DNS A record, or a name of the form `20-1-2-3.sslip.io`).'],
            ['Delivery', 'A GitHub repository with Actions and the container registry GHCR; a pair of SSH keys for the deployment.'],
        ],
        [3.0, 8.6],
    )

    d.h2('D.3 Installation for development')
    d.code(['make up        # build and start the containers, install the dependencies, run the migrations',
            'make fresh     # DESTRUCTIVE: recreate the development database and add a demo reader',
            'make down      # stop the containers (the data stay)'], size=20)
    d.p('The first start installs the PHP and Node dependencies and takes a couple of minutes. The program opens at '
        '`http://localhost:8000`, the description of the API (Scramble) at `http://localhost:8000/docs/api`, MySQL at '
        '`127.0.0.1:3308`. If a port is taken, put `WEB_PORT` or `MYSQL_PORT` into a `.env` file in the root of the '
        'repository. The settings of the development environment are in `docker-compose.yml`.')

    d.h2('D.4 Installation on a server')
    d.numbered([
        'Create a virtual machine (Ubuntu 24.04, x64) with a static address and open the ports 22, 80 and 443.',
        'Copy `deploy/bootstrap-server.sh` to the server and run `sudo bash bootstrap-server.sh <domain>`. The script '
        'installs Docker, creates `/opt/bookshelf`, creates a swap file when the memory is small and writes '
        '`production.env` with generated secrets. An existing `production.env` is not changed.',
        'In the repository create the environment `production` with the secrets `DEPLOY_HOST`, `DEPLOY_USER`, '
        '`DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` and the repository variables `DEPLOY_DOMAIN` and `DEPLOY_ENABLED=true`.',
        'Make a commit to the main branch. When the checks are green, the workflow publishes the images, copies the '
        'deployment files to the server, runs `rollout.sh` and checks that `https://<domain>/up` answers.',
    ])
    ctx.table(d, 'tab:envvars', 'Settings of the production stack (the file production.env)')
    d.table(
        ['Name', 'Meaning'],
        [
            ['`APP_DOMAIN`', 'The domain; Caddy gets a certificate for it and the application builds its address from it.'],
            ['`APP_KEY`', 'The key of the application (a generated secret).'],
            ['`DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`', 'The passwords of the database (generated).'],
            ['`SESSION_SECURE_COOKIE`', 'The session cookie is sent only over HTTPS (true by default).'],
            ['`SANCTUM_STATEFUL_DOMAINS`', 'The domains of the single-page application (the domain by default).'],
            ['`IMAGE_REPO`, `IMAGE_TAG`', 'The repository and the tag of the images (given by the deployment).'],
        ],
        [4.2, 7.0],
    )
    d.p('The file `production.env` is created on the server only, is readable by its owner only and is never put into the '
        'repository.')

    d.h2('D.5 Update, rollback and backup')
    d.bullets([
        'Update: a merge into the main branch. The new images are pulled, the stack is restarted and the migrations run '
        '(`php artisan migrate --force`).',
        'Rollback of the code: run an old successful run of the workflow again, or `bash /opt/bookshelf/rollout.sh '
        'ghcr.io/<owner> <commit>` on the server. The migrations are not rolled back by themselves.',
        'Backup: `/opt/bookshelf/backup.sh` makes a compressed dump of the database in `/opt/bookshelf/backups` and deletes '
        'the dumps older than 14 days. For a nightly run add the line `0 3 * * * /opt/bookshelf/backup.sh` to `crontab -e`. '
        'The dumps are on the same disk, so copy them off the server from time to time with `scp`.',
        'Logs: `docker compose --env-file production.env -f docker-compose.prod.yml logs -f app caddy`.',
    ])

    d.h2('D.6 Checking the installation')
    d.p('The address `/up` answers 200 when the application works. After the installation open `https://<domain>`, '
        'register a reader and add a book. The command `docker compose ps` shows four containers, and `free -m` shows the '
        'memory: on a server of 1 GB a part of the stack goes to the swap, which is normal while the program answers.')

    d.h2('D.7 Typical problems')
    ctx.table(d, 'tab:problems', 'Typical problems of the installation')
    d.table(
        ['Symptom', 'What to check'],
        [
            ['`exec format error` in the logs of `app`', 'The images are built for another architecture than the server (they are for x64).'],
            ['The site does not open, errors of the certificate in `caddy`', 'The domain must point to the server, and the ports 80 and 443 must be open in the network rules.'],
            ['`mysql` restarts or the server hangs', 'Not enough memory: `free -m`, `docker stats`, `swapon --show`.'],
            ['The sign-in is not kept', '`APP_DOMAIN` in `production.env` must be right; the session cookie works only over HTTPS.'],
            ['The step `Copy the deployment files` fails with the code 255', 'Read the annotation of the failed step: an empty secret, an incomplete key or a wrong `DEPLOY_KNOWN_HOSTS`.'],
        ],
        [4.4, 7.2],
    )


def programmer_guide(d, ctx) -> None:
    d.h1('Appendix E. Programmer’s guide')
    d.h2('E.1 Purpose')
    d.p('This guide is for a developer who changes or extends the program. It explains how the repository is organized, '
        'how a request passes through the program, how to add a function and how to check the change.')

    d.h2('E.2 Structure of the program')
    ctx.table(d, 'tab:layout', 'Layout of the repository')
    d.table(
        ['Folder', 'Content'],
        [
            ['`backend/`', 'The Laravel API: `app/Actions`, `app/Services`, `app/Http` (controllers, requests, resources), `app/Models`, `app/Policies`, `app/Enums`, `app/Console/Commands`, `routes/`, `database/migrations`, `tests/`.'],
            ['`frontend/`', 'The Vue 3 application: `src/views`, `src/components`, `src/stores`, `src/api`, `src/composables`, `src/router`, `src/i18n`, `src/sw` and `src/pwa`.'],
            ['`docker/`, `deploy/`', 'The Dockerfiles and the configuration of nginx and MySQL; the production stack, the scripts of the server.'],
            ['`docs/`', 'The product plan, the deployment guide, the notes on the PWA and the documents of the laboratory works.'],
        ],
        [3.0, 8.6],
    )
    d.p('A request passes the layers in the order: the route (`routes/*.php`, with the limits of the rate) → the form request '
        '(access by a policy, then the rules of the fields) → the controller → an action or a service (the logic, '
        'often in one transaction) → the models → a resource (the JSON of the answer). Access to the data of a reader is '
        'decided in the policies: a foreign resource gets the answer 404. The business-rule errors are validation '
        'errors whose message is a stable code, for example `library.invalid_transition`; the client translates the code. '
        'The client keeps its state in Pinia stores and talks to the server only through `src/api`.')

    d.h2('E.3 Adding a function')
    d.p('As an example, a new action of the reader “archive a quote”:')
    d.numbered([
        'Write a migration in `backend/database/migrations` (never change an applied one) and add the column to the model.',
        'Write a form request with `authorize()` (the policy of the resource) and `rules()`.',
        'Write an action class with the method `handle`; keep the logic out of the controller. A broken rule is a '
        '`ValidationException` with a code.',
        'Add a thin controller method and a route (with a named limiter if the action can be abused) and a resource.',
        'Write the tests first or together with the code: the success, the isolation of data (another reader gets 404), '
        'the broken rules.',
        'On the client add the method to `src/api`, the state to a store, the screen or the component, and the texts to '
        '`src/i18n` (the interface is in Ukrainian). Add the tests.',
        'Run `make lint` and `make test`.',
    ])

    d.h2('E.4 Data')
    d.p('The database is MySQL 8.4 with sixteen tables (see the technical design). The tests use a separate database '
        '`bookshelf_testing`; for a parallel work give every process its own with `TEST_DB_DATABASE`. The structure is '
        'changed only by migrations. The demo reader `demo@bookshelf.test` is made by `make fresh`.')

    d.h2('E.5 Tools and commands')
    ctx.table(d, 'tab:commands', 'Commands of the developer (all run in the containers)')
    d.table(
        ['Command', 'What it does'],
        [
            ['`make up` / `make down`', 'Start and stop the stack.'],
            ['`make test`', 'The tests of the server part (Pest) and of the client (Vitest).'],
            ['`make lint`', 'Larastan (level 6), Pint, ESLint and vue-tsc.'],
            ['`make format`', 'Formats the PHP code with Pint.'],
            ['`make migrate`', 'Runs the pending migrations.'],
            ['`make shell`', 'A shell in the PHP container.'],
            ['`make build`', 'The production build of the client (and of the service worker).'],
            ['`docker compose exec app composer require ...`', 'Composer and artisan run in the container.'],
        ],
        [4.6, 7.0],
    )
    d.p('The tools are: PHP 8.4, Composer, Laravel 13, Node.js 22, npm, Vite, TypeScript, Vue, Tailwind CSS, Docker '
        'Compose, Git and GitHub Actions; for the checks Pest, Vitest, Larastan, Pint, ESLint and vue-tsc.')

    d.h2('E.6 Debugging and quality')
    d.bullets([
        'The program is debugged by tests first. A failing test names the rule that is broken; the fix goes with a test '
        'that fails without it.',
        'The logs go to the standard error of the containers: `docker compose logs -f app`. In development the debug '
        'mode of Laravel shows the stack of an error.',
        'The browser tools show the requests of the client; the panel Application shows the service worker, but the worker '
        'is not registered in development.',
        'The definition of done is `make lint` and `make test` green. CI repeats them for every pull request, and also '
        'builds the production images.',
    ])

    d.h2('E.7 Conventions and limits')
    d.bullets([
        'The code, the comments, the identifiers and the commit messages are in English; the text of the interface is in '
        'Ukrainian in `src/i18n`.',
        'Do not read or write `.env` files; the configuration is in `docker-compose.yml` and in `ci.yml`.',
        'New dependencies and the push to the main branch (it starts the deployment) are agreed with the team.',
        'The API is described by Scramble at `/docs/api`; the types of the answers on the client are written by hand in '
        '`src/types`.',
    ])


def operator_guide(d, ctx) -> None:
    d.h1('Appendix F. Operator’s guide')
    d.h2('F.1 Who the operator is')
    d.p('The program has no screen of an operator. The operator works on the server, in a console, with the commands of '
        'Artisan. There are two kinds of work: the moderation of the community (the reports of the readers) and the '
        'checking of the system. The operator needs access to the server and the right to run `docker compose`.')

    d.h2('F.2 Running a command')
    d.code(['cd /opt/bookshelf',
            'docker compose --env-file production.env -f docker-compose.prod.yml exec app php artisan <command>'], size=18)
    d.p('In development the same commands run as `docker compose exec app php artisan <command>`.')

    d.h2('F.3 Commands of the moderator')
    ctx.table(d, 'tab:opcommands', 'Commands of the moderation')
    d.table(
        ['Command', 'What it does'],
        [
            ['`moderation:reports`', 'Lists the open reports, the oldest first: the number, the object, the reason, the reporter, the note, whether the object is visible, hidden or deleted, and the text.'],
            ['`moderation:hide {type} {id}`', 'Hides a post or a comment (`post` or `comment`) from everybody but its author and closes the reports about it.'],
            ['`moderation:restore {type} {id}`', 'Shows a hidden post or comment again and closes the reports about it, so that the next single report does not hide it at once.'],
            ['`moderation:resolve {report}`', 'Marks one report as looked at without changing whether its object is hidden. If it was the last open report of a hidden object, the command warns that the object stays hidden.'],
        ],
        [4.0, 7.6],
    )

    d.h2('F.4 Handling the reports')
    d.numbered([
        'Run `moderation:reports`. The program hides a post or a comment by itself when three different readers have reported it; '
        'such an object is shown as “hidden” in the column of the state.',
        'Read the text and the reasons (`spam`, `abuse` or `other`) and the notes.',
        'If the content breaks the rules, leave it hidden and close the reports with `moderation:hide post <id>` (or `comment`).',
        'If the reports are wrong, run `moderation:restore post <id>`: the object is shown again and the reports are closed.',
        'To close one report without changing the visibility, run `moderation:resolve <report>`.',
        'Run `moderation:reports` again: the list is empty when everything is handled.',
    ])
    d.p('The real output of a work with three reports is in the report: ' + ctx.ref('tab:out_mod') + ' and '
        + ctx.ref('tab:out_restore') + '. The commands print the text of the readers safely (control characters and long '
        'texts are cut), so a hostile text cannot spoil the console.')

    d.h2('F.5 Messages of the commands')
    ctx.table(d, 'tab:opmessages', 'Messages of the commands of the moderation')
    d.table(
        ['Message', 'Meaning and action'],
        [
            ['There are no open reports.', 'Nothing to do.'],
            ['Unknown type "…": use "post" or "comment".', 'The first argument is wrong.'],
            ['The id must be a whole number…', 'The id has letters or is empty; type the number.'],
            ['There is no post with id N.', 'The object does not exist (it may have been deleted); check the list.'],
            ['Hidden. / It was hidden already.', 'The result of `moderation:hide`; the number of closed reports follows.'],
            ['Restored. / It was not hidden.', 'The result of `moderation:restore`.'],
            ['Resolving a report does not show it again…', 'The object is still hidden; run `moderation:restore` to show it.'],
        ],
        [5.0, 6.6],
    )

    d.h2('F.6 Checking the system')
    d.bullets([
        'Open `https://<domain>/up`: it answers 200 when the application works.',
        '`docker compose ... ps` shows the four containers (`caddy`, `web`, `app`, `mysql`); the database has a healthcheck.',
        '`docker compose ... logs -f app caddy` shows the errors (the level is “warning”).',
        '`free -m` and `df -h` show the memory and the disk: the server has 1 GB of memory and 30 GB of disk.',
        '`/opt/bookshelf/backup.sh` makes a dump of the database; the copies must be taken off the server by hand.',
    ])
