"""Section 13: the software and the DBMS used to build, run and test the system."""

from __future__ import annotations

# (name, version, role, where it is used). A tuple of one item is the heading of a group.
SOFTWARE = [
    ('Runtime of the server part',),
    ('PHP', '8.4 (image `php:8.4-fpm-alpine`)',
     'Runs the Laravel application and the Artisan commands. `composer.json` allows `^8.3`, but 20 locked packages '
     '(the Symfony 8.1 components) need 8.4.1 or newer, so 8.4 is the working minimum. The patch version is not pinned.',
     'server, test'),
    ('PHP-FPM', 'The same image', 'Process manager of PHP: `pm = ondemand`, at most 6 children (`docker/php/prod-fpm.conf`).', 'server'),
    ('PHP extensions', '`pdo_mysql`, `bcmath`, `intl`, `opcache`, `pcntl`, `zip` are installed by the Dockerfile',
     'Access to MySQL; exact arithmetic; internationalization; cache of the byte code; signals; ZIP archives (`ZipArchive` '
     'reads the EPUB). The code also uses `dom` and `libxml` (`DOMDocument`) and `mbstring` (`mb_*`); they come with the base image.',
     'server, test'),
    ('Composer', '2 (image `composer:2`; CI `composer:v2`)', 'Installs the PHP packages exactly as `composer.lock` says.', 'build, test'),
    ('Alpine Linux', 'The base of the images `php`, `node`, `nginx`, `caddy`; the version is that of the tag', 'The system inside the containers.', 'server, build, delivery'),
    ('Ubuntu Server', '24.04 LTS, x64 (`docs/deploy.md`)', 'The system of the production virtual machine (Section 14).', 'delivery'),

    ('Libraries of the server part (direct dependencies of composer.lock)',),
    ('laravel/framework', 'v13.34.0', 'The framework: routing, requests, validation, Eloquent, migrations, cache, locks, the HTTP client, Artisan.', 'server, test'),
    ('laravel/sanctum', 'v4.3.3', 'Cookie sessions of the single-page application (stateful mode) and the CSRF cookie. No tokens are used.', 'server'),
    ('laravel/fortify', 'v1.40.0', 'Headless authentication: registration, sign-in and sign-out under `/api/auth`. In `config/fortify.php` only the '
     'feature `registration` is on, so there are no routes for the reset of a password, the verification of the e-mail, '
     'two-factor authentication or passkeys.', 'server'),
    ('dedoc/scramble', 'v0.13.47', 'Builds the OpenAPI description of the API from the code and shows it under `/docs/api`. '
     'Access is limited by `RestrictedDocsAccess` (the local environment, or a gate).', 'server, development'),
    ('laravel/tinker', 'v3.0.2', 'A console for trying the code by hand.', 'development'),
    ('Other packages', '101 locked packages for production, 47 more for development',
     'For example `guzzlehttp/guzzle` 8.2.0 (the HTTP client that calls Open Library), `nesbot/carbon` 3.14.2 (dates), '
     '`monolog/monolog` 3.12.1 (log), the components `symfony/*` v8.1.x (console, HTTP, routing).', 'server'),

    ('DBMS',),
    ('MySQL', '8.4 (image `mysql:8.4`)',
     'The only database. The engine is InnoDB (the migrations do not name one). Used: foreign keys with `ON DELETE`, 18 CHECK '
     'constraints, `REGEXP_LIKE`, a virtual generated column, a `JSON` column, row locks, `INSERT IGNORE`. The tables of '
     'the sessions, the cache and the locks are in the same database. The settings are in Section 13.3.', 'server, test'),
    ('Databases for the tests', '`bookshelf_testing`, `bookshelf_testing_2`, `bookshelf_testing_3`',
     'Created by `docker/mysql/init/01-testing-database.sql`. The tests run on MySQL, never on SQLite, and cannot touch the development data.', 'test'),

    ('Runtime and libraries of the client (package-lock.json)',),
    ('Vue', '3.5.43', 'The framework of the single-page application (single-file components, the Composition API).', 'client'),
    ('Vue Router', '5.3.1', 'Routes of the screens and the guard of the access.', 'client'),
    ('Pinia', '4.0.3', 'Stores of the state: library, quotes, authentication, community.', 'client'),
    ('vue-i18n', '11.4.13', 'Dictionaries of the interface (Ukrainian) and the rules of the plural forms.', 'client'),
    ('Tailwind CSS', '4.3.3 (with `@tailwindcss/vite` 4.3.3)', 'Styles of the interface.', 'client, build'),
    ('TypeScript', '6.0.3', 'The language of the client. The types of the answers of the API are written by hand in `src/types`; '
     'the package `openapi-typescript` is not used.', 'client, build'),
    ('Browser APIs', 'See Section 13.5', 'Service worker, Cache API, Canvas 2D, Web Share, `Intl`, the selection of text.', 'client'),

    ('Build tools',),
    ('Node.js', '22 (image `node:22-alpine`; CI `node-version: 22`)',
     'Runs the build, the tests and the development server. Vite 8.3.2 needs 20.19 or 22.12 and newer, Vitest 5.0.3 needs 22.12 or newer in the series 22, 24 and 26.', 'build, test'),
    ('npm', 'The one that comes with Node.js 22; `lockfileVersion` 3', 'Installs the packages with `npm ci` exactly as `package-lock.json` says (311 entries, 4 of them in `dependencies`).', 'build'),
    ('Vite', '8.3.2 (bundler Rolldown 1.2.12)',
     'The development server (port 5173, hot reload) and the production bundle in `dist/`. The target of the build is '
     '`baseline-widely-available`: Chrome 111, Edge 111, Firefox 114, Safari 16.4, iOS 16.4. A second run with '
     '`vite.sw.config.ts` builds `sw.js`.', 'build, development'),
    ('@vitejs/plugin-vue', '6.0.9', 'Compiles the single-file components.', 'build'),
    ('vue-tsc', '3.3.12', 'Checks the types of `.ts` and `.vue` files (`npm run typecheck`, the first step of `npm run build`).', 'build, test'),
    ('GNU Make', 'Not pinned', 'The short commands of `Makefile`: `make up`, `make test`, `make lint`.', 'development'),
    ('Python 3 and Pillow', '3.9.6 and 11 on the machine of the author; not pinned in the repository',
     'Draws the icons of the application (`frontend/scripts/make-icons.py`) and the diagrams of the documents (`docs/tools`).', 'build, development'),
    ('Git', 'Not pinned', 'Version control; the repository is on GitHub.', 'development'),

    ('Test and quality tools',),
    ('Pest', 'v4.7.8 (with `pest-plugin-laravel` v4.1.0)', 'The tests of the server part in `tests/Feature` and `tests/Unit` (`vendor/bin/pest`).', 'test'),
    ('PHPUnit', '12.5.33', 'The engine under Pest.', 'test'),
    ('Mockery, Faker', '1.6.15, v1.24.1', 'Test doubles; fake data in the factories of `database/factories`.', 'test'),
    ('Paratest', 'v7.20.0', 'Parallel run of the tests (a dependency of Pest).', 'test'),
    ('Larastan, PHPStan', 'v3.12.2, 2.2.16', 'Static analysis of the PHP code, level 6 (`phpstan.neon`).', 'test'),
    ('Laravel Pint', 'v1.32.1', 'Style of the PHP code (`pint --test`); there is no `pint.json`, so the default preset applies.', 'test'),
    ('Collision, Pail, Pao', 'v8.9.5, v1.2.7, v1.1.5', 'Collision: readable errors in the console. Pail: reading the log. Pao: compact output of the tests for tools.', 'development'),
    ('Vitest', '5.0.3', 'The tests of the client (`npm run test`).', 'test'),
    ('jsdom, Vue Test Utils', '30.1.2, 2.5.1', 'The DOM for the tests; mounting of components.', 'test'),
    ('ESLint', '10.12.0 (with `typescript-eslint` 8.71.0, `eslint-plugin-vue` 10.11.1, `@eslint/js` 10.0.1, `globals` 17.13.0)', 'Style and errors of the client code; `--max-warnings 0`.', 'test'),

    ('Containers and images',),
    ('Docker and Docker Compose', 'On the server `docker.io` and `docker-compose-v2` of Ubuntu (installed by `bootstrap-server.sh`)', 'Run the stack from one description: `docker-compose.yml` (development), `deploy/docker-compose.prod.yml` (production).', 'development, delivery'),
    ('Image `php:8.4-fpm-alpine`', 'With `mlocati/php-extension-installer` and `composer:2`', 'Base of the image `bookshelf-app` (targets `dev`, `vendor`, `prod` in `docker/php/Dockerfile`).', 'server, build'),
    ('Image `node:22-alpine`', '22', 'The container `frontend` of development and the build stage of the image `bookshelf-web`.', 'development, build'),
    ('Image `nginx:1.28-alpine`', '1.28', 'The final stage of the image `bookshelf-web` and the service `web` of development.', 'server'),
    ('Image `mysql:8.4`', '8.4', 'The database in development, in production and in CI.', 'server, test'),
    ('Image `caddy:2-alpine`', '2', 'The entrance of the production stack.', 'delivery'),
    ('Images `ghcr.io/<owner>/bookshelf-app`, `bookshelf-web`', 'The tag is the SHA of the commit; the default owner in the compose file is `sashacn`', 'Built by CI for `linux/amd64` and kept in GitHub Container Registry.', 'delivery'),

    ('Web servers',),
    ('nginx', '1.28', 'Gives the files of the client; passes `/api`, `/docs` and `/up` to PHP-FPM (FastCGI, `app:9000`); sets the caching and the '
     'Content-Security-Policy; limits the size of a body. In development it also proxies to the Vite server.', 'server, development'),
    ('Caddy', '2', 'Terminates HTTPS, gets and renews the certificate from Let’s Encrypt, compresses (`zstd`, `gzip`), sets `Strict-Transport-Security` '
     'and other headers, and proxies to `web:80` (`deploy/Caddyfile`).', 'delivery'),

    ('CI/CD',),
    ('GitHub Actions', 'Runner `ubuntu-latest`; `.github/workflows/ci.yml`', 'Five jobs: `backend` (Pint, Larastan, Pest on a MySQL 8.4 service), `frontend` (ESLint, vue-tsc, Vitest, build), `docker` '
     '(builds both images and checks `php-fpm -t`), `images` (publishes), `deploy` (rolls out). The last two run only on `main` and only if the variable `DEPLOY_ENABLED` is `true`.', 'delivery'),
    ('Actions used', '`actions/checkout` v4, `shivammathur/setup-php` v2 (PHP 8.4), `actions/cache` v4, `actions/setup-node` v4 (Node 22), '
     '`docker/setup-buildx-action` v3, `docker/build-push-action` v6, `docker/login-action` v3', 'The steps of the jobs.', 'delivery'),
    ('GitHub Container Registry', 'ghcr.io', 'Keeps the images; the deployment pulls them from there.', 'delivery'),
    ('OpenSSH, curl, Bash', 'Of the runner and of the server', '`ssh` and `scp` copy the files and start `deploy/rollout.sh`; `curl` checks `/up` after the rollout; `rollout.sh` pulls the images, restarts the stack and runs `php artisan migrate --force`; `backup.sh` dumps the database.', 'delivery'),

    ('External service',),
    ('Open Library', 'Public API, no key', 'The catalog of books: `search.json` and the covers. The server calls it with a `User-Agent`, an 8 s time limit and a cache of 24 hours; the client never calls it.', 'server'),
]


