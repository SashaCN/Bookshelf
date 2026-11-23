"""Builds the report on laboratory work 7 (variant 19):

    php docs/lab7/measure.php       # the numbers (results.json)
    python3 docs/lab7/figures.py    # the figures
    python3 docs/lab7/build.py      # Zvit_LR7_Bookshelf.docx
"""

from __future__ import annotations

import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE.parent / 'tools'))

from ctx import Ctx, Doc  # noqa: E402
from titlepage import title_page  # noqa: E402
import figures  # noqa: E402
import report  # noqa: E402

REPORT = HERE / 'Zvit_LR7_Bookshelf.docx'


def build() -> Path:
    figures.main()
    r = report.data()
    known: dict = {}
    for _ in range(2):
        ctx = Ctx('', known)
        d = Doc(title='Laboratory Work No. 7: Bookshelf')
        title_page(d, 7, ['Metrics of Object-Oriented Software Systems'])
        report.write(d, ctx, r)
        report.write_answers(d, ctx)
        report.write_appendix(d, ctx, r)
        known = dict(ctx.labels)
    d.save(REPORT)
    print(f'{REPORT.name}: {ctx.tables} tables, {ctx.figures} figures')
    return REPORT


if __name__ == '__main__':
    build()
