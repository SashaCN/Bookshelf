"""The sections of the technical design, in the order of the document. Every module has `write(d, ctx)`."""

from __future__ import annotations

import importlib

ORDER = [
    's01_intro', 's02_documents', 's03_note', 's03_quantities', 's03b_deviations', 's04_coding', 's05_input', 's06_output',
    's07_algorithms', 's08_structure', 's09_functional', 's10_constantine', 's11_jackson', 's12_ui', 's12b_screens',
    's13_software', 's14_hardware', 's15_reliability', 's16_bill', 's17_order', 's18_glossary',
]


def load(name: str):
    return importlib.import_module(f'content.{name}')
