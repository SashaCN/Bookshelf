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
})
