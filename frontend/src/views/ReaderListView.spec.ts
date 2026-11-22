import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { readerApi } from '@/api/reader'
import { makeReaderDoc } from '@/test/readerFixtures'
import { createTestEnvironment } from '@/test/utils'
import ReaderListView from './ReaderListView.vue'

vi.mock('@/api/reader', () => ({
  readerApi: { list: vi.fn(), get: vi.fn(), upload: vi.fn(), remove: vi.fn(), savePosition: vi.fn(), chapter: vi.fn() },
}))

async function mountView() {
  const { plugins } = createTestEnvironment()
  const wrapper = mount(ReaderListView, { global: { plugins } })
  await flushPromises()
  return wrapper
}

async function pickFile(wrapper: ReturnType<typeof mount>) {
  const input = wrapper.get('[data-testid="epub-input"]')
  Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'a.epub')], configurable: true })
  await input.trigger('change')
  await flushPromises()
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('ReaderListView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists books with author and percent', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1, title: 'Кобзар', progress: 42 })])

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Кобзар')
    expect(wrapper.text()).toContain('Тарас Шевченко')
    expect(wrapper.text()).toContain('42%')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('42')
    expect(wrapper.get('input[type="file"]').attributes('accept')).toBe('.epub,application/epub+zip')
  })

  it('keeps the upload out of the header, as a full-width button under it', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc()])
    const wrapper = await mountView()

    expect(wrapper.get('header').text()).not.toContain('Завантажити EPUB')
    expect(buttonWithText(wrapper, 'Завантажити EPUB').classes()).toContain('w-full')
  })

  it('shows just the author name and the chapter', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([
      makeReaderDoc({ position: { chapter: 2, fraction: 0.1 }, chapters_count: 12, progress: 20 }),
    ])
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Тарас Шевченко')
    expect(wrapper.text()).not.toContain('Автор:')
    expect(wrapper.text()).toContain('Розділ 3 з 12')
  })

  it('offers to read a new book and to continue a started one', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([
      makeReaderDoc({ id: 1, progress: 0 }),
      makeReaderDoc({ id: 2, progress: 30 }),
    ])
    const wrapper = await mountView()

    const links = wrapper.findAll('a').filter((a) => ['Читати', 'Продовжити'].includes(a.text()))
    expect(links.map((a) => [a.text(), a.attributes('href')])).toEqual([
      ['Читати', '/reader/1'],
      ['Продовжити', '/reader/2'],
    ])
  })

  it('shows an empty state', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([])

    expect((await mountView()).text()).toContain('Тут поки порожньо')
  })

  it('uploads a file and shows the new book', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([])
    vi.mocked(readerApi.upload).mockResolvedValue(makeReaderDoc({ id: 7, title: 'Нова' }))
    const wrapper = await mountView()

    await pickFile(wrapper)

    expect(readerApi.upload).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Нова')
  })

  it('shows the message for an upload error code', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([])
    vi.mocked(readerApi.upload).mockRejectedValue(new ApiError(422, { message: 'reader.not_epub' }))
    const wrapper = await mountView()

    await pickFile(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toContain('Це не схоже на файл EPUB.')
  })

  it('deletes only after confirmation', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1 })])
    vi.mocked(readerApi.remove).mockResolvedValue(null)
    const wrapper = await mountView()

    await buttonWithText(wrapper, 'Видалити').trigger('click')
    expect(readerApi.remove).not.toHaveBeenCalled()
    await buttonWithText(wrapper, 'Так, видалити').trigger('click')
    await flushPromises()

    expect(readerApi.remove).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('Тут поки порожньо')
  })

  it('offers the upload right in the empty state', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([])
    vi.mocked(readerApi.upload).mockResolvedValue(makeReaderDoc({ id: 7, title: 'Нова' }))
    const wrapper = await mountView()
    const click = vi.spyOn(wrapper.get('[data-testid="epub-input"]').element as HTMLInputElement, 'click')

    const buttons = wrapper.findAll('button').filter((button) => button.text() === 'Завантажити EPUB')
    expect(buttons).toHaveLength(1)
    await buttons[0].trigger('click')

    expect(click).toHaveBeenCalled()
  })

  it('shows progress and disables the upload button while uploading', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([])
    let finish: (doc: ReturnType<typeof makeReaderDoc>) => void = () => undefined
    vi.mocked(readerApi.upload).mockReturnValue(new Promise((resolve) => { finish = resolve }))
    const wrapper = await mountView()

    await pickFile(wrapper)

    expect(wrapper.get('[role="status"]').text()).toBe('Завантажуємо…')
    const buttons = wrapper.findAll('button').filter((button) => button.text() === 'Завантажуємо…')
    expect(buttons).toHaveLength(1)
    buttons.forEach((button) => expect(button.attributes('disabled')).toBeDefined())

    finish(makeReaderDoc({ id: 7, title: 'Нова' }))
    await flushPromises()

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Нова')
  })

  it('refuses a file over 20 MB without sending it', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([])
    const wrapper = await mountView()
    const input = wrapper.get('[data-testid="epub-input"]')
    const big = new File(['x'], 'big.epub')
    Object.defineProperty(big, 'size', { value: 21 * 1024 * 1024 })
    Object.defineProperty(input.element, 'files', { value: [big], configurable: true })

    await input.trigger('change')
    await flushPromises()

    expect(readerApi.upload).not.toHaveBeenCalled()
    expect(wrapper.get('[role="alert"]').text()).toContain('максимум 20 МБ')
  })

  it('ignores a cancelled file dialog', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([])
    const wrapper = await mountView()

    await wrapper.get('[data-testid="epub-input"]').trigger('change')

    expect(readerApi.upload).not.toHaveBeenCalled()
  })

  it('names the book for screen readers and can cancel the deletion', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1, title: 'Кобзар' })])
    const wrapper = await mountView()

    await wrapper.get('[aria-label="Видалити «Кобзар»"]').trigger('click')
    expect(wrapper.text()).toContain('Видалити цю книгу з читалки?')
    await buttonWithText(wrapper, 'Скасувати').trigger('click')

    expect(readerApi.remove).not.toHaveBeenCalled()
    expect(wrapper.find('[aria-label="Видалити «Кобзар»"]').exists()).toBe(true)
  })

  it('shows the pending state of a deletion and an error when it fails', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1 })])
    let fail: (error: Error) => void = () => undefined
    vi.mocked(readerApi.remove).mockReturnValue(new Promise((_, reject) => { fail = reject }))
    const wrapper = await mountView()

    await buttonWithText(wrapper, 'Видалити').trigger('click')
    await buttonWithText(wrapper, 'Так, видалити').trigger('click')

    expect(buttonWithText(wrapper, 'Видаляємо…').attributes('disabled')).toBeDefined()
    expect(buttonWithText(wrapper, 'Скасувати').attributes('disabled')).toBeDefined()

    fail(new ApiError(500, null))
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')
    expect(wrapper.text()).toContain('Кобзар')
  })

  it('shows an error with a retry when the list cannot be loaded', async () => {
    vi.mocked(readerApi.list).mockRejectedValueOnce(new ApiError(500, null))
    const wrapper = await mountView()
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ title: 'Кобзар' })])

    await buttonWithText(wrapper, 'Повторити').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Кобзар')
  })

  it('shows a loading note and links each book to the reader', async () => {
    vi.mocked(readerApi.list).mockReturnValue(new Promise(() => undefined))
    const { plugins } = createTestEnvironment()
    const wrapper = mount(ReaderListView, { global: { plugins } })

    expect(wrapper.text()).toContain('Завантаження…')

    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 3 })])
    const loaded = await mountView()
    expect(loaded.get('a[href="/reader/3"]').text()).toContain('Кобзар')
  })
})
