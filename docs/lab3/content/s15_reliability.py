"""Section 15: the design (expert) estimate of the reliability of Bookshelf."""

from __future__ import annotations

try:
    from docxkit import LANDSCAPE_TEXT_WIDTH
except ImportError:  # the tools folder is put on the path by ctx.py; this is the same value
    LANDSCAPE_TEXT_WIDTH = 14570

# Availability of the components that stand in a chain (assumptions of the team, not measurements).
CHAIN = [
    ('Virtual machine (Azure, with the disk and the network)', 0.9950, 'Assumption. See the paragraph about the SLA of Azure below.'),
    ('`caddy`', 0.9995, 'Assumption: a small program with an automatic certificate; a stopped container is started again by `restart: unless-stopped`.'),
    ('`web` (nginx)', 0.9995, 'Assumption: static files and a proxy; the configuration changes rarely.'),
    ('`app` (php-fpm and Laravel)', 0.9990, 'Assumption: our own code, replaced at every deployment, and the pressure on the memory (row 2 of the table of risks).'),
    ('`mysql`', 0.9990, 'Assumption: one instance with a small buffer on a shared disk (rows 3 and 9).'),
]

# Counted with grep in the repository (the Pest tests: calls of it() and test(); the Vitest tests: it(), test(), it.each()).
# The result of a full run of both suites on 2026-10-21: Pest 1053 tests (5409 assertions) in 42 files, Vitest 1181 tests in 76 files.
PEST_TESTS, PEST_ASSERTIONS, PEST_FILES = 1053, 5409, 42
VITEST, VITEST_FILES = 1181, 76
CHECK_CONSTRAINTS = 18

HOURS_YEAR = 8760

try:  # the volume of the data of section 3.3, so that the two sections cannot disagree
    from content.s03_quantities import figures as _figures, human as _human
    _f = _figures()
    DATA_TEXT = _human(_f['total'])
    DUMPS_TEXT = _human(_f['dump'] * 14)
    FITS = _f['fits']
except Exception:  # pragma: no cover
    DATA_TEXT, DUMPS_TEXT, FITS = '6.9 GB', '32 GB', 6


def chain(values: list) -> float:
    product = 1.0
    for value in values:
        product *= value
    return product


def pct(x: float, digits: int = 2) -> str:
    return f'{x * 100:.{digits}f} %'


