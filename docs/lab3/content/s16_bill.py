"""Section 16: the bill of equipment and materials, and the estimate of the labour of the project."""

from __future__ import annotations

# The works of the network schedule of laboratory work 2: code, name, duration in working days, predecessors, total slack.
# The durations are the ones of the report on laboratory work 2 (the technical design is a part of the work M).
WORKS = [
    ('A', 'Technical specification', 5, 'none', 0),
    ('B', 'Preliminary design', 5, 'A', 0),
    ('C', 'Skeleton, Docker, CI', 5, 'B', 0),
    ('D', 'Authentication', 4, 'C', 0),
    ('E', 'Library server and Open Library', 6, 'D', 0),
    ('F', 'Library screens', 6, 'D', 0),
    ('G', 'First deployment', 3, 'E, F', 24),
    ('H', 'Progress server and log', 4, 'E', 12),
    ('I', 'Progress screen', 5, 'F, H', 14),
    ('J', 'Quotes and quote card', 7, 'E, F', 0),
    ('K', 'Statistics and annual goal', 7, 'H', 12),
    ('N', 'Community: server', 8, 'E, J', 0),
    ('O', 'Community: screens', 8, 'N, F', 0),
    ('P', 'Moderation and abuse protection', 4, 'N', 8),
    ('Q', 'Built-in reading: EPUB import', 8, 'E', 6),
    ('R', 'Built-in reading: screens, quote selection, progress', 9, 'Q, F, J', 6),
    ('L', 'PWA and polishing', 4, 'I, K, O, R', 0),
    ('M', 'Acceptance and documentation', 5, 'G, L, P', 0),
]
CRITICAL = 57       # working days, the critical path A-B-C-D-E/F-J-N-O-L-M
TEAM = 3
DAYS_PER_WEEK = 5


