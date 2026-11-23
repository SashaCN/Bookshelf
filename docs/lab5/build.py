"""Builds the report on laboratory work 5:

    python3 docs/lab5/measure.py && python3 docs/lab5/calc.py     # the numbers (once, or after the code changes)
    python3 docs/lab5/build.py                                    # Zvit_LR5_Bookshelf.docx
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE.parent / 'tools'))

from ctx import Ctx, Doc  # noqa: E402
from titlepage import title_page  # noqa: E402
import report  # noqa: E402

REPORT = HERE / 'Zvit_LR5_Bookshelf.docx'


def build() -> Path:
    r = report.data()
    inventory = json.loads((HERE / 'fp_inventory.json').read_text(encoding='utf-8'))
    known: dict = {}
    for _ in range(2):
        ctx = Ctx('', known)
        d = Doc(title='Laboratory Work No. 5: Bookshelf')
        title_page(d, 5, ['Estimate of a Software Project', 'on the Basis of LOC and FP Metrics'])
        report.write(d, ctx, r, inventory)
        report.write_answers(d, ctx)
        report.write_appendix(d, ctx, inventory)
        known = dict(ctx.labels)
    d.save(REPORT)
    print(f'{REPORT.name}: {ctx.tables} tables')
    return REPORT


if __name__ == '__main__':
    build()
