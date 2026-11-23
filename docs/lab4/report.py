"""The text of the report on laboratory work 4 (before the appendices)."""

from __future__ import annotations

from pathlib import Path

import thirdparty

RESULTS = Path(__file__).resolve().parent / 'results'


def title_page(d) -> None:
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
    centered('LABORATORY WORK No. 4', size=32, bold=True)
    d.p('', indent=0, line=360)
    centered('Discipline: “Software Construction”')
    centered('Topic: “The Stage “Implementation” of Software Development.')
    centered('Design and Implementation of a Simple Software Project')
    centered('with a User Interface”')
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


def read(name: str) -> list:
    return (RESULTS / name).read_text(encoding='utf-8').rstrip('\n').split('\n')


def write(d, ctx, size) -> None:
    """`size` holds the numbers of the program (lines and files) counted by build.py."""
    title_page(d)

    d.h1('Objective', page_break=True)
    d.p('To study the stage “implementation” of software development and to acquire the skills of developing a software '
        'product according to the individual task: to write and debug the program, to design its user interface and to '
        'prepare the program documentation.')

    d.h1('Tasks for the laboratory work')
    d.numbered([
        'Study the lecture material on the stages of software development and the literature on the stage '
        '“implementation”.',
        'Develop the program according to the individual task, choosing the programming language, on the basis of the '
        'results of laboratory works 1-3.',
        'Prepare the report and answer the control questions.',
    ])

    d.h1('Theoretical background')
    d.p('The stage of implementation follows the technical design. The program is written (coded) in a programming language '
        'by the design, then it is debugged: the errors are found and removed, and the program is checked against the '
        'requirements. The stage ends with the program and its documentation. Every software product must have '
        'documentation of two kinds: for the developers and for the users. All the information about the use of the '
        'software belongs to the documentation of the users. The information that is needed to develop and to maintain the '
        'software belongs to the documentation of the developer.')
    d.p('Software is documented by the Unified System of Program Documentation (GOST 19.xxx). GOST 19.101-77 lists the '
        'kinds of program documents for software of different types. They are given in ' + ctx.ref('tab:gost19') + '. '
        'Which of them are needed is decided at the stage of the technical specification.')
    ctx.table(d, 'tab:gost19', 'Kinds of program documents (GOST 19.101-77)')
    d.table(
        ['Code', 'Document', 'Content'],
        [
            ['-', 'Specification', 'The list and a short description of all files of the software and of its documents.'],
            ['05', 'List of holders of originals', 'The enterprises where the originals of the software are kept.'],
            ['12', 'Program text', 'The code of the program with all the necessary comments.'],
            ['13', 'Program description', 'The logical structure and the functioning of the program.'],
            ['20', 'List of operational documents', 'The list of the documents with the codes 30, 31, 32, 33, 34, 35, 46.'],
            ['30', 'Form', 'The main characteristics of the software, its completeness and the data about its use.'],
            ['31', 'Description of application', 'The purpose, the field of use, the methods used, the classes of tasks, the limits, the minimum configuration of the technical means.'],
            ['32', 'System programmer’s guide', 'The data for checking, running and tuning the software for the conditions of a concrete use.'],
            ['33', 'Programmer’s guide', 'The data for the use of the software.'],
            ['34', 'Operator’s guide', 'The interaction of the operator and the computer system while the program runs.'],
            ['35', 'Description of the language', 'The syntax and the semantics of the language in which the software is written.'],
            ['46', 'Maintenance manual', 'The use of the program in the maintenance of the technical means.'],
        ],
        [1.0, 3.6, 7.0], align=['center', 'left', 'left'],
    )

    d.h1('Performing the laboratory work')
    d.p('The problem statement: to implement the web application Bookshelf according to the technical specification '
        '(version 2), the preliminary design and the technical design, to debug it, to show its results and to prepare '
        'the documentation. The work was done with the program of laboratory works 1-3, which was developed in seven '
        'stages: the library, the progress, the quotes, the statistics and the annual goal, the PWA, the community and '
        'the built-in reading of EPUB books. The following steps were performed.')
    d.numbered([
        'The lecture material and the literature on the stage “implementation” were studied.',
        'The languages were chosen. The client is written in TypeScript (a typed superset of JavaScript, one of the '
        'languages of the methodology). The server part is written in PHP 8.4 with the framework Laravel 13, as the '
        'technical specification requires.',
        f'The program was written: {size["files"]} source files, {size["lines"]} lines, of them {size["test_lines"]} lines of '
        'automated tests (' + ctx.ref('tab:size') + ').',
        'The program was debugged with automated tests and static checks, in the containers of the project, and by '
        'working with the running application (' + ctx.ref('tab:checks') + ').',
        'The code was analysed and the tests were measured with third-party tools: SonarQube, Larastan, Pint, Pest and '
        'Vitest (Section “Testing with third-party tools”).',
        'The user interface was made and its real screens were taken from the running program (Appendix B).',
        'The documentation of the program was prepared (' + ctx.ref('tab:documents') + ').',
        'The results of the program were obtained on the running system (Section “Results of the program”).',
    ])
    ctx.table(d, 'tab:size', 'Size of the program')
    d.table(
        ['Part', 'Language', 'Files', 'Lines'],
        [
            ['Server part (`backend/app`)', 'PHP', str(size['app_files']), str(size['app_lines'])],
            ['Routes, configuration, migrations', 'PHP', str(size['cfg_files']), str(size['cfg_lines'])],
            ['Tests of the server part', 'PHP (Pest)', str(size['btest_files']), str(size['btest_lines'])],
            ['Client (`frontend/src`), without tests', 'TypeScript, Vue, CSS', str(size['fe_files']), str(size['fe_lines'])],
            ['Tests of the client', 'TypeScript (Vitest)', str(size['spec_files']), str(size['spec_lines'])],
            ['Containers, delivery, CI', 'Dockerfile, YAML, shell', str(size['ops_files']), str(size['ops_lines'])],
        ],
        [5.2, 3.0, 1.2, 1.4], align=['left', 'left', 'right', 'right'],
    )
    d.p('The kinds of documents of ' + ctx.ref('tab:gost19') + ' that were made for the program, and where they are, are in '
        + ctx.ref('tab:documents') + '. The form (30) and the maintenance manual (46) were not made: the program has no '
        'hardware, and its characteristics are in the technical specification and the technical design. The description '
        'of the language (35) is not needed, because the languages are standard.')
    ctx.table(d, 'tab:documents', 'Documents of the program')
    d.table(
        ['Code', 'Document', 'Where it is'],
        [
            ['12', 'Program text', 'Appendix A (the main modules) and the repository (`backend`, `frontend`).'],
            ['13', 'Program description', 'The technical design of laboratory work 3 and Appendix E, Section 2.'],
            ['31', 'Description of application', 'Appendix C, Section 1 (purpose, limits, requirements).'],
            ['32', 'System programmer’s guide', 'Appendix D.'],
            ['33', 'Programmer’s guide', 'Appendix E.'],
            ['34', 'Operator’s guide', 'Appendix F.'],
            ['-', 'Guide of the user', 'Appendix C (for the readers; the codes of GOST have no separate document for it).'],
        ],
        [1.0, 3.6, 7.0], align=['center', 'left', 'left'],
    )

    d.h1('Results of the program')
    d.p('The definition of done of the project is that the static checks and the tests are green. ' + ctx.ref('tab:checks')
        + ' shows the result of a full run of all checks of the project (`make lint`, `make test` and the build of the '
        'client) on the development stack.')
    ctx.table(d, 'tab:checks', 'Result of the checks of the program')
    d.table(
        ['Check', 'Tool', 'Result'],
        [list(row) for row in size['checks']],
        [3.6, 4.0, 4.0],
    )
    d.p('The program was also run, and its answers were saved. The listings below are real outputs of the running '
        'program (four demo readers were created through its API and removed afterwards). ' + ctx.ref('tab:out_progress')
        + ' is the answer to a progress update, ' + ctx.ref('tab:out_refused') + ' is the refusal of a page that the book '
        'does not have, and ' + ctx.ref('tab:out_mod') + ' and ' + ctx.ref('tab:out_restore')
        + ' are the work of the moderator with three reports.')
    for key, title, file in [
        ('tab:out_progress', 'Output: a progress update (POST /api/library/{id}/progress)', 'progress_update.json'),
        ('tab:out_refused', 'Output: a page that the book does not have', 'progress_refused.json'),
        ('tab:out_mod', 'Output: open reports (the third report has hidden the post)', 'moderation_reports.txt'),
        ('tab:out_restore', 'Output: the moderator restores the post', 'moderation_restore.txt'),
    ]:
        ctx.table(d, key, title)
        d.code(read(file), size=12 if file == 'moderation_reports.txt' else 16)
    d.p('The screens of the program are in Appendix B.')

    thirdparty.write(d, ctx)

    d.h1('Conclusion')
    d.p('In this laboratory work the program Bookshelf was implemented and debugged according to the technical '
        'specification and the technical design. The client is written in TypeScript and the server part in PHP; '
        f'together it is {size["files"]} source files and {size["lines"]} lines, with a large share of tests. All the '
        'checks of the project pass. The core of the program was also checked by third-party tools: SonarQube passed the '
        'quality gate with the rating A and no open issues, and the coverage by tests is 96.2% by SonarQube, 99.2% for '
        'the server part by Pest and 96.44% of lines for the client by Vitest. The user interface was made for a phone '
        'and its real screens are given in the report. '
        'The documentation was prepared: the listing of the main modules, the guide of the user, the guide of the system '
        'programmer, the guide of the programmer and the guide of the operator. The results of the program were '
        'obtained on the running system.')


