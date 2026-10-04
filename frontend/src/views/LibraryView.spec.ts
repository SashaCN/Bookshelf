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
})
