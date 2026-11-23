"""The report on laboratory work 7: the Chidamber and Kemerer metrics of the backend of Bookshelf (variant 19).
All the numbers come from results.json, which `measure.php` writes."""

from __future__ import annotations

import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
CK = ['wmc', 'dit', 'noc', 'cbo', 'rfc', 'lcom']


def data() -> dict:
    r = json.loads((HERE / 'results.json').read_text(encoding='utf-8'))
    r['cl'] = [x for x in r['classes'] if x['kind'] in ('class', 'enum')]
    r['by_name'] = {x['name']: x for x in r['classes']}
    return r


def f(v, digits: int = 1) -> str:
    return f'{v:.{digits}f}'


def top(r: dict, metric: str, k: int = 5) -> str:
    return ', '.join(f'{x["name"]} ({x["value"]})' for x in r['top'][metric][:k])


def write(d, ctx, r: dict) -> None:
    s = r['system']
    cl = r['cl']
    n = r['measured']
    B = r['by_name']
    cat = r['by_category']
    ex = r['exceed']
    zero_props = sum(1 for x in cl if x['properties'] == 0 and x['wmc'] >= 2)
    multi = sum(1 for x in cl if x['wmc'] >= 2)
    low_wmc = sum(1 for x in cl if x['wmc'] <= 5)
    cbo_le5 = sum(1 for x in cl if x['cbo'] <= 5)
    lcom4_1 = sum(1 for x in cl if x['lcom4'] <= 1)
    dit0 = sum(1 for x in cl if x['dit'] == 0)
    dit_app0 = sum(1 for x in cl if x['dit_app'] == 0)
    loc_sum = sum(x['loc'] for x in cl)

    # ------------------------------------------------------------------ objective, tasks
    d.h1('Objective', page_break=True)
    d.p('To estimate the quality of the visual models of the developed software with the best known object-oriented '
        'metrics.')

    d.h1('Tasks for the laboratory work')
    d.numbered([
        'Study the lecture material on the evaluation of the quality of visual models with the known object-oriented '
        'metrics.',
        'Estimate the quality of the own software project with the object-oriented metrics. The variant is 19: the '
        'suite of Chidamber and Kemerer (WMC, DIT, NOC, CBO, RFC, LCOM).',
        'Prepare the report and answer the control questions.',
    ])

    # ------------------------------------------------------------------ theory
    d.h1('Theoretical background')
    d.h2('Characteristics of object-oriented systems')
    d.p('Metrics of object-oriented software must take into account five characteristics that the classical metrics '
        'of functions do not see. Localization: the information is grouped inside classes, so a metric must work with a '
        'class as a whole. Encapsulation: data and operations are packed together, so the focus moves from one module to '
        'a group of properties and operations (for example, the number of operations in a class). Information hiding: '
        'a good class shows to the others only what is needed. Inheritance: a class gets the properties and operations '
        'of its parents, so the depth and the width of the hierarchy matter. Abstraction: a class is a view of a '
        'concept on some level of detail, so the metrics speak in the terms of classes.')

    d.h2('The suite of Chidamber and Kemerer')
    d.p('In 1994 S. Chidamber and C. Kemerer proposed six metrics for classes. It is the most cited suite in industry and '
        'research, and it is the suite of variant 19. ' + ctx.ref('tab:ck') + ' gives them. Here C is the class, '
        'M1 ... Mn are its methods, and Ii is the set of the instance variables (properties) that the method Mi uses.')
    ctx.table(d, 'tab:ck', 'The metrics of Chidamber and Kemerer')
    d.table(
        ['Metric', 'Definition', 'What it shows'],
        [['WMC, weighted methods per class', 'WMC = C1 + C2 + ... + Cn, where Ci is the complexity of the method Mi. With Ci = 1 it '
          'is the number of methods.', 'The effort to write and test the class. A big value means a class with too many duties '
          'that is hard to reuse.'],
         ['DIT, depth of the inheritance tree', 'The length of the longest path from the class to the root of the hierarchy.',
          'A deep class inherits much and its behaviour is hard to predict. A high DIT also means much reuse.'],
         ['NOC, number of children', 'The number of the direct subclasses of the class.',
          'More reuse, but a weaker abstraction of the parent and more tests: a change of the parent touches every child.'],
         ['CBO, coupling between object classes', 'The number of the other classes to which the class is connected: its methods '
          'use methods or variables of them.', 'The static part of the external links. A high CBO makes a change and its '
          'testing harder and the reuse smaller.'],
         ['RFC, response for a class', 'RFC = |RS|, RS = {M} ∪ (the methods called by the methods of C). In words: own '
          'methods plus the methods of other classes that they call.', 'The dynamic part of the external links: how many '
          'methods may run in answer to one message. A big RFC means a harder test.'],
         ['LCOM, lack of cohesion in methods', 'P = pairs of methods without common properties, Q = pairs with a common '
          'property. LCOM = P - Q if P > Q, otherwise 0.', 'How badly the methods are tied through the data. A high '
          'value suggests that the class should be split into two or more classes.']],
        [3.4, 6.2, 5.2], align=['left', 'left', 'left'], size=19,
    )
    d.p('The methodology says that WMC can be counted in several ways: the methods of the class only, or with all the '
        'inherited methods, or something in between, and that any option is acceptable if it is kept the same for the whole '
        'project. This work counts the methods declared in the class itself and gives the weight 1 to each method. The '
        'cyclomatic complexity as the weight is given as a second variant, WMC_cc.')

    d.h2('How the values are read')
    d.p('The metrics of Chidamber and Kemerer have no official limits. The values in ' + ctx.ref('tab:limits') + ' are '
        'the rules of thumb that are often cited; the sources differ (for example, for CBO from 5 to 14), so the numbers are '
        'only a reading aid and a reason to look at a class, not a verdict. The advice of Booch from the methodology is a '
        'hierarchy not higher than 7 ± 2 levels and not wider than 7 + 2 branches.')
    ctx.table(d, 'tab:limits', 'Rules of thumb used to read the values')
    d.table(
        ['Metric', 'Looks fine', 'Worth a look', 'Basis'],
        [['WMC', 'up to 20', 'above 20', 'a class with more than about 20 methods has too many duties'],
         ['DIT', 'up to 5', 'above 5', 'Booch: the hierarchy of 7 ± 2 levels at most'],
         ['NOC', 'up to 9', 'above 9', 'Booch: not wider than 7 + 2 branches'],
         ['CBO', 'up to 9', 'above 9', 'the usual range of the sources is 5 to 14'],
         ['RFC', 'up to 50', 'above 50', 'a big response set makes the test long'],
         ['LCOM', 'small', 'large', 'no common limit; 0 is the best. LCOM4 above 1 means that the class falls into groups']],
        [1.6, 2.4, 2.8, 8.0], align=['left', 'left', 'left', 'left'], size=19,
    )

    # ------------------------------------------------------------------ project
    d.h1('The software project')
    d.p('The project is Bookshelf, the reading tracker of laboratory works 1 to 6: a reader keeps a library, updates the '
        'progress, saves quotes, sees the statistics, reads EPUB books and shares quotes with other readers. The server is '
        'PHP 8.4 with Laravel 12 (the folder backend); the client is a PWA in TypeScript with Vue (the folder frontend). '
        'The object-oriented model of the project is its backend: the classes of the server hold the data model, the '
        'rules and the API.')
    d.p(f'The measured part is the folder backend/app. It has {r["files"]} PHP files; each file declares one type: '
        f'{r["kinds"]["class"]} classes ({r["abstract"]} of them abstract, {r["final"]} final), {r["kinds"]["enum"]} '
        f'enumerations and {r["traits"]} traits. The enumerations are measured as small classes. The traits are listed in '
        'Appendix A but are not counted in the statistics, because a trait is not a class: its methods work in the '
        f'classes that use it. So {n} classes are measured, {loc_sum} lines of code in all. ' + ctx.ref('tab:struct') +
        ' shows how they are grouped. The groups follow the folders and the roles of the classes in Laravel.')
    ctx.table(d, 'tab:struct', 'The classes of the backend by group')
    desc = {'Actions': 'one business operation per class (add a book, save a quote, follow a reader)',
            'Controllers': 'HTTP entry points of the API', 'Services': 'EPUB reader, statistics, catalogue clients',
            'Requests': 'validation of the input', 'Models': 'Eloquent models of the tables',
            'Resources': 'JSON form of the answers', 'Enums': 'fixed lists of values', 'Policies': 'access rules',
            'Console': 'artisan commands', 'Exceptions': 'domain errors', 'Providers': 'start-up of the services',
            'Middleware': 'HTTP filter', 'Responses': 'response class of the login', 'Rules': 'own validation rule',
            'Support': 'a small helper'}
    rows = []
    for name, v in sorted(cat.items(), key=lambda kv: (-kv[1]['n'], kv[0])):
        rows.append([name, str(v['n']), str(v['loc']['sum']), desc.get(name, '')])
    rows.append(['Total', str(n), str(loc_sum), ''])
    d.table(['Group', 'Classes', 'Lines', 'What it holds'], rows, [2.6, 1.6, 1.6, 9.0], align=['left', 'right', 'right', 'left'],
            size=19, shade={len(rows) - 1})
    d.p('Not measured: the migrations, seeders and factories (they are scripts, not a model of the domain), the tests, the '
        'configuration and the routes, and the code of the libraries in backend/vendor. The libraries are used only to '
        'know the parents of the framework classes. The client is about 70 Vue components and 160 TypeScript files; '
        'a component of Vue is not a class and the TypeScript code is mostly functions and composables, so the '
        'metrics of Chidamber and Kemerer do not fit it, and it is outside this work.')

    # ------------------------------------------------------------------ method
    d.h1('The measurement method')
    d.p('The measurement is made by the script docs/lab7/measure.php. It reads every file of backend/app with the parser '
        'PHP-Parser (the version that is in the project), builds the syntax tree, resolves the names of the classes and '
        'computes the six metrics for every class. The parents of the framework classes (Model, FormRequest and others) '
        'are found with the reflection of PHP, because their sources are in vendor. The script writes results.json, and '
        'all the tables and the figures of this report are made from this file. The script does not run the project and '
        'does not use the network. ' + ctx.ref('tab:rules') + ' gives the counting rules; they are fixed and the same for '
        'every class.')
    ctx.table(d, 'tab:rules', 'The counting rules')
    d.table(
        ['Metric', 'Rule'],
        [['WMC', 'The number of the methods declared in the body of the class, weight 1 each (constructors, static, private and '
          'abstract methods are included). Inherited methods and the methods of used traits are not counted. WMC_cc: the same, '
          'but the weight is the cyclomatic complexity of the method, 1 + the number of if, elseif, loops, case, catch, '
          '?:, ??, &&, ||, and, or and match arms.'],
         ['DIT', 'The length of the path of "extends" to the root, through the framework classes too. DIT_app counts only '
          'the ancestors declared in backend/app. Interfaces and traits are not parents.'],
         ['NOC', 'The number of the classes of backend/app that extend the class directly.'],
         ['CBO', 'The number of the distinct other classes that the class mentions: parents, interfaces, used traits, the '
          'types of the properties, parameters and results, new, static calls and constants, instanceof, catch, attributes and '
          'X::class. The class itself, self, static, parent and the scalar types are not counted. CBO_app counts only the '
          'classes of backend/app.'],
         ['RFC', 'Own methods plus the distinct methods of other classes that are called. A call is recognised as '
          'class::method when the class of the receiver is known ($this, typed properties and parameters, new, static calls); '
          'otherwise it is recognised by the name of the method.'],
         ['LCOM', 'LCOM = max(P - Q, 0) for the methods with a body, without the constructor. A property is $this->name '
          'or self::$name. LCOM4 is the number of the groups of methods connected through a common property or a call '
          '$this->method().']],
        [1.6, 13.2], align=['left', 'left'], size=19,
    )
    d.p('The count was checked by hand on the class PostPolicy (the file backend/app/Policies/PostPolicy.php). It has the '
        'methods view, update, delete, canSee and isAuthor, so WMC = 5. It mentions Post, User and Response, so CBO = 3. '
        'It calls Response::allow, Response::denyAsNotFound, User::isBlockedWith and Post::isHidden, so RFC = 5 + 4 = 9. '
        'It has no properties, so no two methods share one: of the 10 pairs all are unconnected, P = 10, Q = 0, '
        'LCOM = 10. The methods view and canSee call each other and so do update, delete and isAuthor, so LCOM4 = 2. '
        'The class does not extend anything, so DIT = 0, and nobody extends it, NOC = 0. The script gives exactly the '
        'same numbers. In the same way the 23 methods of HtmlSanitizer were checked against the number of the "function" '
        'declarations of the file, and the three static methods of EpubPaths against its LCOM of 3.')

    # ------------------------------------------------------------------ results
    d.h1('Results of the measurement')
    d.p('The results for the whole system are in ' + ctx.ref('tab:system') + '. The "Class" column names the class with '
        'the largest value. The values for each of the classes are in Appendix A.')
    ctx.table(d, 'tab:system', f'The metrics of the {n} classes of the backend')
    names = {'wmc': 'WMC (methods)', 'wmc_cc': 'WMC_cc (cyclomatic)', 'dit': 'DIT', 'dit_app': 'DIT_app', 'noc': 'NOC',
             'cbo': 'CBO', 'cbo_app': 'CBO_app', 'rfc': 'RFC', 'lcom': 'LCOM', 'lcom4': 'LCOM4'}
    rows = []
    for m in ['wmc', 'wmc_cc', 'dit', 'dit_app', 'noc', 'cbo', 'cbo_app', 'rfc', 'lcom', 'lcom4']:
        v = s[m]
        who = r['top'][m][0]['name'] if m in r['top'] else '-'
        rows.append([names[m], f(v['mean'], 2), f(v['median'], 0) if v['median'] == int(v['median']) else f(v['median'], 1),
                     str(v['p90']), str(v['max']), who])
    d.table(['Metric', 'Mean', 'Median', '90th percentile', 'Maximum', 'Class'], rows, [3.6, 1.6, 1.6, 2.4, 2.0, 4.0],
            align=['left', 'right', 'right', 'right', 'right', 'left'], size=20)
    d.p(f'The classes are small: the mean WMC is {f(s["wmc"]["mean"], 2)}, the median is {s["wmc"]["median"]}, and '
        f'{low_wmc} classes of {n} have 5 methods or fewer. The mean CBO is {f(s["cbo"]["mean"], 2)} '
        f'({f(s["cbo_app"]["mean"], 2)} if only the classes of the project are counted), and the mean RFC is '
        f'{f(s["rfc"]["mean"], 1)}. The distributions in ' + ctx.ref('fig:hist') + ' have a long tail to the right: '
        'a few classes are many times larger than the typical one.')
    ctx.figure(d, 'fig:hist', 'histograms.png', 'The distribution of WMC, CBO, RFC and LCOM4', max_w=5600000)

    d.h2('The metrics by group of classes')
    d.p(ctx.ref('tab:cat') + ' gives the mean and the largest values for each group (DIT and NOC are described '
        'below). ' + ctx.ref('fig:cat') + ' shows the means.')
    ctx.table(d, 'tab:cat', 'The metrics by group of classes (mean / maximum)')
    rows = []
    for name, v in sorted(cat.items(), key=lambda kv: (-kv[1]['n'], kv[0])):
        rows.append([name, str(v['n']), f'{f(v["wmc"]["mean"])} / {v["wmc"]["max"]}', f'{f(v["cbo"]["mean"])} / {v["cbo"]["max"]}',
                     f'{f(v["rfc"]["mean"])} / {v["rfc"]["max"]}', f'{f(v["lcom"]["mean"])} / {v["lcom"]["max"]}',
                     f'{f(v["lcom4"]["mean"])} / {v["lcom4"]["max"]}', f(v['dit']['mean'], 0) if v['dit']['mean'] == int(v['dit']['mean']) else f(v['dit']['mean'], 1)])
    d.table(['Group', 'Classes', 'WMC', 'CBO', 'RFC', 'LCOM', 'LCOM4', 'DIT'], rows, [2.4, 1.5, 1.9, 1.9, 1.9, 2.1, 1.9, 1.2],
            align=['left'] + ['right'] * 7, size=18)
    ctx.figure(d, 'fig:cat', 'categories.png', 'The mean WMC, CBO and RFC by group of classes (the number of classes in brackets)',
               max_w=5600000)

    d.h2('The worst classes by each metric')
    ctx.table(d, 'tab:top', 'The five largest values of each metric')
    d.table(['Metric', 'Classes with the largest values'],
            [['WMC', top(r, 'wmc')], ['WMC_cc', top(r, 'wmc_cc')], ['CBO', top(r, 'cbo')], ['CBO_app', top(r, 'cbo_app')],
             ['RFC', top(r, 'rfc')], ['LCOM', top(r, 'lcom')], ['LCOM4', top(r, 'lcom4')]],
            [2.0, 12.8], align=['left', 'left'], size=20)
    d.p('The classes in ' + ctx.ref('tab:limits') + ' that go beyond the rules of thumb are given in ' + ctx.ref('tab:exceed') + '.')
    ctx.table(d, 'tab:exceed', 'The classes that exceed the rules of thumb')
    rows = []
    for m, label in [('wmc', 'WMC above 20'), ('dit', 'DIT above 5'), ('noc', 'NOC above 9'), ('cbo', 'CBO above 9'),
                     ('rfc', 'RFC above 50'), ('lcom4', 'LCOM4 above 1')]:
        e = ex[m]
        names_list = ', '.join(e['names'][:8]) + (f', and {e["count"] - 8} more' if e['count'] > 8 else '') if e['names'] else 'none'
        rows.append([label, str(e['count']), names_list])
    d.table(['Rule', 'Classes', 'Which (the value in brackets)'], rows, [2.8, 1.6, 10.4], align=['left', 'right', 'left'], size=19)

    d.h2('Inheritance')
    d.p(f'The inheritance is shallow and almost all of it comes from the framework. {ctx.ref("fig:inh")} shows the chains. '
        f'Only one hierarchy is declared in the project itself: the abstract class Controller with {B["Controller"]["noc"]} '
        f'children. The other families extend the classes of Laravel and PHP: the {cat["Requests"]["n"]} requests extend '
        f'FormRequest (three levels in all, with Request of Laravel and Request of Symfony), the {cat["Models"]["n"]} models '
        f'extend Model, the {cat["Resources"]["n"]} resources extend JsonResource. The mean DIT is {f(s["dit"]["mean"], 2)} and '
        f'the maximum is {s["dit"]["max"]} (the requests); counted inside the project only (DIT_app) the mean is '
        f'{f(s["dit_app"]["mean"], 2)} and the maximum is 1. The classes with DIT = 0 are {dit0}: the actions, the policies, '
        'the services and the enumerations. Only one class (Controller) has children in the project, so NOC is 0 for '
        f'all the others, and the NOC of Controller is {B["Controller"]["noc"]}.')
    ctx.figure(d, 'fig:inh', 'inheritance.png', 'The chains of inheritance of the classes (blue: declared in the project, grey: framework or PHP)',
               max_w=5700000)

    # ------------------------------------------------------------------ analysis
    d.h1('Analysis of the results')
    d.h2('WMC: weighted methods per class')
    d.p(f'The system is made of small classes: {low_wmc} of {n} have no more than 5 methods, and the mean is '
        f'{f(s["wmc"]["mean"], 2)}. This follows from the style of the project: one action does one operation, one '
        'request validates one input, one resource converts one model. Only HtmlSanitizer (23 methods) is above the limit of '
        '20; then come ExportUserData (16), User (14), ReadingStatistics (13), ImportGoodreadsLibrary and PackageReader (12 '
        'each). The count of the methods and the cyclomatic weight give different pictures. ExportUserData has 16 '
        f'methods but WMC_cc of only {B["ExportUserData"]["wmc_cc"]}: it is a flat list of small exporters. The model '
        f'User has 14 methods and WMC_cc of 14, so every method is trivial: these are relations of the database. On the '
        f'other hand HtmlSanitizer has WMC_cc of {B["HtmlSanitizer"]["wmc_cc"]} (the sum for the whole class is 131, the '
        f'most complex method is {B["HtmlSanitizer"]["max_cc"]}), PackageReader {B["PackageReader"]["wmc_cc"]} and '
        f'ImportGoodreadsLibrary {B["ImportGoodreadsLibrary"]["wmc_cc"]}. The real complexity of the system is in the '
        'EPUB reader and in the import of Goodreads, that is, in the code that reads and repairs foreign data.')
    d.h2('DIT and NOC: inheritance')
    d.p('The values of DIT and NOC are low, so the inheritance is not a problem here, and nothing is near the limit of '
        '7 ± 2. The price of the reuse is small: nothing but the framework base classes is inherited, and the project '
        'does not have its own chains of subclasses. The DIT of 3 of the requests is not a design decision of the project; it '
        'is the depth of the Laravel classes. The abstract Controller is empty (4 lines) and has 21 children, '
        'which is above the limit of 9, but this is a base class without behaviour, and a change of it cannot break its '
        'children. The project prefers the composition (the actions are called by the controllers) to the inheritance, which is the '
        'modern style of Laravel; the metric shows the low risk of the fragile base class and the low reuse by '
        'inheritance in the same time.')
    d.h2('CBO and RFC: coupling')
    d.p(f'The mean CBO is {f(s["cbo"]["mean"], 2)}, and {cbo_le5} of {n} classes are at or below 5. {ex["cbo"]["count"]} '
        f'classes are above 9. They are three kinds. The controllers (the mean CBO is {f(cat["Controllers"]["cbo"]["mean"])}, '
        f'QuoteController has {B["QuoteController"]["cbo"]}, UserBookController {B["UserBookController"]["cbo"]}): a '
        'controller is the place where requests, actions, models and resources meet, and it is coupled to all of them. '
        f'The models (the mean is {f(cat["Models"]["cbo"]["mean"])}, User has {B["User"]["cbo"]}, Post {B["Post"]["cbo"]}, '
        f'Quote {B["Quote"]["cbo"]}): the relations of Eloquent mention the related models and the types of the relations. '
        f'The service providers (FortifyServiceProvider has {B["FortifyServiceProvider"]["cbo"]}): they wire many classes '
        'by definition. A part of the CBO is the framework: if only the classes of the project are counted, the mean is '
        f'{f(s["cbo_app"]["mean"], 2)} and the maximum is {s["cbo_app"]["max"]} (QuoteController). Among the actions, '
        'AddBookToLibrary and ImportBookText have 13: they work with several models and services at once, which is natural '
        'for an operation that crosses the library, the catalogue and the texts.')
    d.p(f'RFC has the mean {f(s["rfc"]["mean"], 1)} and the maximum {s["rfc"]["max"]} (ReadingStatistics), so only '
        'ReadingStatistics (52) is above the limit of 50: it calls many methods of the query '
        f'builder and of the date classes. The controllers (the mean {f(cat["Controllers"]["rfc"]["mean"])}) and the actions '
        f'({f(cat["Actions"]["rfc"]["mean"])}) have the highest averages, which agrees with CBO: they are the classes '
        'that coordinate other classes. The tests of such classes need the most mocks and the most set-up. The requests, '
        'resources and policies have RFC of 4 to 6, and they are easy to test.')
    d.h2('LCOM: cohesion')
    d.p(f'LCOM has the median {s["lcom"]["median"]} but the mean {f(s["lcom"]["mean"], 2)}, because of a few big values: '
        'HtmlSanitizer (199), ExportUserData (120), User (91). The big values must be read carefully. The metric looks '
        f'for the common properties, and in this project {zero_props} of the {multi} classes with two or more methods do '
        'not declare a property at all: the actions, the policies and the controllers are stateless, and their '
        'dependencies come in the parameters of the methods. Every pair of such methods is "unconnected", although '
        'the methods can be quite near each other. So the high LCOM of ExportUserData (16 methods, all private helpers that '
        'take the user as a parameter) means "a set of independent exporters in one class" and is not a sign of a '
        'tangled class. The models have a second reason: the relations of Eloquent do not use $this->property, they build '
        'queries, so for User the metric shows 12 groups (LCOM4), and it is a feature of the framework and not '
        'a defect.')
    d.p(f'LCOM4 is more useful here: {lcom4_1} of {n} classes have one group of methods or none, that is, the methods '
        'are connected through the data or through the calls. HtmlSanitizer has LCOM of 199 but LCOM4 of 1, which is the '
        'sign of a class that holds one state (the tree under construction) and has 23 methods that all work on it: '
        'it is a big class of one duty, and the problem is its size, not its cohesion. The classes with LCOM4 of 5 and more '
        '(User 12, Quote 8, UserBook 8, Post 7, BookText 6, QuoteController 6, UserBookController 5, Book 5, Text 5) '
        'are models and controllers, which are the groups of unrelated operations by their nature.')

    # ------------------------------------------------------------------ conclusions
    d.h1('Conclusions and recommendations')
    d.p(f'In this work the metrics of Chidamber and Kemerer were measured for the {n} classes of the backend of Bookshelf '
        f'with a script that parses the source code. The design is healthy in the main: the classes are small (the mean WMC '
        f'is {f(s["wmc"]["mean"], 1)}), the inheritance is shallow (the mean DIT is {f(s["dit"]["mean"], 1)}, and the project '
        f'declares only one hierarchy), the typical class is coupled to {s["cbo"]["median"]} others and answers with '
        f'{s["rfc"]["median"]} methods. This is the result of the chosen structure: one action per operation, thin '
        'requests and resources, and a composition instead of the inheritance. The exceptions are few and they are the same '
        'classes in several metrics. ' + ctx.ref('tab:advice') + ' gives the recommendations.')
    ctx.table(d, 'tab:advice', 'Recommendations for the improvement of the software')
    d.table(
        ['Class', 'Metrics', 'Problem', 'Recommendation'],
        [['HtmlSanitizer', f'WMC {B["HtmlSanitizer"]["wmc"]}, WMC_cc {B["HtmlSanitizer"]["wmc_cc"]}, {B["HtmlSanitizer"]["loc"]} lines',
          'The biggest and most complex class: one class does the copying of the tree, the repair of tables and the writing '
          'of the HTML.', 'Split by the stages: the repair of the white space and of the tables (trimPre, wrapInRows, '
          'wrapInCells, flattenCells) and the writer of the HTML (write, textOf, firstHeading) can be classes of their '
          'own that work on ElementNode. The class has its own unit tests (HtmlSanitizerTest and the corpus tests), which protect the change.'],
         ['QuoteController, UserBookController', f'CBO {B["QuoteController"]["cbo"]} and {B["UserBookController"]["cbo"]}, '
          f'RFC {B["QuoteController"]["rfc"]} and {B["UserBookController"]["rfc"]}, LCOM4 {B["QuoteController"]["lcom4"]} and '
          f'{B["UserBookController"]["lcom4"]}', 'Controllers that do several unrelated things (a list, an export, the quote '
          'of the day, the changes).', 'Move the export and the quote of the day to controllers of their own '
          '(for example, a single-action controller for each); every class then has a smaller set of the coupled classes.'],
         ['User', f'CBO {B["User"]["cbo"]}, WMC {B["User"]["wmc"]}, LCOM4 {B["User"]["lcom4"]}',
          'The central model has the relations of every feature: library, quotes, posts, follows, blocks.',
          'Keep the relations (they are the framework style), but move the logic of blocking '
          '(isBlockedWith, scopeNotBlockedWith, excludeBlocked) to a trait or an own class, as it is done for '
          'VisibleToReaders.'],
         ['ReadingStatistics', f'RFC {B["ReadingStatistics"]["rfc"]}, WMC {B["ReadingStatistics"]["wmc"]}',
          'The only class above RFC 50: streak, pace, forecasts and counts in one class.',
          'Separate the forecasts and the streak into small classes (the project already has GoalProgress and YearRecap '
          'next to it).'],
         ['ImportGoodreadsLibrary, OpenLibraryClient, PackageReader',
          f'WMC_cc {B["ImportGoodreadsLibrary"]["wmc_cc"]}, {B["OpenLibraryClient"]["wmc_cc"]}, {B["PackageReader"]["wmc_cc"]}; '
          f'one method with a complexity of {B["ImportGoodreadsLibrary"]["max_cc"]}',
          'The code that converts foreign data has many branches in single methods.',
          'Cut the long methods (the field mapping of the import, toEntry of the catalogue) into small ones and test the '
          'branches with a table of examples.'],
         ['ExportUserData', f'WMC {B["ExportUserData"]["wmc"]}, LCOM {B["ExportUserData"]["lcom"]}',
          'Sixteen private exporters in one class; LCOM is high only because the class has no state.',
          'No change is needed for the quality; if the class grows, one small class for each section of the export.'],
         ['FortifyServiceProvider', f'CBO {B["FortifyServiceProvider"]["cbo"]}', 'High CBO by the nature of a provider.',
          'Leave as is: wiring many classes is the duty of a provider.']],
        [2.6, 3.0, 4.2, 5.4], align=['left', 'left', 'left', 'left'], size=17,
    )
    d.p('Three general recommendations follow. First, keep the habit that makes the numbers low: small classes with one '
        'duty, and add a check of the metrics (for example, a limit for the cyclomatic complexity of a method and for the '
        'number of the methods of a class in the tool of static analysis that the project already uses) so that a '
        'growth is seen at once. Second, the metrics of Chidamber and Kemerer must be read with the knowledge of the framework: '
        'DIT, NOC, LCOM and a part of CBO of the models, the requests and the controllers are the result of the way '
        'Laravel works. Third, the metrics measure the structure and not the correctness; they show where to look first, '
        'and the final decision about a split must be made by reading the class. The limit of this work is the use '
        'of the static analysis only: the calls through the variables of an unknown type are recognised by the name of '
        'the method, so RFC can be a little different from the real number of the called methods.')


