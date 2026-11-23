"""A small builder of .docx files for the lab reports (no dependencies except the standard library).

The package it writes looks like the lab 2 report: Times New Roman 14 pt, 1.5 line spacing, justified text with a
first-line indent, centered bold headings, tables with a grey header, pseudocode in grey one-cell tables and
figures with a caption under them. Portrait A4 pages with a landscape section for wide figures.

Inline markup in the text of paragraphs and cells:  **bold**   ~~italic~~   ==yellow highlight==   `code`   (\t is a tab)
"""

from __future__ import annotations

import re
import struct
import zipfile
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from xml.sax.saxutils import escape

NS = (
    'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
)
PORTRAIT_TEXT_WIDTH = 9355  # twips: A4 11906 - 1701 - 850
LANDSCAPE_TEXT_WIDTH = 14570  # twips: A4 16838 - 2 * 1134
EMU_PER_TWIP = 635
PORTRAIT_MAX_W = PORTRAIT_TEXT_WIDTH * EMU_PER_TWIP
PORTRAIT_MAX_H = 7_300_000
LANDSCAPE_MAX_W = LANDSCAPE_TEXT_WIDTH * EMU_PER_TWIP
LANDSCAPE_MAX_H = 4_900_000

STYLES = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles {NS}>
<w:docDefaults>
<w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="28"/><w:szCs w:val="28"/><w:lang w:val="en-US" w:eastAsia="en-US" w:bidi="ar-SA"/></w:rPr></w:rPrDefault>
<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="360" w:lineRule="auto"/></w:pPr></w:pPrDefault>
</w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/><w:pPr><w:ind w:firstLine="709"/><w:jc w:val="both"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="320" w:after="200"/><w:ind w:firstLine="0"/><w:jc w:val="center"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:bCs/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="300" w:after="120"/><w:ind w:firstLine="0"/><w:jc w:val="left"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:bCs/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="200" w:after="80"/><w:ind w:firstLine="0"/><w:jc w:val="left"/><w:outlineLvl w:val="2"/></w:pPr><w:rPr><w:b/><w:bCs/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:line="240" w:lineRule="auto"/><w:ind w:firstLine="0"/><w:jc w:val="left"/></w:pPr><w:rPr><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Caption"><w:name w:val="caption"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="120" w:after="60" w:line="240" w:lineRule="auto"/><w:ind w:firstLine="0"/><w:jc w:val="left"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Header"><w:name w:val="header"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:line="240" w:lineRule="auto"/><w:ind w:firstLine="0"/><w:jc w:val="center"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="TOC1"><w:name w:val="toc 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:tabs><w:tab w:val="right" w:leader="dot" w:pos="9355"/></w:tabs><w:spacing w:before="80" w:after="0" w:line="240" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="0"/><w:jc w:val="left"/></w:pPr><w:rPr><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="TOC2"><w:name w:val="toc 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:tabs><w:tab w:val="right" w:leader="dot" w:pos="9355"/></w:tabs><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:ind w:left="284" w:firstLine="0"/><w:jc w:val="left"/></w:pPr><w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:ind w:firstLine="0"/></w:pPr></w:style>
</w:styles>"""

NUMBERING = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering {NS}>
<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="hybridMultilevel"/>
<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="1276" w:hanging="567"/></w:pPr><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/></w:rPr></w:lvl>
</w:abstractNum>
<w:num w:numId="1"><w:abstractNumId w:val="1"/></w:num>
</w:numbering>"""

SETTINGS = (
    f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings {NS}><w:defaultTabStop w:val="709"/>'
    '<w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/>'
    '</w:compat><w:themeFontLang w:val="en-US"/></w:settings>'
)

FOOTER = (
    f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr {NS}><w:p><w:pPr><w:pStyle w:val="Header"/></w:pPr>'
    '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
    '<w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>'
    '</w:p></w:ftr>'
)

CONTENT_TYPES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Default Extension="png" ContentType="image/png"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>"""

ROOT_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>"""

APP = (
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
    '<Application>Microsoft Office Word</Application></Properties>'
)

