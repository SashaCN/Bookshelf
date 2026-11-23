"""The text of the report on laboratory work 3 (before Appendix A, which is the technical design itself)."""

from __future__ import annotations

TOPIC = ('The Stage “Technical Design” of Software Development. Structured Approach to Programming. '
         'Design of the User Interface. Development of a Software Prototype')


def title_page(d) -> None:
    def centered(text: str = '', **kwargs) -> None:
        d.p(text, align='center', indent=0, line=360, **kwargs)

    centered('Ministry of Education and Science of Ukraine', size=26)
    centered('KHMELNYTSKYI NATIONAL UNIVERSITY', bold=True)
    centered('Faculty of Information Technologies')
    centered('Department of Software Engineering')
    for _ in range(3):
        d.p('', indent=0, line=360)
    centered('LABORATORY WORK No. 3', size=32, bold=True)
    d.p('', indent=0, line=360)
    centered('Discipline: “Software Construction”')
    centered('Topic: “The Stage “Technical Design” of Software Development.')
    centered('Structured Approach to Programming. Design of the User Interface.')
    centered('Development of a Software Prototype”')
    for _ in range(4):
        d.p('', indent=0, line=360)
    d.raw('<w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="9355"/></w:tabs><w:spacing w:line="360" w:lineRule="auto"/>'
          '<w:ind w:firstLine="0"/></w:pPr><w:r><w:t xml:space="preserve">Completed by: 3rd-year students of group SE-23-1</w:t>'
          '<w:tab/><w:t xml:space="preserve">Oleksandr Cheshun</w:t></w:r></w:p>')
    for name in ('', 'Teammate 2', 'Teammate 3'):
        if name:
            d.raw('<w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="9355"/></w:tabs><w:spacing w:line="360" w:lineRule="auto"/>'
                  '<w:ind w:firstLine="0"/></w:pPr><w:r><w:tab/></w:r><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr>'
                  f'<w:t xml:space="preserve">{name}</w:t></w:r></w:p>')
        d.p('~~(name, surname)~~', align='right', indent=0, line=240, after=120, size=18)
    d.raw('<w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="9355"/></w:tabs><w:spacing w:line="360" w:lineRule="auto"/>'
          '<w:ind w:firstLine="0"/></w:pPr><w:r><w:t xml:space="preserve">Checked by:</w:t><w:tab/></w:r>'
          '<w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t xml:space="preserve">Teacher name</w:t></w:r></w:p>')
    d.p('~~(name, surname)~~', align='right', indent=0, line=240, after=120, size=18)
    d.p('', indent=0, line=360)
    d.p('', indent=0, line=360)
    centered('Khmelnytskyi, 2026')


