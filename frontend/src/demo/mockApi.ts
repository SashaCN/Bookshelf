/**
 * An in-browser stand-in for the Laravel API, used only by the standalone demo build (`npm run build:demo`).
 * It replaces `window.fetch` for `/api/*` requests, so the real screens, stores and API clients run unchanged.
 * Changes are kept in localStorage when it is available; "Reset" in the demo banner brings the sample data back.
 */
import type {
  Book,
  BookForecast,
  BookStatus,
  CatalogEntry,
  ReadPass,
  FeedQuote,
  GoalProgress,
  PersonSummary,
  PublicProfile,
  Quote,
  QuoteType,
  QuoteVisibility,
  ReaderBookmark,
  ReaderChapter,
  PublicReview,
  ReaderDocument,
  ReadingLog,
  User,
  UserBook,
} from '@/types/api'

const STORAGE_KEY = 'bookshelf-demo-state-v4'
const PAGE_SIZE = 15
const DAY_MS = 86_400_000

interface Entry extends Omit<UserBook, 'book' | 'allowed_statuses' | 'progress_percent' | 'times_read' | 'reads'> {
  book: Book
  /** Archived earlier passes, newest first. */
  reads?: ReadPass[]
}

type StoredQuote = Omit<Quote, 'book'>

interface State {
  user: User
  signedIn: boolean
  nextId: number
  library: Entry[]
  logs: Record<number, ReadingLog[]>
  quotes: StoredQuote[]
  goals: Record<number, number>
  /** Usernames of the fake readers the demo user follows. */
  following: string[]
  /** Ids of the quotes the demo user liked. */
  liked: number[]
  documents: StoredDocument[]
  /** Bookmarks of the sample document, by document id. */
  bookmarks: Record<number, ReaderBookmark[]>
}

/** A document as stored: the linked book's title is looked up when it is shown. */
type StoredDocument = Omit<ReaderDocument, 'user_book'>

class HttpError extends Error {
  constructor(
    readonly status: number,
    readonly body: unknown = null,
  ) {
    super(`HTTP ${status}`)
  }
}

const transitions: Record<BookStatus, BookStatus[]> = {
  want: ['reading', 'finished'],
  reading: ['finished', 'abandoned'],
  abandoned: ['reading'],
  finished: ['reading'],
}

// --- dates -------------------------------------------------------------------------------------------------------

