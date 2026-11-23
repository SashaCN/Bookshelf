"""Copies the screenshots of the third-party testing (SonarQube and the terminal output of the test tools) into
`figures/` under the names that `thirdparty.py` uses. The docx builder takes only PNG files, so the photos of the
terminal are converted; the resolution is set so that small pictures are not stretched.

    python3 docs/lab4/prepare_thirdparty.py [source directory]
"""

from __future__ import annotations

import sys
from pathlib import Path

from PIL import Image

SOURCE = Path(sys.argv[1]) if len(sys.argv) > 1 else Path.home() / 'Downloads' / 'tests'
FIGURES = Path(__file__).resolve().parent / 'figures'

FILES = {
    '01-overview-overall.png': 'sonar_summary.png',
    'photo_2026-11-03_20-43-38.jpg': 'sonar_overview.png',
    '02-issues.png': 'sonar_issues.png',
    '08-code.png': 'sonar_code.png',
    '04-coverage.png': 'sonar_coverage.png',
    '05-size.png': 'sonar_size.png',
    '06-complexity.png': 'sonar_complexity.png',
    '03-measures-overview.png': 'sonar_measures.png',
    'photo_2026-11-03_20-43-12.jpg': 'tool_phpstan.png',
    'photo_2026-11-03_20-43-18.jpg': 'tool_pint.png',
    'photo_2026-11-03_20-43-26.jpg': 'tool_pest_run.png',
    'photo_2026-11-03_20-43-21.jpg': 'tool_pest_coverage.png',
    'photo_2026-11-03_20-43-03.jpg': 'tool_vitest_coverage.png',
}

# The empty white area under the content of some screens is cut off (box: left, top, right, bottom, in pixels of the original).
CROPS = {
    'sonar_issues.png': (0, 0, 2160, 1000),
    'sonar_code.png': (0, 0, 2160, 940),
    'sonar_summary.png': (0, 0, 2160, 1340),
}

for source, target in FILES.items():
    image = Image.open(SOURCE / source).convert('RGB')
    if target in CROPS:
        image = image.crop(CROPS[target])
    image.save(FIGURES / target, optimize=True, dpi=(150, 150))
    print(f'{target}: {image.size[0]}x{image.size[1]}')