# Licenses as the lock files give them. A dash: the item is not in a lock file and its license was not checked.
LICENSES = {
    'laravel/framework': 'MIT', 'laravel/sanctum': 'MIT', 'laravel/fortify': 'MIT', 'dedoc/scramble': 'MIT',
    'laravel/tinker': 'MIT', 'Other packages': 'Mostly MIT, see 13.4', 'Vue': 'MIT', 'Vue Router': 'MIT',
    'Pinia': 'MIT', 'vue-i18n': 'MIT', 'Tailwind CSS': 'MIT', 'TypeScript': 'Apache-2.0', 'Vite': 'MIT',
    '@vitejs/plugin-vue': 'MIT', 'vue-tsc': 'MIT', 'Pest': 'MIT', 'PHPUnit': 'BSD-3-Clause',
    'Mockery, Faker': 'BSD-3-Clause, MIT', 'Paratest': 'MIT', 'Larastan, PHPStan': 'MIT', 'Laravel Pint': 'MIT',
    'Collision, Pail, Pao': 'MIT', 'Vitest': 'MIT', 'jsdom, Vue Test Utils': 'MIT', 'ESLint': 'MIT',
}


def _software_table(d, ctx) -> None:
    rows: list = []
    shade: set = set()
    number = 0
    for item in SOFTWARE:
        if len(item) == 1:
            shade.add(len(rows))
            rows.append(['', f'**{item[0]}**', '', '', '', ''])
            continue
        number += 1
        name, version, role, used = item
        rows.append([str(number), name, version, role, LICENSES.get(name, 'n/a'), used])
    d.table(['No.', 'Name', 'Version', 'Role', 'License', 'Used in'], rows, [0.9, 2.3, 2.8, 5.0, 1.3, 1.5], shade=shade,
            align=['center', 'left', 'left', 'left', 'left', 'left'], size=18)