QUESTIONS = [
    ('Which factors of object-oriented systems influence the metrics for their evaluation, and how does this influence appear?',
     ['Five factors: localization, encapsulation, information hiding, inheritance and abstraction. Localization means that the '
      'information is grouped in classes, so the metrics are applied to a class as a whole and must handle the relations '
      '"one to many" and "many to many" between operations and classes. Encapsulation moves the measurement from one module to '
      'a group of properties and operations (for example, the number of operations in a class). Information hiding gives '
      'metrics that show how much of a class is hidden. Inheritance gives the metrics of the depth, the width and the '
      'number of children of a class hierarchy. Abstraction gives the metrics in the terms of the class (the number of '
      'instances, the share of the generic classes).']),
    ('What influence does inheritance have on the cohesion of classes?',
     ['Inheritance lowers the cohesion of the class that inherits. The functions come from the superclasses, so the class '
      'cannot be seen as a separate module: all its superclasses must be taken into account. A class that inherits from '
      'several parents is much harder to understand. In Bookshelf this shows in the models and the requests, whose '
      'behaviour is partly in Model and FormRequest of Laravel.']),
    ('Describe the metrics of data cohesion of classes.',
     ['They are the metrics of Ott and Mehra, based on slices of data. For every output of a method a slice is built: the '
      'sequence of the data tokens (variables and constants) needed to compute it. The tokens that belong to more than one '
      'slice are glued tokens, and those that belong to all slices are strongly glued tokens. Strong data cohesion is the share '
      'of the strongly glued tokens among all the tokens of the class, weak data cohesion is the share of the glued tokens, '
      'and data adhesiveness is the sum of the numbers of slices for each glued token divided by the product of the number of '
      'tokens and the number of slices. A class without glued tokens has zero cohesion by the data.']),
    ('Describe the metrics of cohesion of classes by methods.',
     ['They are the metrics of Bieman and Kang. Two methods are directly connected if they use a common instance variable, '
      'and indirectly if they are connected through other methods. Constructors and destructors are not counted. TCC (tight '
      'class cohesion) is the share of the directly connected pairs of methods among all the possible pairs, and LCC (loose '
      'class cohesion) is the share of the pairs that are connected directly or indirectly. Always TCC <= LCC. In this work '
      'LCOM and LCOM4 are used in the same spirit.']),
    ('Which characteristics of object-oriented systems make the coupling of classes worse?',
     ['Inheritance: a class is coupled to all its superclasses, and a change of a superclass spreads to the subclasses. Also '
      'the number of the kinds of dependencies grows: inheritance, delegation, implementation of interfaces, plus more '
      'kinds of modules (classes, free functions, packages). The object-oriented mechanisms do not guarantee a low '
      'coupling by themselves. In Bookshelf the coupling is highest in the controllers and the models, which meet many other '
      'classes.']),
    ('Explain how to determine the coupling of classes with the metric "change dependency between classes".',
     ['CDBC (Hitz and Montazeri) shows how many methods of a client class CC may need a change after a change of a server '
      'class SC. It depends on the kind of the relation: if SC is the type of an instance variable of CC, or its superclass, or '
      'is used through a global variable, all n methods of CC are affected; if SC is the type of a parameter or of a local '
      'variable in j methods, then j methods are affected; if SC is not used, the value is 0. The access through a stable interface '
      'is multiplied by (1 - k), where k is the stability factor of the interface. The metric is lowered by limiting the access '
      'to the interface of the server and its visibility.']),
    ('Explain the meaning of the metric of locality of data.',
     ['Locality of data is the share of the data that is local to the class among all the data that its methods use. Local data '
      'are the non-public instance variables, the protected variables of the parents and the static variables of the class. A '
      'high value means that the class is self-sufficient, which gives a better reuse and testability. It is a measure of '
      'the quality of the abstraction that the class implements.']),
    ('Which metrics make up the suite of Chidamber and Kemerer? Which tasks do they solve?',
     ['Six metrics: WMC (weighted methods per class), DIT (depth of the inheritance tree), NOC (number of children), CBO '
      '(coupling between object classes), RFC (response for a class) and LCOM (lack of cohesion in methods). They '
      'evaluate the quality of the design of classes: the size and the complexity (WMC), the inheritance (DIT, NOC), the '
      'coupling (CBO, RFC) and the cohesion (LCOM). They help to find the classes that are hard to write, test and change.']),
    ('How can the number of methods in a class be counted?',
     ['There are several accepted ways. Count only the methods declared in the class (the inherited ones are counted '
      'in their own classes); or count the declared and all the inherited methods (it stresses the state space of the class); '
      'or something in between, for example the declared methods and the methods inherited directly from the parents. Any '
      'way is acceptable if it is the same for the whole project. In this work the methods declared in the class body are '
      'counted, the weight is 1 for each, and the cyclomatic complexity is a second variant.']),
    ('Which metrics of Chidamber and Kemerer evaluate the coupling of classes? Explain their meaning.',
     ['CBO and RFC. CBO is the number of the other classes to which the class is connected (its methods use their methods or '
      'variables); it is the static part of the external links. RFC is the number of the methods of the response set: the own '
      'methods plus the methods of other classes that they call; it is the dynamic part, the potential interaction with '
      'other classes. Large values make the testing and the change harder. DIT and NOC also express the coupling through '
      'the inheritance.']),
    ('Which metric of Chidamber and Kemerer evaluates the cohesion of a class? Explain its meaning.',
     ['LCOM. Every method uses some instance variables. P is the number of pairs of methods without a common variable, Q is the '
      'number of pairs with a common variable, and LCOM = P - Q (not below zero). If all the methods use the same variables, '
      'LCOM is 0. A high value means that the methods are weakly connected, and the class should probably be split into two '
      'or more classes.']),
    ('How can the independence of the metric WMC from the implementation be achieved?',
     ['The number of the methods depends on the way a duty is written: one complex operation with many parameters or '
      'several simple ones. To remove this, Lorenz and Kidd use the normalized size by the number of the arguments: the '
      'metric NMA (the number of methods multiplied by the average number of arguments) is the same for both versions. '
      'The average number of arguments of a method (NPAVG) is also given. A weight by the complexity of the methods '
      '(WMC_cc) reduces the dependence too.']),
    ('How can the information hiding of a class be evaluated?',
     ['The suite of Chidamber and Kemerer has no such metric, so the behavioural information hiding is proposed: the '
      'share of the operations that are visible from outside (the external weighted operations) among all the operations '
      'of the class. The nearer it is to 1, the more the class shows to the others; the smaller, the more is hidden. The '
      'factors MHF and AHF of Abreu (the share of the hidden methods and of the hidden attributes) measure it for the system.']),
    ('Compare the suites of Chidamber-Kemerer and Lorenz-Kidd. What is similar and what is different?',
     ['Both are class oriented and both use the size of a class, the inheritance and the complexity. Lorenz and Kidd take '
      'WMC, DIT and NOC from Chidamber and Kemerer in a different form. The suite of Chidamber and Kemerer is academic, '
      'six metrics for the design of classes, with the coupling and the cohesion (CBO, RFC, LCOM). The suite of Lorenz and '
      'Kidd is practical and industrial: the size of a class, the operations overridden and added by a subclass, the index '
      'of specialization, the size and the complexity of an operation, the number of parameters, and metrics for the '
      'planning of a project (scenarios, key classes, subsystems), with recommended values. It has no direct '
      'coupling and cohesion metrics.']),
    ('For which goals is the suite of metrics of Fernando Abreu oriented?',
     ['The suite MOOD (1994) aims to cover the basic mechanisms of the object paradigm (encapsulation, inheritance, '
      'polymorphism, message passing), to be formally defined (no subjective measuring), to be independent of the size of '
      'the product and independent of the programming language.']),
    ('Describe the composition of the suite of metrics of Fernando Abreu.',
     ['Six factors in the range from 0 to 1: MHF (method hiding factor) and AHF (attribute hiding factor) for the '
      'encapsulation; MIF (method inheritance factor) and AIF (attribute inheritance factor) for the inheritance; POF '
      '(polymorphism factor); COF (coupling factor) for the message passing, the share of the pairs of classes in the '
      'client-supplier relation among all the possible pairs.']),
    ('Compare the suites of Chidamber-Kemerer and Fernando Abreu. What is similar and what is different?',
     ['Both are academic and formally defined, and both cover the inheritance and the coupling. The suite of Chidamber and '
      'Kemerer measures each class (six values per class) and has the cohesion (LCOM) and the response set; the suite of Abreu '
      'measures the whole system (six percentages), is independent of the size and the language, and adds the '
      'hiding and the polymorphism, which the first one does not have.']),
    ('Compare the suites of Lorenz-Kidd and Fernando Abreu. What is similar and what is different?',
     ['Both describe the inheritance and the size of the system. The suite of Lorenz and Kidd is practical: absolute values '
      'for classes and operations, with recommended limits and the metrics for planning. The suite of Abreu is theoretical: '
      'relative factors for the whole system with the formal definitions, and it has the hiding, the polymorphism and '
      'the coupling factor, which Lorenz and Kidd do not have in this form.']),
    ('Characterize the metrics for object-oriented testing.',
     ['They are the metrics of Binder that influence the testability. Encapsulation: LCOM (more states to test), percent public '
      'and protected, public access to data. Inheritance: the number of root classes (a set of tests for each tree), the fan-in '
      '(multiple inheritance), NOC and DIT (the methods of a superclass are retested for each subclass). Complexity: WMC and '
      'the number of methods. Polymorphism: the percent of the not overridden calls, the percent of the dynamic calls and the '
      'bounce metrics for the class and for the system. For Bookshelf the metrics say that the controllers and the actions '
      '(a high RFC) need the most set-up in the tests.']),
]

