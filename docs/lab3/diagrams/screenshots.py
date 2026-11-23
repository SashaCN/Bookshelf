"""Takes screenshots of the working application (the prototype) for the technical design.

It needs the development stack (`make up`, http://localhost:8000) and Google Chrome. The script

 1. removes the demo readers of an earlier run from the DEVELOPMENT database,
 2. fills the application with demo data through its own HTTP API (three readers, books, progress, quotes, an EPUB book,
    posts, comments, likes and follows) and a few days of reading history,
 3. opens the screens in headless Chrome with the size of a phone and saves them to `docs/lab3/figures/shot_*.png`,
 4. removes the demo readers again (pass `--keep` to leave them).

It talks only to localhost. The passwords are made up for the run and are not stored. Run from the repository root:

    python3 docs/lab3/diagrams/screenshots.py
"""

from __future__ import annotations

import base64
import http.cookiejar
import io
import json
import os
import secrets
import socket
import struct
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
FIGURES = ROOT / 'docs' / 'lab3' / 'figures'
BASE = 'http://localhost:8000'
CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
DEBUG_PORT = 9333
PHONE = {'width': 390, 'height': 844}
EMAILS = ['demo.olena@example.test', 'demo.taras@example.test', 'demo.ivan@example.test']
PASSWORD = 'Demo-' + secrets.token_urlsafe(12)


# ---- the HTTP API of the application -------------------------------------------------------------------------------

class Api:
    def __init__(self) -> None:
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def cookie(self, name: str) -> str | None:
        for cookie in self.jar:
            if cookie.name == name:
                return cookie.value
        return None

    def call(self, method: str, path: str, data: dict | None = None, raw: tuple | None = None) -> dict:
        headers = {'Accept': 'application/json', 'Origin': BASE, 'Referer': BASE + '/'}
        token = self.cookie('XSRF-TOKEN')
        if token:
            headers['X-XSRF-TOKEN'] = urllib.parse.unquote(token)
        body = None
        if raw is not None:
            body, content_type = raw
            headers['Content-Type'] = content_type
        elif data is not None:
            body = json.dumps(data).encode()
            headers['Content-Type'] = 'application/json'
        request = urllib.request.Request(BASE + path, data=body, headers=headers, method=method)
        try:
            with self.opener.open(request, timeout=60) as response:
                text = response.read().decode()
        except urllib.error.HTTPError as error:
            raise RuntimeError(f'{method} {path}: {error.code} {error.read().decode()[:300]}') from None
        return json.loads(text) if text else {}

    def start(self) -> None:
        self.call('GET', '/api/sanctum/csrf-cookie')

    def register(self, name: str, email: str) -> None:
        self.start()
        self.call('POST', '/api/auth/register', {
            'name': name, 'email': email, 'password': PASSWORD, 'password_confirmation': PASSWORD,
            'timezone': 'Europe/Kyiv',
        })


def multipart(field: str, filename: str, content: bytes, mime: str) -> tuple:
    boundary = '----bookshelf' + secrets.token_hex(8)
    body = (
        f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{filename}"\r\n'
        f'Content-Type: {mime}\r\n\r\n'
    ).encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    return body, f'multipart/form-data; boundary={boundary}'


