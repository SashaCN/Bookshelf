import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { createTestEnvironment } from '@/test/utils'
import type { GoodreadsImportResult } from '@/types/libraryImport'
import LibraryImportView from './LibraryImportView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: {
    list: vi.fn(),
    importGoodreads: vi.fn(),
  },
  catalogApi: { search: vi.fn() },
}))

async function mountView() {
  const { plugins, router } = createTestEnvironment()
  await router.push({ name: 'library-import' })
  const wrapper = mount(LibraryImportView, { global: { plugins } })
  return { wrapper, router }
}

async function pick(wrapper: Awaited<ReturnType<typeof mountView>>['wrapper'], file: File) {
  const input = wrapper.get('[data-testid="csv-input"]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
}

const csv = () => new File(['Title,Author,Exclusive Shelf\n'], 'goodreads_library_export.csv', { type: 'text/csv' })

const summary: GoodreadsImportResult = {
  imported: 2,
  skipped_existing: 5,
  skipped_invalid: 1,
  errors: [{ row: 4, reason: 'unknown_shelf' }],
}

describe('LibraryImportView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(libraryApi.list).mockResolvedValue([])
  })

  it('explains the export and keeps the button off until a file is chosen', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Export Library')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="file-name"]').text()).toBe('Файл не вибрано')
    expect(wrapper.get('label').text()).toBe('Обрати файл CSV')

    await pick(wrapper, csv())

    expect(wrapper.get('[data-testid="file-name"]').text()).toBe('goodreads_library_export.csv')

    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
  })

  it('uploads the file, shows a pending state, then the summary with plurals and reasons', async () => {
    let finish!: (value: GoodreadsImportResult) => void
    vi.mocked(libraryApi.importGoodreads).mockReturnValue(new Promise((resolve) => (finish = resolve)))
    const { wrapper } = await mountView()
    const file = csv()
    await pick(wrapper, file)

    await wrapper.get('form').trigger('submit')

    expect(libraryApi.importGoodreads).toHaveBeenCalledWith(file)
    expect(wrapper.text()).toContain('Імпортуємо…')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()

    finish(summary)
    await flushPromises()

    const text = wrapper.get('[data-testid="import-result"]').text()
    expect(text).toContain('Додано 2 книги')
    expect(text).toContain('Уже є в бібліотеці: 5')
    expect(text).toContain('Пропущено 1 рядок')
    expect(text).toContain('Рядок 4: невідома полиця')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('reloads the library store after a successful import and links back to it', async () => {
    vi.mocked(libraryApi.importGoodreads).mockResolvedValue(summary)
    const { wrapper } = await mountView()
    await pick(wrapper, csv())

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(libraryApi.list).toHaveBeenCalledTimes(1)
    const back = wrapper.findAll('a').find((a) => a.text() === 'До бібліотеки')
    expect(back?.attributes('href')).toBe('/library')
  })

  it('shows the translated message for a refused file and lets the reader retry', async () => {
    vi.mocked(libraryApi.importGoodreads).mockRejectedValue(
      new ApiError(422, { message: 'The given data was invalid.', errors: { file: ['library.import_invalid'] } }),
    )
    const { wrapper } = await mountView()
    await pick(wrapper, csv())

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Це не схоже на експорт Goodreads')
    expect(libraryApi.list).not.toHaveBeenCalled()
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
  })

  it('says it is too many requests on a 429', async () => {
    vi.mocked(libraryApi.importGoodreads).mockRejectedValue(new ApiError(429, null))
    const { wrapper } = await mountView()
    await pick(wrapper, csv())

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Забагато запитів')
  })

  it('refuses a file over 2 MB without uploading it', async () => {
    const { wrapper } = await mountView()
    const big = new File(['x'], 'big.csv')
    Object.defineProperty(big, 'size', { value: 3 * 1024 * 1024 })

    await pick(wrapper, big)

    expect(wrapper.text()).toContain('максимум 2 МБ')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    expect(libraryApi.importGoodreads).not.toHaveBeenCalled()
  })
})