def _dbms(d, ctx) -> None:
    d.h2('13.3 DBMS and its settings')
    d.p('The system uses one DBMS, MySQL 8.4. It was chosen because the data is relational and because it has the features '
        'that the design relies on: CHECK constraints, unique indexes, row locks and a JSON column. The tests use the '
        'same engine as production. The settings of the server are given in ' + ctx.ref('tab:software_dbms')
        + '. The production settings are trimmed for a virtual machine with 1 GB of memory, of which about 400 MB is free '
        'after the system and Docker (`deploy/docker-compose.prod.yml`).')
    ctx.table(d, 'tab:software_dbms', 'Settings of MySQL')
    d.table(
        ['Setting', 'Development', 'Production', 'Meaning'],
        [
            ['Image', '`mysql:8.4`', '`mysql:8.4`', 'The series of the server; the patch version is not pinned.'],
            ['`character-set-server`', '`utf8mb4`', '`utf8mb4`', 'Any text of the readers, including emoji.'],
            ['`collation-server`', '`utf8mb4_0900_ai_ci`', '`utf8mb4_0900_ai_ci`', 'Comparison without regard to case and accents: it makes the e-mail unique without regard to case and serves the search '
             'of readers. The handle is checked separately, with a case-sensitive CHECK.'],
            ['`performance-schema`', 'Default', '`OFF`', 'Saves a few hundred megabytes of memory.'],
            ['`innodb-buffer-pool-size`', 'Default', '`64M`', 'The cache of the data is small, because the memory is small.'],
            ['`skip-log-bin`', 'Default', 'On', 'There is no replication, so the binary log is not kept.'],
            ['`max-connections`', 'Default', '`40`', 'PHP-FPM has at most 6 workers.'],
            ['Connection of the application', 'Strict mode; `utf8mb4`', 'The same', '`config/database.php`: `strict` is true, `engine` is not set.'],
            ['Access from the host', '`127.0.0.1:3308` to the port 3306', 'The port is not published', 'A database client of the developer can connect in development only.'],
            ['Data', 'Volume `mysql_data`', 'Volume `mysql_data`', 'The data survives the restart of the container.'],
            ['Health check', '`mysqladmin ping`', '`mysqladmin ping`', 'The application waits until the database is healthy.'],
            ['Backup', '', '`deploy/backup.sh`', '`mysqldump --single-transaction --no-tablespaces`, gzip, files older than 14 days are deleted; the suggested time is 03:00 every night.'],
        ],
        [3.4, 2.6, 2.6, 5.0], size=20,
    )
    d.p('The database has 16 tables of the application (the stores D1 to D9 of the preliminary design) and 7 tables of the '
        'framework: `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. The '
        'sessions, the cache and the locks use the database (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` are '
        '`database`), so the system needs no Redis. The application defines no queued job; the tables of the queue are '
        'those that the framework creates.')


