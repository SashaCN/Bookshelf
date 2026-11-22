import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { readerApi } from '@/api/reader'
import { makeReaderChapter, makeReaderDoc } from '@/test/readerFixtures'
import { createTestEnvironment } from '@/test/utils'
import ReaderView from './ReaderView.vue'

vi.mock('@/api/reader', () => ({
  readerApi: { list: vi.fn(), get: vi.fn(), upload: vi.fn(), remove: vi.fn(), savePosition: vi.fn(), chapter: vi.fn() },
}))

async function mountView(chapter = 2) {
  vi.mocked(readerApi.get).mockResolvedValue(makeReaderDoc({ id: 4, position: { chapter, fraction: 0 } }))
  vi.mocked(readerApi.chapter).mockImplementation(async (_id, index) =>
    makeReaderChapter({ index, html: `<p>Розділ-${index}</p>` }),
  )
  vi.mocked(readerApi.savePosition).mockResolvedValue(makeReaderDoc())
  const { plugins } = createTestEnvironment()
  const wrapper = mount(ReaderView, { props: { id: 4 }, global: { plugins } })
  await flushPromises()
  return wrapper
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('ReaderView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    window.localStorage.clear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('opens at the saved chapter inside a strict sandbox', async () => {
    const wrapper = await mountView(2)

    const frame = wrapper.get('iframe')
    expect(readerApi.chapter).toHaveBeenCalledWith(4, 2)
    expect(frame.attributes('sandbox')).toBe('')
    expect(frame.attributes('srcdoc')).toContain('http-equiv="Content-Security-Policy"')
    expect(frame.attributes('srcdoc')).toContain("default-src 'none'")
    expect(frame.attributes('srcdoc')).toContain('<p>Розділ-2</p>')
    expect(wrapper.text()).toContain('Розділ 3 з 12')
  })

  it('turns pages and saves the position once, debounced', async () => {
    const wrapper = await mountView(2)

    await buttonWithText(wrapper, 'Наступний розділ').trigger('click')
    await flushPromises()
    await buttonWithText(wrapper, 'Наступний розділ').trigger('click')
    await flushPromises()

    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Розділ-4')
    expect(readerApi.savePosition).not.toHaveBeenCalled()

    vi.advanceTimersByTime(800)

    expect(readerApi.savePosition).toHaveBeenCalledTimes(1)
    expect(readerApi.savePosition).toHaveBeenCalledWith(4, { chapter: 4, fraction: 0 })

    await buttonWithText(wrapper, 'Попередній розділ').trigger('click')
    await flushPromises()
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Розділ-3')
  })

  it('disables the buttons at both ends', async () => {
    const first = await mountView(0)
    expect(buttonWithText(first, 'Попередній розділ').attributes('disabled')).toBeDefined()

    const last = await mountView(11)
    expect(buttonWithText(last, 'Наступний розділ').attributes('disabled')).toBeDefined()
  })

  it('changes and remembers the font size within 14-28', async () => {
    const wrapper = await mountView(0)
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('font-size: 18px')

    await wrapper.get('[aria-label="Збільшити шрифт"]').trigger('click')
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('font-size: 20px')
    expect(window.localStorage.getItem('reader.fontSize')).toBe('20')

    for (let i = 0; i < 10; i++) await wrapper.get('[aria-label="Зменшити шрифт"]').trigger('click')
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('font-size: 14px')
  })
})