def write_front(d, ctx, app) -> None:
    """The title page, the objective, the tasks, the theory and the problem statement."""
    title_page(d)
    if getattr(ctx, 'toc_entries', None):
        d.toc(ctx.toc_entries)

    d.h1('Objective', page_break=True)
    d.p('To study the stage “technical design” of software development and to acquire the skills of developing '
        'the technical design of a software product by the structured approach: of refining the algorithms step by '
        'step, of building a structural and a functional scheme of the software and of representing the structure '
        'of the software as structure charts of Constantine and of Jackson.')

    d.h1('Tasks for the laboratory work')
    d.numbered([
        'Study the lecture material on the stages of software development and on the design of software at the '
        'stage “technical design”.',
        'Develop the technical design according to the individual task: refine the algorithms of the programs of the '
        'module on the basis of the technical specification of laboratory work 1 and of the specifications of '
        'laboratory work 2; build the structural and the functional scheme; present the structural scheme as '
        'structure charts of Constantine and of Jackson; format the result as a technical product with MS Office.',
        'Prepare the report and answer the control questions.',
    ])

    d.h1('Theoretical background')
    d.p('The technical design is the stage that follows the technical specification and the preliminary design. The '
        'image of the system that was outlined earlier is now represented as a description, schemes, calculations, '
        'justifications and numerical indicators. The aim of the stage is to determine the main methods that will be '
        'used to create the system and to make the final estimate of its cost. The detailed description of a technical '
        'design contains the functions that will be performed and the variants of their use, the corresponding '
        'documents, the structures of the databases, the relations between the data and the algorithms of data '
        'processing. The data that must be included in it are the volume and the intensity of the flows of '
        'information, the number of users and the characteristics of the hardware and the software that interact '
        'with the designed software.')
    d.p('The technical design consists of the general information and the statement of the design, the explanatory '
        'note, the description of the systems of coding and classification, the lists of the input and the output '
        'data (documents), the description of the software, the list of the software and the DBMS, the description of '
        'the technical means, the design estimate of the reliability of the system and the bill of equipment and '
        'materials.')
    d.p('A **structural scheme** shows the composition of the parts of the software and their interaction by control. '
        'Depending on the architecture it shows the structure of the databases that are processed, the relations of '
        'the data and the algorithms of their processing. A **functional scheme** shows the interaction of the '
        'components of the software with a description of the flows of information, of the composition of the data in '
        'the flows and with the files and the devices that are used. It is more informative than the structural scheme. '
        'The symbols of the functional schemes are set by GOST 19.701-90 (' + ctx.ref('tab:symbols') + '). All the '
        'components of both schemes must be described, and in the structural approach the specifications of the '
        'interfaces between the programs must be worked out with special care, because the most expensive errors, '
        'those found by the integration testing, come from them.')
    ctx.table(d, 'tab:symbols', 'Basic symbols of data schemes by GOST 19.701-90')
    d.table(
        ['Symbol', 'Purpose'],
        [
            ['Stored data', 'Tables and other data structures that must be stored, without naming the type of the device'],
            ['Internal (operative) memory', 'Tables and other data structures that are kept in the main memory'],
            ['Direct access storage', 'Tables and other data structures that are kept on magnetic disks'],
            ['Document', 'Tables and other data structures that are printed'],
            ['Manual input', 'Data that are typed on the keyboard'],
            ['Display', 'Data that are shown on the screen of the computer'],
        ],
        [3.2, 8.0],
    )
    d.p('The **method of stepwise refinement** (the top-down approach) is used to develop the algorithms: the task is '
        'stated as one step, the step is split into several simpler steps, each of them is refined in turn, until every '
        'step can be written with the operations of a programming language or is a ready module. The method gives '
        'structured algorithms and, at the same time, the hierarchical structure of the program.')
    d.p('At the stage of design the **structure charts** are used to show how the program meets the requirements. '
        'There are two kinds of them. The charts of **Constantine** describe the relations between the modules. '
        'A chart is a graph whose vertices are modules, subsystems (programs), libraries and data areas and whose arcs '
        'are the calls of the modules and the references to the common data areas. The calls are sequential, '
        'parallel or coroutine; a conditional node (a diamond) and an iteration node (a semicircle) show a conditional '
        'and a repeated call. The data and the control that are passed between the modules are shown by arrows next to '
        'the arc of the call. The charts allow to evaluate the decomposition by two criteria. **Coupling** is the '
        'measure of the interdependence of the modules; it should be minimal. The normal kinds of coupling are '
        'coupling by data (elementary parameters), by stamp (a composite object is passed) and by control (a flag or a '
        'command that steers the logic of the other module). **Cohesion** is the measure of the strength of the link '
        'between the elements inside one module. Its levels, from the best, are functional, sequential, informational, '
        'procedural, temporal, logical and coincidental.')
    d.p('The charts of **Jackson** are based on the method of structural programming of Jackson: the structure of the '
        'program must correspond to the structure of the data that it processes, and the main attention is paid to the '
        'correspondence of the input and the output data. A chart is a tree of blocks. It is built of four '
        'components: an operation (a block of code with one entry and one exit), a sequence (operations that are '
        'executed from left to right), a selection (one of the operations is executed depending on a condition) and an '
        'iteration (a block is executed many times). The technique allows to design the lower level of the structure '
        'of the software and is close to the traditional flowcharts.')

    d.h1('Performing the laboratory work')
    d.p('The problem statement: to develop the technical design of the web application Bookshelf according to the '
        'technical specification (version 2: the personal tracker, the community and the built-in reading) and the '
        'preliminary design. The application is implemented, so the technical design describes the real modules, data '
        'and infrastructure of the repository, and not a plan. The following steps were performed.')


