import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { quotesApi } from '@/api/quotes'
import { useQuotesStore } from '@/stores/quotes'
import { makeQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Quote } from '@/types/api'
import QuoteCard from './QuoteCard.vue'

vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

async function mountCard(quote: Quote) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(quotesApi.list).mockResolvedValue({ data: [quote], links: { next: null } })
  const quotes = useQuotesStore()
  await quotes.load()
  await router.push('/quotes')

  // The card gets its quote from the store, the way the list passes it in (and goes away with it).
  const wrapper = mount(
    {
      components: { QuoteCard },
      template: '<QuoteCard v-if="current" :quote="current" />',
      computed: { current: () => quotes.find(quote.id) },
    },
    { global: { plugins } },
  )

  return { wrapper, quotes }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('QuoteCard', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows the text, the note and where the quote comes from', async () => {
    const { wrapper } = await mountCard(makeQuote({ note: 'Systems beat goals.', page: 42 }))

    expect(wrapper.get('blockquote').text()).toBe('You do not rise to the level of your goals.')
    expect(wrapper.text()).toContain('Systems beat goals.')
    expect(wrapper.text()).toContain('Atomic Habits · James Clear · с. 42')
  })

  it('shows the own tags as chips that open the list filtered by the tag', async () => {
    const { wrapper } = await mountCard(makeQuote({ tags: ['мудрість', 'звички'] }))

    const chips = wrapper.findAll('ul[aria-label="Теги"] a')

    expect(chips.map((chip) => chip.text())).toEqual(['#мудрість', '#звички'])
    expect(chips.map((chip) => chip.attributes('href'))).toEqual([
      '/quotes?tag=%D0%BC%D1%83%D0%B4%D1%80%D1%96%D1%81%D1%82%D1%8C',
      '/quotes?tag=%D0%B7%D0%B2%D0%B8%D1%87%D0%BA%D0%B8',
    ])
  })

  it('shows no tag list for a quote without tags', async () => {
    const { wrapper } = await mountCard(makeQuote({ tags: [] }))

    expect(wrapper.find('ul[aria-label="Теги"]').exists()).toBe(false)
  })

  it('links the title to the book', async () => {
    const { wrapper } = await mountCard(makeQuote({ user_book_id: 7 }))

    const link = wrapper.findAll('a').find((candidate) => candidate.text() === 'Atomic Habits')

    expect(link?.attributes('href')).toBe('/library/7')
  })

  it('leaves out the parts of the source that are not known', async () => {
    const { wrapper } = await mountCard(
      makeQuote({ page: null, book: { title: 'Atomic Habits', authors: [], cover_url: null } }),
    )

    expect(wrapper.text()).not.toContain('·')
    expect(wrapper.text()).not.toContain('с.')
  })

  it('marks an insight, and only an insight', async () => {
    const insight = await mountCard(makeQuote({ type: 'insight' }))
    expect(insight.wrapper.text()).toContain('Інсайт')

    const quote = await mountCard(makeQuote({ type: 'quote' }))
    expect(quote.wrapper.text()).not.toContain('Інсайт')
  })

  it('links to sharing and editing', async () => {
    const { wrapper } = await mountCard(makeQuote({ id: 5 }))

    const hrefs = Object.fromEntries(wrapper.findAll('a').map((link) => [link.text(), link.attributes('href')]))

    expect(hrefs['Поділитися']).toBe('/quotes/5/share')
    expect(hrefs['Редагувати']).toBe('/quotes/5/edit')
  })

  it('links to publishing the quote in the community', async () => {
    const { wrapper } = await mountCard(makeQuote({ id: 5 }))

    const link = wrapper.findAll('a').find((candidate) => candidate.text() === 'Опублікувати')

    expect(link?.attributes('href')).toBe('/community/new?quote=5')
  })

  describe('the place in the text of the book', () => {
    it('leads to the reader at the quote when the quote was selected from the uploaded text', async () => {
      const { wrapper } = await mountCard(
        makeQuote({ id: 5, user_book_id: 7, anchor: { book_text_id: 10, chapter: 3, start: 6, end: 11 } }),
      )

      const link = wrapper.findAll('a').find((candidate) => candidate.text() === 'Відкрити в тексті')

      expect(link?.attributes('href')).toBe('/read/7?chapter=3&highlight=5')
    })

    it('has no such link for a quote that was typed in', async () => {
      const { wrapper } = await mountCard(makeQuote({ anchor: null }))

      expect(wrapper.text()).not.toContain('Відкрити в тексті')
    })
  })

  describe('favorite star', () => {
    it('is a toggle that says whether the quote is a favorite', async () => {
      const { wrapper } = await mountCard(makeQuote({ is_favorite: true }))

      expect(wrapper.get('[aria-pressed]').attributes('aria-label')).toBe('В улюблених')
      expect(wrapper.get('[aria-pressed]').attributes('aria-pressed')).toBe('true')
    })

    it('turns on at once and tells the server', async () => {
      const { wrapper } = await mountCard(makeQuote({ id: 3 }))
      vi.mocked(quotesApi.update).mockReturnValue(new Promise(() => {}))

      await wrapper.get('[aria-pressed]').trigger('click')

      expect(wrapper.get('[aria-pressed]').attributes('aria-pressed')).toBe('true')
      expect(quotesApi.update).toHaveBeenCalledWith(3, { is_favorite: true })
    })

    it('turns off again with another tap', async () => {
      const { wrapper } = await mountCard(makeQuote({ id: 3, is_favorite: true }))
      vi.mocked(quotesApi.update).mockResolvedValue(makeQuote({ id: 3, is_favorite: false }))

      await wrapper.get('[aria-pressed]').trigger('click')
      await flushPromises()

      expect(quotesApi.update).toHaveBeenCalledWith(3, { is_favorite: false })
      expect(wrapper.get('[aria-pressed]').attributes('aria-pressed')).toBe('false')
    })

    it('goes back and says why when the server turns it down', async () => {
      const { wrapper } = await mountCard(makeQuote())
      vi.mocked(quotesApi.update).mockRejectedValue(new ApiError(500, null))

      await wrapper.get('[aria-pressed]').trigger('click')
      await flushPromises()

      expect(wrapper.get('[aria-pressed]').attributes('aria-pressed')).toBe('false')
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
    })

    it('ignores a second tap while the first is still on its way', async () => {
      const { wrapper } = await mountCard(makeQuote())
      vi.mocked(quotesApi.update).mockReturnValue(new Promise(() => {}))

      await wrapper.get('[aria-pressed]').trigger('click')
      await wrapper.get('[aria-pressed]').trigger('click')

      expect(quotesApi.update).toHaveBeenCalledTimes(1)
    })
  })

  describe('removing', () => {
    it('asks first, and does nothing until the reader confirms', async () => {
      const { wrapper } = await mountCard(makeQuote())

      await buttonWithText(wrapper, 'Видалити').trigger('click')

      expect(wrapper.text()).toContain('Видалити цей запис назавжди?')
      expect(quotesApi.remove).not.toHaveBeenCalled()
    })

    it('removes the quote from the list once confirmed', async () => {
      const { wrapper, quotes } = await mountCard(makeQuote({ id: 4 }))
      vi.mocked(quotesApi.remove).mockResolvedValue(null)

      await buttonWithText(wrapper, 'Видалити').trigger('click')
      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(quotesApi.remove).toHaveBeenCalledWith(4)
      expect(quotes.items).toEqual([])
    })

    it('lets the reader back out', async () => {
      const { wrapper } = await mountCard(makeQuote())

      await buttonWithText(wrapper, 'Видалити').trigger('click')
      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(quotesApi.remove).not.toHaveBeenCalled()
      expect(wrapper.text()).not.toContain('Видалити цей запис назавжди?')
      expect(buttonWithText(wrapper, 'Видалити').exists()).toBe(true)
    })

    it('keeps the quote and says why when removing fails', async () => {
      const { wrapper, quotes } = await mountCard(makeQuote())
      vi.mocked(quotesApi.remove).mockRejectedValue(new ApiError(500, null))

      await buttonWithText(wrapper, 'Видалити').trigger('click')
      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(quotes.items).toHaveLength(1)
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(wrapper.text()).not.toContain('Видалити цей запис назавжди?')
    })
  })
})
