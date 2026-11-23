"""Measures the size of Bookshelf for laboratory work 5: lines of the product code (without tests) by function group.

    python3 docs/lab5/measure.py          # writes docs/lab5/loc.json

The groups follow the works of the network schedule of laboratory work 2, so that the effort of a work can be put
against the size of what it produced. A file goes to the first group whose pattern matches its path.
"""

from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]

# (key, name, works of the schedule, patterns). The order matters: the first match wins.
GROUPS = [
    ('reader', 'Built-in reading of EPUB', 'Q, R',
     r'Services/Epub|Actions/Reader|BookText|BookChapter|ReadingPosition|ReaderError|routes/reader|reader\.uk|types/reader|'
     r'router/reader|usePositionSaver|useReaderSettings|useTextSelection|components/reader|readerDom|api/reader|'
     r'ReaderView|book_texts|text_anchor|ImportBusy|SaveReadingPosition'),
    ('community', 'Community and moderation', 'N, O, P',
     r'Actions/Social|BlockController|CommentController|FeedController|FollowController|LikeController|PostController|'
     r'ProfileController|ReportController|BookReviewController|SearchReaders|StorePost|UpdatePost|StoreComment|StoreReport|'
     r'Resources/(Post|Comment|Profile|Reader|Report)|PostType|Report(Reason|Status|ableType)|Policies/(Post|Comment)|'
     r'Console/Commands|routes/social|social\.uk|types/social|router/social|stores/(blocks|comments|feed|posts|profiles|social)|'
     r'views/(Community|ComposePost|Post|ReaderProfile|BlockedReaders)|components/(CommentForm|CommentList|PostCard|'
     r'PostEditForm|PublishQuoteButton|ReportDialog|ReaderAvatar|ReaderListItem|BookReviews)|utils/social|api/social|'
     r'VisibleToReaders|2026_10_09_1001|2026_10_09_1002|2026_10_09_1003|2026_10_09_1004|2026_10_09_1005|2026_10_09_1006|'
     r'community_columns|Factory.*(Post|Comment|Report)|(Post|Comment|Report)Factory|Models/(Post|Comment|Report)'),
    ('stats', 'Statistics and the annual goal', 'K',
     r'Services/Stats|StatsController|GoalController|SetGoalRequest|ReadingGoal|reading_goals|views/StatsView|'
     r'components/(GoalCard|PagesChart|StatTile)|utils/chart|api/stats'),
    ('quotes', 'Quotes and the quote card', 'J',
     r'Actions/Quotes|QuoteController|Quote(Request|Resource|Policy|Type|Factory)|StoreQuote|UpdateQuote|Models/Quote|'
     r'create_quotes|views/Quote|components/(QuoteCard|DailyQuoteCard|BookQuotes|QuoteSource)|utils/quoteCard|api/quotes|'
     r'stores/quotes'),
    ('progress', 'Reading progress and the journal', 'H, I',
     r'ProgressUpdate|RecordReadingLog|UpdateProgress|ReadingProgress|ReadingLog|reading_logs|'
     r'components/(PageJumpForm|ProgressBar|ReadingJournal)'),
    ('library', 'Library and the catalog', 'E, F',
     r'AddBookToLibrary|ChangeUserBookStatus|UpdateUserBook|StoreUserBook|BookController|Resources/(Book|UserBook)Resource|'
     r'UserBook|Models/(Book|Author)\.php|Author|BookFactory|CatalogSearch|OpenLibrary|BookAlreadyInLibrary|CatalogUnavailable|'
     r'BookSource|BookStatus|BookPolicy|create_books|create_authors|create_user_books|views/(AddBook|Book|Library|Home)View|components/(BookCard|BookCover|'
     r'ReadingCard|RatingStars)|api/library|stores/library|CatalogEntry'),
    ('auth', 'Accounts and sign-in', 'D',
     r'Actions/Fortify|MeController|UserResource|UserPolicy|Models/User\.php|UserFactory|api/auth|stores/auth|'
     r'views/(Login|Register|Profile)View|AuthLayout|router/access|FortifyServiceProvider|UpdateProfileRequest|'
     r'create_users_table'),
    ('pwa', 'PWA: service worker, installation, offline', 'L',
     r'frontend/src/sw/|frontend/src/pwa/|InstallCard|OfflineGate|AppBanners|CrashNotice'),
    ('infra', 'Skeleton, containers, delivery, shared code', 'C, G, L',
     r'.*'),
]
COMPILED = [(key, name, works, re.compile(pattern)) for key, name, works, pattern in GROUPS]


def product_files() -> list:
    names = subprocess.run(
        ['git', 'ls-files', 'backend/app', 'backend/routes', 'backend/database', 'backend/config', 'frontend/src',
         'docker', 'deploy', '.github', 'Makefile', 'docker-compose.yml'],
        cwd=ROOT, capture_output=True, text=True, check=True).stdout.split('\n')
    skip = re.compile(r'\.spec\.ts$|frontend/src/test/|\.gitignore$')
    return [n for n in names if n and not skip.search(n)]


def language(path: str) -> str:
    if path.endswith('.php'):
        return 'PHP'
    if path.endswith(('.ts', '.vue')):
        return 'TypeScript'
    if path.endswith('.css'):
        return 'CSS'
    return 'Other'


def measure() -> dict:
    groups = {key: {'name': name, 'works': works, 'files': 0, 'lines': 0, 'php': 0, 'ts': 0, 'other': 0}
              for key, name, works, _ in COMPILED}
    unmatched = []
    for path in product_files():
        text = (ROOT / path).read_text(encoding='utf-8', errors='ignore')
        lines = len(text.splitlines())
        for key, _, _, pattern in COMPILED:
            if pattern.search(path):
                group = groups[key]
                group['files'] += 1
                group['lines'] += lines
                lang = language(path)
                group['php' if lang == 'PHP' else 'ts' if lang == 'TypeScript' else 'other'] += lines
                if key == 'infra':
                    unmatched.append(path)
                break
    total = {'files': sum(g['files'] for g in groups.values()), 'lines': sum(g['lines'] for g in groups.values()),
             'php': sum(g['php'] for g in groups.values()), 'ts': sum(g['ts'] for g in groups.values()),
             'other': sum(g['other'] for g in groups.values())}
    return {'groups': groups, 'total': total, 'infra_files': unmatched}


if __name__ == '__main__':
    result = measure()
    (HERE / 'loc.json').write_text(json.dumps(result, indent=1, ensure_ascii=False), encoding='utf-8')
    for key, group in result['groups'].items():
        print(f"{key:10} {group['files']:4} files {group['lines']:6} lines (php {group['php']}, ts {group['ts']}, other {group['other']})")
    print('total', result['total'])
    print('infra files:', len(result['infra_files']))