def _licenses(d, ctx) -> None:
    d.h2('13.4 Licenses of the libraries')
    d.p('The licenses of the libraries are read from `composer.lock` and `package-lock.json`. They are summed up in '
        + ctx.ref('tab:software_licenses') + '. All the direct dependencies are MIT, except TypeScript (Apache-2.0) and the '
        'development packages `mockery/mockery` and `phpunit/phpunit` (BSD-3-Clause). The packages `nette/schema` and '
        '`nette/utils` offer a choice of BSD-3-Clause, GPL-2.0-only and GPL-3.0-only. The 24 packages under MPL-2.0 are the '
        'compiler of CSS Lightning CSS with its binaries for the platforms; it is a tool of the build, and nothing of it is '
        'sent to the browser. The licenses of the products in the images (PHP, MySQL, nginx, Caddy, Node.js) were not '
        'checked in this project.')
    ctx.table(d, 'tab:software_licenses', 'Licenses of the packages in the lock files')
    d.table(
        ['Group of packages', 'Number', 'Licenses (number of packages)'],
        [
            ['PHP, production (`require`)', '101', 'MIT (91), BSD-3-Clause (5), BSD-2-Clause (2), BSD-3-Clause or GPL-2.0-only or GPL-3.0-only (2), Apache-2.0 (1)'],
            ['PHP, development (`require-dev`)', '47', 'MIT (23), BSD-3-Clause (24)'],
            ['JavaScript, the packages that the four `dependencies` need', '68', 'MIT (65), ISC (1), BSD-3-Clause (1), BSD-2-Clause (1)'],
            ['JavaScript, the whole lock file', '311', 'MIT (237), MPL-2.0 (24), Apache-2.0 (16), ISC (13), BSD-2-Clause (10), BlueOak-1.0.0 (5), BSD-3-Clause (3), MIT-0 (2), CC0-1.0 (1)'],
        ],
        [4.4, 1.2, 6.8], align=['left', 'center', 'left'], size=20,
    )