def _count(app, prefix: str, exclude: tuple = ()) -> int:
    return sum(1 for key in app.known if key.startswith(prefix) and key not in exclude)


def write_rest(d, ctx, app) -> None:
    """Steps, results, conclusion and the answers to the control questions."""
    n_cc = _count(app, 'fig:cc_', ('fig:cc_legend',))
    n_jk = _count(app, 'fig:jk_', ('fig:jk_legend',))
    d.numbered([
        'The lecture material on the stage “technical design” and on the structured approach to the design of '
        'software was studied.',
        'The nine algorithms of the preliminary design were refined step by step, in three levels, down to the '
        'algorithms of the modules (Section 7 of Appendix A).',
        'The structural scheme of the system was built, with the hierarchies of the modules of the server part and of '
        'the client (Section 8).',
        'The functional scheme was built in the symbols of GOST 19.701-90, for the whole system and for the built-in '
        'reading (Section 9).',
        f'The structural scheme was presented as {n_cc} structure charts of Constantine with the specifications of '
        'the modules and the evaluation of the coupling and the cohesion (Section 10).',
        f'The structure of {n_jk} programs was presented as structure charts of Jackson, derived from the structure of '
        'their data (Section 11).',
        'The user interface was designed and the prototype was described: the navigation, the wireframes of the '
        'screens and the states of the screens (Section 12). The prototype is the working application.',
        'The systems of coding and classification, the lists of the input and the output data, the software, the '
        'technical means, the design estimate of the reliability and the bill of equipment and materials were '
        'described, and the order of implementation of the modules was given (Sections 3 to 6 and 13 to 18). The '
        'result was formatted as a technical product with MS Office.',
    ])
    d.p('The technical design is given in Appendix A, and ' + ctx.ref('tab:contents') + ' shows its contents.')
    ctx.table(d, 'tab:contents', 'Contents of the technical design')
    d.table(
        ['No.', 'Section', 'Content'],
        [
            ['1', 'Introduction and list of documents', 'Purpose of the document, its parts and the list of its figures.'],
            ['2', 'Explanatory note', 'Purpose and basis of the design, main technical decisions, number of users, volumes and intensity of the flows of data.'],
            ['3', 'Coding, input and output data', 'Identifiers, enumerations, codes of errors, classification of the data; the lists of the input and the output documents.'],
            ['4', 'Refined algorithms', 'Nine algorithms refined in three levels, with the pseudocode of the modules.'],
            ['5', 'Structural and functional schemes', 'Structure of the system, hierarchy of the modules, the functional schemes in the symbols of GOST 19.701-90.'],
            ['6', 'Structure charts of Constantine and of Jackson', 'The structural scheme as charts of the modules and of the programs, specifications and the evaluation of the design.'],
            ['7', 'User interface', 'Principles, navigation, wireframes of the screens, states of the screens, the prototype.'],
            ['8', 'Software, technical means, reliability, cost', 'Software and DBMS, hardware, the design estimate of the reliability, the bill of equipment and materials.'],
            ['9', 'Order of implementation and glossary', 'The order in which the modules were implemented; the new terms.'],
        ],
        [0.8, 3.6, 7.0], align=['center', 'left', 'left'],
    )