QUESTIONS = [
    ('Describe the stage of implementation and debugging of software.',
     ['At the stage of implementation the program is written in a programming language by the technical design: the '
      'modules are coded, the data structures and the interfaces are made, and the user interface is built. The stage '
      'goes together with debugging. The programmer finds the errors by tests, by a debugger and by reading the logs, '
      'finds their cause and removes it, and checks again that the program does what the specification says. The '
      'stage ends with a program that works and with its documentation.',
      'In Bookshelf the modules were written from the controllers down to the models, slice by slice, and every slice '
      'was debugged with automated tests before the next one (Appendix E, Section 6).']),
    ('List the tools for the development of software.',
     ['The tools are: a text editor or an integrated development environment; a compiler or an interpreter; a debugger; '
      'a version control system; build tools and package managers; libraries and frameworks; tools of testing and of static '
      'analysis; tools of documentation; and tools of delivery (containers and continuous integration).',
      'In Bookshelf we used PHP and Composer, Laravel, Node.js and npm, Vite, TypeScript and Vue, Docker Compose, Git and '
      'GitHub Actions, Pest and Vitest for the tests, Larastan, Pint, ESLint and vue-tsc for the static checks '
      '(Appendix E, Section 5).']),
    ('Define the stage of spontaneous programming and characterize it.',
     ['Spontaneous programming is the first stage in the history of programming, up to the middle of the 1960s. A program '
      'was written by one person for a concrete machine, in machine code or in assembler, without a method. There were no '
      'rules of design and no standards of style: the program was an individual work, and its quality depended on the '
      'skill of its author. Such programs were short, hard to read and almost impossible to maintain by another person. '
      'There was no documentation as a separate product, and a program was usually written “to the end” and not changed.']),
    ('Characterize the stages of structured and modular programming.',
     ['Structured programming (the end of the 1960s and the 1970s) is based on the idea that a program is built of three '
      'structures with one entry and one exit: sequence, selection and loop. The jump operator is avoided, the program is '
      'designed from the top down and refined step by step, so it is easy to read and to check.',
      'Modular programming divides a program into modules. A module has a name, does one task and has a clear interface '
      'and a hidden inside. The modules are written, tested and changed separately, and different people can work on them. '
      'The quality of the division is judged by coupling and cohesion. Bookshelf uses both ideas: its use cases are '
      'refined as structured algorithms and are implemented as small modules (classes) with weak coupling.']),
    ('Define the documentation of software and describe its purpose.',
     ['The documentation of software is a set of documents that describe the program, its structure, its use and its '
      'maintenance. It has two kinds: for the developers (the program text, the description of the program, the '
      'guides of the programmer and of the system programmer) and for the users (the description of application, the '
      'guides of the user and of the operator). The purpose of the documentation is to let the users run the program '
      'correctly, to let the developers understand, change and test it, and to make the program a product that can '
      'be handed over, supported and developed after its authors have left.',
      'The documentation of Bookshelf is in the appendices of this report.']),
]


def write_answers(d, ctx) -> None:
    d.h1('Answers to the control questions')
    for number, (question, paragraphs) in enumerate(QUESTIONS, 1):
        d.p(f'{number}. {question}', indent=0, keep_next=True)
        for paragraph in paragraphs:
            d.p(paragraph)