function isoDay(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function daysAgo(days: number): Date {
  const date = new Date()
  date.setHours(12, 0, 0, 0)
  return new Date(date.getTime() - days * DAY_MS)
}

const today = () => isoDay(daysAgo(0))
const dayNumber = (day: string) => Math.round(Date.parse(`${day}T00:00:00Z`) / DAY_MS)
const addDays = (day: string, days: number) => new Date((dayNumber(day) + days) * DAY_MS).toISOString().slice(0, 10)

// --- sample data -------------------------------------------------------------------------------------------------

function book(id: number, title: string, authors: string[], pages: number, year: number, subjects: string[]): Book {
  return { id, title, subtitle: null, authors, isbn_13: null, cover_url: null, page_count: pages, published_year: year, subjects, source: 'manual' }
}

function seed(): State {
  const now = daysAgo(0).toISOString()
  const entry = (
    id: number,
    status: BookStatus,
    current: number,
    b: Book,
    extra: Partial<Entry> = {},
  ): Entry => ({
    id,
    status,
    current_page: current,
    total_pages: b.page_count,
    rating: null,
    review: null,
    review_visibility: 'private',
    reviewed_at: null,
    started_at: null,
    finished_at: null,
    created_at: now,
    book: b,
    ...extra,
  })

  const library: Entry[] = [
    entry(1, 'reading', 214, book(1, 'Сто років самотності', ['Габріель Гарсія Маркес'], 480, 1967, ['Роман']), {
      started_at: daysAgo(20).toISOString(),
    }),
    entry(2, 'reading', 96, book(2, 'Тіні забутих предків', ['Михайло Коцюбинський'], 160, 1911, ['Повість']), {
      started_at: daysAgo(6).toISOString(),
    }),
    entry(3, 'finished', 320, book(3, 'Маленький принц', ['Антуан де Сент-Екзюпері'], 96, 1943, ['Казка']), {
      current_page: 96,
      total_pages: 96,
      rating: 5,
      review: 'Проста казка, після якої хочеться жити уважніше. Перечитую щоразу, коли поспішаю.\n\nЛисиця — мій улюблений персонаж.',
      review_visibility: 'public',
      reviewed_at: daysAgo(34).toISOString(),
      started_at: daysAgo(40).toISOString(),
      finished_at: daysAgo(35).toISOString(),
      reads: [{ started_at: daysAgo(400).toISOString(), finished_at: daysAgo(390).toISOString() }],
    }),
    entry(4, 'finished', 0, book(4, '1984', ['Джордж Орвелл'], 328, 1949, ['Антиутопія']), {
      current_page: 328,
      rating: 4,
      started_at: daysAgo(80).toISOString(),
      finished_at: daysAgo(62).toISOString(),
    }),
    entry(5, 'finished', 0, book(5, 'Лісова пісня', ['Леся Українка'], 128, 1911, ['Драма']), {
      current_page: 128,
      rating: 5,
      started_at: daysAgo(130).toISOString(),
      finished_at: daysAgo(124).toISOString(),
    }),
    entry(6, 'want', 0, book(6, 'Чистий код', ['Роберт Мартін'], 464, 2008, ['Програмування'])),
    entry(7, 'want', 0, book(7, 'Музей покинутих секретів', ['Оксана Забужко'], 832, 2009, ['Роман'])),
    entry(8, 'abandoned', 70, book(8, 'Улісс', ['Джеймс Джойс'], 730, 1922, ['Роман']), {
      started_at: daysAgo(100).toISOString(),
    }),
  ]

  // A believable reading history for the last weeks, so the streak, chart and forecasts have something to show.
  const logs: Record<number, ReadingLog[]> = {}
  let logId = 1
  const log = (bookId: number, ago: number, from: number, to: number) => {
    const at = daysAgo(ago)
    ;(logs[bookId] ??= []).push({ id: logId++, from_page: from, to_page: to, pages: to - from, logged_on: isoDay(at), created_at: at.toISOString() })
  }
  const book1 = [[19, 0, 18], [17, 18, 44], [16, 44, 60], [14, 60, 95], [12, 95, 110], [10, 110, 142], [8, 142, 150], [6, 150, 171], [4, 171, 190], [3, 190, 196], [2, 196, 205], [1, 205, 214]]
  for (const [ago, from, to] of book1) log(1, ago, from, to)
  const book2 = [[5, 0, 22], [4, 22, 41], [3, 41, 60], [2, 60, 78], [1, 78, 96]]
  for (const [ago, from, to] of book2) log(2, ago, from, to)
  log(3, 38, 0, 40)
  log(3, 36, 40, 96)
  for (const [ago, from, to] of [[70, 0, 160], [64, 160, 328]]) log(4, ago, from, to)
  for (const [ago, from, to] of [[128, 0, 60], [124, 60, 128]]) log(5, ago, from, to)

  const quote = (
    id: number,
    userBookId: number,
    type: QuoteType,
    content: string,
    page: number | null,
    ago: number,
    extra: Partial<StoredQuote> = {},
  ): StoredQuote => ({
    id,
    type,
    content,
    note: null,
    page,
    is_favorite: false,
    visibility: 'private',
    created_at: daysAgo(ago).toISOString(),
    user_book_id: userBookId,
    ...extra,
  })

  const quotes: StoredQuote[] = [
    quote(1, 3, 'quote', 'Зорко одне лише серце. Найголовнішого очима не побачиш.', 72, 36, {
      note: 'Те, до чого повертаюся щоразу, коли забуваю зупинитися.',
      is_favorite: true,
      visibility: 'public',
    }),
    quote(2, 3, 'quote', 'Ти назавжди береш на себе відповідальність за тих, кого приручив.', 80, 36, { is_favorite: true }),
    quote(3, 1, 'quote', 'Життя не те, що з тобою сталося, а те, що ти пам\'ятаєш і як ти про це розповідаєш.', 130, 9),
    quote(4, 1, 'insight', 'Родина Буендіа повторює імена й помилки — ніби історія ходить колами. Цікаво, чи є вихід із циклу.', 160, 6, {
      note: 'Порівняти з Орвеллом — там цикл насильницький, тут — самотність.',
    }),
    quote(5, 4, 'quote', 'Хто керує минулим, той керує майбутнім; хто керує сьогоденням, той керує минулим.', 44, 63, { is_favorite: true, visibility: 'public' }),
    quote(6, 5, 'quote', 'Contra spem spero! Без надії сподіваюсь.', 97, 125, { visibility: 'public' }),
    quote(7, 2, 'quote', 'Гуцульщина — це не місце на мапі, це спосіб слухати гори.', 40, 3),
    quote(8, 2, 'insight', 'У Коцюбинського природа не тло, а персонаж: вона вирішує долю Івана не менше за людей.', 52, 2),
  ]

  return {
    user: { id: 1, name: 'Демо-читач', email: 'demo@bookshelf.test', timezone: 'Europe/Kyiv', username: 'demo_reader', is_public: true, new_followers_count: 1 },
    signedIn: true,
    nextId: 100,
    library,
    logs,
    quotes,
    goals: { [new Date().getFullYear()]: 12 },
    following: ['olena_reads'],
    liked: [],
    documents: [
      {
        id: 1,
        title: demoBook.title,
        author: demoBook.author,
        user_book_id: null,
        chapters_count: demoBook.chapters.length,
        position: { chapter: 0, fraction: 0 },
        progress: 0,
        last_read_at: daysAgo(1).toISOString(),
        created_at: daysAgo(2).toISOString(),
        toc: demoBook.chapters.map((c, index) => ({ index, title: c.title })),
      },
    ],
    bookmarks: {},
  }
}

// --- fake readers (for profiles, follows and the feed) and the sample EPUB -----------------------------------------

/** The key of the demo user in the follow graph. */
const ME = '@me'

interface FakeReader {
  username: string
  name: string
  books_finished_this_year: number
  quotes: { content: string; note: string | null; page: number | null; type: QuoteType; ago: number; book: Quote['book'] }[]
  reviews: { book: Quote['book']; rating: number | null; review: string; ago: number }[]
}

const fakeReaders: FakeReader[] = [
  {
    username: 'olena_reads',
    name: 'Олена Коваль',
    books_finished_this_year: 9,
    quotes: [
      { content: 'Книга — це вікно, яке ти відчиняєш сам.', note: null, page: 12, type: 'quote', ago: 1, book: { title: 'Лісова пісня', authors: ['Леся Українка'], cover_url: null } },
      { content: 'Читати повільно — теж спосіб жити повільно.', note: 'Записала після довгої дороги потягом.', page: null, type: 'insight', ago: 4, book: { title: 'Тіні забутих предків', authors: ['Михайло Коцюбинський'], cover_url: null } },
      { content: 'Найтемніша ніч закінчується світанком.', note: null, page: 201, type: 'quote', ago: 11, book: { title: 'Зів\'яле листя', authors: ['Іван Франко'], cover_url: null } },
    ],
    reviews: [
      { book: { title: 'Лісова пісня', authors: ['Леся Українка'], cover_url: null }, rating: 5, review: 'Читається як музика.\nМавка назавжди залишилася зі мною.', ago: 8 },
      { book: { title: 'Тіні забутих предків', authors: ['Михайло Коцюбинський'], cover_url: null }, rating: 4, review: 'Гори, говірка, кохання. Місцями важко, але варто.', ago: 20 },
    ],
  },
  {
    username: 'taras_books',
    name: 'Тарас Мельник',
    books_finished_this_year: 14,
    quotes: [
      { content: 'Свобода починається там, де закінчується страх.', note: 'До цього місця завжди повертаюся.', page: 88, type: 'quote', ago: 2, book: { title: '1984', authors: ['Джордж Орвелл'], cover_url: null } },
      { content: 'Хороший код читається як проза.', note: null, page: 31, type: 'quote', ago: 7, book: { title: 'Чистий код', authors: ['Роберт Мартін'], cover_url: null } },
    ],
    reviews: [{ book: { title: '1984', authors: ['Джордж Орвелл'], cover_url: null }, rating: 5, review: 'Страшно актуально. Читати повільно.', ago: 15 }],
  },
  {
    username: 'iryna_p',
    name: 'Ірина Павленко',
    books_finished_this_year: 5,
    quotes: [
      { content: 'Дім там, де на тебе чекає розгорнута книга.', note: null, page: 5, type: 'quote', ago: 3, book: { title: 'Маленький принц', authors: ['Антуан де Сент-Екзюпері'], cover_url: null } },
    ],
    reviews: [],
  },
]

/** Who follows whom among the fake readers; the demo user's own follows live in the state. */
const fakeFollows: [string, string][] = [
  ['olena_reads', 'taras_books'],
  ['taras_books', 'olena_reads'],
  ['iryna_p', 'olena_reads'],
  ['taras_books', ME],
]

const demoBook = {
  title: 'Прогулянка до річки',
  author: 'Демо-автор',
  chapters: [
    {
      title: 'Ранок',
      paragraphs: [
        'Ранок прийшов тихо, як гість, що боїться розбудити господарів. Роса ще лежала на траві сріблястим покривалом.',
        'Хтось далеко ударив у відро, і цей дзвінкий звук розбіг по подвір\'ю, наче маленька хвиля по воді.',
        'Я взяв кошик, хліб і вирушив стежкою вниз, до річки, де туман щойно починав розходитися.',
      ],
    },
    {
      title: 'Берег',
      paragraphs: [
        'Річка текла повільно, ніби думала, куди їй поспішати. Над водою кружляли дрібні мушки, а в очереті щось шелестіло.',
        'Я сів на старе повалене дерево й довго дивився, як вода малює на піску вузькі світлі смужки.',
        'Здавалося, що час тут працює за іншими правилами: година тягнулася, мов хвилина, а хвилина — мов година.',
      ],
    },
    {
      title: 'Дорога додому',
      paragraphs: [
        'Назад я йшов іншою дорогою, повз вишневий сад, де вже достигали перші ягоди.',
        'Сонце піднялося високо, і тінь від мене стала короткою й смішною, наче в казковому гнома.',
        'Біля воріт мене зустрів пес. Він ледь помітно махнув хвостом, мовби питав: «Ну що, варто було ходити?» Варто.',
      ],
    },
  ],
}

// --- catalog (stand-in for Open Library) ---------------------------------------------------------------------------

const catalog: Omit<CatalogEntry, 'in_library'>[] = [
  ['OL1', 'Кобзар', ['Тарас Шевченко'], 1840, 352, ['Поезія']],
  ['OL2', 'Захар Беркут', ['Іван Франко'], 1883, 224, ['Роман']],
  ['OL3', 'Intermezzo', ['Михайло Коцюбинський'], 1909, 64, ['Новела']],
  ['OL4', 'Майстер і Маргарита', ['Михайло Булгаков'], 1967, 480, ['Роман']],
  ['OL5', 'Хіба ревуть воли, як ясла повні?', ['Панас Мирний'], 1880, 400, ['Роман']],
  ['OL6', 'Гаррі Поттер і філософський камінь', ['Дж. К. Роулінг'], 1997, 336, ['Фентезі']],
  ['OL7', 'Володар перснів', ['Дж. Р. Р. Толкін'], 1954, 1216, ['Фентезі']],
  ['OL8', 'Прагматичний програміст', ['Ендрю Хант', 'Девід Томас'], 1999, 352, ['Програмування']],
  ['OL9', 'Чистий код', ['Роберт Мартін'], 2008, 464, ['Програмування']],
  ['OL10', 'Тигролови', ['Іван Багряний'], 1944, 384, ['Роман']],
].map(([work_key, title, authors, published_year, page_count, subjects]) => ({
  work_key: work_key as string,
  title: title as string,
  subtitle: null,
  authors: authors as string[],
  cover_url: null,
  page_count: page_count as number,
  published_year: published_year as number,
  subjects: subjects as string[],
  isbn_13: null,
}))

// --- state -------------------------------------------------------------------------------------------------------

let state: State = load()

function load(): State {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) return JSON.parse(raw) as State
  } catch {
    // Storage may be blocked (private mode, file:// in some browsers): the demo then simply keeps data in memory.
  }
  return seed()
}

