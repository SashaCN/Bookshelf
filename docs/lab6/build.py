"""Builds the report on laboratory work 6 (variant 19):

    python3 docs/lab6/model.py      # the numbers (needs docs/lab5/results.json)
    python3 docs/lab6/build.py      # Zvit_LR6_Bookshelf.docx
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

REPORT = HERE / 'Zvit_LR6_Bookshelf.docx'


def build() -> Path:
    r = report.data()
    known: dict = {}
    for _ in range(2):
        ctx = Ctx('', known)
        d = Doc(title='Laboratory Work No. 6: Bookshelf')
        title_page(d, 6, ['Sensitivity Analysis of a Software Project', 'on the Basis of the COCOMO II Model'])
        report.write(d, ctx, r)
        report.write_answers(d, ctx)
        report.write_appendix(d, ctx, r)
        known = dict(ctx.labels)
    d.save(REPORT)
    print(f'{REPORT.name}: {ctx.tables} tables')
    return REPORT


if __name__ == '__main__':
    build()