def make_epub() -> bytes:
    """A small EPUB with three chapters of a public domain text (Alice's Adventures in Wonderland)."""
    chapters = [
        ('Down the Rabbit-Hole', [
            'Alice was beginning to get very tired of sitting by her sister on the bank, and of having nothing to do: '
            'once or twice she had peeped into the book her sister was reading, but it had no pictures or '
            'conversations in it, “and what is the use of a book,” thought Alice “without pictures or conversations?”',
            'So she was considering in her own mind (as well as she could, for the hot day made her feel very sleepy '
            'and stupid), whether the pleasure of making a daisy-chain would be worth the trouble of getting up and '
            'picking the daisies, when suddenly a White Rabbit with pink eyes ran close by her.',
            'There was nothing so very remarkable in that; nor did Alice think it so very much out of the way to hear '
            'the Rabbit say to itself, “Oh dear! Oh dear! I shall be late!”',
        ]),
        ('The Pool of Tears', [
            '“Curiouser and curiouser!” cried Alice (she was so much surprised, that for the moment she quite forgot '
            'how to speak good English); “now I’m opening out like the largest telescope that ever was! Good-bye, '
            'feet!”',
            'Just then her head struck against the roof of the hall: in fact she was now rather more than nine feet '
            'high, and she at once took up the little golden key and hurried off to the garden door.',
        ]),
        ('A Caucus-Race and a Long Tale', [
            'They were indeed a queer-looking party that assembled on the bank—the birds with draggled feathers, the '
            'animals with their fur clinging close to them, and all dripping wet, cross, and uncomfortable.',
            'The first question of course was, how to get dry again: they had a consultation about this, and after a '
            'few minutes it seemed quite natural to Alice to find herself talking familiarly with them, as if she had '
            'known them all her life.',
        ]),
    ]
    out = io.BytesIO()
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('mimetype', 'application/epub+zip', compress_type=zipfile.ZIP_STORED)
        z.writestr('META-INF/container.xml',
                   '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'
                   '<rootfiles><rootfile full-path="content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>')
        manifest = ''.join(f'<item id="c{i}" href="c{i}.xhtml" media-type="application/xhtml+xml"/>' for i in range(len(chapters)))
        spine = ''.join(f'<itemref idref="c{i}"/>' for i in range(len(chapters)))
        z.writestr('content.opf',
                   '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="id">'
                   '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="id">alice-demo</dc:identifier>'
                   "<dc:title>Alice's Adventures in Wonderland</dc:title><dc:language>en</dc:language></metadata>"
                   f'<manifest>{manifest}</manifest><spine>{spine}</spine></package>')
        for i, (title, paragraphs) in enumerate(chapters):
            body = ''.join(f'<p>{p}</p>' for p in paragraphs)
            z.writestr(f'c{i}.xhtml',
                       '<?xml version="1.0" encoding="utf-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>'
                       f'{title}</title></head><body><h2>{title}</h2>{body}</body></html>')
    return out.getvalue()


# ---- the database (development only) -------------------------------------------------------------------------------

def tinker(code: str) -> str:
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', 'tinker', '--execute', code],
                            cwd=ROOT, capture_output=True, text=True, timeout=120)
    return result.stdout + result.stderr


def remove_demo_readers() -> None:
    emails = json.dumps(EMAILS)
    tinker(f'App\\Models\\User::whereIn("email", {emails})->get()->each->delete();')


def backfill_history(email: str, books: dict) -> None:
    """A fortnight of reading in the log of the first reader, so that the statistics have something to show. The log
    that the progress updates wrote is replaced: the pages add up to the current pages of the books."""
    schedule = [  # (days ago, book, pages)
        (13, 'Кобзар', 14), (12, 'Кобзар', 20), (11, 'Тіні забутих предків', 15), (10, 'Кобзар', 25),
        (8, 'Кобзар', 18), (7, 'Тіні забутих предків', 10), (6, 'Кобзар', 30), (5, 'Кобзар', 22),
        (3, 'Тіні забутих предків', 12), (3, 'Кобзар', 15), (2, 'Кобзар', 28), (1, 'Кобзар', 24),
        (0, 'Кобзар', 16), (0, 'Тіні забутих предків', 8), (0, "Alice's Adventures in Wonderland", 6),
    ]
    position: dict = {}
    inserts = []
    for days_ago, title, amount in schedule:
        start = position.get(title, 0)
        position[title] = start + amount
        inserts.append(
            f'DB::table("reading_logs")->insert(["user_id" => $u->id, "user_book_id" => {books[title]}, "from_page" => {start}, '
            f'"to_page" => {start + amount}, "logged_on" => now("Europe/Kyiv")->subDays({days_ago})->toDateString()])'
        )
    tinker(f'$u = App\\Models\\User::where("email", "{email}")->first(); DB::table("reading_logs")->where("user_id", $u->id)->delete(); '
           + ';'.join(inserts) + ';')


# ---- demo data ------------------------------------------------------------------------------------------------------

