import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { quotesApi } from '@/api/quotes'
import { useAuthStore } from '@/stores/auth'
import { useQuotesStore } from '@/stores/quotes'
import { socialApi } from '@/api/social'
import { makeQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Quote } from '@/types/api'
import QuoteCard from './QuoteCard.vue'

vi.mock('@/api/social', () => ({ socialApi: { like: vi.fn(), unlike: vi.fn() } }))
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

  describe('likes', () => {
    const viewer = { id: 1, name: 'Іван', email: 'ivan@example.com', timezone: 'Europe/Kyiv', username: 'ivan', is_public: true }

    function mountReadonly(quote: Quote, signedIn = true) {
      const { plugins } = createTestEnvironment()
      useAuthStore().user = signedIn ? viewer : null
      // A reactive object, the way the lists of the social store hand quotes to the card.
      const reactiveQuote = reactive(quote)
      return { wrapper: mount(QuoteCard, { props: { quote: reactiveQuote, readonly: true }, global: { plugins } }), reactiveQuote }
    }

    it('toggles the heart at once and keeps it when the server agrees', async () => {
      vi.mocked(socialApi.like).mockResolvedValue(null)
      const { wrapper, reactiveQuote } = mountReadonly(makeQuote({ likes_count: 1, liked_by_me: false }))
      const heart = wrapper.get('button[aria-pressed]')
      expect(heart.attributes('aria-pressed')).toBe('false')
      expect(heart.attributes('aria-label')).toBe('Вподобати. 1 вподобання')
      expect(heart.classes()).toContain('min-h-11')

      await heart.trigger('click')

      expect(socialApi.like).toHaveBeenCalledWith(1)
      expect(reactiveQuote).toMatchObject({ liked_by_me: true, likes_count: 2 })
      expect(wrapper.get('button[aria-pressed]').attributes('aria-label')).toBe('Прибрати вподобання. 2 вподобання')
      await flushPromises()
      expect(wrapper.get('button[aria-pressed]').attributes('aria-pressed')).toBe('true')
    })

    it('shows the number next to the heart only when there are likes', () => {
      const none = mountReadonly(makeQuote({ likes_count: 0, liked_by_me: false })).wrapper.get('button[aria-pressed]')
      const some = mountReadonly(makeQuote({ likes_count: 3, liked_by_me: false })).wrapper.get('button[aria-pressed]')

      expect(none.text()).toBe('')
      expect(none.attributes('aria-label')).toBe('Вподобати. 0 вподобань')
      expect(some.text()).toBe('3')
    })

    it('takes a like back', async () => {
      vi.mocked(socialApi.unlike).mockResolvedValue(null)
      const { wrapper, reactiveQuote } = mountReadonly(makeQuote({ likes_count: 5, liked_by_me: true }))

      await wrapper.get('button[aria-pressed]').trigger('click')
      await flushPromises()

      expect(socialApi.unlike).toHaveBeenCalledWith(1)
      expect(reactiveQuote).toMatchObject({ liked_by_me: false, likes_count: 4 })
    })

    it('puts the heart and the count back when the request fails', async () => {
      vi.mocked(socialApi.like).mockRejectedValue(new ApiError(404, 'Not found'))
      const { wrapper, reactiveQuote } = mountReadonly(makeQuote({ likes_count: 2, liked_by_me: false }))

      await wrapper.get('button[aria-pressed]').trigger('click')
      await flushPromises()

      expect(reactiveQuote).toMatchObject({ liked_by_me: false, likes_count: 2 })
      expect(wrapper.get('button[aria-pressed]').attributes('aria-pressed')).toBe('false')
      expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    })

    it('shows a guest the count only, without a button', () => {
      const { wrapper } = mountReadonly(makeQuote({ likes_count: 5, liked_by_me: false }), false)

      expect(wrapper.find('button').exists()).toBe(false)
      expect(wrapper.get('[data-test="likes-count"]').text()).toBe('5 вподобань')
    })

    it('shows the likes of an own quote only when there are some', async () => {
      const liked = await mountCard(makeQuote({ likes_count: 2 }))
      const none = await mountCard(makeQuote({ likes_count: 0 }))

      expect(liked.wrapper.get('[data-test="likes-count"]').text()).toBe('2 вподобання')
      expect(none.wrapper.find('[data-test="likes-count"]').exists()).toBe(false)
    })
  })
})