def _user_software(d, ctx) -> None:
    d.h2('13.5 Requirements for the software of the user')
    d.p('The reader needs only a browser; nothing is installed except, by choice, the application itself (the PWA). The '
        'target of the Vite build is `baseline-widely-available`: Chrome 111, Edge 111, Firefox 114, Safari 16.4 (iOS 16.4) '
        'and newer. The CSS framework can ask for a newer browser than the build; the minimum versions that follow from '
        'both are derived in Section 14.5, and the highest of them applies. The service worker is built for ES2022. '
        'JavaScript and cookies must be on, because the sign-in is a cookie session, and the screen must be at least 360 '
        'pixels wide (the technical specification, Section 5.1.4).')
    d.p('The features of the browser that the client uses are given in ' + ctx.ref('tab:software_browser') + '. The '
        'application works without the optional ones.')
    ctx.table(d, 'tab:software_browser', 'Features of the browser that the client uses')
    d.table(
        ['Feature', 'Used for', 'Where', 'Required'],
        [
            ['`fetch` with `FormData`, cookies', 'Every request of the API; the upload of a book', '`src/api/http.ts`', 'Yes'],
            ['`fetch` with `keepalive`', 'Saving the reading position as the page is left', '`usePositionSaver`, `http.ts`', 'No: the last position may be lost'],
            ['`AbortSignal.timeout`', 'The limit of waiting for an answer (20 s, 120 s for an upload)', '`http.ts` (checks that it exists)', 'No'],
            ['Service worker, Cache API, a secure context (HTTPS, `localhost`)', 'Caching of the application shell, working without a network, update of the version', '`src/sw`, `src/pwa/updates.ts`', 'No: without it there is no start offline'],
            ['Web App Manifest', 'Installing, icons, shortcuts, receiving a shared text', '`public/manifest.webmanifest`', 'No'],
            ['`beforeinstallprompt`', 'The button “Install” (Chromium); on iOS a hint to use “Add to Home Screen”', '`src/pwa/install.ts`', 'No'],
            ['`display-mode: standalone`', 'To know that the app is already installed', '`install.ts`', 'No'],
            ['Canvas 2D, `toBlob`', 'The quote card', '`quoteCard.ts`, `QuoteShareView.vue`', 'Only for the card'],
            ['`navigator.share` with files', 'Sharing the card through the system menu', '`QuoteShareView.vue`', 'No: the file is downloaded'],
            ['Selection API (`getSelection`, `selectionchange`), ranges', 'Selecting a quote in the text of a book', '`useTextSelection`, `readerDom.ts`', 'Only for the reader'],
            ['`Intl` (`DateTimeFormat`, `NumberFormat`, `supportedValuesOf`)', 'Dates and numbers in Ukrainian; the list of time zones; the time zone at registration', '`utils/format.ts`, `ProfileView.vue`, `RegisterView.vue`', 'Yes'],
            ['`localStorage`', 'Settings of the reader', '`useReaderSettings`', 'No: the defaults are used (the access is in `try`)'],
            ['Content-Security-Policy', 'Scripts only from the own origin; `connect-src` the own origin; images from `https:`, `data:`, `blob:`', '`docker/web/nginx.conf`', 'Enforced by the browser'],
        ],
        [3.4, 4.2, 3.0, 2.6], size=20,
    )
    d.p('The manual check of the PWA (`docs/pwa.md`) covers Android with Chrome and iPhone with Safari. The test of the '
        'service worker in a browser was not run by the automatic tests, which use a fake environment of the worker; '
        'the other browsers of the build target are expected to work, but they are not on the checklist.')


