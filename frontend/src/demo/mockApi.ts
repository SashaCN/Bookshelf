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
  GoalProgress,
  Quote,
  QuoteType,
  ReadingLog,
  User,
  UserBook,
} from '@/types/api'

const STORAGE_KEY = 'bookshelf-demo-state-v1'
const PAGE_SIZE = 15
const DAY_MS = 86_400_000

interface Entry extends Omit<UserBook, 'book' | 'allowed_statuses' | 'progress_percent'> {
  book: Book
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
}

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
      started_at: daysAgo(40).toISOString(),
      finished_at: daysAgo(35).toISOString(),
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
    created_at: daysAgo(ago).toISOString(),
    user_book_id: userBookId,
    ...extra,
  })

  const quotes: StoredQuote[] = [
    quote(1, 3, 'quote', 'Зорко одне лише серце. Найголовнішого очима не побачиш.', 72, 36, {
      note: 'Те, до чого повертаюся щоразу, коли забуваю зупинитися.',
      is_favorite: true,
    }),
    quote(2, 3, 'quote', 'Ти назавжди береш на себе відповідальність за тих, кого приручив.', 80, 36, { is_favorite: true }),
    quote(3, 1, 'quote', 'Життя не те, що з тобою сталося, а те, що ти пам\'ятаєш і як ти про це розповідаєш.', 130, 9),
    quote(4, 1, 'insight', 'Родина Буендіа повторює імена й помилки — ніби історія ходить колами. Цікаво, чи є вихід із циклу.', 160, 6, {
      note: 'Порівняти з Орвеллом — там цикл насильницький, тут — самотність.',
    }),
    quote(5, 4, 'quote', 'Хто керує минулим, той керує майбутнім; хто керує сьогоденням, той керує минулим.', 44, 63, { is_favorite: true }),
    quote(6, 5, 'quote', 'Contra spem spero! Без надії сподіваюсь.', 97, 125),
    quote(7, 2, 'quote', 'Гуцульщина — це не місце на мапі, це спосіб слухати гори.', 40, 3),
    quote(8, 2, 'insight', 'У Коцюбинського природа не тло, а персонаж: вона вирішує долю Івана не менше за людей.', 52, 2),
  ]

  return {
    user: { id: 1, name: 'Демо-читач', email: 'demo@bookshelf.test', timezone: 'Europe/Kyiv' },
    signedIn: true,
    nextId: 100,
    library,
    logs,
    quotes,
    goals: { [new Date().getFullYear()]: 12 },
  }
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
  }
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

function invalid(field: string, code: string): never {
  throw new HttpError(422, { message: code, errors: { [field]: [code] } })
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
  return state.library.filter((e) => e.finished_at?.startsWith(prefix) || (e.finished_at && isoDay(new Date(e.finished_at)).startsWith(prefix))).length
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
  return ok(state.user)
})
// Any credentials work in the demo, so nobody needs to remember a password.
route('POST', '/api/auth/login', () => {
  state.signedIn = true
  return null
})
route('POST', '/api/auth/register', (_p, _q, body) => {
  state = seed()
  state.user = { ...state.user, name: String(body.name ?? 'Читач'), email: String(body.email ?? ''), timezone: String(body.timezone ?? 'Europe/Kyiv') }
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
    started_at: null,
    finished_at: null,
    created_at: new Date().toISOString(),
    book: newBook,
  }
  state.library.unshift(entry)
  if (body.status && body.status !== 'want') changeStatus(entry, body.status as BookStatus)
  return ok(presentEntry(entry))
})
route('GET', '/api/library/(\\d+)', ([id]) => ok(presentEntry(findEntry(Number(id)))))
route('PATCH', '/api/library/(\\d+)', ([id], _q, body) => {
  const e = findEntry(Number(id))

  if (body.total_pages !== undefined) {
    const pages = Number(body.total_pages)
    if (pages < e.current_page) invalid('total_pages', 'library.total_pages_below_current')
    e.total_pages = pages
  }
  if ('rating' in body) e.rating = body.rating as number | null
  if (body.status) changeStatus(e, body.status as BookStatus)

  state.library = [e, ...state.library.filter((x) => x.id !== e.id)]
  return ok(presentEntry(e))
})
route('DELETE', '/api/library/(\\d+)', ([id]) => {
  const e = findEntry(Number(id))
  state.library = state.library.filter((x) => x.id !== e.id)
  state.quotes = state.quotes.filter((q) => q.user_book_id !== e.id)
  delete state.logs[e.id]
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
route('GET', '/api/quotes', (_p, query) => {
  const page = Number(query.get('page') ?? 1)
  const all = state.quotes
    .filter((q) => query.get('favorite') !== '1' || q.is_favorite)
    .filter((q) => !query.get('book') || q.user_book_id === Number(query.get('book')))
    .filter((q) => !query.get('type') || q.type === query.get('type'))
    .sort((a, b) => b.created_at.localeCompare(a.created_at) || b.id - a.id)
  const slice = all.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)
  return { data: slice.map(presentQuote), links: { next: page * PAGE_SIZE < all.length ? `/api/quotes?page=${page + 1}` : null } }
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
