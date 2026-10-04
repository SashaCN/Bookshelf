import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { useLibraryStore } from '@/stores/library'
import { makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { UserBook } from '@/types/api'
import BookView from './BookView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
  catalogApi: { search: vi.fn() },
}))

async function mountView(userBook: UserBook) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue([userBook])
  await router.push({ name: 'book', params: { id: userBook.id } })

  const wrapper = mount(BookView, { props: { id: userBook.id }, global: { plugins } })
  await flushPromises()

  return { wrapper, router, library: useLibraryStore() }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('BookView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows the book, its status and its progress', async () => {
    const { wrapper } = await mountView(
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 160, progress_percent: 50 }),
    )

    expect(wrapper.text()).toContain('Atomic Habits')
    expect(wrapper.text()).toContain('James Clear')
    expect(wrapper.text()).toContain('160 з 320 стор.')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('50')
  })

  it('offers exactly the actions the server allows', async () => {
    const { wrapper } = await mountView(makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'] }))

    expect(buttonWithText(wrapper, 'Прочитано').exists()).toBe(true)
    expect(buttonWithText(wrapper, 'Відкласти').exists()).toBe(true)
    expect(() => buttonWithText(wrapper, 'Почати читати')).toThrow()
  })

  it('moves to the chosen status', async () => {
    const { wrapper, library } = await mountView(makeUserBook())
    vi.mocked(libraryApi.update).mockResolvedValue(
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'] }),
    )

    await buttonWithText(wrapper, 'Почати читати').trigger('click')
    await flushPromises()

    expect(libraryApi.update).toHaveBeenCalledWith(1, { status: 'reading' })
    expect(library.find(1)?.status).toBe('reading')
    expect(wrapper.text()).toContain('Читаю')
  })

  it('will not start reading a book with an unknown page count', async () => {
    const { wrapper } = await mountView(makeUserBook({ total_pages: null }))

    expect(buttonWithText(wrapper, 'Почати читати').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Щоб почати читати, вкажіть кількість сторінок.')
  })

  it('saves the page count and then allows starting', async () => {
    const { wrapper } = await mountView(makeUserBook({ total_pages: null }))
    vi.mocked(libraryApi.update).mockResolvedValue(makeUserBook({ total_pages: 250 }))

    await wrapper.get('#total-pages').setValue('250')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(libraryApi.update).toHaveBeenCalledWith(1, { total_pages: 250 })
    expect(buttonWithText(wrapper, 'Почати читати').attributes('disabled')).toBeUndefined()
  })

  it('explains a rejected change in the reader\'s language', async () => {
    const { wrapper } = await mountView(makeUserBook())
    vi.mocked(libraryApi.update).mockRejectedValue(
      new ApiError(422, { errors: { status: ['library.invalid_transition'] } }),
    )

    await buttonWithText(wrapper, 'Почати читати').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('З поточного статусу не можна перейти до цього.')
  })

  it('saves a rating and clears it when the same star is tapped again', async () => {
    const { wrapper } = await mountView(makeUserBook({ status: 'finished', allowed_statuses: ['reading'] }))
    vi.mocked(libraryApi.update)
      .mockResolvedValueOnce(makeUserBook({ status: 'finished', rating: 4 }))
      .mockResolvedValueOnce(makeUserBook({ status: 'finished', rating: null }))

    await wrapper.findAll('[aria-label="4 / 5"]')[0]!.trigger('click')
    await flushPromises()
    await wrapper.findAll('[aria-label="4 / 5"]')[0]!.trigger('click')
    await flushPromises()

    expect(libraryApi.update).toHaveBeenNthCalledWith(1, 1, { rating: 4 })
    expect(libraryApi.update).toHaveBeenNthCalledWith(2, 1, { rating: null })
  })

  it('asks for confirmation before removing, then returns to the library', async () => {
    const { wrapper, router } = await mountView(makeUserBook())
    vi.mocked(libraryApi.remove).mockResolvedValue(null)

    await buttonWithText(wrapper, 'Видалити з бібліотеки').trigger('click')
    expect(libraryApi.remove).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Книга зникне з вашої бібліотеки')

    await buttonWithText(wrapper, 'Видалити').trigger('click')
    await flushPromises()

    expect(libraryApi.remove).toHaveBeenCalledWith(1)
    expect(router.currentRoute.value.name).toBe('library')
  })

  it('lets the reader back out of removing', async () => {
    const { wrapper } = await mountView(makeUserBook())

    await buttonWithText(wrapper, 'Видалити з бібліотеки').trigger('click')
    await buttonWithText(wrapper, 'Скасувати').trigger('click')

    expect(libraryApi.remove).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('Книга зникне з вашої бібліотеки')
  })

  it('says so when the book does not exist', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockResolvedValue([])
    vi.mocked(libraryApi.get).mockRejectedValue(new ApiError(404, { message: 'Not found' }))
    await router.push({ name: 'book', params: { id: 99 } })

    const wrapper = mount(BookView, { props: { id: 99 }, global: { plugins } })
    await flushPromises()

    expect(wrapper.text()).toContain('Цієї книги немає у вашій бібліотеці.')
  })
})
