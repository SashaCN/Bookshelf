import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { catalogApi, libraryApi } from '@/api/library'
import { makeEntry, makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import AddBookView from './AddBookView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
    progress: vi.fn(),
    logs: vi.fn(),
  },
  catalogApi: { search: vi.fn() },
}))

async function mountView() {
  const { plugins, router } = createTestEnvironment()
  await router.push({ name: 'library-add' })
  const wrapper = mount(AddBookView, { global: { plugins } })
  return { wrapper, router }
}

async function type(wrapper: Awaited<ReturnType<typeof mountView>>['wrapper'], text: string) {
  await wrapper.get('#catalog-search').setValue(text)
}

/** Lets the debounce expire and the search promise settle. */
async function settleSearch() {
  await vi.advanceTimersByTimeAsync(400)
  await flushPromises()
}

describe('AddBookView', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.resetAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('searching the catalog', () => {
    it('does not search for a single character', async () => {
      const { wrapper } = await mountView()

      await type(wrapper, 'a')
      await settleSearch()

      expect(catalogApi.search).not.toHaveBeenCalled()
    })

    it('waits for the reader to stop typing and searches once', async () => {
      vi.mocked(catalogApi.search).mockResolvedValue([])
      const { wrapper } = await mountView()

      await type(wrapper, 'ato')
      await vi.advanceTimersByTimeAsync(200)
      await type(wrapper, 'atomic')
      await settleSearch()

      expect(catalogApi.search).toHaveBeenCalledTimes(1)
      expect(catalogApi.search).toHaveBeenCalledWith('atomic')
    })

    it('lists the results and marks books that are already in the library', async () => {
      vi.mocked(catalogApi.search).mockResolvedValue([
        makeEntry({ work_key: '/works/OL1W', title: 'Atomic Habits' }),
        makeEntry({ work_key: '/works/OL2W', title: 'Deep Work', in_library: true }),
      ])
      const { wrapper } = await mountView()

      await type(wrapper, 'work')
      await settleSearch()

      const items = wrapper.findAll('li')
      expect(items).toHaveLength(2)
      expect(items[0]!.text()).toContain('Atomic Habits')
      expect(items[0]!.find('button').text()).toBe('Додати')
      expect(items[1]!.find('button').exists()).toBe(false)
      expect(items[1]!.text()).toContain('У бібліотеці')
    })

    it('says so when nothing is found', async () => {
      vi.mocked(catalogApi.search).mockResolvedValue([])
      const { wrapper } = await mountView()

      await type(wrapper, 'zzzz')
      await settleSearch()

      expect(wrapper.text()).toContain('Нічого не знайшли.')
    })

    it('ignores an answer that arrives after a newer search was started', async () => {
      let resolveFirst: (entries: ReturnType<typeof makeEntry>[]) => void = () => {}
      vi.mocked(catalogApi.search)
        .mockImplementationOnce(() => new Promise((resolve) => (resolveFirst = resolve)))
        .mockResolvedValueOnce([makeEntry({ title: 'Second answer' })])
      const { wrapper } = await mountView()

      await type(wrapper, 'first')
      await settleSearch()
      await type(wrapper, 'second')
      await settleSearch()

      resolveFirst([makeEntry({ title: 'Stale answer' })])
      await flushPromises()

      expect(wrapper.text()).toContain('Second answer')
      expect(wrapper.text()).not.toContain('Stale answer')
    })

    it('offers manual entry when the catalog is down', async () => {
      vi.mocked(catalogApi.search).mockRejectedValue(new ApiError(503, { message: 'catalog.unavailable' }))
      const { wrapper } = await mountView()

      await type(wrapper, 'Тигролови')
      await settleSearch()

      expect(wrapper.get('[role="alert"]').text()).toContain('Каталог Open Library зараз недоступний')

      await wrapper.get('button.text-accent').trigger('click')

      // The search text becomes the title of the manual form.
      expect((wrapper.get('#manual-title').element as HTMLInputElement).value).toBe('Тигролови')
    })
  })

  describe('adding', () => {
    it('adds a catalog book and opens it', async () => {
      vi.mocked(catalogApi.search).mockResolvedValue([makeEntry({ work_key: '/works/OL1W' })])
      vi.mocked(libraryApi.create).mockResolvedValue(makeUserBook({ id: 42 }))
      const { wrapper, router } = await mountView()
      await type(wrapper, 'atomic')
      await settleSearch()

      await wrapper.get('li button').trigger('click')
      await flushPromises()

      expect(libraryApi.create).toHaveBeenCalledWith({ openlibrary_work_key: '/works/OL1W' })
      expect(router.currentRoute.value.name).toBe('book')
      expect(router.currentRoute.value.params.id).toBe('42')
    })

    it('opens the existing entry when the book is already in the library', async () => {
      vi.mocked(catalogApi.search).mockResolvedValue([makeEntry()])
      vi.mocked(libraryApi.create).mockRejectedValue(
        new ApiError(409, { message: 'library.already_added', user_book_id: 7 }),
      )
      const { wrapper, router } = await mountView()
      await type(wrapper, 'atomic')
      await settleSearch()

      await wrapper.get('li button').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.params.id).toBe('7')
    })

    it('shows a readable message when adding fails', async () => {
      vi.mocked(catalogApi.search).mockResolvedValue([makeEntry()])
      vi.mocked(libraryApi.create).mockRejectedValue(new ApiError(500, null))
      const { wrapper } = await mountView()
      await type(wrapper, 'atomic')
      await settleSearch()

      await wrapper.get('li button').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
    })

    it('adds a book by hand with authors split on commas', async () => {
      vi.mocked(libraryApi.create).mockResolvedValue(makeUserBook({ id: 5 }))
      const { wrapper, router } = await mountView()
      await wrapper.get('button.text-accent').trigger('click')

      await wrapper.get('#manual-title').setValue('  Тигролови  ')
      await wrapper.get('#manual-authors').setValue('Іван Багряний, , Редактор ')
      await wrapper.get('#manual-pages').setValue('320')
      await wrapper.get('#manual-year').setValue('')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(libraryApi.create).toHaveBeenCalledWith({
        title: 'Тигролови',
        authors: ['Іван Багряний', 'Редактор'],
        total_pages: 320,
        published_year: null,
      })
      expect(router.currentRoute.value.params.id).toBe('5')
    })
  })
})