QUESTIONS = [
    ('What are the structural and the functional scheme of software? When are they used? How do the structural and the '
     'functional schemes of software with different architectures differ?',
     lambda app: [
         'A structural scheme shows the composition of the parts of the software and their interaction by control: which '
         'parts there are, which part calls which. A functional scheme shows the interaction of the components with the '
         'description of the flows of information, of the composition of the data in the flows and with the files and '
         'the devices that are used, in the symbols of GOST 19.701-90. The functional scheme is more informative.',
         'The structural scheme is built first, when the program is divided into parts, to plan the modules and the '
         'specifications of their interfaces. The functional scheme is built after it, when the flows of data and the '
         'devices must be shown, and it gives the fuller idea of the future program from the point of view of the '
         'interaction of its components with each other and with the environment.',
         'The schemes depend on the architecture. For a software complex (a set of programs) they show the programs, '
         'their calls and the input and output of each program. For a system (subsystems around a database) they show '
         'the subsystems, the structure of the database, the relations of the data and the algorithms of processing, '
         f'and the forms of data input and the reports. In Bookshelf the structural scheme is {app.ref("fig:structure_system")} '
         f'and the hierarchy of the modules is {app.ref("fig:structure_server")}; the functional schemes are '
         f'{app.ref("fig:functional_system")} and {app.ref("fig:functional_reader")}.',
     ]),
    ('On what properties of software systems is the method of stepwise refinement based? Why does it give only '
     'structured algorithms? What, in your opinion, is the main difficulty of the method?',
     lambda app: [
         'The method is based on three properties. A complex task can be decomposed into simpler subtasks, which gives a '
         'hierarchy. Any algorithm can be built from three structures with one entry and one exit (sequence, selection, '
         'iteration), so a step can be replaced by its refinement without changing the rest of the algorithm. Every step '
         'has a defined input and output, that is, an interface, so the steps can be refined independently.',
         'Every refinement replaces one step by a composition of the three basic structures, and a composition of them '
         'in any nesting is again a structured algorithm with one entry and one exit. A jump between the branches of '
         'different levels does not fit the method, so only structured algorithms are obtained.',
         'The main difficulty is the choice of the decomposition at the top levels. A wrong choice is found late, when the '
         'lower levels are already refined, and is expensive to correct. The method does not say when to stop, and it '
         'does not show in advance that the same step appears in different branches, so the common part may be refined '
         'twice. In Bookshelf this was seen when the reading position was refined: it ended in the same module '
         '`UpdateProgress` as the progress update, which is one module in the code, not two.',
     ]),
    ('How is the method of stepwise refinement used in the development of the algorithms and of the structure of '
     'software?',
     lambda app: [
         'The task is stated as one step (level 0). The step is split into three to seven steps that are connected by '
         'sequence, selection and iteration, and the data that pass between the steps are named (level 1). Each step is '
         'refined in the same way until it is an operation of the language or an existing module (level 2). The tree of '
         'the steps that is obtained is the hierarchical structure of the program: the steps become the modules, the '
         'steps that occur in several branches become common modules or libraries, and the data that pass between the '
         'steps become the parameters of the modules and their specifications.',
         f'In Bookshelf each algorithm of the preliminary design is refined in this way in Section 7 of Appendix A; for '
         f'example, the import of an EPUB book is given in {app.ref("tab:alg_import")} and its algorithm of level 2 '
         'follows the table. The steps of level 1 are the calls of the structure chart '
         f'{app.ref("fig:cc_import")}.',
     ]),
    ('What are the structure charts of Constantine built for?',
     lambda app: [
         'They show the result of the decomposition of a program into modules and the relations between the modules: '
         'the calls (sequential, parallel, conditional, repeated) and the passing of data and control. With them the '
         'quality of the decomposition is evaluated by the coupling and the cohesion, and the possible improvements '
         'are found, for example the transfer of a module to another level to reduce the coupling by stamp. They '
         'give the specifications of the interfaces of the modules (the name, the parameters with types and ranges) and '
         'the order of implementation and testing from the top down.',
         f'In Bookshelf the charts are in Section 10 of Appendix A ({app.ref("fig:cc_progress")}, '
         f'{app.ref("fig:cc_import")}, {app.ref("fig:cc_quote")}, {app.ref("fig:cc_stats")}), and the evaluation of the '
         f'couplings and the cohesion of the modules is in {app.ref("tab:cc_quality")}.',
     ]),
    ('How do the structure charts of Jackson differ from the structure charts of Constantine?',
     lambda app: [
         'A chart of Constantine is a graph of modules: it shows which module calls which and what data and control '
         'pass between them, so it describes the structure of the program between the modules and allows to evaluate '
         'the coupling and the cohesion. A chart of Jackson is a tree that describes the structure of one program or '
         'module inside, and it is derived from the structure of the data that the program processes. It uses only '
         'four components (operation, sequence, selection, iteration) and is close to a flowchart.',
         'The first answers the question “which modules are there and how are they connected”, the second the '
         'question “in what order does one module process its data”. In Bookshelf the same import of an EPUB book is '
         f'drawn both ways: {app.ref("fig:cc_import")} shows the modules and their calls, and {app.ref("fig:jk_import")} '
         'shows how the program walks over the documents of the book.',
     ]),
    ('What is the basis of the methods of Jackson and of Warnier-Orr? How do the methods differ?',
     lambda app: [
         'Both methods are data-structured: the structure of a program is derived from the structure of the data that it '
         'processes, so a program that works with well-structured data is itself well structured and is easy to '
         'change when the data change.',
         'In the method of Jackson the structures of the input and of the output data are built first, each as a tree '
         'of sequences, selections and iterations; the corresponding components are then joined into one tree, which is '
         'the structure of the program, and the operations are put on its components. The tree is drawn with boxes. '
         'The method of Warnier-Orr (the logical construction of programs of Warnier, developed by Orr) is oriented to '
         'the output: the structure of the required output data is the starting point, and the program is derived from it '
         'backwards. The hierarchy is written with nested braces from left to right, and the number of repetitions of a '
         'component and the exclusive choice are written next to it. So the methods differ in the data from which '
         'they start (the input and the output together against the output) and in the notation (a tree of boxes against '
         'braces).',
     ]),
    ('For what kinds of developments is it reasonable to use the structured methodologies?',
     lambda app: [
         'They fit the systems in which the functions and the data are well defined and can be decomposed: information '
         'and data-processing systems, the accounting and the reporting systems, the programs with a clear input, a '
         'clear output and a definite algorithm. They are useful for medium and large projects with several '
         'developers, because the models and the specifications are a common language of the team, and for the '
         'projects whose requirements are stable through the development.',
         'They fit less where the behaviour depends on events and on the state of an interactive interface, or where the '
         'requirements are unclear and change quickly; there the object-oriented or the iterative methods are better. '
         'Bookshelf combines them: its use cases are described and refined as structured algorithms, and the same use cases '
         'are implemented as classes of an object-oriented framework.',
     ]),
]