_MARKUP = re.compile(r'(\*\*.+?\*\*|~~.+?~~|==.+?==|`.+?`)')


def png_size(path: Path) -> tuple[int, int, float]:
    """Width and height in pixels and the resolution in dots per inch of a PNG file."""
    data = path.read_bytes()
    width, height = struct.unpack('>II', data[16:24])
    dpi = 96.0
    pos = 8
    while pos < len(data):
        length, kind = struct.unpack('>I4s', data[pos:pos + 8])
        if kind == b'pHYs':
            ppu, _, unit = struct.unpack('>IIB', data[pos + 8:pos + 17])
            if unit == 1:
                dpi = ppu * 0.0254
            break
        pos += 12 + length
    return width, height, dpi


def _run(text: str, bold: bool = False, italic: bool = False, code: bool = False, size: int | None = None,
         highlight: bool = False) -> str:
    props = ''
    if code:
        props += '<w:rFonts w:ascii="Courier New" w:hAnsi="Courier New" w:cs="Courier New"/>'
    if bold:
        props += '<w:b/>'
    if italic:
        props += '<w:i/>'
    if highlight:
        props += '<w:highlight w:val="yellow"/>'
    if size is not None:
        props += f'<w:sz w:val="{size}"/><w:szCs w:val="{size}"/>'
    rpr = f'<w:rPr>{props}</w:rPr>' if props else ''
    pieces = text.split('\t')
    body = '<w:tab/>'.join(f'<w:t xml:space="preserve">{escape(piece)}</w:t>' for piece in pieces)
    return f'<w:r>{rpr}{body}</w:r>'


def _left_if_long_code(text: str) -> str | None:
    """A paragraph with a long unbreakable code span is aligned to the left: justified, it would be stretched into big gaps."""
    return 'left' if re.search(r'`[^`]{14,}`', text) else None


def runs(text: str, size: int | None = None, bold: bool = False, italic: bool = False) -> str:
    """Turns text with the inline markup into runs."""
    out = []
    for part in _MARKUP.split(text):
        if not part:
            continue
        if part.startswith('**') and part.endswith('**') and len(part) > 4:
            # The lab reports have no bold in running text (only headings, table headers and the title); `bold=True`
            # is for those. The markup is kept so that old sources still work.
            out.append(_run(part[2:-2], bold=bold, italic=italic, size=size))
        elif part.startswith('~~') and part.endswith('~~') and len(part) > 4:
            out.append(_run(part[2:-2], bold=bold, italic=True, size=size))
        elif part.startswith('==') and part.endswith('==') and len(part) > 4:
            out.append(_run(part[2:-2], bold=bold, italic=italic, size=size, highlight=True))
        elif part.startswith('`') and part.endswith('`') and len(part) > 2:
            out.append(_run(part[1:-1], bold=bold, code=True, size=(size or 28) - 4))
        else:
            out.append(_run(part, bold=bold, italic=italic, size=size))
    return ''.join(out)