def write(d, ctx) -> None:
    base = chain([c[1] for c in CHAIN])
    premium = chain([0.9990] + [c[1] for c in CHAIN[1:]])
    down = (1 - base) * HOURS_YEAR
    down_premium = (1 - premium) * HOURS_YEAR
    vm_share = (1 - CHAIN[0][1]) / (1 - base) * 100

    d.h1('15 Reliability estimate')
    d.p('The methodology asks for a design estimate of the reliability: an expert assessment that finds the most reliable parts of '
        'the system and its bottlenecks. This section gives it in four steps: the scale of the estimate (15.1), the risks and the '
        'protection that exists (15.2), the availability of the chain of the components (15.3) and the automated checks as '
        'evidence (15.4). The estimate is made from the code, the configuration and the documents of the repository. It was '
        'not tested on the running server: nothing here comes from a measurement of failures.')

    d.h2('15.1 Scale of the estimate')
    d.p('Every risk gets two marks from 1 to 5, given in ' + ctx.ref('tab:scale') + '. They are the opinion of the team, not '
        'statistics. The risk is the product P × I. A value of 12 and more is handled first.')
    ctx.table(d, 'tab:scale', 'Marks of the probability (P) and of the impact (I)')
    d.table(
        ['Mark', 'Probability P (in a year of work)', 'Impact I (on the readers)'],
        [
            ['1', 'hardly ever: once in several years', 'not noticed'],
            ['2', 'rarely: about once a year', 'one function is slow or unavailable for minutes'],
            ['3', 'sometimes: a few times a year', 'a function is unavailable for hours, nothing is lost'],
            ['4', 'often: about once a month', 'the whole system is unavailable for hours, or a small part of the data is lost'],
            ['5', 'almost always', 'the data of many readers are lost, or the system is unavailable for days'],
        ],
        [1.0, 5.0, 6.0], align=['center', 'left', 'left'], size=22,
    )

    d.h2('15.2 Risks and protection')
    d.p('The risks are given in ' + ctx.ref('tab:reliability') + ', from the infrastructure to the program and the client. The column '
        '“Existing protection” lists only what the repository contains; what is missing is said so. Several protections are '
        'built twice: in the code and in the database, for example the rules of the data are written as `CHECK` '
        f'constraints ({CHECK_CONSTRAINTS} of them in the migrations) besides the checks of the form requests and the actions.')

    rows = []
    shade = set()
    number = [0]

    def group(title: str) -> None:
        shade.add(len(rows))
        rows.append(['', f'**{title}**', '', '', '', '', '', ''])

    def risk(name: str, fails: str, p: int, i: int, protection: str, action: str) -> None:
        number[0] += 1
        rows.append([str(number[0]), name, fails, str(p), str(i), f'**{p * i}**', protection, action])

    group('Infrastructure')
    risk('Virtual machine, one instance',
         'The host is maintained or fails; the disk or the network is lost. The whole system is unavailable; with the disk the database and the dumps on it are lost.',
         2, 5,
         '`restart: unless-stopped` on every container and Docker enabled at boot, so the stack starts after a reboot; a static IP keeps the address and the certificate. There is no second server and no replica.',
         'Accept it for a study project and say so. Keep the dumps outside the machine (row 4). A new machine is described: `bootstrap-server.sh`, new secrets; the restore of a dump is not written down, add it.')
    risk('Memory of 1 GiB',
         'At the heaviest moment the stack needs more than the 410 MB that are free (14.4); the swap grows; the kernel stops MySQL or a worker. Slow answers, errors 502, a container is restarted; the data stay consistent.',
         3, 3,
         'MySQL is trimmed (buffer 64M, no performance schema, no binary log, 40 connections); php-fpm `ondemand` with 6 workers, `memory_limit` 256M, a worker is renewed after 500 requests; one import at a time; swap of 2 GB; `EpubLimits`.',
         'Write down `free -m` and `docker stats` after a week of real use. Set `mem_limit` for `app`, so that it is stopped before MySQL. Move to 4 GB (`B2als_v2`) if the swap grows all the time.')
    risk('Disk of 30 GB',
         'It fills with the texts of books, the 14 dumps, the table `cache` (expired rows are not deleted), the logs of containers and the images. MySQL cannot write: all changes fail, MySQL may stop. At the planned scale (3.3) the estimate is ' + DATA_TEXT + ' of data and ' + DUMPS_TEXT + ' of 14 dumps.',
         3, 4,
         'A quota per reader (30 texts, 40 million characters) and limits per text; `backup.sh` deletes dumps older than 14 days; `docker image prune -f` after every rollout; the original EPUB file is not stored. There is no quota for the server as a whole, no size limit of the logs in the compose files, no alert.',
         f'Keep fewer dumps (about {FITS} fit at that scale) or move them off the server. Leave `cache` and `sessions` out of the dump. Delete the expired rows of `cache` with a cron line. Cache the normalised entries of Open Library, not the raw answer. Set `max-size` for the logs. Watch `df -h`.')
    risk('Backups of MySQL',
         'No dump is made (the cron entry is added by hand), a failed dump leaves a damaged file that is kept for 14 days, the dumps lie on the same disk, and a restore is not described or recorded as tested in the repository. If the disk is lost, all data of the readers are lost, with the texts of books.',
         3, 5,
         '**Exists:** `deploy/backup.sh` (`mysqldump --single-transaction`, gzip, the dumps of 14 days), the cron line in `docs/deploy.md`, the script is copied to the server by every deployment. **Missing:** an automatic copy outside the server (the document says to take the dumps with `scp` by hand; the technical specification, item 5.7, asks for a separate medium), a check that a dump was made, a written and tried restore, a binary log (no restore to a moment in time).',
         'Copy the dumps off the machine on a schedule. Make one test restore into the test database and write the steps in `docs/deploy.md`. Delete a dump that failed. Run `backup.sh` before a release with a migration that changes data.')
    risk('No monitoring or alerts',
         'An outage, a full disk or a stopped container is seen by the readers first. The time to repair grows.',
         4, 2,
         'Only the check of `https://<domain>/up` (12 attempts, 5 s apart) at the end of a deployment. The logs go to the standard error of the containers (`LOG_CHANNEL=stderr`, level warning) and are read with `docker compose logs`.',
         'Add an external check of `/up` that sends a message (a free service is enough).')
    risk('Name and certificate',
         'The certificate is not issued or renewed (the name does not point to the IP, ports 80 and 443 are closed, limits of the authority), or `sslip.io` does not answer. Browsers refuse the site (HSTS is set for a year) and the service worker does not run.',
         2, 4,
         'Caddy obtains and renews the certificate by itself; it is kept in the volume `caddy_data`, so a restart does not ask for a new one; `docs/deploy.md` lists the causes and the checks.',
         'Use an own domain from the GitHub Student Pack if the system lives on; add the expiry of the certificate to the external check.')

    group('Containers and data')
    risk('Container `web` (nginx)',
         'A mistake in the configuration or a missing file after a build. The client or the whole API is not reachable.',
         1, 4,
         'Files with a hash in the name; the entry point and `sw.js` are not cached; the image is built in the job `docker` before every merge; `restart`.',
         'Add `nginx -t` to the job `docker` (it runs `php-fpm -t` for `app` only).')
    risk('Pool of php-fpm (`app`)',
         'All 6 workers wait for slow requests (an import up to 60 s, Open Library up to 8 s with 2 attempts) or php-fpm hangs. Other requests wait; errors 502 or 504; the client gives up after 20 s.',
         3, 3,
         'The limiter `catalog` (30 per minute); a timeout of 8 s for Open Library; one import at a time; `fastcgi_read_timeout` 60 s; `max_requests` 500; `restart`. `app` has no healthcheck, so a php-fpm that is alive but hung is not restarted.',
         'Add a healthcheck for `app`. Limit the number of workers that an upload may take.')
    risk('MySQL, all the state',
         'A crash, a damaged table, too many connections, a long lock. The database holds the data and also the sessions, the cache, the counters of the limiters and the lock of the import (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` are `database`). No sign-in, no limits, no import: the whole system stops.',
         2, 5,
         'InnoDB with transactions and crash recovery; a healthcheck (`mysqladmin ping` every 5 s) and `depends_on: service_healthy` for `app` at the start; `restart`; a named volume; `max_connections=40` above the 6 workers.',
         'Try the restore (row 4). The single point is accepted for the study project; Redis would take the sessions and the cache away, but needs the memory that the server does not have.')
    risk('Integrity under parallel requests',
         'Two quick taps read the same page; two uploads for one entry; a counter of likes or comments drifts; two reports hide a post twice; a handle taken at the same moment. A wrong page, a wrong counter or an error 500.',
         2, 3,
         '`DB::transaction` with `lockForUpdate` in `UpdateProgress`, `SaveReadingPosition`, `ImportBookText`, `ReportContent`, `PostComments`; unique indexes (`user_books (user_id, book_id)`, `reading_goals (user_id, year)`, `reports (reporter_id, reportable_type, reportable_id)`, `posts (user_id, review_book_id)`, the keys of likes, follows and blocks, `users.handle`); `UniqueConstraintViolationException` is caught in `CreateNewUser`, `MeController`, `PublishPost`, `ReportContent`; the `CHECK` constraints. The rules hold in the code and in the database.',
         'None now. Add a test for every new rule.')

    group('Program')
    risk('Import of an EPUB book',
         'A hostile or very big file (a decompression bomb, deep nesting, an external entity, a path in a name) takes memory or time; the server slows or `app` is stopped; a bad text reaches the page.',
         2, 3,
         '`EpubLimits` (file 15 MiB, unpacked 60 MiB, 2,000 entries, 500 chapters, 8 million characters, a quota per reader); `SafeXml`; the sanitizer builds a new tree from an allow-list without attributes; the content security policy of nginx; one import at a time (`Cache::lock(\'epub-import\', 120)`, a wait of 5 s, then 503 with `Retry-After`); one transaction (the text is stored whole or not at all); 20 uploads an hour; 25 MiB of body only on this route. The tests include a fuzz corpus and files over the limits.',
         'Measure the time and the memory of the import of a real book of 15 MiB on the server.')
    risk('Open Library',
         'The service is slow, down or changes its format. No search of a book; the manual entry stays.',
         4, 1,
         'The cache of 24 h; a timeout of 8 s and 2 attempts; `CatalogUnavailableException` gives 503 `catalog.unavailable` and one log line; the client offers the manual entry; the limiter `catalog`; the tests use `Http::fake` and forbid real calls (`Http::preventStrayRequests`).',
         'None. The size of the cache is in row 3.')
    risk('Abuse by readers',
         'Registration is open and e-mail is not confirmed. Spam in posts and comments; three new accounts hide a post with reports; a script floods the routes that have no limit. The pool is busy or the community is polluted.',
         3, 3,
         'The limiters (login 5 per minute, posts 20 per hour, comments 60 per hour, reports 20 per hour, likes and follows 120 per minute, search 30 per minute); one report per reader per object; hiding after reports of three different readers; blocks; the commands `moderation:reports|hide|restore|resolve`; the policies answer 404 for foreign data. **Missing:** a limit on registration and on all other routes, a limit at the proxy, an interface for the moderator.',
         'Add `throttle` to the group of the API and `limit_req` for `/api/auth/*` in nginx. Count only the reports of accounts older than a day (the idea is in `docs/mvp-plan.md`).')

    group('Client and delivery')
    risk('Network and device of the reader',
         'No network or one bar of signal; the session ends; the browser is old. A request fails.',
         4, 1,
         'The client tells “no answer” from “not signed in” (`NetworkError`, `auth.unreachable`) and shows the screen “No connection” with a button instead of a blank page or the sign-in form (`OfflineGate.vue`); a banner when the browser is offline (`useOnline`); the shell is cached by the service worker and data are never cached; a timeout of 20 s; the reading position is saved every 15 s and when the page is left (`keepalive`); a page update is shown at once and sent in a queue with a rollback; `onUnauthorized` leads to sign-in.',
         'None. Check on real phones with the list in `docs/pwa.md`.')
    risk('Delivery of a new version',
         'A faulty release reaches the server; a migration fails or is long; an old client talks to the new API. Errors until the next deployment; the database may be changed while the code is rolled back.',
         3, 3,
         'The images are published only when the jobs `backend` (Pint, Larastan, Pest), `frontend` (ESLint, vue-tsc, Vitest, build) and `docker` (production images, `php-fpm -t`) are green; one deployment at a time (`concurrency: deploy`); the environment `production` may require approval; the tag is the hash of the commit, so a rollback is a re-run or `rollout.sh <repo> <sha>`; `php artisan migrate --force` runs at every rollout; the job checks `/up` at the end; a new client waits for the consent of the reader. **Missing:** a rollback of migrations, a staging server, a check of an old client against a new API.',
         'Write migrations so that the old code works with the new schema (add first, remove later). Run `backup.sh` before a migration that changes data.')
    risk('End of the free hosting',
         'The 12 months of the free offer and the credit of $100 end; the subscription is closed. The server stops.',
         3, 4,
         'A budget alert of $20 (`docs/deploy.md`); the images and `docker-compose.prod.yml` run on any server with Docker, so a move is a new machine, `bootstrap-server.sh`, new secrets and a restored dump.',
         'Decide before the 12 months end who pays or where the system moves; keep a dump outside.')

    with ctx.landscape(d):
        ctx.table(d, 'tab:reliability', 'Expert estimate of the risks (P: probability, I: impact, marks 1 to 5)')
        d.table(
            ['No.', 'Component or risk', 'What can fail and the effect', 'P', 'I', 'P×I', 'Existing protection', 'Recommended action'],
            rows, [0.45, 1.6, 3.4, 0.4, 0.4, 0.55, 4.7, 3.4],
            align=['center', 'left', 'left', 'center', 'center', 'center', 'left', 'left'],
            total=LANDSCAPE_TEXT_WIDTH, size=18, shade=shade)

    d.h3('15.2.1 The most reliable parts and the bottlenecks')
    d.p('**The most reliable parts.** The rules of the data are the strongest: they are checked in the form request, in the '
        f'action and in the database ({CHECK_CONSTRAINTS} `CHECK` constraints, unique indexes, row locks), and the access to the '
        'data of another reader answers 404 in the policies. The import of a book is built against hostile input from the '
        'start: limits, a safe XML reader, a text rebuilt from an allow-list, one transaction. The client survives the loss '
        'of the network without a blank screen. The delivery has gates (seven checks and an image build) before anything reaches '
        'the server.')
    d.p('**The bottlenecks.** The first is the single machine with a single database: one failure stops everything, and '
        'the database also holds the sessions, the cache and the limiters. The second is the data copy: the dumps lie on the '
        'same disk, are copied out by hand and no test of a restore is recorded (row 4, the product 15). The third is the room of the server: 1 GiB of memory '
        'and 30 GB of disk with no global quota (rows 2 and 3). The fourth is that the system has no monitoring (row 5) and '
        'only the database has a healthcheck (rows 8 and 9). The rows with the highest product are 4 (15), 3 (12) and 16 (12).')

    # ------------------------------------------------------------------------------------------------------------------
    d.h2('15.3 Availability of the chain of components')
    d.p('A request of a reader passes through the virtual machine, `caddy`, `web`, `app` and `mysql` one after another, and the '
        'failure of any of them stops the service. For such a chain the availability is the product of the availabilities of '
        'the components. The values are given in ' + ctx.ref('tab:availability') + '. They are assumptions of the team; the '
        'technical specification gives no numerical target of availability.')
    ctx.table(d, 'tab:availability', 'Availability of the chain of components (assumed values)')
    chain_rows = [[name, pct(value), basis] for name, value, basis in CHAIN]
    chain_rows.append(['**The chain**', f'**{pct(base)}**',
                       f'The product of the five values. It is {down:.0f} hours of unavailability in a year ({down / 12:.1f} hours a month).'])
    chain_rows.append(['The chain if the machine had 99.9 %', pct(premium),
                       f'The value of the SLA for a single machine with Premium SSD. {down_premium:.0f} hours a year.'])
    d.table(['Component', 'Availability', 'Basis'], chain_rows, [4.2, 1.7, 6.0], align=['left', 'right', 'left'], size=22,
            shade={len(chain_rows) - 2})
    d.p(f'The machine gives {vm_share:.0f} % of the unavailability, so it is the weakest link of the chain. The model counts '
        'every failure as a full stop, assumes that the failures are independent and leaves out the short stop of a container '
        'during a rollout (not measured). GitHub, Open Library, Let’s Encrypt and `sslip.io` are not in the chain: a failure '
        'of GitHub only blocks a new deployment, a failure of Open Library blocks one function, while `sslip.io` and the '
        'certificate authority are covered by row 6.')
    d.p('**The SLA of Azure.** I looked for the public SLA for Virtual Machines. The pages that I could read disagree about '
        'one machine with Standard SSD disks. The SLA page of Azure operated by 21Vianet (azure.cn, “SLA for Virtual Machines”, '
        'dated 11/2020) gives at least 99.5 % for such a machine, 99.9 % with Premium SSD or Ultra Disk and 95 % with Standard '
        'HDD. An answer in Microsoft Q&A (“sla for single instance that using standard SSD”, 2020) quotes the FAQ of the Azure '
        'disks: Standard SSD has no single-instance SLA. I could not read the current global SLA document. Therefore 99.5 % '
        'is an assumption and not a promise of Azure. An SLA is also only a credit against the bill, not a forecast of the '
        'real availability.')

    # ------------------------------------------------------------------------------------------------------------------
    d.h2('15.4 Automated checks as evidence')
    d.p('The checks that run on every pull request and on every merge into the main branch are given in ' + ctx.ref('tab:checks') +
        '. The numbers of tests are the result of a full run of both suites (`make test`) on the development stack on '
        '21 October 2026: all of them passed. A test that uses a dataset (for example the corpus of chapters in '
        '`ChapterCorpusTest`) is counted once for every case.')
    ctx.table(d, 'tab:checks', 'Automated checks (workflow ci.yml, the job is given in brackets)')
    d.table(
        ['Check', 'Tool', 'What it covers', 'Size'],
        [
            ['Tests of the server part (`backend`)', 'Pest 4 on PHPUnit 12; MySQL 8.4, the database `bookshelf_testing`',
             'Every endpoint with the isolation of data (“reader B does not see the data of reader A”), the rules of the library, progress, quotes, statistics, the community and the reader, the importer of EPUB.',
             f'{PEST_TESTS} tests ({PEST_ASSERTIONS} assertions) in {PEST_FILES} files, all passed in a full run.'],
            ['Tests of the client (`frontend`)', 'Vitest 5, jsdom, Vue Test Utils',
             'Views, components, stores, the API client, the service worker (on a fake environment), the update flow, the router, the helpers.',
             f'{VITEST} tests in {VITEST_FILES} files, all passed in a full run.'],
            ['Static analysis of PHP (`backend`)', 'Larastan 3 (PHPStan), level 6 (`phpstan.neon`)', 'The folders `app`, `bootstrap`, `config`, `database`, `routes`; model casts are read.', 'Must pass for the merge.'],
            ['Code style of PHP (`backend`)', 'Pint, the default preset (`pint --test`)', 'The format of all PHP files.', 'Must pass.'],
            ['Lint and types of the client (`frontend`)', 'ESLint with `--max-warnings 0`, vue-tsc', 'TypeScript and Vue files and their types (the types of the answers of the API are written by hand in `src/types/api.ts`).', 'Must pass.'],
            ['Build of the client (`frontend`)', 'Vite and the build of the service worker', 'The production bundle and `sw.js` with the list of files and the version.', 'Must pass.'],
            ['Production images (`docker`)', 'Docker Buildx', 'Both images are built; the image `app` is started with `php-fpm -t`, which is its entry point runs `artisan optimize`, which once crash-looped on the server.', 'Must pass.'],
            ['Delivery (`images`, `deploy`)', 'GitHub Actions, SSH', 'Runs only on `main` and when `DEPLOY_ENABLED` is `true`: publishes the images, rolls them out, runs the migrations, calls `/up`.', 'After the checks above.'],
        ],
        [2.5, 2.8, 4.3, 3.6], size=20,
    )
    d.p('Definition of done in `CLAUDE.md` of the project is that `make lint` and `make test` are green, which is the same set '
        'of checks that CI runs. **Not covered by automated checks:** end-to-end tests in a real browser (Playwright is '
        'planned for after the MVP), the service worker and the installation on real phones (`docs/pwa.md` has a manual '
        'list), the selection of text on iOS and Android, a load test, a restore of a backup, and the behaviour of the '
        'server under memory pressure.')
