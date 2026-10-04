import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { quotesApi } from '@/api/quotes'
import { useQuotesStore } from '@/stores/quotes'
import { makeQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Quote } from '@/types/api'
import { drawQuoteCard } from '@/utils/quoteCard'
import QuoteShareView from './QuoteShareView.vue'

vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))
// jsdom has no canvas to draw on: what is drawn is covered by the specs of the util itself.
vi.mock('@/utils/quoteCard', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/utils/quoteCard')>()),
  drawQuoteCard: vi.fn(),
}))

const context = { fake: 'context' } as unknown as CanvasRenderingContext2D
const png = new Blob(['png'], { type: 'image/png' })

/** What the browser offers for sharing and saving; jsdom has none of it. */
function stubBrowser({ canShare }: { canShare?: boolean } = {}) {
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(context as never)
  const toBlob = vi
    .spyOn(HTMLCanvasElement.prototype, 'toBlob')
    .mockImplementation((callback) => callback(png))

  const share = vi.fn().mockResolvedValue(undefined)
  if (canShare !== undefined) {
    Object.defineProperty(navigator, 'canShare', { configurable: true, value: vi.fn().mockReturnValue(canShare) })
    Object.defineProperty(navigator, 'share', { configurable: true, value: share })
  }

  const createObjectURL = vi.fn().mockReturnValue('blob:card')
  const revokeObjectURL = vi.fn()
  Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: createObjectURL })
  Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: revokeObjectURL })

  const downloads: { href: string; download: string }[] = []
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
    downloads.push({ href: this.href, download: this.download })
  })

  return { toBlob, share, createObjectURL, revokeObjectURL, downloads }
}

