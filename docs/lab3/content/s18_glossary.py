"""Section 18: the terms of the structured design that the document uses."""

from __future__ import annotations

TERMS = [
    ('Technical design', 'The stage of development after the preliminary design: the program is described by its modules, algorithms, data, software, technical means, reliability and cost.'),
    ('Stepwise refinement', 'The top-down method of design: a task is split into steps, and each step is refined until it is the algorithm of a module.'),
    ('Structural scheme', 'A scheme that shows the composition of the parts of the software and their interaction by control.'),
    ('Functional scheme', 'A scheme that shows the interaction of the components with the flows of data, their composition, the files and the devices.'),
    ('Module', 'A named sequence of statements that can be called as a whole and can take and give data through parameters or common data areas.'),
    ('Subsystem', 'A group of modules that works as one program; in a structure chart of Constantine it is a rectangle with two vertical lines on each side.'),
    ('Library of modules', 'A set of routines kept together and used by several modules, for example the `Text` helpers; a rectangle with horizontal lines in a chart.'),
    ('Data area', 'A specially formed set of data to which other modules may refer; an ellipse in a chart.'),
    ('Coupling', 'The measure of the dependence between modules; a good design keeps it minimal.'),
    ('Coupling by data', 'Two modules exchange only elementary parameters; the best kind of coupling.'),
    ('Coupling by stamp', 'One module passes to another a composite object (a record, an array, a model), although the other needs only a part of it.'),
    ('Coupling by control', 'One module passes to another a flag or a command that steers its internal logic.'),
    ('Cohesion', 'The measure of the strength of the link between the elements inside one module; levels from the best: functional, sequential, informational, procedural, temporal, logical, coincidental.'),
    ('Structure chart of Constantine', 'A graph of the modules and of the calls between them, with the couplings and the data areas.'),
    ('Structure chart of Jackson', 'A tree of the structure of a program derived from the structure of its input and output data, built of operations, sequences, selections and iterations.'),
    ('Action', 'A class of the server part that performs one use case (`UpdateProgress`, `PublishPost`) and is called by controllers and by other actions.'),
    ('Service', 'A class of the server part that groups a technical capability (`EpubImporter`, `ReadingStatistics`, `OpenLibraryClient`).'),
    ('Form request', 'A class that checks who may send a request and what the request contains, before the controller runs.'),
    ('Policy', 'A class that decides whether a reader may see or change a resource; a foreign resource is answered as “not found”.'),
    ('Resource', 'A class that turns a model into the JSON that the API sends (the output document).'),
    ('Anchor of a quote', 'The place of a quote in the text of a book: the chapter and the start and the end of the fragment in UTF-16 code units.'),
    ('Sanitizing', 'Building a new tree of a chapter from the parsed one, copying only the text and the elements of an allow-list, with no attributes.'),
]


def write(d, ctx) -> None:
    d.h1('18 Glossary of the new terms')
    d.p('The glossary of the preliminary design (forty-two terms) remains valid. The terms that the technical design adds '
        'are in ' + ctx.ref('tab:glossary') + '.')
    ctx.table(d, 'tab:glossary', 'New terms of the technical design')
    d.table(['Term', 'Definition'], [[f'**{t}**', definition] for t, definition in TERMS], [3.0, 8.6], size=22)
