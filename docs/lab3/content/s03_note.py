"""Section 3 (3.1 and 3.2): the explanatory note. Subsection 3.3 is written by s03_quantities."""

from __future__ import annotations


def write(d, ctx) -> None:
    d.h1('3 Explanatory note')

    d.h2('3.1 Purpose and basis of the design')
    d.p('Bookshelf helps a reader to keep a personal library of books, to follow the progress of reading and to keep '
        'quotes. In the second version the reader can also read the own EPUB books in the application, select a '
        'fragment of the text and save it as a quote that remembers its place, publish quotes and reviews, follow '
        'other readers, like and comment their posts, and report abuse. The application is used mostly on a phone, so '
        'the client is a progressive web application (PWA); the server part is a REST API.')
    d.p('The technical design is based on the technical specification of version 2 (forty-eight functions, codes F-1.1 to '
        'F-9.7), on the preliminary design (the roles, the structure, the diagrams, the specifications of nine '
        'processes and the glossary) and on the methodology of laboratory work 3. The application is developed in '
        'seven stages, all of which are implemented: the library, the progress, the quotes, the statistics and the '
        'annual goal, the PWA, the community and the built-in reading. Section 17 gives their order.')
    d.p('The users of the system are the readers; a moderator is a technical role that works with commands of the '
        'server, and an administrator maintains the server. The roles are as in the preliminary design.')

    d.h2('3.2 Main technical decisions')
    d.p('The preliminary design fixed the decisions that affect the whole system (privacy by default, a post as a '
        'snapshot, the sanitized text, the limits). The technical design adds the decisions about the structure of the '
        'program. They are listed in ' + ctx.ref('tab:decisions') + '.')
    ctx.table(d, 'tab:decisions', 'Main decisions of the technical design')
    d.table(
        ['Decision', 'Reason and consequence'],
        [
            ['A layered server part: route, middleware, controller, form request, action or service, policy, model, resource',
             'Every layer has one task, so the modules are weakly coupled and can be tested alone. The controllers are thin: they call a form request, one action and a resource.'],
            ['One class for one use case (an action with the method `handle`)',
             'The logic of a use case, for example `UpdateProgress`, is in one place and is called both by a controller and by another action (the reading position uses the progress update).'],
            ['Access control in policies; a foreign resource is “not found”',
             'The rule “a reader sees only own data” is written once per resource in `app/Policies` and is used in the form requests (`authorize`) and in the controllers (`Gate`), so no route can forget it.'],
            ['Business-rule errors are stable codes',
             'An action throws a validation error whose message is a code, for example `library.invalid_transition`. The API answers 422 and the client translates the code, so the text of the interface is only on the client.'],
            ['Races are closed in the database',
             'A row lock (`lockForUpdate`) in the progress update, the reading position and the reports; a unique index for the pairs that must be unique; one global lock for the import of an EPUB book. The code does not rely on the order of requests.'],
            ['The EPUB file is read by the built-in tools of PHP and is never stored',
             '`ZipArchive` and `DOMDocument` need no new dependencies. The file is checked, read chapter by chapter and turned into the sanitized text; only the text is stored.'],
            ['The statistics are counted in the calendar of the reader',
             'The day of a log record is the day in the time zone of the reader; the days are compared as dates, not as moments.'],
            ['The client keeps the state of each area in its own store; the API client is typed',
             'Pinia stores (library, quotes, auth, social stores) and the modules of `src/api` are the only places that talk to the server; the types of the answers are written in `src/types/api.ts` and are checked by the compiler and the tests.'],
            ['The PWA never caches the data of the reader',
             'The hand-written service worker caches only the shell of the application; the requests to `/api` always go to the network, so a reader never sees the data of another reader from a shared cache.'],
            ['Everything runs in containers, and a merge into the main branch is deployed',
             'The same images are used in development, in the checks and on the server; the pipeline builds the images, runs the migrations and restarts the containers.'],
        ],
        [4.2, 7.4], size=22,
    )