@dataclass
class Doc:
    title: str
    creator: str = 'Bookshelf team'
    _body: list[str] = field(default_factory=list)
    _images: list[Path] = field(default_factory=list)
    _drawing_id: int = 0
    last_heading: str = ''
    headings: list = field(default_factory=list)  # (level, text) of every heading of levels 1 and 2, in order

    # ---- paragraphs -------------------------------------------------------------------------------------------

    def raw(self, xml: str) -> None:
        self._body.append(xml)

    def p(self, text: str = '', *, align: str | None = None, indent: int | None = None, left: int | None = None,
          size: int | None = None, bold: bool = False, italic: bool = False, line: int | None = None,
          before: int | None = None, after: int | None = None, keep_next: bool = False, page_break: bool = False,
          tabs_right: int | None = None) -> None:
        ppr = ''
        if page_break:
            ppr += '<w:pageBreakBefore/>'
        if keep_next:
            ppr += '<w:keepNext/>'
        if tabs_right is not None:
            ppr += f'<w:tabs><w:tab w:val="right" w:pos="{tabs_right}"/></w:tabs>'
        if line is not None or before is not None or after is not None:
            attrs = ''
            if before is not None:
                attrs += f' w:before="{before}"'
            if after is not None:
                attrs += f' w:after="{after}"'
            if line is not None:
                attrs += f' w:line="{line}" w:lineRule="auto"'
            ppr += f'<w:spacing{attrs}/>'
        if left is not None or indent is not None:
            attrs = ''
            if left is not None:
                attrs += f' w:left="{left}"'
            attrs += f' w:firstLine="{indent if indent is not None else 0}"'
            ppr += f'<w:ind{attrs}/>'
        align = align or _left_if_long_code(text)
        if align:
            ppr += f'<w:jc w:val="{align}"/>'
        body = runs(text, size=size, bold=bold, italic=italic) if text else ''
        self._body.append(f'<w:p><w:pPr>{ppr}</w:pPr>{body}</w:p>' if ppr else f'<w:p>{body}</w:p>')

    def h1(self, text: str, page_break: bool = False) -> None:
        self.last_heading = text
        self.headings.append((1, text))
        pb = '<w:pageBreakBefore/>' if page_break else ''
        self._body.append(f'<w:p><w:pPr><w:pStyle w:val="Heading1"/>{pb}</w:pPr>{_run(text)}</w:p>')

    def h2(self, text: str) -> None:
        self.last_heading = text
        self.headings.append((2, text))
        self._body.append(f'<w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr>{_run(text)}</w:p>')

    def h3(self, text: str) -> None:
        self.last_heading = text
        self._body.append(f'<w:p><w:pPr><w:pStyle w:val="Heading3"/></w:pPr>{_run(text)}</w:p>')

    def toc(self, entries: list, page_break: bool = True) -> None:
        """A table of contents of the headings of levels 1 and 2 as a Word field (it can be updated in Word); `entries` are
        (level, text, page) and give the text that is shown until it is updated."""
        pb = '<w:pageBreakBefore/>' if page_break else ''
        self._body.append(f'<w:p><w:pPr>{pb}<w:keepNext/><w:spacing w:before="0" w:after="240"/><w:ind w:firstLine="0"/>'
                          f'<w:jc w:val="center"/></w:pPr>{_run("Contents", bold=True)}</w:p>')
        start = ('<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> TOC \\o "1-2" \\h \\z \\u '
                 '</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r>')
        for index, (level, text, page) in enumerate(entries):
            self._body.append(
                f'<w:p><w:pPr><w:pStyle w:val="TOC{level}"/></w:pPr>{start if index == 0 else ""}'
                f'<w:r><w:t xml:space="preserve">{escape(text)}</w:t></w:r><w:r><w:tab/></w:r>'
                f'<w:r><w:t>{escape(str(page))}</w:t></w:r></w:p>')
        self._body.append('<w:p><w:pPr><w:ind w:firstLine="0"/></w:pPr><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>')

    def numbered(self, items: list[str], start: int = 1) -> None:
        """Numbered items with a hanging indent, as typed text (the numbers do not depend on Word's lists)."""
        for number, item in enumerate(items, start):
            jc = '<w:jc w:val="left"/>' if _left_if_long_code(item) else ''
            self._body.append(
                f'<w:p><w:pPr><w:ind w:left="1276" w:hanging="567" w:firstLine="0"/>{jc}</w:pPr>'
                f'<w:r><w:t xml:space="preserve">{number}.</w:t><w:tab/></w:r>{runs(item)}</w:p>'
            )

    def bullets(self, items: list[str]) -> None:
        for item in items:
            jc = '<w:jc w:val="left"/>' if _left_if_long_code(item) else ''
            self._body.append(
                '<w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>'
                f'{jc}</w:pPr>{runs(item)}</w:p>'
            )

    def caption(self, text: str) -> None:
        self._body.append(f'<w:p><w:pPr><w:pStyle w:val="Caption"/></w:pPr>{_run(text)}</w:p>')

    def page_break(self) -> None:
        self._body.append('<w:p><w:pPr><w:ind w:firstLine="0"/></w:pPr><w:r><w:br w:type="page"/></w:r></w:p>')

    # ---- tables -----------------------------------------------------------------------------------------------

    def table(self, header: list[str], rows: list[list[str]], widths: list[int], *, align: list[str] | None = None,
              total: int = PORTRAIT_TEXT_WIDTH, shade: set[int] | None = None, size: int | None = None,
              header_fill: str = 'DDDDDD') -> None:
        """A table with a repeating grey header. The widths are relative and scaled to `total` twips.
        `shade` holds the indexes of rows (from 0, the header excluded) that get a light fill."""
        scale = total / sum(widths)
        cols = [int(w * scale) for w in widths]
        cols[-1] += total - sum(cols)
        size = size or 22  # 11 pt: the tables of the technical design are long and dense
        cols = self._fit_columns(header, rows, cols, total, size)
        align = align or ['left'] * len(cols)

        def cell(text: str, width: int, jc: str, fill: str | None, bold: bool = False) -> str:
            if text.strip() in ('—', '–'):
                text = ''
            shd = f'<w:shd w:val="clear" w:color="auto" w:fill="{fill}"/>' if fill else ''
            paragraphs = ''.join(
                f'<w:p><w:pPr><w:pStyle w:val="TableText"/><w:jc w:val="{jc}"/></w:pPr>'
                f'{runs(line, size=size, bold=bold)}</w:p>'
                for line in (text.split('\n') if text else [''])
            )
            return f'<w:tc><w:tcPr><w:tcW w:w="{width}" w:type="dxa"/>{shd}</w:tcPr>{paragraphs}</w:tc>'

        xml = [
            f'<w:tbl><w:tblPr><w:tblW w:w="{total}" w:type="dxa"/><w:tblBorders>'
            + ''.join(f'<w:{side} w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
                      for side in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'))
            + '</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="40" w:type="dxa"/>'
            '<w:left w:w="100" w:type="dxa"/><w:bottom w:w="40" w:type="dxa"/><w:right w:w="100" w:type="dxa"/>'
            '</w:tblCellMar></w:tblPr><w:tblGrid>'
            + ''.join(f'<w:gridCol w:w="{c}"/>' for c in cols) + '</w:tblGrid>'
        ]
        xml.append('<w:tr><w:trPr><w:cantSplit/><w:tblHeader/></w:trPr>'
                   + ''.join(cell(h, c, 'center', header_fill, bold=True) for h, c in zip(header, cols)) + '</w:tr>')
        for index, row in enumerate(rows):
            fill = 'F2F2F2' if shade and index in shade else None
            xml.append('<w:tr><w:trPr><w:cantSplit/></w:trPr>'
                       + ''.join(cell(v, c, a, fill, bold=bool(fill)) for v, c, a in zip(row, cols, align)) + '</w:tr>')
        xml.append('</w:tbl>')
        self._body.append(''.join(xml))
        self.after_table()

    @staticmethod
    def _fit_columns(header: list, rows: list, cols: list, total: int, size: int) -> list:
        """Gives a column at least the width of its longest word (up to 40 % of the table), taking the width from the
        columns that have more than they need, so that words are not broken in the middle."""
        char = size / 2 * 20 * 0.56  # twips of one character: the size in points, 20 twips a point, 0.56 em on average
        need = []
        for index in range(len(cols)):
            longest = 0
            for cell in [header[index]] + [row[index] for row in rows]:
                for word in re.sub(r'[*~=`]', '', cell).split():
                    longest = max(longest, len(word))
            need.append(min(int(longest * char) + 230, int(total * 0.4)))
        cols = list(cols)
        wanted = [max(0, need[i] - cols[i]) for i in range(len(cols))]
        spare = [max(0, cols[i] - need[i]) for i in range(len(cols))]
        take = min(sum(wanted), sum(spare))
        if take == 0:
            return cols
        for i in range(len(cols)):
            if wanted[i]:
                cols[i] += int(take * wanted[i] / sum(wanted))
            elif spare[i]:
                cols[i] -= int(take * spare[i] / sum(spare))
        cols[-1] += total - sum(cols)
        return cols

    def after_table(self) -> None:
        """A small gap after a table, so the next paragraph does not stick to it."""
        self._body.append('<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="120" w:lineRule="exact"/>'
                          '<w:ind w:firstLine="0"/></w:pPr></w:p>')

    def code(self, lines: list[str], total: int = PORTRAIT_TEXT_WIDTH, size: int = 18) -> None:
        """Pseudocode in a grey one-cell table, one paragraph per line (leading spaces are kept)."""
        paragraphs = ''.join(
            '<w:p><w:pPr><w:pStyle w:val="TableText"/></w:pPr><w:r><w:rPr>'
            '<w:rFonts w:ascii="Courier New" w:hAnsi="Courier New" w:cs="Courier New"/>'
            f'<w:sz w:val="{size}"/><w:szCs w:val="{size}"/></w:rPr><w:t xml:space="preserve">{escape(line)}</w:t></w:r></w:p>'
            for line in lines
        )
        border = ''.join(f'<w:{side} w:val="single" w:sz="4" w:space="0" w:color="808080"/>'
                         for side in ('top', 'left', 'bottom', 'right'))
        row_props = '<w:trPr><w:cantSplit/></w:trPr>' if len(lines) <= 30 else ''
        self._body.append(
            f'<w:tbl><w:tblPr><w:tblW w:w="{total}" w:type="dxa"/><w:tblBorders>{border}</w:tblBorders>'
            '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="140" w:type="dxa"/>'
            '<w:bottom w:w="80" w:type="dxa"/><w:right w:w="140" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            f'<w:tblGrid><w:gridCol w:w="{total}"/></w:tblGrid><w:tr>{row_props}<w:tc><w:tcPr><w:tcW w:w="{total}" w:type="dxa"/>'
            f'<w:shd w:val="clear" w:color="auto" w:fill="F2F2F2"/></w:tcPr>{paragraphs}</w:tc></w:tr></w:tbl>'
        )
        self.after_table()

    # ---- figures ----------------------------------------------------------------------------------------------

    def figure(self, path: Path | str, caption: str, alt: str | None = None, landscape: bool = False,
               max_w: int | None = None, max_h: int | None = None) -> None:
        """A picture centered on its own paragraph and the caption under it. `landscape` only chooses the size
        limits: use `begin_landscape()` and `end_landscape()` around figures that need a landscape page."""
        path = Path(path)
        width_px, height_px, dpi = png_size(path)
        native_w = int(width_px / dpi * 914400)
        native_h = int(height_px / dpi * 914400)
        limit_w = max_w or (LANDSCAPE_MAX_W if landscape else PORTRAIT_MAX_W)
        limit_h = max_h or (LANDSCAPE_MAX_H if landscape else PORTRAIT_MAX_H)
        ratio = min(1.0, limit_w / native_w, limit_h / native_h)
        cx, cy = int(native_w * ratio), int(native_h * ratio)

        if path not in self._images:
            self._images.append(path)
        index = self._images.index(path) + 1
        self._drawing_id += 1
        drawing_id = self._drawing_id
        description = escape(alt or caption, {'"': '&quot;'})
        self._body.append(
            '<w:p><w:pPr><w:keepNext/><w:spacing w:before="120" w:after="60" w:line="240" w:lineRule="auto"/>'
            '<w:ind w:firstLine="0"/><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            '<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
            f'distT="0" distB="0" distL="0" distR="0"><wp:extent cx="{cx}" cy="{cy}"/>'
            f'<wp:docPr id="{drawing_id}" name="Figure {drawing_id}" descr="{description}"/>'
            '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            f'<pic:nvPicPr><pic:cNvPr id="{drawing_id}" name="image{index}.png"/><pic:cNvPicPr/></pic:nvPicPr>'
            f'<pic:blipFill><a:blip r:embed="rIdImg{index}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            f'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{cx}" cy="{cy}"/></a:xfrm>'
            '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic>'
            '</wp:inline></w:drawing></w:r></w:p>'
        )
        self._body.append(
            '<w:p><w:pPr><w:spacing w:before="0" w:after="240" w:line="240" w:lineRule="auto"/>'
            f'<w:ind w:firstLine="0"/><w:jc w:val="center"/></w:pPr>{_run(caption)}</w:p>'
        )

    @staticmethod
    def _section(landscape: bool) -> str:
        size = '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>' if landscape else '<w:pgSz w:w="11906" w:h="16838"/>'
        margins = (
            '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/>'
            if landscape else
            '<w:pgMar w:top="1134" w:right="850" w:bottom="1134" w:left="1701" w:header="567" w:footer="567" w:gutter="0"/>'
        )
        return f'<w:sectPr><w:footerReference w:type="default" r:id="rId10"/>{size}{margins}</w:sectPr>'

    def _section_break(self, landscape: bool) -> None:
        # The small gap that follows a table is not needed before a section break, and it could push the break onto a page of its own.
        if self._body and self._body[-1].startswith('<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="120"'):
            self._body.pop()
        self._body.append(
            '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="20" w:lineRule="exact"/>'
            f'<w:rPr><w:sz w:val="2"/><w:szCs w:val="2"/></w:rPr>{self._section(landscape)}</w:pPr></w:p>'
        )

    def begin_landscape(self) -> None:
        """Ends the portrait section; what follows is on landscape pages until `end_landscape()`. A heading with a few
        short paragraphs just before the call goes to the landscape page with the figure, so that a page does not hold
        a lone paragraph."""
        cut = len(self._body)
        for index in range(len(self._body) - 1, max(len(self._body) - 5, -1), -1):
            element = self._body[index]
            if '<w:tbl>' in element or '<w:sectPr>' in element or 'w:drawing' in element:
                break
            if 'w:pStyle w:val="Heading' in element:
                cut = index
                break
        marker = len(self._body)
        self._section_break(landscape=False)
        if cut < marker:
            self._body.insert(cut, self._body.pop())

    def end_landscape(self) -> None:
        self._section_break(landscape=True)

    # ---- output -----------------------------------------------------------------------------------------------

    def save(self, path: Path | str) -> None:
        path = Path(path)
        path.parent.mkdir(parents=True, exist_ok=True)
        document = (
            f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document {NS}><w:body>'
            + ''.join(self._body) + self._section(landscape=False) + '</w:body></w:document>'
        )
        rels = [
            ('rId1', 'styles', 'styles.xml'), ('rId2', 'numbering', 'numbering.xml'),
            ('rId3', 'settings', 'settings.xml'), ('rId10', 'footer', 'footer1.xml'),
        ]
        rels_xml = ''.join(
            f'<Relationship Id="{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/{t}" Target="{target}"/>'
            for i, t, target in rels
        ) + ''.join(
            f'<Relationship Id="rIdImg{n}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
            f'Target="media/image{n}.png"/>' for n in range(1, len(self._images) + 1)
        )
        core = (
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            f'<dc:title>{escape(self.title)}</dc:title><dc:creator>{escape(self.creator)}</dc:creator>'
            '<dc:language>en-US</dc:language>'
            f'<dcterms:created xsi:type="dcterms:W3CDTF">{datetime.now(timezone.utc):%Y-%m-%dT%H:%M:%SZ}</dcterms:created>'
            '</cp:coreProperties>'
        )
        with zipfile.ZipFile(path, 'w', zipfile.ZIP_DEFLATED) as z:
            z.writestr('[Content_Types].xml', CONTENT_TYPES)
            z.writestr('_rels/.rels', ROOT_RELS)
            z.writestr('docProps/app.xml', APP)
            z.writestr('docProps/core.xml', core)
            z.writestr('word/document.xml', document)
            z.writestr('word/styles.xml', STYLES)
            z.writestr('word/numbering.xml', NUMBERING)
            z.writestr('word/settings.xml', SETTINGS)
            z.writestr('word/footer1.xml', FOOTER)
            z.writestr('word/_rels/document.xml.rels',
                       '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                       f'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">{rels_xml}</Relationships>')
            for n, image in enumerate(self._images, 1):
                z.write(image, f'word/media/image{n}.png')