function save(): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state))
  } catch {
    // See load().
  }
}

export function resetDemo(): void {
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    // See load().
  }
  state = seed()
}

// --- shapes ------------------------------------------------------------------------------------------------------

function presentEntry(e: Entry): UserBook {
  return {
    ...e,
    allowed_statuses: transitions[e.status],
    progress_percent: e.total_pages ? Math.round((e.current_page / e.total_pages) * 100) : null,
    times_read: (e.reads?.length ?? 0) + (e.status === 'finished' ? 1 : 0),
    reads: e.reads ?? [],
  }
}

/** Other readers' likes are made up from the id, so every quote has a plausible count; the demo user's own like comes on top. */
function withLikes<T extends Quote | StoredQuote>(q: T): T {
  const liked = state.liked.includes(q.id)
  return { ...q, likes_count: (q.id % 4) + (liked ? 1 : 0), liked_by_me: liked }
}

function presentQuote(q: StoredQuote): Quote {
  const e = findEntry(q.user_book_id)
  return { ...q, book: { title: e.book.title, authors: e.book.authors, cover_url: e.book.cover_url } }
}

function findEntry(id: number): Entry {
  const found = state.library.find((e) => e.id === id)
  if (!found) throw new HttpError(404, { message: 'Not found' })
  return found
}

function findQuote(id: number): StoredQuote {
  const found = state.quotes.find((q) => q.id === id)
  if (!found) throw new HttpError(404, { message: 'Not found' })
  return found
}

const nextId = () => state.nextId++

function paginate<T>(all: T[], page: number, path: string, size = PAGE_SIZE): { data: T[]; links: { next: string | null } } {
  return { data: all.slice((page - 1) * size, page * size), links: { next: page * size < all.length ? `${path}?page=${page + 1}` : null } }
}

const newestFirst = <T extends { created_at: string; id: number }>(a: T, b: T) => b.created_at.localeCompare(a.created_at) || b.id - a.id

// --- social ---------------------------------------------------------------------------------------------------------

/** A reader by username: the demo user or one of the fake readers; unknown names are a 404. */
function findPerson(username: string): { key: string; name: string } {
  if (state.user.username === username) return { key: ME, name: state.user.name }
  const reader = fakeReaders.find((r) => r.username === username)
  if (!reader) throw new HttpError(404, { message: 'Not found' })
  return { key: reader.username, name: reader.name }
}

