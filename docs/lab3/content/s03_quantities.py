"""Subsection 3.3 of the explanatory note: quantitative data (the number of readers, the volume of the data and the
intensity of the flows of information that the technical design takes as its basis).

Every number that is an estimate is computed here from the stated assumptions, so the text and the tables cannot
disagree with each other. Limits are copied from the code and the configuration (the file is named in the table).
"""

from __future__ import annotations

# ---- assumptions (all of them are estimates, see the "Basis" column of the tables) --------------------------------------

READERS = 500                 # technical specification, item 6 "Expected load"
DAILY_SHARE = 0.20            # readers who open the application on one day
BUSY_HOUR_SHARE = 0.25        # of the daily readers, active in the busiest hour
REQ_PER_READER_HOUR = 120     # API requests of one active reader in that hour
READING_SHARE = 0.20          # of the busy-hour readers, with the reading screen open
POSITION_PER_HOUR = 3600 // 15  # the reading position is saved every 15 s (SAVE_INTERVAL_MS)
BURST = 5                     # peak second against the average of the hour
SERVICE_S = 0.100             # service time of one request in a php-fpm worker
CPU_S = 0.050                 # CPU time of one request, with the share of MySQL, nginx and Caddy
WORKERS = 6                   # pm.max_children
BASE_VCPU = 0.4               # B2ats_v2: base performance 20 % of 2 vCPU
VCPU = 2
ANSWER_KB = 5                 # average size of an API answer
IMPORT_S = 10                 # TZ item 5.1.3: import of a typical book in at most 10 s

HDR = 18       # bytes of an InnoDB record: header 5, transaction id 6, rollback pointer 7
IDX = 5        # bytes of the header of a record of a secondary index
FILL = 1.2     # reserve for the fill of pages (estimate)
TEXT_BYTES = 1_000_000   # TZ item 5.4: about 1 MB of stored text for a typical book
DUMP_RATIO = 3           # gzip of text and HTML (estimate)
DUMPS_KEPT = 14          # deploy/backup.sh deletes dumps older than 14 days
DISK_GB = 30
SYSTEM_GB = 5            # Ubuntu, Docker and the images (estimate)
SWAP_GB = 2
CACHE_SEARCHES_PER_DAY = 100
CACHE_ANSWER_KB = 50


def human(n: float) -> str:
    """Bytes in decimal units (KB = 1,000 B), the way the technical specification counts."""
    for unit, size in (('GB', 1e9), ('MB', 1e6), ('KB', 1e3)):
        if n >= size:
            value = n / size
            return f'{value:.1f} {unit}' if value < 100 else f'{value:.0f} {unit}'
    return f'{n:.0f} B'


def num(n: float) -> str:
    return f'{n:,.0f}'


class Record:
    """Bytes of one record of a table: the columns, the header of the record and the entries of its indexes."""

    def __init__(self, name: str, columns: str, fixed: int, var: int, nullable: int, index_keys: list, count: int):
        self.name = name
        self.columns = columns
        self.row = fixed + var + HDR + (nullable + 7) // 8
        self.index = sum(IDX + key for key in index_keys)
        self.count = count

    @property
    def total(self) -> int:
        return self.row + self.index

    @property
    def volume(self) -> float:
        return self.total * self.count


