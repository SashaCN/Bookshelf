"""Program listings: real files of the lab, numbered line by line (a long line is continued with a deeper indent; blank lines are left out)."""

from __future__ import annotations

from pathlib import Path

HERE = Path(__file__).resolve().parent
WIDTH = 104  # characters of a line of a code box at 7 pt


def lines_of(relative: str, start: int = 1, end: int | None = None) -> list:
    text = (HERE / relative).read_text(encoding='utf-8').replace('\t', '    ').split('\n')
    if text and text[-1] == '':
        text.pop()
    out = []
    for number in range(start, (end or len(text)) + 1):
        line = text[number - 1].rstrip()
        if not line:
            continue  # blank lines are not printed (the numbers of the lines are those of the file)
        prefix = f'{number:>3}  '
        room = WIDTH - len(prefix)
        if len(line) <= room:
            out.append(prefix + line)
            continue
        indent = len(line) - len(line.lstrip(' '))
        pad = ' ' * (len(prefix) + indent + 4)
        out.append(prefix + line[:room].rstrip())
        rest = line[room:].strip()
        while rest:
            out.append(pad + rest[:WIDTH - len(pad)])
            rest = rest[WIDTH - len(pad):]
    return out


def line_count(relative: str) -> int:
    return len((HERE / relative).read_text(encoding='utf-8').splitlines())