def write_answers(d, ctx, app) -> None:
    d.h1('Answers to the control questions', page_break=True)
    for number, (question, answer) in enumerate(QUESTIONS, 1):
        d.p(f'{number}. {question}', indent=0, keep_next=True)
        for paragraph in answer(app):
            d.p(paragraph)


def write_results(d, ctx, app) -> None:
    n_cc = _count(app, 'fig:cc_', ('fig:cc_legend',))
    n_ui = _count(app, 'fig:ui_', ('fig:ui_map',))
    n_boards = _count(app, 'fig:proto_')
    d.h2('Results of the technical design')

    d.h3('Refined algorithms')
    d.p('The nine algorithms of the preliminary design and the sanitizing of a chapter were refined step by step '
        f'(Section 7 of Appendix A, {app.ref("tab:alg_list")}). For every algorithm the table gives the levels 0 and 1, '
        'with the module of each step, and the pseudocode of level 2 gives the checks, the locks, the limits and the '
        'codes of the errors. The refinement of the progress update shows the main rule of the design: one transaction '
        'locks the row of the book, so two quick taps do not interleave, and the reading position of the built-in '
        'reader uses the same module and the same lock.')

    d.h3('Structural and functional schemes')
    d.p(f'The structural scheme is given as the structure of the system ({app.ref("fig:structure_system")}) and as the hierarchies '
        f'of the modules of the server part ({app.ref("fig:structure_server")}) and of the client ({app.ref("fig:structure_client")}); '
        f'the modules are listed in {app.ref("tab:modules_server")} and {app.ref("tab:modules_client")}. The functional scheme in the symbols '
        f'of GOST 19.701-90 is built for the whole system ({app.ref("fig:functional_system")}) and for the built-in reading '
        f'({app.ref("fig:functional_reader")}). The interfaces between the client and the server are specified in '
        f'{app.ref("tab:interfaces")}.')

    d.h3('Structure charts of Constantine')
    d.p(f'The structural scheme is presented as {n_cc} structure charts of Constantine: the update of progress '
        f'({app.ref("fig:cc_progress")}), the import of an EPUB book ({app.ref("fig:cc_import")}, with {app.ref("fig:cc_epub")} for '
        f'the opening of the archive and the reading of its package), the saving of a quote selected in the text '
        f'({app.ref("fig:cc_quote")}), the saving of the reading position ({app.ref("fig:cc_position")}) and the statistics '
        f'({app.ref("fig:cc_stats")}). Every chart has the table of the specifications of its modules (the name, '
        'the purpose, the parameters with their types and ranges). The evaluation of the design '
        f'({app.ref("tab:cc_quality")}) shows no modules with coincidental or temporal cohesion. The weaker places are '
        '`ReadingStatistics` (informational cohesion), `ChangeUserBookStatus::apply` (logical cohesion and coupling by '
        'control by an enumeration value) and the size of `HtmlSanitizer`. Most calls pass a model or an array, that is, '
        'coupling by stamp; the section names the changes that would reduce it and what each of them would cost, as the '
        'methodology does in its example. No change of the design was found necessary.')

    d.h3('Structure charts of Jackson')
    d.p(f'The structure of three programs is derived from the structure of their data: the reading of the chapters of an EPUB '
        f'book ({app.ref("fig:jk_import")}), the counting of the reading streak ({app.ref("fig:jk_streak")}) and the report with '
        f'the automatic hiding ({app.ref("fig:jk_report")}). The first program is an iteration over the documents of the book with '
        'a selection of what to do with each of them, the second has two iterations (over the days with reading and back '
        'from today), and the third consists mostly of selections. For each of them the section gives the pseudocode that '
        'matches the chart and the table of the correspondence between the data and the program.')

    d.h3('User interface and prototype')
    d.p(f'The interface is described by the map of the screens ({app.ref("fig:ui_map")}), by {n_ui} figures with wireframes '
        f'and by the table of the screens ({app.ref("tab:screens")}). The prototype is the working application: {n_boards} '
        'figures of Section 12.6 show its real screens, taken from the application that runs in the development '
        'containers. The client has 1181 automated tests and the server part 1053; all of them passed in a full run.')

    d.h3('Technical means, reliability and cost')
    d.p(f'The system runs on one virtual machine with 2 vCPU, 1 GB of memory and a disk of 30 GB ({app.ref("fig:deployment")}, '
        f'{app.ref("tab:hardware")}). The design estimate of the reliability ({app.ref("tab:reliability")}) finds the weak places '
        'honestly: one machine without a replica, a small memory, the dumps of the database on the same disk as the '
        'database (the copy outside the server is made by hand), no description of a tested restore, no monitoring. For each of them '
        f'an action is recommended. The cost is given in {app.ref("tab:bill")}: the equipment and the services are free '
        'for a student project (Azure for Students and the free tools), and the labour is the 18 works of the network '
        'schedule, 103 working days in total, with the critical path of 57 working days.')

    d.h3('Differences found between the program and the earlier documents')
    d.p(f'The technical design was written from the code, and this showed where the program and the earlier documents '
        f'disagree ({app.ref("tab:deviations")}). The most important are the function “recover a password”, which the '
        'technical specification and the preliminary design list but which is not implemented, and the removal of a book '
        'added by hand, which deletes the posts about it, although the technical specification says that published posts '
        'stay. The rest are the differences in the limits of the fields and in the details of the algorithms.')


