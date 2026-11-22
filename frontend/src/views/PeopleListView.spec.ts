import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { socialApi } from '@/api/social'
import { createTestEnvironment } from '@/test/utils'
import PeopleListView from './PeopleListView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn() },
}))

async function mountView(kind: 'followers' | 'following', data: { username: string; name: string }[]) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(socialApi[kind]).mockResolvedValue({ data, links: { next: null } })
  await router.push(`/u/olena/${kind}`)

  const wrapper = mount(PeopleListView, { props: { username: 'olena', kind }, global: { plugins } })
  await flushPromises()
  return { wrapper }
}

describe('PeopleListView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists followers linking to their pages', async () => {
    const { wrapper } = await mountView('followers', [{ username: 'ivan', name: 'Іван' }])

    expect(socialApi.followers).toHaveBeenCalledWith('olena', 1)
    expect(wrapper.get('h1').text()).toBe('Підписники')
    expect(wrapper.get('a[href="/u/ivan"]').text()).toContain('@ivan')
  })

  it('shows an empty state for following', async () => {
    const { wrapper } = await mountView('following', [])

    expect(wrapper.get('h1').text()).toBe('Підписки')
    expect(wrapper.text()).toContain('Підписок поки немає.')
  })
})
