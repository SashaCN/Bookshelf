"""Section 17: the order of implementation of the modules."""

from __future__ import annotations


def write(d, ctx) -> None:
    d.h1('17 Order of implementation of the modules')
    d.p('The methodology recommends the top-down order of implementation: the main program first, then the modules that it '
        'calls. Bookshelf was implemented in the combined way that the methodology calls descending development: first a '
        'skeleton of the whole system that runs from the first day (the containers, the checks, the sign-in), then one '
        'working slice of functions at a time, each slice from the controller down to the tables and from the router '
        'down to the screen. ' + ctx.ref('tab:order') + ' gives the order, the modules of each step and the work of the '
        'network schedule that it corresponds to. The commits are those of the repository.')
    ctx.table(d, 'tab:order', 'Order of implementation of the modules')
    d.table(
        ['Stage', 'Modules of the server part', 'Modules of the client', 'Work', 'Commits'],
        [
            ['0', 'The skeleton of the API: routes, Sanctum cookie session, Fortify; Docker Compose; the CI pipeline',
             'The skeleton of the SPA: router, `AuthLayout`, sign-in and registration views, `auth` store',
             'C, D', '`b718271`, `4575966`, `be1f810`, `dfaf91d`'],
            ['1', '`OpenLibraryClient`, `AddBookToLibrary`, `ChangeUserBookStatus`, `UserBookController`, `CatalogSearchController`; production images, Caddy and the delivery pipeline',
             '`AddBookView`, `LibraryView`, `BookView`, the `library` store',
             'E, F, G', '`0780d14`, `fd24bd6`, `d7b6b3c`'],
            ['2', '`UpdateProgress`, `RecordReadingLog`, `ReadingProgressController`',
             '`ReadingCard`, `PageJumpForm`, `ReadingJournal`', 'H, I', '`a037224`, `76a37f3`'],
            ['3', '`SaveQuote`, `PickQuoteOfTheDay`, `QuoteController`',
             '`QuotesView`, `QuoteFormView`, `QuoteShareView`, `quoteCard.ts`', 'J', '`c466582`, `2ae1fe6`'],
            ['4', '`ReadingStatistics`, `GoalProgress`, `StatsController`, `GoalController`',
             '`StatsView`, `PagesChart`, `GoalCard`', 'K', '`5f44b4d`, `b986fc2`, `f8d033c`'],
            ['5', 'none', 'The PWA: `src/sw`, the manifest, `src/pwa`, `OfflineGate`, `BootScreen`', 'L', '`7afb3d8`'],
            ['6', 'The community: `PublishPost`, `FollowReader`, `LikePost`, `PostComments`, `ReportContent`, `BlockReader`, `ModerateContent`, the moderation commands and the policies',
             '`CommunityView`, `ComposePostView`, `PostView`, `ReaderProfileView`, the social stores', 'N, O, P', '`6f14209`'],
            ['7', 'The reader: `ImportBookText`, `EpubImporter`, `EpubArchive`, `PackageReader`, `SafeXml`, `HtmlSanitizer`, `ResolveQuoteAnchor`, `SaveReadingPosition`',
             '`ReaderView`, the components of `components/reader`, `useTextSelection`, `usePositionSaver`', 'Q, R', '`6f14209`'],
        ],
        [0.9, 5.0, 4.2, 1.2, 2.6], align=['center', 'left', 'left', 'center', 'left'], size=20,
    )
    d.p('The stages are those of the product plan. The network schedule of version 2 puts the work L (the PWA and the '
        'polishing) after the works O and R, because the PWA and the polishing must cover the new screens; the PWA '
        'itself was built earlier (stage 5), before the scope of the product was extended by the community and the reader.')
    d.p('The order of the methodology and the real order differ in one point. The methodology puts the library of common '
        'routines last; here the common parts (the `Text` helpers, the `EpubLimits`, the enumerations) were written '
        'together with the first module that needed them and were reused by the next ones. The charts of Section 10 '
        'show the result: the modules that are used by several callers (`UpdateProgress`, `ReadingStatistics`) are '
        'written once and called from several places.')