async function mountView(quote: Quote = makeQuote({ id: 3 })) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(quotesApi.get).mockResolvedValue(quote)
  await router.push({ name: 'quote-share', params: { id: quote.id } })

  const wrapper = mount(QuoteShareView, { props: { id: quote.id }, global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('QuoteShareView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.useRealTimers()
    for (const owner of [navigator, URL]) {
      for (const name of ['canShare', 'share', 'createObjectURL', 'revokeObjectURL']) {
        Reflect.deleteProperty(owner, name)
      }
    }
  })

  describe('the preview', () => {
    it('is a canvas the size of the picture that will be shared', async () => {
      stubBrowser()
      const { wrapper } = await mountView()

      const canvas = wrapper.get('canvas')

      expect(canvas.attributes('width')).toBe('1080')
      expect(canvas.attributes('height')).toBe('1350')
      expect(canvas.attributes('role')).toBe('img')
      expect(canvas.attributes('aria-label')).toBe('Попередній перегляд картки')
    })

    it('draws the quote with its book, its page and the name of the app', async () => {
      stubBrowser()
      await mountView(makeQuote({ id: 3, content: 'Text', page: 42 }))

      expect(drawQuoteCard).toHaveBeenCalledTimes(1)
      expect(drawQuoteCard).toHaveBeenCalledWith(
        context,
        { text: 'Text', title: 'Atomic Habits', author: 'James Clear', pageLabel: 'с. 42', brand: 'Bookshelf' },
        'light',
      )
    })

    it('has no page label when the quote has no page', async () => {
      stubBrowser()
      await mountView(makeQuote({ page: null }))

      expect(vi.mocked(drawQuoteCard).mock.calls[0]?.[1].pageLabel).toBeNull()
    })

    it('is drawn again in the template the reader picks', async () => {
      stubBrowser()
      const { wrapper } = await mountView()

      expect(buttonWithText(wrapper, 'Світла').attributes('aria-pressed')).toBe('true')

      await buttonWithText(wrapper, 'Темна').trigger('click')
      await flushPromises()
      await buttonWithText(wrapper, 'Акцент').trigger('click')
      await flushPromises()

      expect(vi.mocked(drawQuoteCard).mock.calls.map((call) => call[2])).toEqual(['light', 'dark', 'accent'])
      expect(buttonWithText(wrapper, 'Акцент').attributes('aria-pressed')).toBe('true')
      expect(buttonWithText(wrapper, 'Світла').attributes('aria-pressed')).toBe('false')
    })
  })

  describe('loading the quote', () => {
    it('takes it from the list when it is there', async () => {
      stubBrowser()
      const { plugins, router } = createTestEnvironment()
      vi.mocked(quotesApi.list).mockResolvedValue({ data: [makeQuote({ id: 3 })], links: { next: null } })
      await useQuotesStore().load()
      await router.push({ name: 'quote-share', params: { id: 3 } })

      mount(QuoteShareView, { props: { id: 3 }, global: { plugins } })
      await flushPromises()

      expect(quotesApi.get).not.toHaveBeenCalled()
      expect(drawQuoteCard).toHaveBeenCalled()
    })

    it('asks the server otherwise', async () => {
      stubBrowser()
      await mountView(makeQuote({ id: 9 }))

      expect(quotesApi.get).toHaveBeenCalledWith(9)
    })

    it('says so when the quote does not exist', async () => {
      stubBrowser()
      const { plugins, router } = createTestEnvironment()
      vi.mocked(quotesApi.get).mockRejectedValue(new ApiError(404, { message: 'Not found' }))
      await router.push({ name: 'quote-share', params: { id: 99 } })

      const wrapper = mount(QuoteShareView, { props: { id: 99 }, global: { plugins } })
      await flushPromises()

      expect(wrapper.text()).toContain('Цього запису немає у ваших цитатах.')
      expect(wrapper.find('canvas').exists()).toBe(false)
    })

    it('offers a retry when the quote cannot be loaded', async () => {
      stubBrowser()
      const { plugins, router } = createTestEnvironment()
      vi.mocked(quotesApi.get).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(makeQuote({ id: 3 }))
      await router.push({ name: 'quote-share', params: { id: 3 } })
      const wrapper = mount(QuoteShareView, { props: { id: 3 }, global: { plugins } })
      await flushPromises()
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

      await wrapper.get('[role="alert"] button').trigger('click')
      await flushPromises()

      expect(wrapper.find('canvas').exists()).toBe(true)
    })
  })

  describe('sharing', () => {
    it('opens the share sheet with the picture where the browser can share files', async () => {
      const { share, downloads, toBlob } = stubBrowser({ canShare: true })
      const { wrapper } = await mountView(makeQuote({ id: 3 }))

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()

      expect(toBlob).toHaveBeenCalledWith(expect.any(Function), 'image/png')
      expect(share).toHaveBeenCalledTimes(1)
      const shared = share.mock.calls[0]?.[0] as { files: File[]; title: string }
      expect(shared.title).toBe('Atomic Habits')
      expect(shared.files).toHaveLength(1)
      expect(shared.files[0]?.name).toBe('bookshelf-quote-3.png')
      expect(shared.files[0]?.type).toBe('image/png')
      expect(downloads).toEqual([])
    })

    it('checks that this very file can be shared', async () => {
      stubBrowser({ canShare: true })
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()

      const asked = vi.mocked(navigator.canShare).mock.calls[0]?.[0] as { files: File[] }
      expect(asked.files[0]).toBeInstanceOf(File)
    })

    it('downloads the picture when the browser cannot share files', async () => {
      vi.useFakeTimers()
      const { share, downloads, createObjectURL, revokeObjectURL } = stubBrowser({ canShare: false })
      const { wrapper } = await mountView(makeQuote({ id: 3 }))

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()

      expect(share).not.toHaveBeenCalled()
      expect(createObjectURL).toHaveBeenCalledTimes(1)
      expect(createObjectURL.mock.calls[0]?.[0]).toBeInstanceOf(File)
      expect(downloads).toEqual([{ href: 'blob:card', download: 'bookshelf-quote-3.png' }])
      expect(document.querySelector('a[download]')).toBeNull()

      // The address stays valid for a moment, then it is let go.
      expect(revokeObjectURL).not.toHaveBeenCalled()
      await vi.advanceTimersByTimeAsync(10_000)
      expect(revokeObjectURL).toHaveBeenCalledWith('blob:card')
    })

    it('downloads the picture when the browser does not know about sharing at all', async () => {
      const { downloads } = stubBrowser()
      const { wrapper } = await mountView(makeQuote({ id: 4 }))

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()

      expect(downloads).toEqual([{ href: 'blob:card', download: 'bookshelf-quote-4.png' }])
    })

    it('does not treat closing the share sheet as a failure', async () => {
      const { share } = stubBrowser({ canShare: true })
      share.mockRejectedValue(new DOMException('Share canceled', 'AbortError'))
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(buttonWithText(wrapper, 'Поділитися').attributes('disabled')).toBeUndefined()
    })

    it('says so when sharing really fails', async () => {
      const { share } = stubBrowser({ canShare: true })
      share.mockRejectedValue(new DOMException('Not allowed', 'NotAllowedError'))
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося підготувати картинку. Спробуйте ще раз.')
      expect(buttonWithText(wrapper, 'Поділитися').attributes('disabled')).toBeUndefined()
    })

    it('says so when the canvas gives no picture', async () => {
      const { downloads, toBlob } = stubBrowser({ canShare: false })
      toBlob.mockImplementation((callback) => callback(null))
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося підготувати картинку')
      expect(downloads).toEqual([])
    })

    it('clears an old failure when the reader tries again', async () => {
      const { share } = stubBrowser({ canShare: true })
      share.mockRejectedValueOnce(new Error('hiccup')).mockResolvedValueOnce(undefined)
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()
      expect(wrapper.find('[role="alert"]').exists()).toBe(true)

      await buttonWithText(wrapper, 'Поділитися').trigger('click')
      await flushPromises()
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })

    it('is busy while the picture is being prepared', async () => {
      const { toBlob } = stubBrowser({ canShare: true })
      toBlob.mockImplementation(() => {})
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Поділитися').trigger('click')

      expect(buttonWithText(wrapper, 'Поділитися').attributes('disabled')).toBeDefined()
    })
  })
})