function edges(): [string, string][] {
  return [...fakeFollows, ...state.following.map((username): [string, string] => [ME, username])]
}

function summarize(key: string): PersonSummary {
  if (key === ME) return { username: state.user.username ?? '', name: state.user.name }
  const reader = fakeReaders.find((r) => r.username === key) as FakeReader
  return { username: reader.username, name: reader.name }
}

function fakeQuotes(reader: FakeReader): Quote[] {
  return reader.quotes.map((q, i) => ({
    id: 1000 + fakeReaders.indexOf(reader) * 10 + i,
    type: q.type,
    content: q.content,
    note: q.note,
    page: q.page,
    is_favorite: false,
    visibility: 'public',
    created_at: daysAgo(q.ago).toISOString(),
    user_book_id: 0,
    book: q.book,
  }))
}

/** The public reviews of the demo user or of a fake reader, most recent first. */
function publicReviews(name: string): PublicReview[] {
  const person = findPerson(name)
  if (person.key === ME) {
    return state.library
      .filter((e) => e.review !== null && e.review_visibility === 'public')
      .sort((a, b) => (b.reviewed_at ?? '').localeCompare(a.reviewed_at ?? '') || b.id - a.id)
      .map((e) => ({
        id: e.id,
        book: { title: e.book.title, authors: e.book.authors, cover_url: e.book.cover_url },
        rating: e.rating,
        review: e.review as string,
        reviewed_at: e.reviewed_at as string,
      }))
  }

  const reader = fakeReaders.find((r) => r.username === name) as FakeReader
  return reader.reviews.map((r, i) => ({
    id: 2000 + fakeReaders.indexOf(reader) * 10 + i,
    book: r.book,
    rating: r.rating,
    review: r.review,
    reviewed_at: daysAgo(r.ago).toISOString(),
  }))
}

function profile(username: string): PublicProfile {
  const person = findPerson(username)
  const all = edges()
  return {
    username,
    name: person.name,
    books_finished_this_year: person.key === ME ? finishedIn(today().slice(0, 4)) : (fakeReaders.find((r) => r.username === username) as FakeReader).books_finished_this_year,
    followers_count: all.filter(([, to]) => to === person.key).length,
    following_count: all.filter(([from]) => from === person.key).length,
    reviews_count: publicReviews(username).length,
    is_following: state.following.includes(username),
    is_me: person.key === ME,
  }
}

function invalid(field: string, code: string): never {
  throw new HttpError(422, { message: code, errors: { [field]: [code] } })
}

/** Writes or clears the review the way the real API does: only a finished or abandoned book can get one. */
function setReview(e: Entry, value: string | null): void {
  const review = value?.trim() ? value.trim() : null
  if (review === e.review) return
  if (review !== null && review.length > 5000) invalid('review', 'validation.max')
  if (review !== null && e.status !== 'finished' && e.status !== 'abandoned') invalid('review', 'library.review_not_finished')
  e.review = review
  e.reviewed_at = review === null ? null : new Date().toISOString()
}

/** Moves a library entry to another status the way the real API does. */
function changeStatus(e: Entry, target: BookStatus): void {
  if (e.status === target) return
  if (!transitions[e.status].includes(target)) invalid('status', 'library.invalid_transition')

  if (e.status === 'want' && target === 'reading') {
    if (!e.total_pages) invalid('total_pages', 'library.total_pages_required')
    e.started_at = new Date().toISOString()
  } else if (target === 'finished') {
    e.finished_at = new Date().toISOString()
    e.current_page = e.total_pages ?? e.current_page
    if (e.status === 'want') e.started_at = null
  } else if (target === 'reading') {
    e.finished_at = null
  }

  e.status = target
}

// --- statistics ---------------------------------------------------------------------------------------------------

function pagesByDay(): Map<string, number> {
  const days = new Map<string, number>()
  for (const entryLogs of Object.values(state.logs)) {
    for (const l of entryLogs) days.set(l.logged_on, (days.get(l.logged_on) ?? 0) + l.pages)
  }
  return days
}

function streak(days: Map<string, number>): { current: number; longest: number; read_today: boolean } {
  const read = (day: string) => (days.get(day) ?? 0) > 0
  const readToday = read(today())

  let current = 0
  let cursor = readToday ? today() : addDays(today(), -1)
  while (read(cursor)) {
    current++
    cursor = addDays(cursor, -1)
  }

  let longest = 0
  let run = 0
  const sorted = [...days.keys()].filter(read).sort()
  sorted.forEach((day, i) => {
    run = i > 0 && dayNumber(day) - dayNumber(sorted[i - 1]) === 1 ? run + 1 : 1
    longest = Math.max(longest, run)
  })

  return { current, longest, read_today: readToday }
}

function sumPages(days: Map<string, number>, from: string, to: string): number {
  let total = 0
  for (const [day, pages] of days) if (day >= from && day <= to) total += Math.max(pages, 0)
  return total
}

function finishedIn(prefix: string): number {
  const inMonth = (iso: string) => iso.startsWith(prefix) || isoDay(new Date(iso)).startsWith(prefix)
  const archived = state.library.reduce((sum, e) => sum + (e.reads ?? []).filter((r) => inMonth(r.finished_at)).length, 0)
  return archived + state.library.filter((e) => e.finished_at?.startsWith(prefix) || (e.finished_at && isoDay(new Date(e.finished_at)).startsWith(prefix))).length
}

function summary() {
  const days = pagesByDay()
  const t = today()
  const monthPrefix = t.slice(0, 7)
  const yearPrefix = t.slice(0, 4)

  const forecasts: BookForecast[] = state.library
    .filter((e) => e.status === 'reading' && e.total_pages)
    .map((e) => {
      const from = addDays(t, -13)
      const recent = (state.logs[e.id] ?? []).filter((l) => l.logged_on >= from).reduce((sum, l) => sum + l.pages, 0)
      const perDay = recent > 0 ? Math.round((recent / 14) * 10) / 10 : null
      const left = (e.total_pages as number) - e.current_page
      const daysLeft = perDay ? Math.ceil(left / perDay) : null
      return {
        user_book_id: e.id,
        title: e.book.title,
        current_page: e.current_page,
        total_pages: e.total_pages as number,
        pages_per_day: perDay,
        days_left: daysLeft,
        finish_on: daysLeft === null ? null : addDays(t, daysLeft),
      }
    })

  return {
    today: { date: t, pages: Math.max(days.get(t) ?? 0, 0) },
    streak: streak(days),
    pace: Math.round((sumPages(days, addDays(t, -13), t) / 14) * 10) / 10,
    month: { pages: sumPages(days, `${monthPrefix}-01`, t), books_finished: finishedIn(monthPrefix) },
    year: { pages: sumPages(days, `${yearPrefix}-01-01`, t), books_finished: finishedIn(yearPrefix) },
    forecasts,
  }
}