def seed() -> dict:
    ids: dict = {}
    olena, taras, ivan = Api(), Api(), Api()
    olena.register('Олена Коваль', EMAILS[0])
    taras.register('Тарас Мельник', EMAILS[1])
    ivan.register('Іван Бондар', EMAILS[2])
    olena.call('PATCH', '/api/me', {'handle': 'olena_reads', 'bio': 'Читаю класику і веду щоденник читання.'})
    taras.call('PATCH', '/api/me', {'handle': 'taras_m', 'bio': 'Люблю поезію й пригодницькі романи.'})
    ivan.call('PATCH', '/api/me', {'handle': 'ivan_bondar'})

    def add(api: Api, title: str, author: str, pages: int, status: str, year: int | None = None) -> int:
        payload: dict = {'title': title, 'authors': [author], 'total_pages': pages, 'status': status}
        if year:
            payload['published_year'] = year
        if status == 'finished':
            payload['started_at'] = '2026-09-01T10:00:00Z'
            payload['finished_at'] = '2026-09-20T10:00:00Z'
        return api.call('POST', '/api/library', payload)['data']['id']

    kobzar = add(olena, 'Кобзар', 'Тарас Шевченко', 640, 'reading', 1840)
    lisova = add(olena, 'Лісова пісня', 'Леся Українка', 160, 'finished', 1911)
    tini = add(olena, 'Тіні забутих предків', 'Михайло Коцюбинський', 128, 'reading', 1911)
    alice = add(olena, "Alice's Adventures in Wonderland", 'Lewis Carroll', 200, 'reading', 1865)
    add(olena, 'Захар Беркут', 'Іван Франко', 280, 'want', 1883)
    ids.update(kobzar=kobzar, lisova=lisova, tini=tini, alice=alice)

    olena.call('POST', f'/api/library/{kobzar}/progress', {'page': 212})
    olena.call('POST', f'/api/library/{tini}/progress', {'page': 45})
    olena.call('POST', f'/api/library/{alice}/progress', {'page': 6})
    olena.call('PUT', '/api/goals/2026', {'target_books': 12})
    olena.call('PATCH', f'/api/library/{lisova}', {'rating': 5})

    quotes = [
        (kobzar, 'Борітеся — поборете, вам Бог помагає!', 'Кавказ', 112),
        (kobzar, 'І мене в сім’ї великій, в сім’ї вольній, новій, не забудьте пом’янути незлим тихим словом.', 'Заповіт', 188),
        (lisova, 'Contra spem spero!', '', 40),
        (lisova, 'Слово, моя ти єдиная зброє, ми не повинні загинуть обоє.', 'Про силу слова', 12),
    ]
    quote_ids = []
    for book, content, note, page in quotes:
        payload = {'content': content, 'page': page}
        if note:
            payload['note'] = note
        quote_ids.append(olena.call('POST', f'/api/library/{book}/quotes', payload)['data']['id'])
    olena.call('PATCH', f'/api/quotes/{quote_ids[0]}', {'is_favorite': True})
    ids['quote'] = quote_ids[0]

    # Taras and Ivan have a few books and posts of their own.
    t_book = add(taras, 'Кобзар', 'Тарас Шевченко', 640, 'finished', 1840)
    t_quote = taras.call('POST', f'/api/library/{t_book}/quotes', {'content': 'Наша дума, наша пісня не вмре, не загине.', 'page': 98})['data']['id']
    i_book = add(ivan, 'Тіні забутих предків', 'Михайло Коцюбинський', 128, 'finished', 1911)

    olena.call('PUT', '/api/profiles/taras_m/follow')
    olena.call('PUT', '/api/profiles/ivan_bondar/follow')
    taras.call('PUT', '/api/profiles/olena_reads/follow')

    post_t = taras.call('POST', '/api/posts', {'type': 'quote', 'quote_id': t_quote, 'comment': 'Перечитую і щоразу знаходжу нове.'})['data']['id']
    ivan.call('POST', '/api/posts', {'type': 'review', 'user_book_id': i_book, 'rating': 5, 'content': 'Гуцульська казка про любов і долю: мова, якою хочеться читати вголос.'})
    post_o = olena.call('POST', '/api/posts', {'type': 'quote', 'quote_id': quote_ids[0], 'comment': 'Мій улюблений рядок із «Кавказу».'})['data']['id']
    olena.call('POST', '/api/posts', {'type': 'review', 'user_book_id': lisova, 'rating': 5, 'content': 'Драма-феєрія, у якій природа говорить із людиною.'})
    olena.call('PUT', f'/api/posts/{post_t}/like')
    taras.call('PUT', f'/api/posts/{post_o}/like')
    ivan.call('PUT', f'/api/posts/{post_o}/like')
    taras.call('POST', f'/api/posts/{post_o}/comments', {'content': 'Згоден, ці слова варто пам’ятати.'})
    ids.update(post=post_o, post_t=post_t)

    # The EPUB book of the reader.
    epub = make_epub()
    olena.call('POST', f'/api/library/{alice}/text', raw=multipart('file', 'alice.epub', epub, 'application/epub+zip'))
    text = olena.call('GET', f'/api/library/{alice}/text')['data']
    ids['text'] = text['id']

    backfill_history(EMAILS[0], {'Кобзар': kobzar, 'Тіні забутих предків': tini, "Alice's Adventures in Wonderland": alice})
    ids['session'] = {c.name: c.value for c in olena.jar}
    return ids


