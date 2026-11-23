"""Section 2: the documents of the technical design and the list of its figures."""

from __future__ import annotations


def write(d, ctx) -> None:
    d.h1('2 List of documents')
    d.p('The technical design consists of the parts that the methodology of the stage names. ' + ctx.ref('tab:documents')
        + ' shows where each part is given in this document.')
    ctx.table(d, 'tab:documents', 'Documents of the technical design')
    d.table(
        ['No.', 'Document', 'Content', 'Section'],
        [
            ['1', 'General information and the list of documents', 'The purpose of the document, the parts of the technical design and the list of figures.', '1, 2'],
            ['2', 'Explanatory note', 'The purpose and the basis of the design, the main technical decisions, the number of users and the volumes and the intensity of the information flows.', '3'],
            ['3', 'Systems of coding and classification', 'Identifiers, enumerations, codes of errors, codes of the design documents, classification of the data.', '4'],
            ['4', 'List of the input data (documents)', 'Forms and requests with their fields, restrictions, limits and frequency.', '5'],
            ['5', 'List of the output data (documents)', 'Answers of the API, lists, the quote card, the output of the moderation commands.', '6'],
            ['6', 'Refined algorithms', 'Stepwise refinement of the nine algorithms of the preliminary design.', '7'],
            ['7', 'Structural scheme', 'The structure of the system and the hierarchy of the modules of the server part and of the client.', '8'],
            ['8', 'Functional scheme', 'The components, the flows of data and the devices, in the symbols of GOST 19.701-90.', '9'],
            ['9', 'Structure charts of Constantine', 'The structural scheme as charts of the modules with their calls and couplings, specifications of the modules, evaluation of the design.', '10'],
            ['10', 'Structure charts of Jackson', 'The structure of three programs derived from the structure of their data.', '11'],
            ['11', 'User interface and the prototype', 'Principles, navigation, wireframes of the screens, states of the screens.', '12'],
            ['12', 'Description of the software', 'The software and the DBMS used to build, run and test the system.', '13'],
            ['13', 'Description of the technical means', 'The server, the devices of the users, the deployment scheme.', '14'],
            ['14', 'Design estimate of the reliability', 'The bottlenecks, the protection that exists and the recommended actions.', '15'],
            ['15', 'Bill of equipment and materials', 'The equipment, the services and the labour of the project.', '16'],
            ['16', 'Order of implementation of the modules', 'The order in which the modules were implemented and its link with the network schedule.', '17'],
            ['17', 'Glossary of the new terms', 'The terms of the structured design that this document uses.', '18'],
        ],
        [0.8, 3.6, 7.0, 1.2], align=['center', 'left', 'left', 'center'], size=22,
    )
    figures = ctx.known_figures()
    d.p('The documents on which the technical design is based are the technical specification of version 2 and the '
        'preliminary design of laboratory work 2. The network schedule of the works is given in the report on that work.')
    if figures:
        d.p('All the figures of the technical design are listed in ' + ctx.ref('tab:figures') + '.')
    ctx.table(d, 'tab:figures', 'Figures of the technical design')
    rows = [[label, title, section] for label, title, section in figures] or [['', '', '']]
    d.table(['Figure', 'Title', 'Section'], rows, [2.2, 8.4, 1.4], align=['left', 'left', 'center'], size=22)