function goalProgress(year: number): GoalProgress {
  const target = state.goals[year] ?? null
  const finished = finishedIn(String(year))
  if (target === null) {
    return { year, target_books: null, finished_books: finished, expected_books: null, due_books: null, remaining_books: null, on_track: null }
  }

  const start = Date.UTC(year, 0, 1)
  const length = (Date.UTC(year + 1, 0, 1) - start) / DAY_MS
  const elapsed = Math.min(Math.max((Date.now() - start) / DAY_MS, 0), length)
  const expected = (target * elapsed) / length
  const due = Math.floor(expected)

  return {
    year,
    target_books: target,
    finished_books: finished,
    expected_books: Math.round(expected * 10) / 10,
    due_books: due,
    remaining_books: Math.max(target - finished, 0),
    on_track: finished >= due,
  }
}

// --- routing ------------------------------------------------------------------------------------------------------

type Body = Record<string, unknown>
type Handler = (params: string[], query: URLSearchParams, body: Body) => unknown

const ok = (data: unknown) => ({ data })

/** A non-JSON answer (a file for download). */
class TextFile {
  constructor(
    readonly text: string,
    readonly type: string,
  ) {}
}

const routes: { method: string; pattern: RegExp; handle: Handler }[] = []
const route = (method: string, pattern: string, handle: Handler) =>
  routes.push({ method, pattern: new RegExp(`^${pattern}$`), handle })

route('GET', '/api/sanctum/csrf-cookie', () => null)

route('GET', '/api/me', () => {
  if (!state.signedIn) throw new HttpError(401, { message: 'Unauthenticated.' })
  return ok(state.user)
})
route('PATCH', '/api/me', (_p, _q, body) => {
  if (typeof body.name === 'string' && body.name.trim()) state.user.name = body.name.trim()
  if (typeof body.timezone === 'string') state.user.timezone = body.timezone

  const username = 'username' in body ? String(body.username ?? '').trim() || null : state.user.username
  const isPublic = typeof body.is_public === 'boolean' ? body.is_public : state.user.is_public
  if (isPublic && !username) invalid('username', 'profile.username_required')
  state.user.username = username
  state.user.is_public = isPublic
  return ok(state.user)
})
route('POST', '/api/me/followers-seen', () => {
  state.user.new_followers_count = 0
  return null
})
// Any credentials work in the demo, so nobody needs to remember a password.
route('POST', '/api/auth/login', () => {
  state.signedIn = true
  return null
})
route('POST', '/api/auth/register', (_p, _q, body) => {
  state = seed()
  state.user = { ...state.user, name: String(body.name ?? 'Читач'), email: String(body.email ?? ''), timezone: String(body.timezone ?? 'Europe/Kyiv'), username: null, is_public: false, new_followers_count: 0 }
  return null
})
// No mail is sent in the demo: both steps of the reset succeed.
route('POST', '/api/auth/forgot-password', () => ({ message: 'We have emailed your password reset link.' }))
route('POST', '/api/auth/reset-password', () => ({ message: 'Your password has been reset.' }))
route('PUT', '/api/auth/user/password', (_p, _q, body) => {
  if (!body.current_password) invalid('current_password', 'The current password is incorrect.')
  const next = String(body.password ?? '')
  if (next.length < 8 || next !== body.password_confirmation) invalid('password', 'The password is invalid.')
  return null
})
// Deleting the account wipes the demo data too: the next visitor sign-in starts from the sample library again.
route('DELETE', '/api/me', (_p, _q, body) => {
  if (!body.password) invalid('password', 'The password is incorrect.')
  state = seed()
  state.signedIn = false
  return null
})
route('POST', '/api/auth/logout', () => {
  state.signedIn = false
  return null
})

