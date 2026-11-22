import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeFeedQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { FeedQuote } from '@/types/api'
import FeedView from './FeedView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn() },
}))

async function mountView(items: FeedQuote[], next: string | null = null) {
  const { plugins, router } = createTestEnvironment()
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

  it('shows the empty state', async () => {
    const { wrapper } = await mountView([])

    expect(wrapper.text()).toContain('Стрічка порожня')
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
  })
})