def records() -> dict:
    r = READERS
    return {
        'user': Record(
            '`users`', 'id 8, email_verified_at 4, is_admin 1, created_at 4, updated_at 4; name 31, email 26, handle 13, '
            'password (bcrypt) 61, timezone 12, bio 82; unique indexes on email and handle',
            21, 225, 4, [25 + 8, 12 + 8], r),
        'session': Record(
            '`sessions`', 'one row per signed-in browser, 1.5 per reader; user_id 8, last_activity 4; id 41, ip_address 16, '
            'user_agent 122, payload 450; indexes on user_id and last_activity',
            12, 629, 3, [8 + 41, 4 + 41], int(r * 1.5)),
        'book': Record(
            '`books`', 'shared catalog, 10,000 books; id 8, page_count 4, published_year 2, created_by 8, two timestamps 8; '
            'title 61, isbn_13 9, openlibrary_work_key 19, cover_url 52, subjects (JSON) 300, source 12; indexes on the key and created_by',
            30, 453, 8, [18 + 8, 8 + 8], 10_000),
        'author': Record(
            '`authors`', '7,000 authors; id 8, two timestamps 8; name 31, openlibrary_key 12; indexes on the key and the name',
            16, 43, 3, [12 + 8, 31 + 8], 7_000),
        'author_book': Record(
            '`author_book`', '12,000 links; book_id 8, author_id 8, position 1; primary key and an index on author_id',
            17, 0, 0, [8 + 8], 12_000),
        'user_book': Record(
            '`user_books`', '30 entries per reader; id, user_id, book_id 8 each; status 1 + 7; current_page, total_pages 4 each; '
            'rating 1; four timestamps 16; indexes (user_id, book_id), (user_id, status), book_id',
            49, 8, 6, [8 + 8 + 8, 8 + 8 + 8, 8 + 8], r * 30),
        'log': Record(
            '`reading_logs`', '100 entries per reader (technical specification); id, user_book_id, user_id 8 each; from_page, '
            'to_page 4 each; logged_on 3; created_at 4; indexes (user_book_id, id), (user_id, logged_on)',
            39, 0, 0, [8 + 8, 8 + 3 + 8], r * 100),
        'quote': Record(
            '`quotes`', '50 per reader; fixed 57 (ids, page, flag, timestamps, four columns of the anchor); type 6; content '
            '300 characters × 2 B + 2; note on every second quote, 150 B + 2; four indexes',
            57, 760, 6, [16, 17, 16, 20], r * 50),
        'post': Record(
            '`posts`', '20 per reader (technical specification); fixed 49 (ids, rating, page, two counters, three timestamps); '
            'type 6; content 500 B + 2; comment 200 B + 2; three indexes (one on the virtual column review_book_id)',
            49, 710, 6, [24, 16, 22], r * 20),
        'comment': Record(
            '`comments`', '60 per reader; ids 24, three timestamps 12; content 120 characters × 2 B + 2; indexes (post_id, id), (user_id, id)',
            36, 242, 3, [16, 16], r * 60),
        'like': Record(
            '`likes`', '140 per reader; user_id 8, post_id 8, created_at 4; primary key and an index on post_id',
            20, 0, 0, [16], r * 140),
        'follow': Record(
            '`follows`', '20 per reader; follower_id 8, followed_id 8, created_at 4; primary key and an index on followed_id',
            20, 0, 0, [16], r * 20),
        'book_text': Record(
            '`book_texts` (a row)', '10 per reader (5,000 in all, technical specification); three ids 24, six numbers 36, '
            'two more columns 4; source_name 41; indexes on user_book_id and user_id',
            64, 41, 3, [16, 16], r * 10),
    }


def figures() -> dict:
    rec = records()
    small = sum(x.volume for x in rec.values())
    small_fill = small * FILL
    texts = rec['book_text'].count * TEXT_BYTES
    cache = CACHE_SEARCHES_PER_DAY * 365 * CACHE_ANSWER_KB * 1000
    total = small_fill + texts + cache
    # what belongs to one reader (the shared catalog, the authors and the sessions are not counted)
    own = sum(rec[k].total * rec[k].count for k in
              ('user', 'user_book', 'log', 'quote', 'post', 'comment', 'like', 'follow', 'book_text')) / READERS
    dump = total / DUMP_RATIO
    free_gb = DISK_GB - SYSTEM_GB - SWAP_GB - total / 1e9
    fits = int((free_gb - 2) / (dump / 1e9))
    busy_readers = READERS * DAILY_SHARE * BUSY_HOUR_SHARE
    reading = busy_readers * READING_SHARE
    tracker = busy_readers - reading
    hour = tracker * REQ_PER_READER_HOUR + reading * (REQ_PER_READER_HOUR + POSITION_PER_HOUR)
    avg = hour / 3600
    peak = avg * BURST
    return {
        'rec': rec, 'small': small, 'small_fill': small_fill, 'texts': texts, 'cache': cache, 'total': total,
        'own': own, 'dump': dump, 'free_gb': free_gb, 'fits': fits,
        'busy': busy_readers, 'reading': reading, 'tracker': tracker, 'hour': hour, 'avg': avg, 'peak': peak,
    }