route('GET', '/api/library', () => ok(state.library.map(presentEntry)))
route('POST', '/api/library', (_p, _q, body) => {
  let newBook: Book
  const workKey = body.openlibrary_work_key as string | undefined

  if (workKey) {
    const found = catalog.find((c) => c.work_key === workKey)
    if (!found) throw new HttpError(404)
    const existing = state.library.find((e) => e.book.title === found.title)
    if (existing) throw new HttpError(409, { message: 'library.already_added', user_book_id: existing.id })
    newBook = { ...found, id: nextId(), source: 'openlibrary' }
  } else {
    const title = String(body.title ?? '').trim()
    if (!title) invalid('title', 'validation.required')
    newBook = {
      id: nextId(),
      title,
      subtitle: null,
      authors: (body.authors as string[] | undefined) ?? [],
      isbn_13: null,
      cover_url: null,
      page_count: (body.total_pages as number | null | undefined) ?? null,
      published_year: (body.published_year as number | null | undefined) ?? null,
      subjects: [],
      source: 'manual',
    }
  }

  const entry: Entry = {
    id: nextId(),
    status: 'want',
    current_page: 0,
    total_pages: newBook.page_count,
    rating: null,
    review: null,
    review_visibility: 'private',
    reviewed_at: null,
    started_at: null,
    finished_at: null,
    created_at: new Date().toISOString(),
    book: newBook,
  }
  state.library.unshift(entry)
  if (body.status && body.status !== 'want') changeStatus(entry, body.status as BookStatus)
  return ok(presentEntry(entry))
})
route('GET', '/api/library/(\\d+)', ([id]) => {
  const entry = findEntry(Number(id))
  const linked = state.documents
    .filter((doc) => doc.user_book_id === entry.id)
    .sort(recentlyRead)[0]
  return ok({ ...presentEntry(entry), reader_document_id: linked?.id ?? null })
})
route('PATCH', '/api/library/(\\d+)', ([id], _q, body) => {
  const e = findEntry(Number(id))

  if (body.total_pages !== undefined) {
    const pages = Number(body.total_pages)
    if (pages < e.current_page) invalid('total_pages', 'library.total_pages_below_current')
    e.total_pages = pages
  }
  if ('rating' in body) e.rating = body.rating as number | null
  if (body.status) changeStatus(e, body.status as BookStatus)
  if (body.review_visibility !== undefined) {
    if (body.review_visibility !== 'private' && body.review_visibility !== 'public') invalid('review_visibility', 'validation.invalid')
    e.review_visibility = body.review_visibility
  }
  if ('review' in body) setReview(e, body.review as string | null)

  state.library = [e, ...state.library.filter((x) => x.id !== e.id)]
  return ok(presentEntry(e))
})
route('DELETE', '/api/library/(\\d+)', ([id]) => {
  const e = findEntry(Number(id))
  state.library = state.library.filter((x) => x.id !== e.id)
  state.quotes = state.quotes.filter((q) => q.user_book_id !== e.id)
  delete state.logs[e.id]
  for (const doc of state.documents) if (doc.user_book_id === e.id) doc.user_book_id = null
  return null
})
route('POST', '/api/library/(\\d+)/progress', ([id], _q, body) => {
  const e = findEntry(Number(id))
  const page = Number(body.page)

  if (e.status === 'finished') invalid('page', 'library.progress_finished')

  const from = e.current_page
  if (page !== from) {
    if (e.status !== 'reading') changeStatus(e, 'reading')
    if (e.total_pages !== null && page > e.total_pages) invalid('page', 'library.page_above_total')
    e.current_page = page
    const at = new Date()
    ;(state.logs[e.id] ??= []).push({ id: nextId(), from_page: from, to_page: page, pages: page - from, logged_on: isoDay(at), created_at: at.toISOString() })
  }

  return {
    data: presentEntry(e),
    meta: { pages: page - from, reached_end: e.total_pages !== null && page >= e.total_pages, streak: streak(pagesByDay()).current },
  }
})
route('POST', '/api/library/(\\d+)/reread', ([id]) => {
  const e = findEntry(Number(id))
  if (e.status !== 'finished') invalid('status', 'library.reread_not_finished')

  e.reads = [{ started_at: e.started_at, finished_at: e.finished_at ?? new Date().toISOString() }, ...(e.reads ?? [])]
  e.status = 'reading'
  e.current_page = 0
  e.started_at = new Date().toISOString()
  e.finished_at = null
  state.library = [e, ...state.library.filter((x) => x.id !== e.id)]
  return ok(presentEntry(e))
})
route('GET', '/api/library/(\\d+)/logs', ([id], query) => {
  findEntry(Number(id))
  const page = Number(query.get('page') ?? 1)
  const all = [...(state.logs[Number(id)] ?? [])].sort((a, b) => b.created_at.localeCompare(a.created_at))
  const slice = all.slice((page - 1) * 20, page * 20)
  return { data: slice, links: { next: page * 20 < all.length ? `/api/library/${id}/logs?page=${page + 1}` : null } }
})

route('GET', '/api/catalog/search', (_p, query) => {
  const needle = (query.get('q') ?? '').trim().toLowerCase()
  const inLibrary = new Set(state.library.map((e) => e.book.title))
  return ok(
    catalog
      .filter((c) => c.title.toLowerCase().includes(needle) || c.authors.some((a) => a.toLowerCase().includes(needle)))
      .map((c) => ({ ...c, in_library: inLibrary.has(c.title) })),
  )
})

route('GET', '/api/quotes/daily', () => {
  if (state.quotes.length === 0) return ok(null)
  const index = dayNumber(today()) % state.quotes.length
  return ok(presentQuote(state.quotes[index]))
})
// Simple on purpose: the real export escapes Markdown, the demo only has to hand over a believable file.
route('GET', '/api/quotes/export', (_p, query) => {
  const chosen = state.quotes
    .filter((q) => query.get('favorite') !== '1' || q.is_favorite)
    .filter((q) => !query.get('book') || q.user_book_id === Number(query.get('book')))
    .filter((q) => !query.get('type') || q.type === query.get('type'))
    .sort((a, b) => a.id - b.id)

  const byBook = new Map<string, string[]>()
  for (const q of chosen) {
    const { book } = presentQuote(q)
    const lines = byBook.get(book.title) ?? [`## ${book.title}`, ...(book.authors.length ? ['', `_${book.authors.join(', ')}_`] : [])]
    const text = q.type === 'insight' ? `💡 ${q.content}` : `> ${q.content}`
    const details = [q.page ? `— с. ${q.page}` : '', q.note ? `Нотатка: ${q.note}` : '', q.is_favorite ? '★' : ''].filter(Boolean)
    lines.push('', text, ...(details.length ? ['', details.join('  \n')] : []))
    byBook.set(book.title, lines)
  }

  const sections = [...byBook.keys()].sort((a, b) => a.localeCompare(b)).map((title) => (byBook.get(title) ?? []).join('\n'))
  const body = sections.length ? sections.join('\n\n') : 'Цитат поки немає.'
  return new TextFile(`# Мої цитати\n\n${body}\n`, 'text/markdown; charset=UTF-8')
})
route('GET', '/api/quotes', (_p, query) => {
  const page = Number(query.get('page') ?? 1)
  const all = state.quotes
    .filter((q) => query.get('favorite') !== '1' || q.is_favorite)
    .filter((q) => !query.get('book') || q.user_book_id === Number(query.get('book')))
    .filter((q) => !query.get('type') || q.type === query.get('type'))
    .sort((a, b) => b.created_at.localeCompare(a.created_at) || b.id - a.id)
  const { data, links } = paginate(all, page, '/api/quotes')
  return { data: data.map((q) => ({ ...presentQuote(q), likes_count: withLikes(q).likes_count })), links }
})
route('GET', '/api/quotes/(\\d+)', ([id]) => ok(presentQuote(findQuote(Number(id)))))
route('POST', '/api/library/(\\d+)/quotes', ([id], _q, body) => {
  const e = findEntry(Number(id))
  const content = String(body.content ?? '').trim()
  if (!content) invalid('content', 'validation.required')
  if (typeof body.page === 'number' && e.total_pages !== null && body.page > e.total_pages) invalid('page', 'quotes.page_above_total')

  const created: StoredQuote = {
    id: nextId(),
    type: (body.type as QuoteType | undefined) ?? 'quote',
    content,
    note: (body.note as string | null | undefined) ?? null,
    page: (body.page as number | null | undefined) ?? null,
    is_favorite: Boolean(body.is_favorite),
    visibility: (body.visibility as QuoteVisibility | undefined) ?? 'private',
    created_at: new Date().toISOString(),
    user_book_id: e.id,
  }
  state.quotes.push(created)
  return ok(presentQuote(created))
})
route('PATCH', '/api/quotes/(\\d+)', ([id], _q, body) => {
  const q = findQuote(Number(id))
  const entry = findEntry(q.user_book_id)
  if (typeof body.page === 'number' && entry.total_pages !== null && body.page > entry.total_pages) invalid('page', 'quotes.page_above_total')
  Object.assign(q, body)
  return ok(presentQuote(q))
})
route('DELETE', '/api/quotes/(\\d+)', ([id]) => {
  findQuote(Number(id))
  state.quotes = state.quotes.filter((q) => q.id !== Number(id))
  return null
})

