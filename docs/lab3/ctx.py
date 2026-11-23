"""Numbering of the tables and figures of a document and references to them.

The content of a document is written twice by `build.py`: the first pass collects the number of every table and figure
under its key, the second pass can then say "see Table 5" before Table 5 itself has been written.
"""

from __future__ import annotations

import re
import sys
from contextlib import contextmanager
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent / 'tools'))

from docxkit import Doc  # noqa: E402

FIGURES = HERE / 'figures'


class Ctx:
    """`prefix` is '' for the numbers of the main text ("Table 3") and 'A.' for the appendix ("Table A.3")."""

    def __init__(self, prefix: str = '', known: dict | None = None):
        self.prefix = prefix
        self.known = known or {}
        self.labels: dict = {}
        self.tables = 0
        self.figures = 0
        self.figure_list: list = []  # (label, title, section number) of every figure, in order

    def table(self, d: Doc, key: str, title: str) -> str:
        """Writes the caption of a table (it goes above the table) and returns its label, "Table A.3"."""
        self.tables += 1
        label = f'Table {self.prefix}{self.tables}'
        self.labels[key] = label
        d.caption(f'{label}. {title}')
        return label

    def figure(self, d: Doc, key: str, name: str, title: str, landscape: bool = False, alt: str | None = None) -> str:
        """Writes a figure (the PNG file `figures/<name>`) with its caption below it and returns its label."""
        self.figures += 1
        label = f'Figure {self.prefix}{self.figures}'
        self.labels[key] = label
        match = re.match(r'[\d.]+', d.last_heading)
        self.figure_list.append((label, title, match.group(0).rstrip('.') if match else ''))
        d.figure(FIGURES / name, f'{label}. {title}', alt=alt or title, landscape=landscape)
        return label

    def known_figures(self) -> list:
        """(label, title, section) of all figures, known from the first pass; empty during the first pass."""
        return self.known.get('__figures__', [])

    def ref(self, key: str) -> str:
        """The label of a table or a figure, for example `ctx.ref('fig:cc_import')` gives "Figure A.9"."""
        return self.known.get(key, '[?]')

    @contextmanager
    def landscape(self, d: Doc):
        """Figures written inside the block are on landscape pages: `with ctx.landscape(d): ctx.figure(..., landscape=True)`."""
        d.begin_landscape()
        try:
            yield
        finally:
            d.end_landscape()