def write(d, ctx) -> None:
    total = sum(w[2] for w in WORKS)
    critical_works = [w for w in WORKS if w[4] == 0]
    on_path = sum(w[2] for w in critical_works) - 6   # E and F run in parallel: 6 days are counted once
    assert total == 103 and on_path == CRITICAL, (total, on_path)
    load = total / (TEAM * CRITICAL) * 100

    d.h1('16 Bill of equipment and materials')
    d.p('The methodology asks for a list of the equipment and materials that are needed to carry out the project. For Bookshelf '
        'they are the server and its services, the computers and phones of the team and the software, which are listed in '
        + ctx.ref('tab:bill') + ', and the labour, which is estimated in ' + ctx.ref('tab:works') + '. Only what is known from the '
        'repository and from the documents is stated. No price is invented: where the documents give none, the table says so.')

    d.h2('16.1 Equipment, services and materials')
    ctx.table(d, 'tab:bill', 'Bill of equipment and materials')
    d.table(
        ['No.', 'Item', 'Quantity', 'Purpose', 'Basis of the cost'],
        [
            ['1', 'Virtual machine in Azure, `Standard_B2ats_v2` (2 vCPU, 1 GiB), Denmark East, Ubuntu Server 24.04', '1',
             'The production server (section 14).',
             'The free offer: 750 hours a month for 12 months, which covers one machine that works round the clock. The price in money is not shown in the portal for this subscription (`docs/deploy.md`).'],
            ['2', 'Disk of the machine, 30 GB, Standard SSD', '1', 'The system, the database, the images, the dumps and the swap.',
             'Paid from the student credit of $100 for 12 months (`docs/deploy.md`); a budget alert of $20 is set.'],
            ['3', 'Static public IP address', '1', 'A stable address, so that the name and the certificate stay valid.',
             'From the same credit (`docs/deploy.md`).'],
            ['4', 'Outgoing traffic of the machine', 'as used', 'Answers to readers, images, the search of Open Library.',
             'From the same credit. The estimate of the traffic is below 50 kbit/s in the busiest hour (section 3.3).'],
            ['5', 'Name of the site: `<IP with dashes>.sslip.io`', '1', 'The address of the site without buying a domain.',
             'Free (`docs/deploy.md`, step 4). The alternative is a domain from the GitHub Student Pack, also free.'],
            ['6', 'TLS certificate of Let’s Encrypt, obtained by Caddy', '1', 'HTTPS, which the service worker and the cookie of the session need.',
             'Free.'],
            ['7', 'GitHub: the repository, Actions, Container Registry (GHCR)', '1 repository', 'The code, the checks, the images and the delivery.',
             'No payment is recorded in the repository. The limits of the plan are not stated there.'],
            ['8', 'Computers of the developers (a Mac with Docker, Git and a browser)', f'one for each member of the team ({TEAM})', 'Development and the checks before a merge.',
             'Own equipment, nothing is bought for the project.'],
            ['9', 'Phones for the acceptance tests: an Android phone with Chrome and an iPhone with Safari', '1 + 1', 'The manual check of the installation, the offline start and the selection of text (`docs/pwa.md`).',
             'Own devices of the team, nothing is bought. The check is still to be done by hand.'],
            ['10', 'Software: Docker, PHP 8.4, Laravel, MySQL 8.4, Node 22, Vue, Vite, Tailwind CSS, Pest, Vitest, Larastan, Pint, ESLint', 'see section 13', 'Development, checks and the work of the system.',
             'Free, with open licenses (technical specification, item 6); there is no paid license.'],
            ['11', 'Open Library API', 'one service', 'The search of books and their covers.',
             'Free, without a key (`config/services.php`).'],
            ['12', 'Key pair for SSH and the secrets of the deployment', '1 key, 4 secrets', 'The deployment from GitHub Actions to the server.',
             'Made by the team (`docs/deploy.md`, steps 2 and 6); no cost.'],
            ['13', 'Word processor for the documents of the laboratory works (files `.docx`)', 'as needed', 'The technical specification, the preliminary design and this document.',
             'The license is not recorded in the repository.'],
        ],
        [0.5, 3.6, 1.5, 3.2, 3.9], align=['center', 'left', 'left', 'left', 'left'], size=20,
    )
    d.p('There is no consumable material: the system is software. The only recurring cost is the server. It is free for 12 '
        'months, then the machine is paid or the system moves to another host; this is row 16 of ' + ctx.ref('tab:reliability') + '.')

    d.h2('16.2 Estimate of the labour')
    d.p('The labour is estimated from the network schedule of the report on laboratory work 2. It has 18 works, from A to R, '
        f'and the team has {TEAM} people. The works, their durations, their predecessors and their total slack are given in '
        + ctx.ref('tab:works') + '. The technical design (laboratory work 3) is a part of the work M and is not a separate work.')
    rows = []
    shade = set()
    for index, (code, name, days, preds, slack) in enumerate(WORKS):
        critical = 'yes' if slack == 0 else 'no'
        if slack == 0:
            shade.add(index)
        rows.append([code, name, str(days), preds, str(slack), critical])
    rows.append(['', '**Sum of the durations of all works**', f'**{total}**', '', '', ''])
    ctx.table(d, 'tab:works', 'Works of the project and their labour in working days')
    d.table(['Work', 'Name', 'Duration, working days', 'Predecessors', 'Total slack', 'Critical'], rows,
            [0.8, 5.2, 1.6, 1.8, 1.3, 1.2], align=['center', 'left', 'center', 'center', 'center', 'center'], size=22, shade=shade)
    d.p(f'The rows of the critical works are shaded. The critical path is A, B, C, D, E (F runs in parallel with it), J, N, O, '
        f'L, M. Its length is 5 + 5 + 5 + 4 + 6 + 7 + 8 + 8 + 4 + 5 = {CRITICAL} working days, that is about 12 weeks at '
        f'{DAYS_PER_WEEK} working days a week. The sum of the durations of all works is {total} working days.')
    d.p(f'**The labour.** If one person does one work, the labour of the project is {total} person-days. The team of {TEAM} '
        f'has {TEAM * CRITICAL} person-days in the {CRITICAL} days of the critical path, so the average load is '
        f'{total} / {TEAM * CRITICAL} = {load:.0f} %. The rest is the room that the slack of the works outside the critical '
        'path and the delays give. This is an estimate of the schedule, not a measurement of the time that was spent.')
    d.p('**The cost in money is not fixed.** Bookshelf is a study project: the technical specification (item 6) says that the '
        'product is developed by a student team for educational purposes without paid work, and that the hosting is paid '
        'from the student credit. The repository records no rate of pay, so the labour is given in person-days only. If '
        'a price were needed, it would be the number of person-days multiplied by a rate that the customer sets.')
