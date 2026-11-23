"""Section 14: technical means (the hardware and the network on which Bookshelf is developed, delivered and used)."""

from __future__ import annotations

# The memory budget of the server, in MB. The first two figures are from docs/deploy.md; the rest are settings from
# deploy/docker-compose.prod.yml and docker/php/*, or assumptions that are named as such in the text.
VISIBLE_MB = 842        # what the system sees of the 1 GiB of the virtual machine (docs/deploy.md)
USED_MB = 430           # used by the system and Docker on a fresh server (docs/deploy.md)
POOL_MB = 64            # --innodb-buffer-pool-size=64M
OPCACHE_MB = 128        # opcache.memory_consumption
LIMIT_MB = 256          # memory_limit of one PHP request
ORDINARY_MB = 40        # assumption: an ordinary request of Laravel
WORKERS = 6             # pm.max_children


def write(d, ctx) -> None:
    free = VISIBLE_MB - USED_MB
    ordinary = WORKERS - 1
    worst = LIMIT_MB + ordinary * ORDINARY_MB + POOL_MB + OPCACHE_MB
    over = worst - free

    d.h1('14 Technical means')
    d.p('This section describes the equipment on which Bookshelf is developed, checked, delivered and used. The nodes and '
        'their connections are shown in ' + ctx.ref('fig:deployment') + ', their parameters are in ' + ctx.ref('tab:hardware') +
        '. The software that runs on them is listed in section 13, the figures of the load are in section 3.3.')

    d.h2('14.1 Deployment scheme')
    d.p('The scheme of the deployment is given in ' + ctx.ref('fig:deployment') + '. The production system works on one '
        'virtual machine in Azure. Four containers of one Docker Compose project run on it; they are described in '
        '`deploy/docker-compose.prod.yml`.')
    with ctx.landscape(d):
        ctx.figure(d, 'fig:deployment', 'deployment.png', 'Deployment scheme of Bookshelf', landscape=True)
    d.p('A request of a reader goes over HTTPS to the container `caddy`. It holds the certificate and passes the request over '
        'HTTP to `web`. The container `web` (nginx) serves the files of the client and sends the requests `/api`, `/docs` and '
        '`/up` over FastCGI to `app` (php-fpm with Laravel). The container `app` works with `mysql` over SQL and calls the '
        'search API of Open Library over HTTPS. The browser of the reader loads the covers of the books directly from Open '
        'Library. Only `caddy` publishes ports (80 and 443); the other containers are reachable inside the Compose network.')
    d.p('The images `bookshelf-app` and `bookshelf-web` are built by GitHub Actions (the job `images`), stored in GHCR and '
        'pulled by the server during the rollout (`deploy/rollout.sh`). The administrator reaches the server over SSH; the '
        'commands of the moderator (`php artisan moderation:*`) run in the container `app`. Secrets are kept only on the '
        'server, in `/opt/bookshelf/production.env`.')

    d.h2('14.2 Parameters of the technical means')
    d.p('The parameters are given in ' + ctx.ref('tab:hardware') + '. The technical specification (item 5.4) asks for a server with '
        '2 vCPU, 2 GB of memory and 30 GB of SSD. The real server has the same processor and disk and only half of the memory: '
        'the free sizes of Azure for Students have 1 GB (`docs/deploy.md`). The consequences are described in 14.4.')
    ctx.table(d, 'tab:hardware', 'Technical means of Bookshelf')
    d.table(
        ['Node', 'Parameters', 'Basis'],
        [
            ['Server: processor',
             'Azure virtual machine `Standard_B2ats_v2`, region Denmark East, no infrastructure redundancy.\n'
             '2 vCPU, AMD EPYC 7763 (Milan), burstable: base performance 20 % of the machine, 24 credits are banked per hour, '
             'at most 576.',
             '`docs/deploy.md`, step 3. The processor and the credits: Azure documentation, Basv2 series. Free for 750 hours a month for 12 months.'],
            ['Server: memory',
             '1 GiB; the system sees about 842 MB. About 430 MB are used by the system and Docker on a fresh server, about 410 MB are left for the stack. Swap: a file of 2 GB.',
             '`docs/deploy.md` (“Costs and limits”); `deploy/bootstrap-server.sh` makes the swap when the memory is less than 2 GB.'],
            ['Server: disk',
             'OS disk of 30 GB, Standard SSD (not Premium). No local temporary disk in this size. The database, the images, the dumps and the swap file share this disk.',
             '`docs/deploy.md`, step 3; Azure documentation, Basv2 series.'],
            ['Server: system',
             'Ubuntu Server 24.04 LTS, x64 Gen2. Docker Engine (`docker.io`) and Compose v2 (`docker-compose-v2`); `cron`, whose entry for the nightly dump is added by hand. The images are built only for `linux/amd64`.',
             '`docs/deploy.md`, step 3; `deploy/bootstrap-server.sh`; `.github/workflows/ci.yml`.'],
            ['Server: address and ports',
             'A static public IP address; the name is `<IP with dashes>.sslip.io` or an own domain. Inbound ports 22, 80 and 443. SSH sign-in by a public key (user `azureuser`).',
             '`docs/deploy.md`, steps 3 and 4. The ports are listed in ' + ctx.ref('tab:ports') + '.'],
            ['Workstation of a developer',
             'A computer with Docker (Docker Desktop on a Mac) and Compose, and Git. PHP 8.4, Node 22 and MySQL 8.4 run in containers, nothing else is installed. '
             'The stack has four containers: `web` (nginx), `app` (php-fpm), `frontend` (Vite) and `mysql`; it uses the ports 8000 and 127.0.0.1:3308.\n'
             'The memory and the disk of the computers of the team are not recorded in the repository.',
             '`README.md`; `docker-compose.yml`; technical specification, item 5.4 (the development environment). The author works on an Apple Mac with arm64 (`docs/deploy.md`).'],
            ['Runner of the checks and of the delivery',
             'A GitHub-hosted runner `ubuntu-latest`. For a public repository GitHub documents 4 vCPU, 16 GB of memory and 14 GB of SSD, for a private one 2 vCPU, 7 GB and 14 GB. '
             'The job `backend` starts a service container `mysql:8.4`; the images are built with Docker Buildx.',
             '`.github/workflows/ci.yml`; GitHub documentation, “Supported runners and hardware resources”. The files do not say whether the repository is public.'],
            ['Registry of images',
             'GitHub Container Registry (`ghcr.io`): the packages `bookshelf-app` and `bookshelf-web`, the tag is the hash of the commit.',
             '`.github/workflows/ci.yml` (jobs `images` and `deploy`).'],
            ['Device of a reader',
             'A smartphone or a computer with Internet access, a screen at least 360 px wide and a modern browser with the features of ' + ctx.ref('tab:software_browser') + '. '
             'Minimum versions: Chrome and Edge 111, Safari 16.4, Firefox 128. A storage of a few megabytes for the cache of the shell.',
             'Technical specification, item 5.4; the derivation is in 14.5.'],
            ['External services',
             'Open Library (search API, covers), Let’s Encrypt (certificates), sslip.io (names), GitHub (code, Actions, GHCR). All are free; none is under the control of the team.',
             '`config/services.php`; `deploy/Caddyfile`; `docs/deploy.md`.'],
        ],
        [2.2, 6.4, 3.2], size=20,
    )

    d.h2('14.3 Network and ports')
    d.p('The ports are given in ' + ctx.ref('tab:ports') + '. The network of the server is not a limit: the size allows up to '
        '6,250 Mbit/s (Azure documentation), and the estimated traffic of the busiest hour is below 50 kbit/s (section 3.3).')
    ctx.table(d, 'tab:ports', 'Ports and connections')
    d.table(
        ['Port', 'Where', 'Purpose'],
        [
            ['443/TCP', '`caddy`, open to the Internet', 'HTTPS: the client and the API. The certificate of Let’s Encrypt is obtained and renewed by Caddy, kept in the volume `caddy_data`. The header `Strict-Transport-Security` is set (one year).'],
            ['80/TCP', '`caddy`, open to the Internet', 'HTTP: redirect to HTTPS and the check of the domain by the certificate authority.'],
            ['22/TCP', 'sshd of the host, open', 'Administration, copying of the files of the deployment and `rollout.sh` by GitHub Actions. Sign-in by a public key.'],
            ['80/TCP', '`web`, inside the Compose network', 'The requests that `caddy` passes on (`reverse_proxy web:80`).'],
            ['9000/TCP', '`app`, inside', 'FastCGI of php-fpm; the only client is `web`.'],
            ['3306/TCP', '`mysql`, inside', 'SQL of the application (`DB_HOST=mysql`). Not published on the server; `backup.sh` runs `mysqldump` inside the container.'],
            ['443/TCP, outgoing', 'from `app`, from the server, from the browser', '`app` calls `openlibrary.org`; the server pulls images from `ghcr.io`; the browser loads covers from `covers.openlibrary.org` (the policy allows images from any https address).'],
            ['8000/TCP, 127.0.0.1:3308', 'workstation, development only', '`web` of the development stack (`WEB_PORT`) and MySQL for a database client (`MYSQL_PORT`); the Vite server on 5173 is reached only through `web`.'],
        ],
        [1.7, 3.0, 7.1], size=20,
    )

    d.h2('14.4 Memory budget of the server')
    d.p('The memory is the narrowest resource of the server. How the configuration spends it is given in ' + ctx.ref('tab:memory') +
        '. The figures of the first two rows were observed by the authors on a fresh server (`docs/deploy.md`), the settings '
        'are read from the configuration, the rest are estimates and are marked so. The repository does not record a '
        'measurement of the memory of the running stack (`free -m`, `docker stats`), so the real use is not known.')
    ctx.table(d, 'tab:memory', 'Memory budget of the server (MB)')
    d.table(
        ['Item', 'Setting', 'Memory', 'Source'],
        [
            ['Memory the system sees', '1 GiB virtual machine', f'{VISIBLE_MB}', '`docs/deploy.md`.'],
            ['System and Docker', 'Ubuntu, Docker Engine, containerd', f'about {USED_MB}', '`docs/deploy.md`: used on a fresh server.'],
            ['Left for the stack', '', f'about {free}', f'Derived: {VISIBLE_MB} − {USED_MB}. The comment in `docker-compose.prod.yml` says “about 400 MB”.'],
            ['MySQL', '`--innodb-buffer-pool-size=64M`, `--performance-schema=OFF`, `--skip-log-bin`, `--max-connections=40`',
             f'{POOL_MB} for the buffer pool; the rest of the process is not limited and not measured',
             '`deploy/docker-compose.prod.yml`. The comment there says that the performance schema alone would take a few hundred MB.'],
            ['PHP, code cache', '`opcache.memory_consumption=128`, `validate_timestamps=0`, `max_accelerated_files=10000`',
             f'up to {OPCACHE_MB}, shared by the workers; the real use is not measured', '`docker/php/prod.ini`.'],
            ['PHP, one request', '`memory_limit = 256M`', f'up to {LIMIT_MB} for one request; {ORDINARY_MB} is assumed for an ordinary one',
             '`docker/php/prod.ini`. The limit is a ceiling, not a reservation. The assumption is an estimate for Laravel.'],
            ['php-fpm pool', '`pm = ondemand`, `pm.max_children = 6`, `pm.process_idle_timeout = 20s`, `pm.max_requests = 500`',
             'no worker when the site is quiet; at most 6', '`docker/php/prod-fpm.conf`. A worker is renewed after 500 requests, which limits the growth of its memory.'],
            ['Caddy, nginx', 'no settings', 'small; not measured', '`deploy/docker-compose.prod.yml`.'],
            ['Limits of containers', 'none (`mem_limit` is not set)', 'the kernel chooses what to stop when the memory ends',
             'A stopped container is started again by `restart: unless-stopped`.'],
            ['Swap', 'a file `/swapfile` of 2 GB, in `/etc/fstab`', 'the safety net, not a working area', '`deploy/bootstrap-server.sh`.'],
            ['The heaviest moment (estimate)', 'one import of a book and five ordinary requests',
             f'{LIMIT_MB} + {ordinary} × {ORDINARY_MB} + {POOL_MB} + {OPCACHE_MB} = {worst}',
             f'Derived. It is {over} MB above the {free} MB that are free, without the rest of MySQL, Caddy and nginx.'],
        ],
        [2.2, 3.6, 3.0, 3.6], size=20,
    )
    d.p('The budget does not close at the heaviest moment, and the difference goes to the swap. The design reduces the chance '
        'of this in four ways: only one book is imported at a time (the lock `epub-import`), at most six workers run and they '
        'are renewed, MySQL has a small buffer and no binary log, and the quiet site keeps no PHP workers. A swap on a network '
        'disk is slow, so a swap that grows all the time means that the server is too small. The first step then is a '
        'machine with 4 GB (`B2als_v2`), which is paid from the credit of $100; this is the advice of `docs/deploy.md`.')

    d.h2('14.5 Requirements for the device of a reader')
    d.p('The client works in a browser and can be installed as an application. The features of the browser that the code '
        'uses are given in ' + ctx.ref('tab:software_browser') + ' (Section 13.5); here the minimum versions that follow from '
        'them are derived.')
    d.p('The minimum versions are derived, not tested. The client is built by Vite with its default target, which the '
        'documentation of Vite gives as Chrome 111, Edge 111, Firefox 114 and Safari 16.4 (the option `build.target`). '
        'Tailwind CSS 4 needs Chrome 111, Safari 16.4 and Firefox 128 (the documentation of Tailwind, “Compatibility”). '
        'The highest of these is taken: Chrome and Edge 111, Safari 16.4 (iOS 16.4) and Firefox 128. The service worker is '
        'built for ES2022 (`vite.sw.config.ts`). The technical specification asks for the two latest versions of the '
        'four browsers, which is stricter than this minimum. The behaviour on real phones (installation, the offline start, '
        'the selection of text on iOS and Android) is not covered by automated tests; `docs/pwa.md` has a checklist for it.')
