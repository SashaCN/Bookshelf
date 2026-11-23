"""Builds the report on laboratory work 4:

    python3 docs/lab4/build.py

The text is in `report.py` (the report itself) and `appendices.py` (the listing, the interface and the four guides). The
real outputs of the program in `results/` are made by `results.py`; the screens in `figures/` are copied from lab 3.
"""

from __future__ import annotations

import os
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
sys.path.insert(0, str(HERE))

from ctx import Ctx, Doc  # noqa: E402
import appendices  # noqa: E402
import report  # noqa: E402

REPORT = HERE / 'Zvit_LR4_Bookshelf.docx'

CHECKS = [
    ('Code style of PHP', 'Pint (`pint --test`)', '227 files: PASS'),
    ('Static analysis of PHP', 'Larastan 3, level 6', 'No errors'),
    ('Tests of the server part', 'Pest 4 on MySQL 8.4', '1053 tests, 5409 assertions: all passed'),
    ('Style of the client', 'ESLint, `--max-warnings 0`', 'No warnings'),
    ('Types of the client', 'vue-tsc', 'No errors'),
    ('Tests of the client', 'Vitest 5', '1181 tests in 76 files: all passed'),
    ('Production build of the client', 'Vite (the application and the service worker)', 'Built without errors'),
]


def count(roots: list, exclude: tuple = ()) -> tuple:
    files = lines = 0
    for root in roots:
        for directory, _, names in os.walk(ROOT / root):
            if any(part in directory for part in ('vendor', 'node_modules', '/dist', 'storage', 'bootstrap/cache')):
                continue
            for name in names:
                if not (name.endswith(('.php', '.ts', '.vue', '.css', '.sh', '.yml', '.conf', '.py')) or name in ('Dockerfile', 'Caddyfile')):
                    continue
                if any(name.endswith(e) for e in exclude):
                    continue
                with open(os.path.join(directory, name), encoding='utf-8', errors='ignore') as handle:
                    lines += sum(1 for _ in handle)
                files += 1
    return files, lines


def sizes() -> dict:
    app = count(['backend/app'])
    cfg = count(['backend/routes', 'backend/database', 'backend/config'])
    btest = count(['backend/tests'])
    spec = count(['frontend/src'], exclude=())  # all of src, then the specs are taken away
    specs = [Path(dp) / f for dp, _, fn in os.walk(ROOT / 'frontend/src') for f in fn if f.endswith('.spec.ts')]
    spec_lines = sum(len(p.read_text(encoding='utf-8').splitlines()) for p in specs)
    test_support = count(['frontend/src/test'])
    fe_files = spec[0] - len(specs) - test_support[0]
    fe_lines = spec[1] - spec_lines - test_support[1]
    ops = count(['docker', 'deploy', '.github'])
    spec_files = len(specs) + test_support[0]
    spec_lines += test_support[1]
    files = app[0] + cfg[0] + btest[0] + fe_files + spec_files + ops[0]
    lines = app[1] + cfg[1] + btest[1] + fe_lines + spec_lines + ops[1]
    return {
        'app_files': app[0], 'app_lines': f'{app[1]:,}', 'cfg_files': cfg[0], 'cfg_lines': f'{cfg[1]:,}',
        'btest_files': btest[0], 'btest_lines': f'{btest[1]:,}', 'fe_files': fe_files, 'fe_lines': f'{fe_lines:,}',
        'spec_files': spec_files, 'spec_lines': f'{spec_lines:,}', 'ops_files': ops[0], 'ops_lines': f'{ops[1]:,}',
        'files': files, 'lines': f'{lines:,}', 'test_lines': f'{btest[1] + spec_lines:,}', 'checks': CHECKS,
    }


def write(d: Doc, ctx: Ctx, size: dict) -> None:
    report.write(d, ctx, size)
    appendices.listing(d, ctx)
    appendices.interface(d, ctx)
    appendices.user_guide(d, ctx)
    appendices.system_guide(d, ctx)
    appendices.programmer_guide(d, ctx)
    appendices.operator_guide(d, ctx)


def build() -> Path:
    size = sizes()
    known: dict = {}
    for _ in range(2):
        ctx = Ctx('', known)
        d = Doc(title='Laboratory Work No. 4: Bookshelf')
        # The answers come before the appendices, so the text is written in this order.
        report.write(d, ctx, size)
        report.write_answers(d, ctx)
        appendices.listing(d, ctx)
        appendices.interface(d, ctx)
        appendices.user_guide(d, ctx)
        appendices.system_guide(d, ctx)
        appendices.programmer_guide(d, ctx)
        appendices.operator_guide(d, ctx)
        known = dict(ctx.labels)
    d.save(REPORT)
    print(f'{REPORT.name}: {ctx.tables} tables, {ctx.figures} figures, {size["files"]} files, {size["lines"]} lines')
    return REPORT


if __name__ == '__main__':
    build()
