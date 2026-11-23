"""The title page of a laboratory report (the same for all works): `title_page(d, 5, ['Topic line 1', 'line 2'])`."""

from __future__ import annotations


def title_page(d, number: int, topic: list) -> None:
    def centered(text: str = '', **kwargs) -> None:
        d.p(text, align='center', indent=0, line=360, **kwargs)

    def signed(left: str, name: str, mark: bool = True) -> None:
        d.raw('<w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="9355"/></w:tabs><w:spacing w:line="360" w:lineRule="auto"/>'
              f'<w:ind w:firstLine="0"/></w:pPr><w:r><w:t xml:space="preserve">{left}</w:t><w:tab/>'
              + (f'<w:t xml:space="preserve">{name}</w:t></w:r>' if not mark else
                 f'</w:r><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t xml:space="preserve">{name}</w:t></w:r>')
              + '</w:p>')

    centered('Ministry of Education and Science of Ukraine', size=26)
    centered('KHMELNYTSKYI NATIONAL UNIVERSITY', bold=True)
    centered('Faculty of Information Technologies')
    centered('Department of Software Engineering')
    for _ in range(3):
        d.p('', indent=0, line=360)
    centered(f'LABORATORY WORK No. {number}', size=32, bold=True)
    d.p('', indent=0, line=360)
    centered('Discipline: “Software Construction”')
    for index, line in enumerate(topic):
        centered(('Topic: “' if index == 0 else '') + line + ('”' if index == len(topic) - 1 else ''))
    for _ in range(4):
        d.p('', indent=0, line=360)
    d.raw('<w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="9355"/></w:tabs><w:spacing w:line="360" w:lineRule="auto"/>'
          '<w:ind w:firstLine="0"/></w:pPr><w:r><w:t xml:space="preserve">Completed by: 3rd-year students of group SE-23-1</w:t>'
          '<w:tab/><w:t xml:space="preserve">Oleksandr Cheshun</w:t></w:r></w:p>')
    d.p('~~(name, surname)~~', align='right', indent=0, line=240, after=120, size=18)
    for name in ('Teammate 2', 'Teammate 3'):
        signed('', name)
        d.p('~~(name, surname)~~', align='right', indent=0, line=240, after=120, size=18)
    signed('Checked by:', 'Teacher name')
    d.p('~~(name, surname)~~', align='right', indent=0, line=240, after=120, size=18)
    d.p('', indent=0, line=360)
    d.p('', indent=0, line=360)
    centered('Khmelnytskyi, 2026')
