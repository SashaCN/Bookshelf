"""Section 1: the introduction."""

from __future__ import annotations


def write(d, ctx) -> None:
    d.h1('1 Introduction', page_break=True)
    d.p('This document is the technical design of the web application Bookshelf, version 2. The application is a '
        'personal reading tracker with a community of readers and a built-in reader of EPUB books. The technical '
        'design is the next stage after the technical specification and the preliminary design. It turns the models '
        'of the preliminary design into the description of the program: the algorithms, the structure of the modules, '
        'the data that they take and give, the software and the hardware, and the assessment of the reliability and '
        'of the cost.')
    d.p('Bookshelf has been implemented in full, so the technical design describes the program that exists. The names '
        'of the modules, the routes, the tables and the codes in this document are the names in the repository. Where '
        'the figures are the result of a count (the number of files or tests) or of an estimate, the text says so.')
    d.p('The document follows the order of the stage “technical design”. Section 2 lists the documents. Section 3 is the '
        'explanatory note: the purpose, the main decisions and the quantitative data on which the design is based. '
        'Sections 4 to 6 give the systems of coding and classification and the lists of the input and the output data. '
        'Section 7 refines the algorithms of the preliminary design. Sections 8 and 9 give the structural and the '
        'functional scheme, and Sections 10 and 11 represent the structural scheme as the structure charts of Constantine '
        'and of Jackson. Section 12 describes the user interface and the prototype. Sections 13 to 16 describe the '
        'software, the technical means, the design estimate of the reliability and the bill of equipment and materials. '
        'Section 17 gives the order in which the modules were implemented, and Section 18 the new terms.')