// Social: the demo user's public quotes plus a few fake readers.
route('GET', '/api/users', (_p, query) => {
  const q = (query.get('q') ?? '').trim().toLowerCase()
  if (q.length < 2 || q.length > 50) invalid('q', 'search.length')
  const handle = q.startsWith('@') && q.length > 1 ? q.slice(1) : q
  const rank = (r: FakeReader) => {
    const username = r.username.toLowerCase()
    return username === handle ? 0 : username.startsWith(handle) ? 1 : 2
  }
  const found = fakeReaders
    .filter((r) => r.username !== state.user.username)
    .filter((r) => r.username.toLowerCase().startsWith(handle) || r.name.toLowerCase().includes(q))
    .sort((a, b) => rank(a) - rank(b) || a.name.localeCompare(b.name))
    .slice(0, 20)
    .map((r) => ({ username: r.username, name: r.name, is_following: state.following.includes(r.username) }))
  return ok(found)
})
route('GET', '/api/users/([^/]+)', ([username]) => ok(profile(decodeURIComponent(username))))
route('GET', '/api/users/([^/]+)/quotes', ([username], query) => {
  const name = decodeURIComponent(username)
  const person = findPerson(name)
  const all =
    person.key === ME
      ? state.quotes.filter((q) => q.visibility === 'public').sort(newestFirst).map(presentQuote)
      : fakeQuotes(fakeReaders.find((r) => r.username === name) as FakeReader)
  return paginate(all.map(withLikes), Number(query.get('page') ?? 1), `/api/users/${username}/quotes`)
})
route('GET', '/api/users/([^/]+)/reviews', ([username], query) => {
  return paginate(publicReviews(decodeURIComponent(username)), Number(query.get('page') ?? 1), `/api/users/${username}/reviews`)
})
route('POST', '/api/users/([^/]+)/follow', ([username]) => {
  const name = decodeURIComponent(username)
  if (findPerson(name).key === ME) invalid('username', 'follow.self')
  if (!state.following.includes(name)) state.following.push(name)
  return null
})
route('DELETE', '/api/users/([^/]+)/follow', ([username]) => {
  const name = decodeURIComponent(username)
  findPerson(name)
  state.following = state.following.filter((u) => u !== name)
  return null
})
route('GET', '/api/users/([^/]+)/followers', ([username], query) => {
  const key = findPerson(decodeURIComponent(username)).key
  // Like the real API: only the owner sees who is new, and they are the latest followers.
  const newCount = key === ME ? (state.user.new_followers_count ?? 0) : 0
  const people = edges()
    .filter(([, to]) => to === key)
    .map(([from], i): PersonSummary => (key === ME ? { ...summarize(from), is_new: i < newCount } : summarize(from)))
  return paginate(people, Number(query.get('page') ?? 1), `/api/users/${username}/followers`)
})
route('GET', '/api/users/([^/]+)/following', ([username], query) => {
  const key = findPerson(decodeURIComponent(username)).key
  const people = edges().filter(([from]) => from === key).map(([, to]) => summarize(to))
  return paginate(people, Number(query.get('page') ?? 1), `/api/users/${username}/following`)
})
route('GET', '/api/feed', (_p, query) => {
  const quotes = fakeReaders
    .filter((r) => state.following.includes(r.username))
    .flatMap((r) => fakeQuotes(r).map(withLikes).map((q): FeedQuote => ({ ...q, author: { username: r.username, name: r.name } })))
    .sort(newestFirst)
  return paginate(quotes, Number(query.get('page') ?? 1), '/api/feed')
})

/** A quote that can be liked: public, of the demo user or of a fake reader. */
function findLikeable(id: number): void {
  const own = state.quotes.find((q) => q.id === id)
  const known = own ? own.visibility === 'public' : fakeReaders.some((r) => fakeQuotes(r).some((q) => q.id === id))
  if (!known) throw new HttpError(404, { message: 'Not found' })
}
route('POST', '/api/quotes/(\\d+)/like', ([id]) => {
  findLikeable(Number(id))
  if (!state.liked.includes(Number(id))) state.liked.push(Number(id))
  return null
})
route('DELETE', '/api/quotes/(\\d+)/like', ([id]) => {
  if (!state.liked.includes(Number(id))) findLikeable(Number(id))
  state.liked = state.liked.filter((liked) => liked !== Number(id))
  return null
})

// Reader: the demo cannot parse uploaded files, so it ships one sample document.
function findDocument(id: string): StoredDocument {
  const found = state.documents.find((d) => d.id === Number(id))
  if (!found) throw new HttpError(404, { message: 'Not found' })
  return found
}

// Most recently read first, never-read books after them, like the real API.
const recentlyRead = (a: StoredDocument, b: StoredDocument) =>
  Number(a.last_read_at === null) - Number(b.last_read_at === null) ||
  (b.last_read_at ?? '').localeCompare(a.last_read_at ?? '') ||
  b.id - a.id

function presentDocument(d: StoredDocument): ReaderDocument {
  const entry = state.library.find((e) => e.id === d.user_book_id)
  return { ...d, user_book: entry ? { id: entry.id, title: entry.book.title } : null }
}

