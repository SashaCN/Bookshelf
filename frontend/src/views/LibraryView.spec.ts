import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { libraryApi } from '@/api/library'
import { makeBook, makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { UserBook } from '@/types/api'
import LibraryView from './LibraryView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
    progress: vi.fn(),
    reread: vi.fn(),
    logs: vi.fn(),
  },
  catalogApi: { search: vi.fn() },
}))

async function mountView(items: UserBook[], query: Record<string, string> = {}) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue(items)
  await router.push({ name: 'library', query })

  const wrapper = mount(LibraryView, { global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

const library = [
  makeUserBook({ id: 1, status: 'want', book: makeBook({ id: 1, title: 'Want One' }) }),
  makeUserBook({ id: 2, status: 'want', book: makeBook({ id: 2, title: 'Want Two' }) }),
  makeUserBook({ id: 3, status: 'reading', current_page: 50, progress_percent: 15, book: makeBook({ id: 3, title: 'Reading One' }) }),
  makeUserBook({ id: 4, status: 'finished', rating: 5, book: makeBook({ id: 4, title: 'Finished One' }) }),
]

describe('LibraryView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('opens on the "want" tab and shows how many books each tab holds', async () => {
    const { wrapper } = await mountView(library)

    const tabs = wrapper.findAll('[role="tab"]').map((tab) => tab.text().replace(/\s+/g, ' '))

    expect(tabs).toEqual(['Хочу 2', 'Читаю 1', 'Прочитано 1', 'Відкладено 0'])
    expect(wrapper.text()).toContain('Want One')
    expect(wrapper.text()).toContain('Want Two')
    expect(wrapper.text()).not.toContain('Reading One')
  })

  it('switches tabs and remembers the choice in the URL', async () => {
    const { wrapper, router } = await mountView(library)

    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.status).toBe('reading')
    expect(wrapper.text()).toContain('Reading One')
    expect(wrapper.text()).not.toContain('Want One')
  })

  it('restores the tab from the URL', async () => {
    const { wrapper } = await mountView(library, { status: 'finished' })

    expect(wrapper.get('[aria-selected="true"]').text()).toContain('Прочитано')
    expect(wrapper.text()).toContain('Finished One')
  })

  it('falls back to "want" for an unknown tab in the URL', async () => {
    const { wrapper } = await mountView(library, { status: 'bogus' })

    expect(wrapper.get('[aria-selected="true"]').text()).toContain('Хочу')
  })

  it('shows reading progress on a card', async () => {
    const { wrapper } = await mountView(library, { status: 'reading' })

    expect(wrapper.text()).toContain('50 з 320 стор.')
  })

  it('shows an empty state with a way to add a book', async () => {
    const { wrapper } = await mountView([])

    expect(wrapper.text()).toContain('Список порожній')
    expect(wrapper.findAll('a').some((link) => link.attributes('href') === '/library/add')).toBe(true)
  })

  it('offers a retry when the library cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(library)
    await router.push({ name: 'library' })
    const wrapper = mount(LibraryView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Want One')
  })

  describe('search and sorting', () => {
    const shelf = [
      makeUserBook({ id: 10, status: 'want', rating: null, book: makeBook({ id: 10, title: 'Пам’ять', authors: ['Іван Франко'] }) }),
      makeUserBook({ id: 11, status: 'want', rating: 4, book: makeBook({ id: 11, title: 'Абетка', authors: ['Леся Українка'] }) }),
      makeUserBook({ id: 12, status: 'want', rating: 5, book: makeBook({ id: 12, title: 'Яблуко', authors: ['Андрій Бондар'] }) }),
      makeUserBook({ id: 13, status: 'finished', rating: 3, finished_at: '2024-05-01T00:00:00Z', book: makeBook({ id: 13, title: 'Пам’ятка', authors: ['Хтось'] }) }),
      makeUserBook({ id: 14, status: 'finished', rating: null, finished_at: '2025-05-01T00:00:00Z', book: makeBook({ id: 14, title: 'Нова', authors: ['Хтось'] }) }),
    ]

    const wait = (ms = 200) => new Promise((resolve) => setTimeout(resolve, ms))

    async function search(wrapper: Awaited<ReturnType<typeof mountView>>['wrapper'], value: string) {
      await wrapper.get('input[type="search"]').setValue(value)
      await wait()
      await flushPromises()
    }

    const titles = (wrapper: Awaited<ReturnType<typeof mountView>>['wrapper']) =>
      wrapper.findAll('ul > li').map((li) => li.text())

    beforeEach(() => {
      try {
        localStorage.clear()
      } catch {
        // ignore
      }
    })

    it('labels the field and filters by title, author and apostrophe variants', async () => {
      const { wrapper, router } = await mountView(shelf)

      expect(wrapper.get('label[for="library-search"]').text()).toBe('Пошук у бібліотеці')

      await search(wrapper, 'АБЕТ')
      expect(titles(wrapper)).toHaveLength(1)
      expect(wrapper.text()).toContain('Абетка')

      await search(wrapper, 'бондар')
      expect(wrapper.text()).toContain('Яблуко')
      expect(wrapper.text()).not.toContain('Абетка')

      await search(wrapper, "пам'ять")
      expect(wrapper.text()).toContain('Пам’ять')
      expect(router.currentRoute.value.query.q).toBe("пам'ять")

      await search(wrapper, 'памʼ')
      expect(wrapper.text()).toContain('Пам’ять')
    })

    it('only searches the current tab', async () => {
      const { wrapper } = await mountView(shelf, { status: 'finished' })

      await search(wrapper, 'нова')
      expect(titles(wrapper)).toHaveLength(1)

      await search(wrapper, 'абетка')
      expect(wrapper.text()).toContain('Нічого не знайдено за запитом «абетка»')
    })

    it('waits 150 ms before filtering', async () => {
      const { wrapper } = await mountView(shelf)

      await wrapper.get('input[type="search"]').setValue('бондар')
      await flushPromises()
      expect(titles(wrapper)).toHaveLength(3)

      await wait()
      await flushPromises()
      expect(titles(wrapper)).toHaveLength(1)
    })

    it('restores the query from the URL and keeps it across tabs', async () => {
      const { wrapper, router } = await mountView(shelf, { q: 'пам' })

      expect((wrapper.get('input[type="search"]').element as HTMLInputElement).value).toBe('пам')
      expect(titles(wrapper)).toHaveLength(1)

      await wrapper.findAll('[role="tab"]')[2]!.trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toMatchObject({ status: 'finished', q: 'пам' })
      expect(wrapper.text()).toContain('Пам’ятка')
    })

    it('clears the search from the empty result and from the field', async () => {
      const { wrapper, router } = await mountView(shelf)

      await search(wrapper, 'xyz')
      expect(wrapper.text()).toContain('Нічого не знайдено за запитом «xyz»')
      expect(wrapper.text()).not.toContain('Список порожній')

      const clear = wrapper.findAll('button').filter((b) => b.text() === 'Очистити пошук')
      await clear[0]!.trigger('click')
      await flushPromises()

      expect(titles(wrapper)).toHaveLength(3)
      expect(router.currentRoute.value.query.q).toBeUndefined()

      await search(wrapper, 'абет')
      await wrapper.get('button[aria-label="Очистити пошук"]').trigger('click')
      await flushPromises()
      expect(titles(wrapper)).toHaveLength(3)
      expect((wrapper.get('input[type="search"]').element as HTMLInputElement).value).toBe('')
    })

    it('sorts by title, author and rating', async () => {
      const { wrapper } = await mountView(shelf)
      const select = wrapper.get('select')

      expect(wrapper.get('label[for="library-sort"]').text()).toBe('Сортування')
      expect(select.findAll('option').map((o) => o.text())).toEqual([
        'Нещодавно додані',
        'Назва А–Я',
        'Автор А–Я',
        'Оцінка',
        'Нещодавно прочитані',
      ])
      expect(titles(wrapper)[0]).toContain('Пам’ять')

      await select.setValue('title')
      await flushPromises()
      expect([titles(wrapper)[0], titles(wrapper)[2]]).toEqual([expect.stringContaining('Абетка'), expect.stringContaining('Яблуко')])

      await select.setValue('author')
      await flushPromises()
      expect(titles(wrapper)[0]).toContain('Яблуко')

      await select.setValue('rating')
      await flushPromises()
      expect(titles(wrapper)[0]).toContain('Яблуко')
      expect(titles(wrapper)[2]).toContain('Пам’ять')
    })

    it('sorts by the finish date and remembers the sort in the URL and storage', async () => {
      const { wrapper, router } = await mountView(shelf, { status: 'finished' })

      await wrapper.get('select').setValue('finished')
      await flushPromises()

      expect(titles(wrapper)[0]).toContain('Нова')
      expect(router.currentRoute.value.query.sort).toBe('finished')
      expect(localStorage.getItem('bookshelf.librarySort')).toBe('finished')
    })

    it('uses the stored sort when the URL has none, and the URL wins otherwise', async () => {
      localStorage.setItem('bookshelf.librarySort', 'title')
      const first = await mountView(shelf)
      expect((first.wrapper.get('select').element as HTMLSelectElement).value).toBe('title')
      expect(titles(first.wrapper)[0]).toContain('Абетка')

      const second = await mountView(shelf, { sort: 'rating' })
      expect((second.wrapper.get('select').element as HTMLSelectElement).value).toBe('rating')
    })

    it('hides search and sort when the library is empty', async () => {
      const { wrapper } = await mountView([])

      expect(wrapper.find('input[type="search"]').exists()).toBe(false)
      expect(wrapper.find('select').exists()).toBe(false)
    })
  })
})
