"""The section "Testing with third-party tools" of the report on laboratory work 4: the analysis of the code by SonarQube
and the output of the independent tools of testing. The screenshots are made by `prepare_thirdparty.py`."""

from __future__ import annotations

SONAR_WIDTH = 4_600_000   # EMU: the screens of the browser are shown narrower than the text, 12.8 cm
TOOL_WIDTH = 4_800_000    # EMU: the photos of the terminal, 13.3 cm


def write(d, ctx) -> None:
    d.h1('Testing with third-party tools')
    d.p('Besides the tests that are a part of the project, the program was checked by tools that did not take part in '
        'its writing. The code was analysed by the platform SonarQube Community Build (version 26.10), which was started '
        'on a local computer of a member of the team in a container, and the tests and the static checks were run '
        'with the independent tools of the language: Pest for PHP, Vitest for TypeScript, Larastan (PHPStan) and Pint. '
        'The reports of coverage of both sets of tests were passed to SonarQube, so that it shows the quality of the code '
        'and the coverage of the code by tests in one place.')
    d.p('The checks were made on the core of the program: the library, the progress, the quotes, the statistics, the '
        'annual goal and the PWA (stages 1-5). SonarQube analysed 107 files and 5,425 lines of code, and Pint checked 107 '
        'PHP files. The community and the reader (stages 6-7) were written later and are not in these numbers; for the '
        'whole program the project checks of ' + ctx.ref('tab:checks') + ' apply. The checks of this section were run in '
        'the local environment of the team member (DDEV), not in the Docker stack of the project.')

    d.h2('Analysis by SonarQube')
    d.p(ctx.ref('fig:sq_summary') + ' is the main page of the project in SonarQube. The quality gate (the set of '
        'conditions that the code must satisfy) is passed. Security, reliability and maintainability have no open issues '
        'and the rating A, the coverage by tests is 96.2% on 2.2 thousand lines to cover, and there are no duplications. '
        + ctx.ref('fig:sq_overview') + ' is the new page “Overview” of the same project, opened at the address '
        'localhost:9002 of the local instance. ' + ctx.ref('tab:sonar') + ' collects the measures that are shown on the '
        'screens of this section.')
    ctx.figure(d, 'fig:sq_summary', 'sonar_summary.png',
               'SonarQube, the summary of the project: quality gate “Passed”, ratings A, coverage 96.2%, duplications 0.0%',
               max_w=SONAR_WIDTH)
    ctx.figure(d, 'fig:sq_overview', 'sonar_overview.png',
               'SonarQube, the page “Overview” of the project on the local instance', max_w=SONAR_WIDTH)

    ctx.table(d, 'tab:sonar', 'Measures of the program in SonarQube')
    d.table(
        ['Measure', 'Value'],
        [
            ['Quality gate', 'Passed (all conditions passed)'],
            ['Security, reliability, maintainability', '0 open issues in each, rating A'],
            ['Security hotspots', '0'],
            ['Coverage by tests', '96.2% on 2.2 thousand lines to cover'],
            ['Duplications', '0.0% on 7.1 thousand lines'],
            ['Lines of code', '5,425: JavaScript (the scripts of Vue components) 2.4k, PHP 1.6k, TypeScript 1.3k, CSS 52'],
            ['Server part (`backend`)', '1,629 lines, coverage 98.9%'],
            ['Client (`frontend/src`)', '3,796 lines, coverage 95.2%'],
            ['Cyclomatic complexity', '746 (the cognitive complexity is 404)'],
        ],
        [5.0, 9.6], align=['left', 'left'],
    )
    d.p(ctx.ref('fig:sq_issues') + ' is the list of the issues: there are no bugs, vulnerabilities or code smells, and '
        'the page says “No Issues. Hooray!”. ' + ctx.ref('fig:sq_code') + ' shows the same numbers by the two parts of '
        'the repository: the server part has 1,629 lines and 98.9% of coverage, the client has 3,796 lines and 95.2%.')
    ctx.figure(d, 'fig:sq_issues', 'sonar_issues.png',
               'SonarQube, the issues: 0 issues of any kind and severity', max_w=SONAR_WIDTH)
    ctx.figure(d, 'fig:sq_code', 'sonar_code.png',
               'SonarQube, the code of the project by parts: lines, issues, coverage, duplications', max_w=SONAR_WIDTH)

    d.p(ctx.ref('fig:sq_coverage') + ' lists the files by coverage, from the lowest. The files with the lowest coverage '
        'are the entry points of the client (`App.vue`, `main.ts`), the model `Author.php` without logic and the thin '
        'wrappers of HTTP calls of the client (`api/auth.ts`, `api/library.ts`, `api/stats.ts`, 16.7%). The tests do not '
        'start the entry points, and the wrappers have almost no logic of their own: the tests of the stores and the '
        'views replace them with fakes, so the lines of the wrappers themselves are not run. The low numbers of these '
        'files are therefore expected. The percentage of coverage shows which lines are run by the tests, not how good '
        'the tests are.')
    ctx.figure(d, 'fig:sq_coverage', 'sonar_coverage.png',
               'SonarQube, the coverage by files, sorted from the lowest', max_w=SONAR_WIDTH)
    d.p(ctx.ref('fig:sq_size') + ' shows the size of the code by language and by file. The biggest files are the '
        'Ukrainian texts of the interface (`i18n/uk.ts`, 280 lines) and the views of the client (`BookView.vue`, '
        '`QuoteFormView.vue`, `AddBookView.vue`, 227-233 lines), so no file is large. ' + ctx.ref('fig:sq_complexity')
        + ' shows the complexity: the highest cyclomatic complexity is 45 in the store of quotes (`stores/quotes.ts`), then '
        'the store of the library (38), the generator of the share card (`utils/quoteCard.ts`, 37) and the client of the '
        'catalog Open Library (`OpenLibraryClient.php`, 34). These are the places where the logic of the program is '
        'concentrated.')
    ctx.figure(d, 'fig:sq_size', 'sonar_size.png',
               'SonarQube, the size of the code: lines by language and the biggest files', max_w=SONAR_WIDTH)
    ctx.figure(d, 'fig:sq_complexity', 'sonar_complexity.png',
               'SonarQube, the cyclomatic complexity by files', max_w=SONAR_WIDTH)
    d.p(ctx.ref('fig:sq_measures') + ' is the page “Measures” of SonarQube with the list of all groups of measures '
        '(security, reliability, maintainability, coverage, duplications, size, complexity, issues) and the chart of '
        'risk, where the project is one green circle (the best rating) at zero technical debt.')
    ctx.figure(d, 'fig:sq_measures', 'sonar_measures.png',
               'SonarQube, the measures of the project and the chart of risk', max_w=SONAR_WIDTH)

    d.h2('What the first analysis found and what was corrected')
    d.p('The first analysis found one vulnerability and two code smells, and the coverage was 91.8%. The notes of the '
        'analysis record the changes that were made after it, ' + ctx.ref('tab:sonar_fix') + '.')
    ctx.table(d, 'tab:sonar_fix', 'The first analysis and the result after the corrections')
    d.table(
        ['Measure', 'First analysis', 'After the corrections'],
        [
            ['Vulnerabilities', '1', '0'],
            ['Code smells', '2', '0'],
            ['Coverage by tests', '91.8%', '96.2%'],
            ['Coverage of the client by lines', '90.2%', '96.4%'],
            ['Tests (server and client)', '607', '625'],
        ],
        [6.0, 4.3, 4.3], align=['left', 'right', 'right'],
    )
    d.bullets([
        'The vulnerability: the key of the cache of the catalog search (`OpenLibraryClient.php`) was made with the hash '
        'sha1, which SonarQube considers weak. The key is only the name of a cache entry, not a secret, and the fix is '
        'to use sha256.',
        'The first code smell: the card for sharing a quote (`QuoteShareView.vue`) is drawn on a canvas, and the role '
        '“img” with the description was moved from the canvas to the wrapper, so that the card is available to screen readers.',
        'The second code smell was about the value `autocomplete="name"` of the field of the name in `RegisterView.vue`. '
        'It is a valid value of the HTML standard and it goes to a real input inside the component of the field, so '
        'the remark was marked as a false positive and not changed.',
        'Tests were added for the registration view, the profile view, the protection of the routes and the reset of '
        'the password, and the coverage grew accordingly.',
    ])

    d.h2('Output of the independent tools of testing')
    d.p(ctx.ref('tab:tools') + ' collects the results of the other tools that were run in the same environment, and the '
        'figures below show their real terminal output. The warning “JIT is incompatible with third party extensions” in '
        'the output of PHP is printed by the environment because an extension of PHP overrides the executor of the '
        'language. It is a warning and not an error, and the results of the tools do not depend on it.')
    ctx.table(d, 'tab:tools', 'Results of the static checks and of the measurement of coverage')
    d.table(
        ['Tool', 'What it checks', 'Result'],
        [
            ['Larastan (PHPStan)', 'Static analysis of the server part (`phpstan analyse`, `phpstan.neon`)', '[OK] No errors'],
            ['Pint', 'Code style of PHP (`pint --test`, Laravel preset)', 'PASS, 107 files'],
            ['Pest', 'Tests of the server part with the measurement of coverage',
             '281 tests, 809 assertions: all passed in 19.62 s; total coverage 99.2%'],
            ['Vitest (provider v8)', 'Coverage by the tests of the client',
             'Statements 95.12% (1289/1355), branches 92.84% (805/867), functions 89.15% (329/369), lines 96.44% (1165/1208)'],
        ],
        [3.4, 5.4, 5.8], align=['left', 'left', 'left'],
    )
    d.p(ctx.ref('fig:phpstan') + ' is the run of Larastan, and ' + ctx.ref('fig:pint') + ' is the run of Pint: both '
        'pass. ' + ctx.ref('fig:pest_run') + ' shows the end of the run of Pest, where 281 tests with 809 assertions '
        'passed in 19.62 seconds, and the table of coverage by classes, where the actions and the enums have 100%. '
        + ctx.ref('fig:pest_cov') + ' is the end of this table: the policies, the providers and the statistics '
        'services have 100%, and three classes have less (`Models/Quote` 85.7%, `Models/ReadingLog` 87.5%, '
        '`OpenLibraryClient` 98.4%), and the total coverage of the server part is 99.2%. ' + ctx.ref('fig:vitest') + ' is the summary of coverage of the client '
        'measured by Vitest.')
    ctx.figure(d, 'fig:phpstan', 'tool_phpstan.png', 'Larastan: static analysis of the server part, no errors',
               max_w=TOOL_WIDTH)
    ctx.figure(d, 'fig:pint', 'tool_pint.png', 'Pint: the code style of 107 files of PHP, PASS', max_w=TOOL_WIDTH)
    ctx.figure(d, 'fig:pest_run', 'tool_pest_run.png',
               'Pest: 281 tests passed, and the coverage by classes (the beginning of the table)', max_w=TOOL_WIDTH)
    ctx.figure(d, 'fig:pest_cov', 'tool_pest_coverage.png',
               'Pest: the end of the table of coverage by classes, total 99.2%', max_w=TOOL_WIDTH)
    ctx.figure(d, 'fig:vitest', 'tool_vitest_coverage.png',
               'Vitest: the summary of coverage of the client (statements, branches, functions, lines)', max_w=TOOL_WIDTH)