route('GET', '/api/reader', () => ok([...state.documents].sort(recentlyRead).map(presentDocument)))
route('POST', '/api/reader', () => invalid('file', 'reader.invalid_epub'))
route('GET', '/api/reader/(\\d+)', ([id]) => ok(presentDocument(findDocument(id))))
route('PATCH', '/api/reader/(\\d+)', ([id], _q, body) => {
  const doc = findDocument(id)
  if (!('user_book_id' in body)) invalid('user_book_id', 'validation.required')
  const userBookId = body.user_book_id === null ? null : Number(body.user_book_id)
  if (userBookId !== null && !state.library.some((e) => e.id === userBookId)) invalid('user_book_id', 'validation.invalid')
  doc.user_book_id = userBookId
  return ok(presentDocument(doc))
})
route('DELETE', '/api/reader/(\\d+)', ([id]) => {
  const doc = findDocument(id)
  state.documents = state.documents.filter((d) => d.id !== doc.id)
  delete state.bookmarks[doc.id]
  return null
})
route('PUT', '/api/reader/(\\d+)/position', ([id], _q, body) => {
  const doc = findDocument(id)
  const chapter = Number(body.chapter)
  const fraction = Number(body.fraction)
  if (!Number.isInteger(chapter) || chapter < 0 || chapter >= doc.chapters_count) invalid('chapter', 'validation.invalid')
  if (!(fraction >= 0 && fraction <= 1)) invalid('fraction', 'validation.invalid')
  doc.position = { chapter, fraction }
  doc.last_read_at = new Date().toISOString()
  doc.progress = Math.round(((chapter + fraction) / doc.chapters_count) * 1000) / 10
  return ok(presentDocument(doc))
})
route('GET', '/api/reader/(\\d+)/chapters/(\\d+)', ([id, n]) => {
  const doc = findDocument(id)
  const chapter = demoBook.chapters[Number(n)]
  if (!chapter) throw new HttpError(404, { message: 'Not found' })
  const html = `<h1>${chapter.title}</h1>${chapter.paragraphs.map((p) => `<p>${p}</p>`).join('')}`
  return ok({ index: Number(n), count: doc.chapters_count, title: chapter.title, html } satisfies ReaderChapter)
})

const sortedBookmarks = (doc: StoredDocument) => [...(state.bookmarks[doc.id] ?? [])].sort((a, b) => a.chapter - b.chapter)

route('GET', '/api/reader/(\\d+)/bookmarks', ([id]) => ok(sortedBookmarks(findDocument(id))))
route('POST', '/api/reader/(\\d+)/bookmarks', ([id], _q, body) => {
  const doc = findDocument(id)
  const chapter = Number(body.chapter)
  if (!Number.isInteger(chapter) || chapter < 0 || chapter >= doc.chapters_count) invalid('chapter', 'validation.invalid')
  const note = typeof body.note === 'string' && body.note !== '' ? body.note : null
  if (note !== null && note.length > 200) invalid('note', 'validation.invalid')

  const list = (state.bookmarks[doc.id] ??= [])
  const existing = list.find((b) => b.chapter === chapter)
  if (existing) {
    if ('note' in body) existing.note = note
    return ok(existing)
  }
  const created: ReaderBookmark = {
    id: nextId(),
    chapter,
    chapter_title: doc.toc?.find((t) => t.index === chapter)?.title ?? null,
    note,
    created_at: new Date().toISOString(),
  }
  list.push(created)
  return ok(created)
})
route('DELETE', '/api/reader/(\\d+)/bookmarks/(\\d+)', ([id, bookmarkId]) => {
  const doc = findDocument(id)
  const list = state.bookmarks[doc.id] ?? []
  if (!list.some((b) => b.id === Number(bookmarkId))) throw new HttpError(404, { message: 'Not found' })
  state.bookmarks[doc.id] = list.filter((b) => b.id !== Number(bookmarkId))
  return null
})

route('GET', '/api/stats/summary', () => ok(summary()))
route('GET', '/api/stats/daily', (_p, query) => {
  const count = Math.min(Math.max(Number(query.get('days') ?? 30), 1), 366)
  const days = pagesByDay()
  const to = today()
  const from = addDays(to, -(count - 1))
  const data = Array.from({ length: count }, (_, i) => {
    const date = addDays(from, i)
    return { date, pages: Math.max(days.get(date) ?? 0, 0) }
  })
  return { data, meta: { from, to, total_pages: data.reduce((sum, d) => sum + d.pages, 0) } }
})
route('GET', '/api/goals/(\\d+)', ([year]) => ok(goalProgress(Number(year))))
route('PUT', '/api/goals/(\\d+)', ([year], _q, body) => {
  const y = Number(year)
  const current = new Date().getFullYear()
  if (y < current || y > current + 1) invalid('year', 'goals.year_out_of_range')
  state.goals[y] = Number(body.target_books)
  return ok(goalProgress(y))
})
route('DELETE', '/api/goals/(\\d+)', ([year]) => {
  delete state.goals[Number(year)]
  return null
})

// --- fetch --------------------------------------------------------------------------------------------------------

function respond(status: number, body: unknown): Response {
  if (body instanceof TextFile) return new Response(body.text, { status, headers: { 'Content-Type': body.type } })
  if (body === null) return new Response(null, { status: status === 200 ? 204 : status })
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

async function handle(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const url = new URL(typeof input === 'string' ? input : input instanceof URL ? input.href : input.url, 'http://demo.local')
  const method = (init?.method ?? 'GET').toUpperCase()
  const body = typeof init?.body === 'string' ? (JSON.parse(init.body) as Body) : {}

  // A little latency makes loading states visible and the demo feel like a real app.
  await new Promise((resolve) => setTimeout(resolve, 120))

  for (const r of routes) {
    const match = r.method === method ? url.pathname.match(r.pattern) : null
    if (!match) continue

    try {
      const result = r.handle(match.slice(1), url.searchParams, body)
      if (method !== 'GET') save()
      return respond(200, result)
    } catch (error) {
      if (error instanceof HttpError) return respond(error.status, error.body ?? { message: 'Error' })
      throw error
    }
  }

  return respond(404, { message: 'Not found' })
}

export function installMockApi(): void {
  const realFetch = window.fetch.bind(window)

  window.fetch = (input, init) => {
    const target = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url
    return target.startsWith('/api/') ? handle(input, init) : realFetch(input, init)
  }
}
