"""Runs the program against the local development stack and saves what it prints, for the "Results" part of the report.

It needs `make up` (http://localhost:8000). It registers four demo readers through the HTTP API, takes a few steps of the
use cases (a book, a progress update, statistics, a post, three reports), runs the moderation commands of the operator,
saves every answer to `docs/lab4/results/*.txt` and removes the demo readers again. It talks only to localhost.

    python3 docs/lab4/results.py
"""

from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
RESULTS = Path(__file__).resolve().parent / 'results'
sys.path.insert(0, str(ROOT / 'docs' / 'lab3' / 'diagrams'))

import screenshots as shots  # noqa: E402

EMAILS = ['demo.olena@example.test', 'demo.taras@example.test', 'demo.ivan@example.test', 'demo.marta@example.test']
shots.EMAILS = EMAILS


def artisan(*args: str) -> str:
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args], cwd=ROOT,
                            capture_output=True, text=True, timeout=120)
    text = re.sub(r'\x1b\[[0-9;]*m', '', result.stdout + result.stderr)
    return '\n'.join(line.rstrip() for line in text.strip().splitlines())


def save(name: str, text: str) -> None:
    RESULTS.mkdir(exist_ok=True)
    (RESULTS / name).write_text(text.rstrip() + '\n', encoding='utf-8')
    print('saved', name)


def pretty(data: dict) -> str:
    return json.dumps(data, indent=2, ensure_ascii=False)


def main() -> None:
    shots.remove_demo_readers()
    api = {}
    for index, (name, email) in enumerate(zip(['Olena Koval', 'Taras Melnyk', 'Ivan Bondar', 'Marta Shevchuk'], EMAILS)):
        reader = shots.Api()
        reader.register(name, email)
        api[email] = reader
    olena, taras, ivan, marta = (api[e] for e in EMAILS)
    olena.call('PATCH', '/api/me', {'handle': 'olena_reads'})

    book = olena.call('POST', '/api/library', {'title': 'Kobzar', 'authors': ['Taras Shevchenko'], 'total_pages': 640, 'status': 'reading'})['data']['id']
    save('progress_update.json', pretty(olena.call('POST', f'/api/library/{book}/progress', {'page': 25})))
    save('progress_refused.json', 'POST /api/library/%d/progress {"page": 700}\n' % book + _status(olena, f'/api/library/{book}/progress', {'page': 700}))
    olena.call('POST', f'/api/library/{book}/progress', {'page': 80})
    quote = olena.call('POST', f'/api/library/{book}/quotes', {'content': 'Fight on, and you will win!', 'page': 12})['data']['id']
    save('stats_summary.json', pretty(olena.call('GET', '/api/stats/summary')))

    post = olena.call('POST', '/api/posts', {'type': 'quote', 'quote_id': quote, 'comment': 'My favourite line.'})['data']['id']
    for reader in (taras, ivan, marta):
        reader.call('POST', f'/api/posts/{post}/report', {'reason': 'spam', 'note': 'looks like spam'})
    save('moderation_reports.txt', '$ php artisan moderation:reports\n' + artisan('moderation:reports'))
    save('moderation_restore.txt', f'$ php artisan moderation:restore post {post}\n' + artisan('moderation:restore', 'post', str(post)))
    save('moderation_after.txt', '$ php artisan moderation:reports\n' + artisan('moderation:reports'))
    save('moderation_wrong_type.txt', '$ php artisan moderation:hide video 1\n' + artisan('moderation:hide', 'video', '1'))

    shots.remove_demo_readers()
    print('demo readers removed')


def _status(api, path: str, data: dict) -> str:
    try:
        api.call('POST', path, data)
    except RuntimeError as error:
        text = str(error)
        status = re.search(r': (\d{3}) ', text)
        body = text.split(' ', 3)[-1] if text else ''
        match = re.search(r'(\{.*\})', text)
        return f'HTTP {status.group(1) if status else "?"}\n' + (pretty(json.loads(match.group(1))) if match else body)
    return 'accepted'


if __name__ == '__main__':
    main()
