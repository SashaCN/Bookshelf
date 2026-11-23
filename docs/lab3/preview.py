"""Builds a Word file from one section only, to check it while writing:

    python3 docs/lab3/preview.py s08_structure [--prefix A.] [--out /path/to/file.docx]

The file is written to the temporary directory unless `--out` is given. The script checks that the XML of the document is
well formed and lists the captions it contains. Do not open Word from scripts that run in parallel: look at the figures
(PNG files) with an image viewer and trust the XML check.
"""

from __future__ import annotations

import argparse
import re
import sys
import tempfile
import zipfile
from pathlib import Path
from xml.dom import minidom

sys.path.insert(0, str(Path(__file__).resolve().parent))

from ctx import Ctx, Doc  # noqa: E402
from content import load  # noqa: E402


def check(path: Path) -> list:
    with zipfile.ZipFile(path) as z:
        for name in z.namelist():
            if name.endswith('.xml') or name.endswith('.rels'):
                minidom.parseString(z.read(name))
        document = z.read('word/document.xml').decode('utf8')
        images = [n for n in z.namelist() if n.startswith('word/media/')]
    return re.findall(r'<w:pStyle w:val="Caption"/></w:pPr><w:r><w:t[^>]*>([^<]+)', document) + [f'{len(images)} image(s)']


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('section')
    parser.add_argument('--prefix', default='A.')
    parser.add_argument('--out')
    args = parser.parse_args()
    module = load(args.section)
    known: dict = {}
    for _ in range(2):
        ctx = Ctx(args.prefix, known)
        d = Doc(title=f'Preview of {args.section}')
        module.write(d, ctx)
        known = {**known, **ctx.labels}
    out = Path(args.out) if args.out else Path(tempfile.gettempdir()) / f'preview_{args.section}.docx'
    d.save(out)
    for line in check(out):
        print(' ', line)
    print('written', out)


if __name__ == '__main__':
    main()
