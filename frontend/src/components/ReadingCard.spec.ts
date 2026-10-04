import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { useLibraryStore } from '@/stores/library'
import { makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { ProgressResult, UserBook } from '@/types/api'
import ReadingCard from './ReadingCard.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), update: vi.fn(), progress: vi.fn() },
}))

function reading(overrides: Partial<UserBook> = {}): UserBook {
  return makeUserBook({
    status: 'reading',
    allowed_statuses: ['finished', 'abandoned'],
    current_page: 100,
    total_pages: 320,
    progress_percent: 31,
    ...overrides,
  })
}

/** What the server answers to a progress update: the book on the new page and the pages it was worth. */
function answer(userBook: UserBook, page: number, pages: number, streak = 1): ProgressResult {
  return {
    data: { ...userBook, current_page: page, progress_percent: Math.floor((page / 320) * 100) },
    meta: { pages, reached_end: page === 320, streak },
  }
}

async function mountCard(userBook: UserBook) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue([userBook])
  const library = useLibraryStore()
  await library.load()
  await router.push('/')

  // The card reads the book from the store, the way the home screen passes it in.
  const wrapper = mount(
    { components: { ReadingCard }, template: '<ReadingCard :user-book="book" />', computed: { book: () => library.find(userBook.id)! } },
    { global: { plugins } },
  )

  return { wrapper, library }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('ReadingCard', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the book with its progress', async () => {
    const { wrapper } = await mountCard(reading())

    expect(wrapper.text()).toContain('Atomic Habits')
    expect(wrapper.text()).toContain('100 з 320 стор.')
    expect(wrapper.text()).toContain('31%')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('31')
  })

  it('adds ten pages with one tap and shows the new page before the server answers', async () => {
    const userBook = reading()
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress).mockReturnValue(new Promise(() => {}))

    await buttonWithText(wrapper, '+10').trigger('click')

    expect(libraryApi.progress).toHaveBeenCalledWith(1, 110)
    expect(wrapper.text()).toContain('110 з 320 стор.')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('34')
  })

  it('tells how many pages the update was worth', async () => {
    const userBook = reading()
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress).mockResolvedValue(answer(userBook, 125, 25))

    await buttonWithText(wrapper, '+25').trigger('click')
    await flushPromises()

    expect(wrapper.get('[aria-live="polite"]').text()).toBe('+25 стор.')
  })

  it('adds the streak to the note from the second day in a row', async () => {
    const userBook = reading()
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress).mockResolvedValue(answer(userBook, 118, 18, 5))

    await buttonWithText(wrapper, 'Я на сторінці…').trigger('click')
    await wrapper.get('input').setValue('118')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[aria-live="polite"]').text()).toBe('+18 стор. · 🔥 5')
  })

  it('does not boast about a streak of one day', async () => {
    const userBook = reading()
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress).mockResolvedValue(answer(userBook, 110, 10, 1))

    await buttonWithText(wrapper, '+10').trigger('click')
    await flushPromises()

    expect(wrapper.get('[aria-live="polite"]').text()).toBe('+10 стор.')
  })

  it('adds up quick taps into one note, which fades after a moment', async () => {
    const userBook = reading()
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress)
      .mockResolvedValueOnce(answer(userBook, 110, 10))
      .mockResolvedValueOnce(answer(userBook, 135, 25))

    await buttonWithText(wrapper, '+10').trigger('click')
    await buttonWithText(wrapper, '+25').trigger('click')
    await flushPromises()

    expect(libraryApi.progress).toHaveBeenNthCalledWith(1, 1, 110)
    expect(libraryApi.progress).toHaveBeenNthCalledWith(2, 1, 135)
    expect(wrapper.get('[aria-live="polite"]').text()).toBe('+35 стор.')

    await vi.advanceTimersByTimeAsync(4000)

    expect(wrapper.get('[aria-live="polite"]').text()).toBe('')
  })

  it('never goes past the last page', async () => {
    const userBook = reading({ current_page: 310, progress_percent: 96 })
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress).mockResolvedValue(answer(userBook, 320, 10))

    await buttonWithText(wrapper, '+25').trigger('click')
    await flushPromises()

    expect(libraryApi.progress).toHaveBeenCalledWith(1, 320)
  })

  it('moves to the page typed in "Я на сторінці…"', async () => {
    const userBook = reading()
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress).mockResolvedValue(answer(userBook, 200, 100))

    await buttonWithText(wrapper, 'Я на сторінці…').trigger('click')
    await wrapper.get('input').setValue('200')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(libraryApi.progress).toHaveBeenCalledWith(1, 200)
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('200 з 320 стор.')
    expect(wrapper.get('[aria-live="polite"]').text()).toBe('+100 стор.')
  })

  it('will not send a page outside the book', async () => {
    const { wrapper } = await mountCard(reading())

    await buttonWithText(wrapper, 'Я на сторінці…').trigger('click')
    await wrapper.get('input').setValue('321')

    expect(wrapper.text()).toContain('Введіть номер сторінки від 0 до 320.')
    expect(buttonWithText(wrapper, 'Зберегти').attributes('disabled')).toBeDefined()

    await wrapper.get('form').trigger('submit')
    expect(libraryApi.progress).not.toHaveBeenCalled()
  })

  it('lets the reader cancel typing a page', async () => {
    const { wrapper } = await mountCard(reading())

    await buttonWithText(wrapper, 'Я на сторінці…').trigger('click')
    await buttonWithText(wrapper, 'Скасувати').trigger('click')

    expect(wrapper.find('form').exists()).toBe(false)
    expect(buttonWithText(wrapper, '+10').exists()).toBe(true)
  })

  it('shows the real page again and explains when the server turns an update down', async () => {
    const userBook = reading()
    const { wrapper } = await mountCard(userBook)
    vi.mocked(libraryApi.progress).mockRejectedValue(new ApiError(422, { errors: { page: ['library.page_above_total'] } }))
    vi.mocked(libraryApi.get).mockResolvedValue(userBook)

    await buttonWithText(wrapper, '+10').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('У книзі немає такої сторінки.')
    expect(wrapper.text()).toContain('100 з 320 стор.')
  })

  it('offers to finish the book on the last page and hides the quick buttons', async () => {
    const userBook = reading({ current_page: 320, progress_percent: 100 })
    const { wrapper, library } = await mountCard(userBook)

    expect(wrapper.text()).toContain('Дочитали до кінця!')
    expect(() => buttonWithText(wrapper, '+10')).toThrow()

    vi.mocked(libraryApi.update).mockResolvedValue({ ...userBook, status: 'finished', allowed_statuses: ['reading'] })
    await buttonWithText(wrapper, 'Позначити прочитаною').trigger('click')
    await flushPromises()

    expect(libraryApi.update).toHaveBeenCalledWith(1, { status: 'finished' })
    expect(library.find(1)?.status).toBe('finished')
  })

  it('links to the book', async () => {
    const { wrapper } = await mountCard(reading())

    expect(wrapper.get('a').attributes('href')).toBe('/library/1')
  })
})