HINTS = [
    'theory, "Characteristics of object-oriented systems"', 'theory, the same section', 'the answer only',
    'the answer only; LCOM and LCOM4 in Table 4 and the analysis of LCOM', 'Figure 3 and the analysis of CBO and RFC',
    'the answer only', 'the answer only', 'Table 1', 'Table 4 (counting rules)', 'Table 1, Table 5 and the analysis of CBO and RFC',
    'Table 1 and the analysis of LCOM', 'Table 4 (WMC and WMC_cc)', 'the answer only', 'the answer only', 'the answer only',
    'the answer only', 'the answer only', 'the answer only', 'the answer only',
]


def write_answers(d, ctx) -> None:
    d.h1('Answers to the control questions', page_break=True)
    for number, (question, paragraphs) in enumerate(QUESTIONS, 1):
        d.p(f'{number}. {question}', indent=0, keep_next=True)
        for paragraph in paragraphs:
            d.p(paragraph)


def write_appendix(d, ctx, r: dict) -> None:
    d.h1('Appendix A. The metrics of every class', page_break=True)
    d.p('All the classes of backend/app, by group and name. The columns are WMC (the number of methods), WMC_cc (the '
        'cyclomatic weight), DIT (through the framework), DIT_app (inside the project), NOC, CBO, CBO_app (coupled classes '
        'of the project only), RFC, LCOM and LCOM4. The traits are marked "trait": they are measured but not counted in '
        'the statistics.')
    ctx.table(d, 'tab:app', 'The metrics of the classes of the backend')
    rows = []
    for x in sorted(r['classes'], key=lambda c: (c['category'], c['name'])):
        mark = ' (trait)' if x['kind'] == 'trait' else ' (enum)' if x['kind'] == 'enum' else ' (abstract)' if x['abstract'] else ''
        rows.append([x['category'], x['name'] + mark, str(x['wmc']), str(x['wmc_cc']), str(x['dit']), str(x['dit_app']),
                     str(x['noc']), str(x['cbo']), str(x['cbo_app']), str(x['rfc']), str(x['lcom']), str(x['lcom4'])])
    d.table(['Group', 'Class', 'WMC', 'WMC_cc', 'DIT', 'DIT_app', 'NOC', 'CBO', 'CBO_app', 'RFC', 'LCOM', 'LCOM4'], rows,
            [2.0, 4.6, 1.0, 1.3, 0.9, 1.3, 0.9, 0.9, 1.4, 0.9, 1.1, 1.2],
            align=['left', 'left'] + ['right'] * 10, size=15)