def write_conclusion(d, ctx, app) -> None:
    d.h1('Conclusion')
    d.p('In this laboratory work the technical design of the web application Bookshelf of the second version was '
        'developed. The algorithms of the preliminary design were refined step by step down to the modules; the structural '
        'and the functional scheme were built; the structural scheme was presented as structure charts of Constantine and '
        'of Jackson; the systems of coding and classification, the lists of the input and the output data, the software, '
        'the technical means, the design estimate of the reliability and the bill of equipment and materials were '
        'described; the user interface was designed, and its prototype, which is the working application, was documented '
        'with its real screens. The design describes the program that exists, and it was checked against the code; the '
        'places where the program differs from the earlier documents are listed. The structure of the program is '
        'shallow, the modules are in the most part functional or sequential, and the coupling is mostly by stamp; the '
        'weak places of the reliability are known and have recommended actions.')


def write(d, ctx, app) -> None:
    write_front(d, ctx, app)
    write_rest(d, ctx, app)
    write_results(d, ctx, app)
    write_conclusion(d, ctx, app)
    write_answers(d, ctx, app)


SHORT_QUESTIONS = [
    ('What are the structural and the functional scheme of software? When are they used? How do the structural and the '
     'functional schemes of software with different architectures differ?',
     lambda app: [
         'A structural scheme shows the composition of the parts of the software and their interaction by control. A '
         'functional scheme shows the interaction of the components with the flows of information, the composition of the '
         'data in them and the files and devices that are used, in the symbols of GOST 19.701-90, so it is more informative. '
         'The structural scheme is built first, when the program is divided into parts; the functional one when the flows of '
         'data and the devices must be shown.',
         'For a set of programs they show the programs, their calls and their input and output; for a system with a database, '
         f'the subsystems, the database, the forms and the reports. In Bookshelf: {app.ref("fig:s_structure")} and {app.ref("fig:s_functional")}.',
     ]),
    ('On what properties of software systems is the method of stepwise refinement based? Why does it give only '
     'structured algorithms? What, in your opinion, is the main difficulty of the method?',
     lambda app: [
         'It is based on the hierarchy of a complex task (it splits into subtasks), on the fact that any algorithm can be built '
         'of three structures with one entry and one exit (sequence, selection, iteration), and on the independence of the '
         'steps, each of which has a defined input and output. Every refinement replaces a step with a composition of the '
         'three structures, so the result is a structured algorithm. The main difficulty is the choice of the decomposition at '
         'the top levels: a wrong choice is found late.',
     ]),
    ('How is the method of stepwise refinement used in the development of the algorithms and of the structure of '
     'software?',
     lambda app: [
         'The task is stated as one step (level 0), split into three to seven steps connected by sequence, selection and '
         'iteration (level 1), and every step is refined in the same way until it is an operation of the language or an '
         'existing module (level 2). The tree of steps is the structure of the program: the steps become modules, repeated '
         'steps common modules, and the data between the steps parameters. In Bookshelf: Section 4 of Appendix A '
         f'(for example {app.ref("tab:s_alg_import")}).',
     ]),
    ('What are the structure charts of Constantine built for?',
     lambda app: [
         'They show the result of the decomposition of a program into modules and the relations between the modules: the '
         'calls and the passing of data and control. With them the coupling and the cohesion are evaluated, the possible '
         'improvements are found, and the interfaces of the modules are specified. In Bookshelf they are '
         f'{app.ref("fig:s_cc_progress")} and {app.ref("fig:s_cc_import")}, the evaluation is {app.ref("tab:s_cc_quality")}.',
     ]),
    ('How do the structure charts of Jackson differ from the structure charts of Constantine?',
     lambda app: [
         'A chart of Constantine is a graph of modules: it shows which module calls which and what data and control pass, so '
         'it describes the structure between the modules. A chart of Jackson is a tree of the inside of one program or module, '
         'derived from the structure of its data, of four components only (operation, sequence, selection, iteration), and it '
         'is close to a flowchart. The same import is drawn both ways: '
         f'{app.ref("fig:s_cc_import")} and {app.ref("fig:s_jk_import")}.',
     ]),
    ('What is the basis of the methods of Jackson and of Warnier-Orr? How do the methods differ?',
     lambda app: [
         'Both are data-structured: the structure of a program is derived from the structure of the data that it processes. '
         'Jackson builds the structures of the input and of the output data and joins them into one tree of boxes. Warnier-Orr '
         'is oriented to the output: the program is derived backwards from the required output, and the hierarchy is written '
         'with nested braces. They differ in the starting point and in the notation.',
     ]),
    ('For what kinds of developments is it reasonable to use the structured methodologies?',
     lambda app: [
         'For systems whose functions and data are well defined and can be decomposed (information and data-processing systems), '
         'and for medium and large projects with stable requirements. They fit less where the behaviour depends on events or '
         'the requirements change quickly. Bookshelf combines them: its use cases are refined as structured algorithms and '
         'implemented as classes.',
     ]),
]