# ---- Chrome DevTools Protocol, with nothing but the standard library ---------------------------------------------------

class Socket:
    def __init__(self, url: str) -> None:
        parsed = urllib.parse.urlparse(url)
        self.sock = socket.create_connection((parsed.hostname, parsed.port), timeout=60)
        key = base64.b64encode(os.urandom(16)).decode()
        request = (f'GET {parsed.path} HTTP/1.1\r\nHost: {parsed.hostname}:{parsed.port}\r\nUpgrade: websocket\r\n'
                   f'Connection: Upgrade\r\nSec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n')
        self.sock.sendall(request.encode())
        reply = b''
        while b'\r\n\r\n' not in reply:
            reply += self.sock.recv(1)
        if b' 101 ' not in reply.split(b'\r\n')[0]:
            raise RuntimeError('the websocket handshake failed: ' + reply.decode(errors='replace'))

    def send(self, text: str) -> None:
        data = text.encode()
        header = bytes([0x81])
        n = len(data)
        if n < 126:
            header += bytes([0x80 | n])
        elif n < 65536:
            header += bytes([0x80 | 126]) + struct.pack('>H', n)
        else:
            header += bytes([0x80 | 127]) + struct.pack('>Q', n)
        mask = os.urandom(4)
        self.sock.sendall(header + mask + bytes(b ^ mask[i % 4] for i, b in enumerate(data)))

    def _read(self, n: int) -> bytes:
        chunks = []
        while n:
            chunk = self.sock.recv(min(n, 1 << 20))
            if not chunk:
                raise RuntimeError('the connection was closed')
            chunks.append(chunk)
            n -= len(chunk)
        return b''.join(chunks)

    def recv(self) -> str:
        message = b''
        while True:
            first, second = self._read(2)
            length = second & 0x7F
            if length == 126:
                length = struct.unpack('>H', self._read(2))[0]
            elif length == 127:
                length = struct.unpack('>Q', self._read(8))[0]
            payload = self._read(length)
            opcode = first & 0x0F
            if opcode == 8:
                raise RuntimeError('the browser closed the connection')
            if opcode in (9, 10):
                continue
            message += payload
            if first & 0x80:
                return message.decode()