def write(d, ctx) -> None:
    f = figures()
    rec = f['rec']
    daily = int(READERS * DAILY_SHARE)

    d.h2('3.3 Quantitative data')
    d.p('The technical design is based on the number of readers, on the volume of the stored data and on the intensity of '
        'the flows of information. They are given in ' + ctx.ref('tab:quantities') + '. Every value is of one of three kinds.')
    d.bullets([
        '**Limit**: the code or the configuration enforces the value (a rate limiter, a validation rule, `EpubLimits`, a '
        'setting of php-fpm). The last column names the file where it can be checked.',
        '**Assumption**: a value chosen for the design. It is an estimate, not a measurement, and the last column says how it '
        'was obtained. The scale is the planned scale of the technical specification (item 6, “Expected load”: 500 registered '
        'readers, 50,000 reading log records, 10,000 posts, 100,000 likes and comments together, 5,000 uploaded books). It is '
        'taken as the scale that is reached after one year of work.',
        '**Derived**: a value calculated from limits and assumptions; the calculation is shown.',
    ])
    d.p('The assumptions are justified by the capacity of the server: one virtual machine with 2 vCPU, 1 GiB of memory and a '
        '30 GB disk (section 14, ' + ctx.ref('tab:hardware') + '). No load test and no measurement of memory has been made, '
        'so every figure about the capacity is an estimate and is marked so. The units: MiB is 1,048,576 bytes (the limits of '
        'the code), MB and GB are 10^6 and 10^9 bytes (the estimates).')

    # ------------------------------------------------------------------------------------------------------------------
    avg = f['avg']
    peak = f['peak']
    cpu_peak = peak * CPU_S
    cpu_avg = avg * CPU_S
    pool = WORKERS / SERVICE_S
    sustained = BASE_VCPU / CPU_S
    burst_cap = VCPU / CPU_S
    imports = 3600 // IMPORT_S
    traffic_mb = f['hour'] * ANSWER_KB / 1000
    traffic_kbit = f['hour'] * ANSWER_KB * 1000 * 8 / 3600 / 1000
    per_reader = '`{}`'.format

    rows = []
    shade = set()

    def group(title: str) -> None:
        shade.add(len(rows))
        rows.append([f'**{title}**', '', '', ''])

    group('Readers and activity')
    rows += [
        ['Registered readers', num(READERS), 'Assumption',
         'The planned scale of the technical specification (item 6).'],
        ['Readers who open the application on one day', f'{daily} (20 %)', 'Assumption',
         'Estimate for a study project. The repository has no figures of use, so it cannot be checked.'],
        ['Readers active in the busiest hour', f'{f["busy"]:.0f} (25 % of {daily})', 'Assumption',
         'Estimate: the evening hour.'],
        ['Requests of one active reader in that hour', f'{REQ_PER_READER_HOUR} (one in 30 s)', 'Assumption',
         'Estimate: one screen sends several requests (the home screen asks for the books being read and the quote of the day), a page update sends one.'],
        ['Readers with the reading screen open in that hour', f'{f["reading"]:.0f} (20 % of {f["busy"]:.0f})', 'Assumption',
         f'Estimate. Such a reader adds one request every 15 s, {POSITION_PER_HOUR} an hour (`SAVE_INTERVAL_MS` in `usePositionSaver.ts`).'],
        ['Requests in the busiest hour', num(f['hour']), 'Derived',
         f'{f["tracker"]:.0f} × {REQ_PER_READER_HOUR} + {f["reading"]:.0f} × ({REQ_PER_READER_HOUR} + {POSITION_PER_HOUR}).'],
        ['Average rate in that hour', f'{avg:.1f} requests/s', 'Derived', f'{num(f["hour"])} / 3,600 s.'],
        ['Peak rate inside the hour', f'about {peak:.0f} requests/s', 'Derived',
         f'The average × {BURST}. The factor is an assumption: a screen sends its requests at once.'],
        ['Searches that reach Open Library', f'{CACHE_SEARCHES_PER_DAY} a day', 'Assumption',
         f'{daily} readers × 2 searches, half of them with a new text; the other answers come from the cache of 24 h.'],
    ]

    group('Limits of the requests (per reader)')
    rows += [
        ['Sign in', '5 per minute for a pair (e-mail, address)', 'Limit',
         '`RateLimiter::for(\'login\')` in `FortifyServiceProvider`.'],
        ['Likes, follows, saving the reading position', '120 per minute each', 'Limit',
         'Limiters `likes`, `follows`, `reader-position` in `AppServiceProvider`.'],
        ['Search in the catalog, search of readers', '30 per minute each', 'Limit', 'Limiters `catalog` and `search`.'],
        ['Changes and deletes of posts and comments, blocks', '60 per minute', 'Limit', 'Limiter `social-writes`.'],
        ['New posts, comments, reports, uploaded books', '20, 60, 20 and 20 per hour', 'Limit',
         'Limiters `posts`, `comments`, `reports`; `throttle:20,60` on the upload route in `routes/reader.php`.'],
        ['All other routes (registration, library, progress, quotes, statistics, goals, feed)', 'no limit', 'Limit',
         'These routes have no `throttle` middleware, and `bootstrap/app.php` does not call `throttleApi()`.'],
        ['Most that one reader can send through the limited routes', '8 requests/s', 'Derived',
         '(120 + 120 + 120 + 60 + 30 + 30) per minute = 480 per minute. The limiters are separate buckets; the routes without a limit come on top.'],
    ]

    group('Limits of the size of data')
    rows += [
        ['Body of a request', '2 MiB; 25 MiB for the upload route', 'Limit',
         '`client_max_body_size` in `docker/web/nginx.conf`; PHP: `upload_max_filesize` 20M, `post_max_size` 22M (`docker/php/prod.ini`).'],
        ['EPUB file; unpacked size; entries of the archive; chapters', '15 MiB; 60 MiB; 2,000; 500', 'Limit', '`EpubLimits`.'],
        ['HTML of one chapter; one XML document', '2 MiB; 10 MiB', 'Limit', '`EpubLimits`.'],
        ['Text of one book', '8,000,000 characters (UTF-16 code units)', 'Limit', '`EpubLimits::maxTextCharacters`.'],
        ['Texts of one reader', '30 texts; 40,000,000 characters', 'Limit', '`maxReaderTexts`, `maxReaderCharacters`.'],
        ['Import of a book', 'one at a time on the server; a second upload waits 5 s, then gets 503 with `Retry-After: 5`',
         'Limit', '`ImportBookText`: `Cache::lock(\'epub-import\', 120)`; `ImportBusyException`.'],
        ['Quote; note; page', '2,000 characters; 2,000 characters; 1 to 20,000', 'Limit', '`StoreQuoteRequest`.'],
        ['Comment on a quote post; review; comment; bio; note of a report',
         '2,000; 5,000; 1,000; 280; 500 characters', 'Limit',
         '`StorePostRequest`, `StoreCommentRequest`, `UpdateProfileRequest`, `StoreReportRequest`.'],
        ['Page of a list', '20 records', 'Limit',
         '`simplePaginate(20)` in the controllers of the feed, quotes, logs, comments, posts of a profile, blocks, reviews; the search in Open Library asks for 20.'],
        ['Session of a reader', '30 days, in the table `sessions`', 'Limit', '`SESSION_LIFETIME` 43,200 minutes in `deploy/docker-compose.prod.yml`.'],
        ['Time to wait', '20 s (client), 120 s (upload), 60 s (`fastcgi_read_timeout`), 8 s (Open Library)', 'Limit',
         '`api/http.ts`; `docker/web/nginx.conf`; `config/services.php`.'],
    ]

    group('Data per reader and in the database')
    rows += [
        ['Data of one reader without the texts of books', f'about {human(f["own"])} a year', 'Derived',
         'The sum of the records of one reader in ' + ctx.ref('tab:volume') + ' (the shared catalog is not counted).'],
        ['Texts of one reader', '10 MB on average; at most 40 MB, 80 MB or 120 MB', 'Assumption and limit',
         'Average: 10 texts of 1 MB (technical specification, item 5.4). Maximum: 40,000,000 UTF-16 units of the limit × 1 byte (Latin), 2 bytes (Cyrillic) or 3 bytes (other characters of the basic plane) in UTF-8; the markup is extra.'],
        ['Database after one year', f'about {human(f["total"])}', 'Derived',
         f'Texts {human(f["texts"])}, tables {human(f["small_fill"])}, table `cache` {human(f["cache"])} (' + ctx.ref('tab:volume') + ').'],
        ['One dump of the database (`backup.sh`)', f'about {human(f["dump"])}; {DUMPS_KEPT} dumps are {human(f["dump"] * DUMPS_KEPT)}', 'Derived',
         f'Compression {DUMP_RATIO}:1 is an estimate. `backup.sh` keeps the dumps of {DUMPS_KEPT} days, on the same disk of {DISK_GB} GB.'],
        ['Texts if all readers used the whole quota', '500 × 80 MB = 40 GB (Cyrillic)', 'Derived',
         'No quota exists for the server as a whole, only per reader. The figure is far above the disk, so the design relies on the average use.'],
    ]

    group('Load against the capacity of the server')
    rows += [
        ['Pool of php-fpm', 'at most 6 workers; started on demand; idle for 20 s; renewed after 500 requests', 'Limit',
         '`pm = ondemand`, `pm.max_children = 6` in `docker/php/prod-fpm.conf`.'],
        ['Connections of MySQL', '40', 'Limit', '`--max-connections=40` in `deploy/docker-compose.prod.yml`.'],
        ['Time of one request in a worker', f'{SERVICE_S * 1000:.0f} ms, of it {CPU_S * 1000:.0f} ms of CPU', 'Assumption',
         'Estimate, not measured: every request reads the session and the user from MySQL (and the counter of the limiter on the limited routes). The technical specification asks for at most 500 ms (item 5.1.3).'],
        ['Capacity of the pool', f'{pool:.0f} requests/s', 'Derived',
         f'{WORKERS} workers / {SERVICE_S} s. An upper bound that holds only if the CPU and the memory allow it.'],
        ['Capacity of the processor', f'{sustained:.0f} requests/s at the base performance; up to {burst_cap:.0f} while credits last', 'Derived',
         f'B2ats_v2 has a base performance of 20 % of 2 vCPU = {BASE_VCPU} vCPU (Azure documentation, Basv2 series): {BASE_VCPU} / {CPU_S} s and {VCPU} / {CPU_S} s.'],
        ['Load at the peak and on average', f'{cpu_peak:.2f} vCPU ({cpu_peak / VCPU * 100:.0f} % of the VM) and {cpu_avg:.2f} vCPU ({cpu_avg / VCPU * 100:.0f} %)', 'Derived',
         f'{peak:.0f} × {CPU_S} s and {avg:.1f} × {CPU_S} s. Both are below the base performance of 20 %, so credits are not spent.'],
        ['Workers busy at the peak', f'{peak * SERVICE_S:.1f} on average', 'Derived',
         f'{peak:.0f} requests/s × {SERVICE_S} s (Little’s law). The pool of {WORKERS} is about ten times larger.'],
        ['Imports of books', f'at most {imports} an hour', 'Derived',
         f'One at a time; {IMPORT_S} s for a typical book is the requirement of the technical specification (item 5.1.3). The demand is 5,000 texts a year, about 14 a day.'],
        ['Traffic of the API in the busiest hour', f'about {traffic_mb:.0f} MB ({traffic_kbit:.0f} kbit/s)', 'Derived',
         f'{num(f["hour"])} requests × {ANSWER_KB} KB (assumed average answer). Chapters and uploads are not included. The network of the VM allows up to 6,250 Mbit/s.'],
    ]

    ctx.table(d, 'tab:quantities', 'Quantitative data taken as the basis of the technical design')
    d.table(['Parameter', 'Value', 'Kind', 'Basis'], rows, [3.3, 3.1, 1.25, 4.9], size=20, shade=shade)

    d.p('The estimate says that at the planned scale the pool of php-fpm has a margin of about '
        f'{pool / peak:.0f} times at the peak second. The processor has less: the peak second uses about '
        f'{cpu_peak / BASE_VCPU * 100:.0f} % of its base performance and the average of the hour {cpu_avg / BASE_VCPU * 100:.0f} %, '
        'and the credits of the virtual machine cover the peaks. The memory (section 14) and the disk (section 15) leave '
        'the least room. The rate limiters do not protect the server as a whole: one reader may send '
        f'8 requests/s through the limited routes and any rate through the others, while the pool serves about {pool:.0f}.')

    # ------------------------------------------------------------------------------------------------------------------
    d.h3('3.3.1 Size of the data')
    d.p('The size of a record is counted from the column types in the migrations (`backend/database/migrations`). A record of '
        f'InnoDB has a header of {HDR} bytes (5 for the header, 6 for the transaction id, 7 for the rollback pointer) and one '
        f'byte of the map of NULL values per eight nullable columns. A record of a secondary index takes {IDX} bytes and the '
        'columns of the key and of the primary key. Variable columns are counted at an assumed average length in bytes; '
        'Ukrainian text is taken as 2 bytes per character (UTF-8), the longest value allowed is 4 bytes per character. '
        'A reserve of 1.2 is added for the fill of the pages. The sizes are estimates and have not been compared with '
        '`information_schema` of a running server.')
    d.p('The sizes and the volume after one year are given in ' + ctx.ref('tab:volume') + '. The longest quote that the rules '
        'allow (a content and a note of 2,000 characters of 4 bytes each) is 16 KB, so a row always fits the limit of '
        'a `TEXT` column of 65,535 bytes.')

    order = ['user', 'session', 'book', 'author', 'author_book', 'user_book', 'log', 'quote', 'post', 'comment', 'like',
             'follow', 'book_text']
    vol_rows = []
    for key in order:
        x = rec[key]
        vol_rows.append([x.name, x.columns, num(x.total), num(x.count), human(x.volume)])
    vol_rows.append([
        '`book_chapters`', f'the sanitized HTML of {num(rec["book_text"].count)} texts at {human(TEXT_BYTES)} each '
        '(technical specification, item 5.4); the rows of the chapters (about 25 per text) are small against it',
        num(TEXT_BYTES), num(rec['book_text'].count), human(f['texts'])])
    vol_rows.append([
        '`cache`', f'about {CACHE_SEARCHES_PER_DAY} different searches a day; Open Library answers are kept as received '
        f'(estimate {CACHE_ANSWER_KB} KB each, not measured). Expired rows are not deleted until the same key is read again',
        num(CACHE_ANSWER_KB * 1000), num(CACHE_SEARCHES_PER_DAY * 365), human(f['cache'])])
    vol_rows.append([
        '**Total**', f'tables without texts and cache {human(f["small"])} × {FILL} = {human(f["small_fill"])}; '
        f'texts {human(f["texts"])}; cache {human(f["cache"])}', '', '', f'**{human(f["total"])}**'])
    ctx.table(d, 'tab:volume', 'Size of the records and volume of the database after one year (estimate)')
    d.table(['Table', 'Columns, assumed lengths in bytes', 'Bytes per record', 'Records', 'Volume'], vol_rows,
            [2.1, 6.3, 1.2, 1.2, 1.3], align=['left', 'left', 'right', 'right', 'right'], size=18,
            shade={len(vol_rows) - 1})

    d.p(f'The texts of the books are {f["texts"] / f["total"] * 100:.0f} % of the volume, so the disk is decided by how many '
        'books the readers upload. The table `cache` is the second item: the code stores the answers of Open Library '
        'before they are normalised (`OpenLibraryClient::cachedSearch`), and the database cache driver deletes an expired '
        'row only when the same key is read again. Section 15 returns to both facts.')
