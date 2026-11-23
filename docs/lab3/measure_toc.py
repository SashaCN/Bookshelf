"""Measures the page numbers of the headings with Microsoft Word (macOS) for the table of contents:

    python3 docs/lab3/measure_toc.py design     # or: report

It builds the document, lets Word update the table of contents of a copy and saves the page numbers to toc/<name>.json,
which build.py puts into the table of contents. Run build.py again afterwards. Word is stopped before and after (it
cannot be asked to close a document that it has exported), so the script refuses to run when Word holds other documents.
"""

from __future__ import annotations

import json
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

import build  # noqa: E402

CONTAINER = Path.home() / 'Library/Containers/com.microsoft.Word/Data/tmp'


def word_documents() -> str:
    return subprocess.run(['osascript', '-e', 'tell application "Microsoft Word" to return name of every document'],
                          capture_output=True, text=True).stdout.strip()


def stop_word() -> None:
    subprocess.run(['pkill', '-x', 'Microsoft Word'])
    time.sleep(3)


def main(name: str) -> None:
    path = build.build_design() if name == 'design' else build.build_report()
    running = subprocess.run(['pgrep', '-x', 'Microsoft Word'], capture_output=True).returncode == 0
    if running:
        names = word_documents()
        if names and names != 'toc_copy.docx':
            sys.exit(f'Word has other documents open ({names}): not touching it')
        stop_word()
    CONTAINER.mkdir(parents=True, exist_ok=True)
    copy = CONTAINER / 'toc_copy.docx'
    out = CONTAINER / 'toc_text.txt'
    shutil.copy(path, copy)
    out.unlink(missing_ok=True)
    script = f'''
tell application "Microsoft Word"
    open (POSIX file "{copy}") read only true
    delay 8
    set d to active document
    update table of contents 1 of d
    set t to content of text object of table of contents 1 of d
    set f to open for access POSIX file "{out}" with write permission
    set eof of f to 0
    write t to f as «class utf8»
    close access f
end tell
'''
    result = subprocess.run(['osascript', '-e', script], capture_output=True, text=True)
    stop_word()
    if result.returncode != 0 or not out.exists():
        sys.exit('Word could not update the table of contents: ' + result.stderr)
    lines = [line for line in re.split(r'[\r\n]+', out.read_text(encoding='utf-8')) if line.strip()]
    pages = []
    for line in lines:
        match = re.search(r'\t(\d+)\s*$', line)
        pages.append(match.group(1) if match else '')
    (HERE / 'toc').mkdir(exist_ok=True)
    (HERE / 'toc' / f'{name}.json').write_text(json.dumps(pages))
    print(f'{name}: {len(pages)} entries, last page {pages[-1] if pages else "?"}')


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else 'design')
