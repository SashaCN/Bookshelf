"""Builds the documents of laboratory work 3 from the sections in `content/`:

    python3 docs/lab3/build.py              # the short documents: TechnicalDesign_Bookshelf.docx, Zvit_LR3_Bookshelf.docx
    python3 docs/lab3/build.py extended     # the extended ones (extended/), built from content/
    python3 docs/lab3/build.py design       # only the extended technical design
    python3 docs/lab3/build.py report       # only the extended report

The figures are in `figures/` (drawn by the scripts in `diagrams/`). Every section is written twice, so that a text can
refer to a table or a figure that comes later.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

from ctx import Ctx, Doc  # noqa: E402
from content import ORDER, load  # noqa: E402
import report  # noqa: E402
import short  # noqa: E402

SHORT_DESIGN = HERE / 'TechnicalDesign_Bookshelf.docx'
SHORT_REPORT = HERE / 'Zvit_LR3_Bookshelf.docx'
DESIGN = HERE / 'extended' / 'TechnicalDesign_Bookshelf_extended.docx'
REPORT = HERE / 'extended' / 'Zvit_LR3_Bookshelf_extended.docx'
TOC_DIR = HERE / 'toc'  # the page numbers of the headings, measured with Word by measure_toc.py


def toc_entries(headings: list, name: str) -> list:
    """(level, text, page) for the table of contents; the pages come from toc/<name>.json when it fits the headings."""
    path = TOC_DIR / f'{name}.json'
    pages: list = []
    if path.exists():
        saved = json.loads(path.read_text())
        if len(saved) == len(headings):
            pages = saved
    return [(level, text, pages[i] if pages else '') for i, (level, text) in enumerate(headings)]


def approval_sheet(d: Doc, page_break: bool = False, city_gap: int = 5600) -> None:
    """The title sheet of the technical design, as in the preliminary design."""
    right = dict(left=5103, indent=0, line=240, align='left')
    d.p('APPROVED', page_break=page_break, **right)
    d.p('Head of the Department of Software Engineering', **right)
    d.p('Professor', **right)
    d.p('____________ ==L. P. Bedratiuk==', **right)
    d.p('“____” ____________ 2026', **right)
    d.p('TECHNICAL DESIGN', align='center', indent=0, size=32, before=2400, after=240, line=240, bold=True)
    d.p('for the development of “Bookshelf: a web application for tracking reading and book notes”',
        align='center', indent=0, line=360)
    d.p('(version 2: personal tracker, community and built-in reading)', align='center', indent=0, line=360)
    d.p('Khmelnytskyi, 2026', align='center', indent=0, before=city_gap, line=240)


def write_sections(d: Doc, ctx: Ctx) -> None:
    for name in ORDER:
        try:
            module = load(name)
        except ModuleNotFoundError as error:
            if error.name != f'content.{name}':
                raise
            print(f'  (section {name} is not written yet)')
            continue
        module.write(d, ctx)


def design_pass(prefix: str, known: dict, front, toc_name: str | None = None) -> tuple:
    ctx = Ctx(prefix, known)
    d = Doc(title='Technical Design of Bookshelf (version 2)')
    front(d)
    if toc_name:
        d.toc(toc_entries(known.get('__headings__', []), toc_name))
    write_sections(d, ctx)
    return d, ctx


def collect(prefix: str, front, toc_name: str | None = None) -> tuple:
    """Two passes: the second one knows the numbers of everything from the first."""
    known: dict = {}
    for _ in range(2):
        d, ctx = design_pass(prefix, known, front, toc_name)
        known = {**ctx.labels, '__figures__': ctx.figure_list, '__headings__': list(d.headings)}
    return d, ctx, known


def build_design() -> Path:
    d, ctx, _ = collect('', lambda doc: approval_sheet(doc), 'design')
    d.save(DESIGN)
    print(f'{DESIGN.name}: {ctx.tables} tables, {ctx.figures} figures')
    return DESIGN


def build_report() -> Path:
    # The appendix is the technical design with the numbers of tables and figures "A.n".
    def appendix_front(doc: Doc) -> None:
        doc.h1('Appendix A. Technical design', page_break=True)
        doc.p('The full text of the technical design follows.')
        approval_sheet(doc, page_break=True)

    app_known = collect('A.', appendix_front)[2]

    known: dict = {}
    headings: list = []
    for _ in range(3):
        main_ctx = Ctx('', known)
        main_ctx.toc_entries = toc_entries(headings, 'report')
        app_ctx = Ctx('A.', app_known)
        d = Doc(title='Laboratory Work No. 3: Bookshelf')
        report.write(d, main_ctx, app_ctx)
        appendix_front(d)
        write_sections(d, app_ctx)
        known = dict(main_ctx.labels)
        headings = list(d.headings)
    d.save(REPORT)
    print(f'{REPORT.name}: {main_ctx.tables} tables in the text, {app_ctx.tables} tables and {app_ctx.figures} figures in the appendix')
    return REPORT


def build_short_design() -> Path:
    known: dict = {}
    for _ in range(2):
        ctx = Ctx('', known)
        d = Doc(title='Technical Design of Bookshelf (version 2), short')
        approval_sheet(d)
        short.write(d, ctx)
        known = dict(ctx.labels)
    d.save(SHORT_DESIGN)
    print(f'{SHORT_DESIGN.name}: {ctx.tables} tables, {ctx.figures} figures')
    return SHORT_DESIGN


def build_short_report() -> Path:
    def appendix_front(doc: Doc) -> None:
        doc.h1('Appendix A. Technical design', page_break=True)
        doc.p('The text of the technical design follows. The extended version, with all the lists and tables, is the file '
              '`TechnicalDesign_Bookshelf_extended.docx`.')
        approval_sheet(doc, page_break=False, city_gap=3600)

    app_known: dict = {}
    for _ in range(2):
        app_ctx = Ctx('A.', app_known)
        scratch = Doc(title='scratch')
        appendix_front(scratch)
        short.write(scratch, app_ctx)
        app_known = dict(app_ctx.labels)
    d = Doc(title='Laboratory Work No. 3: Bookshelf')
    report.write_short(d, Ctx('', {}), Ctx('A.', app_known))
    appendix_front(d)
    final_ctx = Ctx('A.', app_known)
    short.write(d, final_ctx)
    d.save(SHORT_REPORT)
    print(f'{SHORT_REPORT.name}: {final_ctx.tables} tables and {final_ctx.figures} figures in the appendix')
    return SHORT_REPORT


if __name__ == '__main__':
    what = sys.argv[1] if len(sys.argv) > 1 else 'short'
    if what in ('short', 'design-short'):
        build_short_design()
    if what in ('short', 'report-short'):
        build_short_report()
    if what in ('extended', 'design'):
        build_design()
    if what in ('extended', 'report'):
        build_report()