class Browser:
    def __init__(self) -> None:
        self.profile = tempfile.mkdtemp(prefix='bookshelf-shots-')
        self.process = subprocess.Popen(
            [CHROME, '--headless=new', f'--remote-debugging-port={DEBUG_PORT}', f'--user-data-dir={self.profile}',
             '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--hide-scrollbars', 'about:blank'],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        target = None
        for _ in range(60):
            try:
                with urllib.request.urlopen(f'http://127.0.0.1:{DEBUG_PORT}/json/list', timeout=2) as response:
                    pages = [t for t in json.load(response) if t.get('type') == 'page']
                if pages:
                    target = pages[0]['webSocketDebuggerUrl']
                    break
            except OSError:
                pass
            time.sleep(0.5)
        if target is None:
            raise RuntimeError('Chrome did not start')
        self.ws = Socket(target)
        self.counter = 0
        self.events: list = []
        self.call('Page.enable')
        self.call('Network.enable')
        self.call('Emulation.setDeviceMetricsOverride', {**PHONE, 'deviceScaleFactor': 2, 'mobile': True})
        self.call('Emulation.setTouchEmulationEnabled', {'enabled': True})
        self.call('Emulation.setEmulatedMedia', {'features': [{'name': 'prefers-color-scheme', 'value': 'light'}]})

    def call(self, method: str, params: dict | None = None) -> dict:
        self.counter += 1
        wanted = self.counter
        self.ws.send(json.dumps({'id': wanted, 'method': method, 'params': params or {}}))
        while True:
            message = json.loads(self.ws.recv())
            if message.get('id') == wanted:
                if 'error' in message:
                    raise RuntimeError(f'{method}: {message["error"]}')
                return message.get('result', {})
            self.events.append(message)

    def evaluate(self, expression: str):
        result = self.call('Runtime.evaluate', {'expression': expression, 'returnByValue': True, 'awaitPromise': True})
        return result.get('result', {}).get('value')

    def open(self, path: str, wait: float = 2.5) -> None:
        self.events.clear()
        self.call('Page.navigate', {'url': BASE + path})
        deadline = time.time() + 20
        while time.time() < deadline:
            if any(e.get('method') == 'Page.loadEventFired' for e in self.events):
                break
            try:
                self.events.append(json.loads(self.ws.recv()))
            except socket.timeout:
                break
        time.sleep(wait)

    def shot(self, name: str) -> None:
        data = self.call('Page.captureScreenshot', {'format': 'png', 'fromSurface': True})['data']
        FIGURES.mkdir(parents=True, exist_ok=True)
        (FIGURES / f'shot_{name}.png').write_bytes(base64.b64decode(data))
        print('  shot', name)

    def set_cookies(self, cookies: dict) -> None:
        for name, value in cookies.items():
            self.call('Network.setCookie', {'name': name, 'value': value, 'url': BASE, 'httpOnly': name != 'XSRF-TOKEN'})

    def close(self) -> None:
        try:
            self.call('Browser.close')
        except Exception:
            pass
        self.process.terminate()


def click_by_label_index(browser: Browser, selector: str, index: int) -> None:
    browser.evaluate(f'document.querySelectorAll({json.dumps(selector)})[{index}].click()')
    time.sleep(0.8)


def capture(ids: dict) -> None:
    browser = Browser()
    try:
        browser.open('/login', 1.5)
        browser.shot('login')
        browser.set_cookies(ids['session'])
        for name, path in [
            ('home', '/'),
            ('library', '/library'),
            ('book', f'/library/{ids["kobzar"]}'),
            ('add_book', '/library/add'),
            ('quotes', '/quotes'),
            ('quote_form', '/quotes/new'),
            ('quote_share', f'/quotes/{ids["quote"]}/share'),
            ('stats', '/stats'),
            ('community', '/community'),
            ('post', f'/community/posts/{ids["post"]}'),
            ('compose', '/community/new'),
            ('reader_profile', '/u/taras_m'),
            ('profile', '/profile'),
        ]:
            browser.open(path)
            browser.shot(name)

        reader = f'/read/{ids["alice"]}'
        browser.open(reader, 3.0)
        browser.shot('reader')
        browser.evaluate("""(() => {
          const p = [...document.querySelectorAll('p')].find((el) => el.textContent.length > 80);
          const node = p.firstChild; const range = document.createRange();
          range.setStart(node, 0); range.setEnd(node, 70);
          const s = window.getSelection(); s.removeAllRanges(); s.addRange(range);
        })()""")
        time.sleep(1.0)
        browser.shot('reader_selection')
        browser.open(reader, 2.5)
        click_by_label_index(browser, 'header.reader-bar button', 0)
        browser.shot('reader_contents')
        browser.open(reader, 2.5)
        click_by_label_index(browser, 'header.reader-bar button', 1)
        browser.shot('reader_settings')
    finally:
        browser.close()


def main() -> None:
    keep = '--keep' in sys.argv
    remove_demo_readers()
    ids = seed()
    print('seeded:', {k: v for k, v in ids.items() if k != 'session'})
    capture(ids)
    if not keep:
        remove_demo_readers()
        print('demo readers removed')


if __name__ == '__main__':
    main()
