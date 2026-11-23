import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeFeedQuote } from '@/test/fixtures'
import { useAuthStore } from '@/stores/auth'
import { createTestEnvironment } from '@/test/utils'
import type { FeedQuote } from '@/types/api'
import FeedView from './FeedView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn(), like: vi.fn(), unlike: vi.fn() },
}))

async function mountView(items: FeedQuote[], next: string | null = null) {
  const { plugins, router } = createTestEnvironment()
  useAuthStore().user = { id: 1, name: 'Я', email: 'me@example.com', timezone: 'Europe/Kyiv', username: 'me', is_public: true, new_followers_count: 0 }
  vi.mocked(socialApi.feed).mockResolvedValue({ data: items, links: { next } })
  await router.push('/feed')

  const wrapper = mount(FeedView, { global: { plugins } })
  await flushPromises()
  return { wrapper }
}

describe('FeedView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists quotes with their authors, read-only', async () => {
    const { wrapper } = await mountView([
      makeFeedQuote({ id: 2, content: 'Second', author: { username: 'ivan', name: 'Іван' } }),
      makeFeedQuote({ id: 1, content: 'First' }),
    ])

    expect(wrapper.findAll('article')).toHaveLength(2)
    expect(wrapper.find('a[href="/u/ivan"]').text()).toBe('Іван')
    expect(wrapper.find('a[href="/u/olena"]').exists()).toBe(true)
    expect(wrapper.find('[aria-label="В улюблених"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Редагувати')
  })

  it('likes a quote from the feed and rolls back on an error', async () => {
    const { wrapper } = await mountView([makeFeedQuote({ likes_count: 3, liked_by_me: false })])
    vi.mocked(socialApi.like).mockResolvedValueOnce(null).mockRejectedValueOnce(new ApiError(500, 'Server error'))

    await wrapper.get('button[aria-pressed]').trigger('click')
    await flushPromises()
    expect(wrapper.get('button[aria-pressed]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('button[aria-pressed]').text()).toBe('4')
    expect(socialApi.like).toHaveBeenCalledWith(1)

    vi.mocked(socialApi.unlike).mockRejectedValueOnce(new ApiError(500, 'Server error'))
    await wrapper.get('button[aria-pressed]').trigger('click')
    await flushPromises()
    expect(wrapper.get('button[aria-pressed]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('button[aria-pressed]').text()).toBe('4')
  })

  it('links to the people search from the header', async () => {
    const { wrapper } = await mountView([makeFeedQuote()])

    expect(wrapper.get('a[href="/people"]').text()).toBe('Знайти людей')
  })

  it('puts the author first, then the quote, then the book', async () => {
    const { wrapper } = await mountView([makeFeedQuote({ content: 'Quote text', author: { username: 'ivan', name: 'Іван' } })])

    const html = wrapper.html()
    expect(html.indexOf('@ivan')).toBeLessThan(html.indexOf('Quote text'))
    expect(html.indexOf('Quote text')).toBeLessThan(html.indexOf('Atomic Habits'))
    expect(wrapper.get('article [aria-hidden="true"]').text()).toBe('І')
  })

  it('shows the empty state', async () => {
    const { wrapper } = await mountView([])

    expect(wrapper.text()).toContain('Стрічка порожня')
    expect(wrapper.text()).toContain('публічні цитати читачів, на яких ви підписані')
    expect(wrapper.findAll('a[href="/people"]').length).toBeGreaterThanOrEqual(2)
    expect(wrapper.findAll('a[href="/profile"]').map((link) => link.text())).toContain('Зробити свій профіль публічним')
  })

  it('keeps the list and offers a retry when loading more fails', async () => {
    const { wrapper } = await mountView([makeFeedQuote({ id: 2, content: 'Kept' })], '/n')
    vi.mocked(socialApi.feed).mockRejectedValueOnce(new ApiError(500, null))

    await wrapper.findAll('button').find((b) => b.text() === 'Показати ще')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Kept')
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')

    vi.mocked(socialApi.feed).mockResolvedValue({ data: [makeFeedQuote({ id: 1 })], links: { next: null } })
    await wrapper.findAll('button').find((b) => b.text() === 'Повторити')!.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('article')).toHaveLength(2)
  })

  it('disables the button while more is loading', async () => {
    const { wrapper } = await mountView([makeFeedQuote({ id: 2 })], '/n')
    vi.mocked(socialApi.feed).mockReturnValue(new Promise(() => undefined))

    await wrapper.findAll('button').find((b) => b.text() === 'Показати ще')!.trigger('click')

    const button = wrapper.findAll('button').find((b) => b.text() === 'Завантаження…')!
    expect(button.attributes('disabled')).toBeDefined()
  })

  it('loads more', async () => {
    const { wrapper } = await mountView([makeFeedQuote({ id: 2 })], '/n')
    vi.mocked(socialApi.feed).mockResolvedValue({ data: [makeFeedQuote({ id: 1 })], links: { next: null } })

    await wrapper.findAll('button').find((b) => b.text() === 'Показати ще')!.trigger('click')
    await flushPromises()

    expect(socialApi.feed).toHaveBeenLastCalledWith(2)
    expect(wrapper.findAll('article')).toHaveLength(2)
  })

  it('shows an error with a retry', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(socialApi.feed).mockRejectedValue(new ApiError(500, null))
    await router.push('/feed')

    const wrapper = mount(FeedView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.text()).toContain('Не вдалося')

    vi.mocked(socialApi.feed).mockResolvedValue({ data: [makeFeedQuote({ id: 3, content: 'Back again' })], links: { next: null } })
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Back again')
  })

  it('shows a loading note before the first answer', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(socialApi.feed).mockReturnValue(new Promise(() => undefined))
    await router.push('/feed')

    const wrapper = mount(FeedView, { global: { plugins } })

    expect(wrapper.text()).toContain('Завантаження…')
  })
})