def write_short(d, ctx, app) -> None:
    """The short report: the title page, the text of the work and the answers; Appendix A is the short technical design."""
    title_page(d)

    d.h1('Objective', page_break=True)
    d.p('To study the stage “technical design” of software development and to acquire the skills of developing the technical '
        'design of a software product by the structured approach: of refining the algorithms step by step, of building a '
        'structural and a functional scheme and of representing the structure of the software as structure charts of '
        'Constantine and of Jackson.')

    d.h1('Tasks for the laboratory work')
    d.numbered([
        'Study the lecture material on the stages of software development and on the design of software at the stage '
        '“technical design”.',
        'Develop the technical design according to the individual task: refine the algorithms of the programs of the module on '
        'the basis of the technical specification of laboratory work 1 and of the specifications of laboratory work 2; build the '
        'structural and the functional scheme; present the structural scheme as structure charts of Constantine and of Jackson; '
        'format the result as a technical product with MS Office.',
        'Prepare the report and answer the control questions.',
    ])

    d.h1('Theoretical background')
    d.p('The technical design is the stage that follows the technical specification and the preliminary design. It describes '
        'the functions and the ways to use them, the documents, the structures of the databases, the relations of the data and '
        'the algorithms of their processing, and it gives the volume and the intensity of the flows of information, the number '
        'of users and the characteristics of the software and the hardware. A structural scheme shows the composition of the '
        'parts of the software and their interaction by control; a functional scheme shows the flows of information, the '
        'composition of the data and the files and devices that are used, in the symbols of GOST 19.701-90.')
    d.p('The algorithms are developed by the method of stepwise refinement: the task is stated as one step, the step is split '
        'into simpler steps, and each of them is refined until it is an operation of the language or a ready module. The '
        'structure charts show how the program meets the requirements. The charts of Constantine are graphs of modules '
        '(modules, subsystems, libraries and data areas) with the calls between them and the data and control that pass; with '
        'them the coupling (by data, by stamp, by control) and the cohesion of the modules are evaluated. The charts of Jackson '
        'are trees derived from the structure of the data, built of four components: an operation, a sequence, a selection and '
        'an iteration.')

    d.h1('Performing the laboratory work')
    d.p('The problem statement: to develop the technical design of the web application Bookshelf according to the technical '
        'specification (version 2: the personal tracker, the community and the built-in reading) and the preliminary design. The '
        'application is implemented, so the technical design describes the real modules, data and infrastructure of the '
        'repository. The following steps were performed.')
    d.numbered([
        'The lecture material on the stage “technical design” and on the structured approach was studied.',
        f'Two algorithms of the preliminary design (the update of progress and the import of an EPUB book) were refined step by '
        f'step in three levels (Section 4 of Appendix A, {app.ref("tab:s_alg_progress")} and {app.ref("tab:s_alg_import")}).',
        f'The structural scheme ({app.ref("fig:s_structure")}) and the functional scheme ({app.ref("fig:s_functional")}) were built.',
        f'The structural scheme was presented as two structure charts of Constantine ({app.ref("fig:s_cc_progress")} and '
        f'{app.ref("fig:s_cc_import")}) with the specifications of the modules and the evaluation of the coupling and the cohesion '
        f'({app.ref("tab:s_cc_quality")}).',
        f'The structure of two programs was presented as structure charts of Jackson ({app.ref("fig:s_jk_import")} and '
        f'{app.ref("fig:s_jk_streak")}).',
        f'The user interface, the prototype (the working application, {app.ref("fig:s_ui_screens")}), the coding, the data, the '
        'software, the technical means, the reliability and the cost were described briefly. The result was formatted as a '
        'technical product with MS Office.',
    ])
    d.p('The technical design is given in Appendix A. It was written from the code, and this showed where the program differs '
        f'from the earlier documents ({app.ref("tab:s_diff")}): the function “recover a password” is not implemented, and '
        'removing a book added by hand deletes the posts about it, although the technical specification says that posts stay. '
        'The weak places of the reliability are known and have recommended actions '
        f'({app.ref("tab:s_risks")}).')

    d.h1('Conclusion')
    d.p('In this laboratory work the technical design of the web application Bookshelf of the second version was developed. The '
        'algorithms were refined step by step down to the modules, the structural and the functional scheme were built, and the '
        'structure of the program was presented as structure charts of Constantine and of Jackson. The coding, the data, the '
        'interface and its prototype, the software, the technical means, the reliability and the cost were described. The design '
        'describes the program that exists and was checked against its code; the differences from the earlier documents are listed.')

    d.h1('Answers to the control questions', page_break=True)
    for number, (question, answer) in enumerate(SHORT_QUESTIONS, 1):
        d.p(f'{number}. {question}', indent=0, keep_next=True)
        for paragraph in answer(app):
            d.p(paragraph)
