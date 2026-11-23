"""Builds the report on laboratory work 8:

    php docs/lab8/tests.php json > docs/lab8/results/tests.json     (and the other files of results/, see README in the report)
    python3 docs/lab8/figures.py
    python3 docs/lab8/build.py      # Zvit_LR8_Bookshelf.docx
"""

from __future__ import annotations

import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE.parent / 'tools'))

from ctx import Ctx, Doc  # noqa: E402
from titlepage import title_page  # noqa: E402
import report  # noqa: E402

REPORT = HERE / 'Zvit_LR8_Bookshelf.docx'


def build() -> Path:
    r = report.data()
    known: dict = {}
    for _ in range(2):
        ctx = Ctx('', known)
        d = Doc(title='Laboratory Work No. 8: Bookshelf')
        title_page(d, 8, ['Organization of Tables in Translators', 'and Operations on Them'])
        report.write(d, ctx, r)
        report.write_answers(d, ctx)
        report.write_appendix(d, ctx, r)
        known = dict(ctx.labels)
    d.save(REPORT)
    print(f'{REPORT.name}: {ctx.tables} tables, {ctx.figures} figures')
    return REPORT


if __name__ == '__main__':
    build()
