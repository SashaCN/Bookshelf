"""Program listings: real files of the repository, copied line by line with the numbers of their lines."""

from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
WIDTH = 92  # characters of a line of a code box at 8 pt


def lines_of(relative: str, start: int = 1, end: int | None = None) -> list:
    """The lines `start`..`end` of a file of the repository, numbered as in the file; a line that is too long for the
    page is continued on the next line with a deeper indent."""
    text = (ROOT / relative).read_text(encoding='utf-8').replace('\t', '    ').split('\n')
    if text and text[-1] == '':
        text.pop()
    out = []
    for number in range(start, (end or len(text)) + 1):
        line = text[number - 1].rstrip()
        prefix = f'{number:>3}  '
        room = WIDTH - len(prefix)
        if len(line) <= room:
            out.append(prefix + line)
            continue
        indent = len(line) - len(line.lstrip(' '))
        pad = ' ' * (len(prefix) + indent + 4)
        first = True
        while len(line) > room:
            cut = line.rfind(' ', indent + 1, room)
            cut = cut if cut > indent else room
            out.append((prefix if first else ' ' * len(prefix) + ' ' * (indent + 4 if not first else 0)) + line[:cut].rstrip() if first
                       else pad + line[:cut].strip())
            line = line[cut:].lstrip() if first else line[cut:].lstrip()
            first = False
            room = WIDTH - len(pad)
        out.append(pad + line)
    return out


def line_count(relative: str) -> int:
    return len((ROOT / relative).read_text(encoding='utf-8').splitlines())