def _runtime_settings(d, ctx) -> None:
    d.h2('13.6 Settings of the runtime')
    d.p('The settings of the components that run the system are given in ' + ctx.ref('tab:software_settings')
        + '. The settings of the application come from the environment of the container: `docker-compose.yml` in '
        'development, `deploy/docker-compose.prod.yml` and `production.env` on the server (the file is created by '
        '`bootstrap-server.sh` with generated secrets and is not in the repository), the environment of the job in CI.')
    ctx.table(d, 'tab:software_settings', 'Settings of PHP, nginx, Caddy and the application')
    d.table(
        ['Component', 'Setting', 'Value', 'File'],
        [
            ['PHP', '`memory_limit`, `upload_max_filesize`, `post_max_size`', '256M, 20M, 22M', '`docker/php/prod.ini`, `dev.ini`'],
            ['PHP (production)', '`expose_php`; OPcache', 'Off; on, 128 MB, 10 000 files, `validate_timestamps` 0 (the code of a container never changes)', '`docker/php/prod.ini`'],
            ['PHP-FPM', '`pm`, `pm.max_children`, `pm.process_idle_timeout`, `pm.max_requests`', '`ondemand`, 6, 20 s, 500', '`docker/php/prod-fpm.conf`'],
            ['nginx', '`client_max_body_size`', '2 MB; 25 MB for `^/api/library/[0-9]+/text$`', '`docker/web/nginx.conf`, `docker/nginx/dev.conf`'],
            ['nginx', '`fastcgi_read_timeout`', '60 s', 'The same'],
            ['nginx', 'Caching', '`/assets/`: one year, `immutable`; `index.html`, `sw.js` and the manifest: `no-cache`', '`docker/web/nginx.conf`'],
            ['nginx', 'Content-Security-Policy', '`default-src \'self\'`; `script-src \'self\'`; `img-src \'self\' data: blob: https:`; `connect-src \'self\'`; `frame-ancestors \'none\'` and others', '`docker/web/nginx.conf`'],
            ['Caddy', 'Compression and headers', '`encode zstd gzip`; `Strict-Transport-Security` (one year); `X-Content-Type-Options: nosniff`; `Referrer-Policy: strict-origin-when-cross-origin`; the header `Server` is removed', '`deploy/Caddyfile`'],
            ['Application', 'Session', '`SESSION_DRIVER` `database`, `SESSION_LIFETIME` 43 200 minutes; in production `SESSION_SECURE_COOKIE` true', 'Both compose files'],
            ['Application', 'Cache, queue, mail, log', '`CACHE_STORE` and `QUEUE_CONNECTION` `database`; `MAIL_MAILER` `log` (the system sends no e-mail); `LOG_CHANNEL` `stderr`; `LOG_LEVEL` `warning` in production; `APP_DEBUG` false in production', 'Both compose files'],
            ['Application', 'Open Library', '`OPENLIBRARY_BASE_URL`, `OPENLIBRARY_COVERS_URL`; timeout 8 s; cache 86 400 s', '`config/services.php`, `docker-compose.yml`'],
            ['Application', 'Trusted proxies', 'All (`trustProxies(at: \'*\')`), because the application is reachable only through nginx and Caddy', '`bootstrap/app.php`'],
        ],
        [2.0, 3.4, 4.3, 3.7], size=20,
    )


def write(d, ctx) -> None:
    d.h1('13 Software and DBMS')

    d.h2('13.1 Basis of the list')
    d.p('The list describes everything that is used to build, run and test Bookshelf. The versions of the PHP and the '
        'JavaScript packages are the exact versions of the lock files `backend/composer.lock` and '
        '`frontend/package-lock.json`; `composer install` and `npm ci` install exactly them. The versions of the images '
        'are the tags in the Dockerfiles, the compose files and the workflow. A tag such as `mysql:8.4` fixes the series '
        'but not the patch version, so the patch version of PHP, Node.js, MySQL, nginx and Caddy depends on the day when '
        'the image is built or pulled; where a version is not pinned, the table says so.')
    d.p('The column “Used in” says where the item works: **server** (in the running system), **client** (in the browser), '
        '**build** (when the application is built), **test** (in the automatic checks), **delivery** (in the pipeline '
        'and on the server) or **development** (on the machine of the developer).')

    d.h2('13.2 Software used')
    d.p('The software is listed in ' + ctx.ref('tab:software') + ', in groups: the runtime of the server part, its '
        'libraries, the DBMS, the client, the tools of the build and of the tests, the containers, the web servers, the '
        'pipeline and the external service. The structure of the system and the places of these components are shown in '
        + ctx.ref('fig:deployment') + '. The column “License” is read from the lock files (Section 13.4); “n/a” means that '
        'the item is not in a lock file and its license was not checked.')
    ctx.table(d, 'tab:software', 'Software and DBMS used by the system')
    _software_table(d, ctx)

    _dbms(d, ctx)
    _licenses(d, ctx)
    _user_software(d, ctx)
    _runtime_settings(d, ctx)
