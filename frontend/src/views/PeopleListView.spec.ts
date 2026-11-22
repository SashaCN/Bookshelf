import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { createTestEnvironment } from '@/test/utils'
import PeopleListView from './PeopleListView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn() },
}))

async function mountView(kind: 'followers' | 'following', data: { username: string; name: string }[], next: string | null = null) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(socialApi[kind]).mockResolvedValue({ data, links: { next } })
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

  it('goes back to the profile of the person, not to the home page', async () => {
    const { wrapper } = await mountView('followers', [])

    expect(wrapper.get('[aria-label="Назад"]').attributes('href')).toBe('/u/olena')
  })

  it('says why the list is empty, with the list title kept for the heading', async () => {
    const { wrapper } = await mountView('followers', [])

    expect(wrapper.text()).toContain('Тут поки нікого')
    expect(wrapper.text()).toContain('Підписників поки немає.')
  })

  it('loads more, and offers a retry when that fails', async () => {
    const { wrapper } = await mountView('followers', [{ username: 'ivan', name: 'Іван' }], '/n')
    vi.mocked(socialApi.followers).mockRejectedValueOnce(new ApiError(500, null))
    const more = () => wrapper.findAll('button').find((b) => b.text() === 'Показати ще')

    await more()!.trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')

    vi.mocked(socialApi.followers).mockResolvedValue({ data: [{ username: 'petro', name: 'Петро' }], links: { next: null } })
    await wrapper.findAll('button').find((b) => b.text() === 'Повторити')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('@petro')
    expect(more()).toBeUndefined()
  })

  it('says so when the profile is missing or private', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(socialApi.followers).mockRejectedValue(new ApiError(404, null))
    await router.push('/u/olena/followers')

    const wrapper = mount(PeopleListView, { props: { username: 'olena', kind: 'followers' }, global: { plugins } })
    await flushPromises()

    expect(wrapper.text()).toContain('Такого профілю немає')
  })

  it('shows an error with a retry', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(socialApi.followers).mockRejectedValueOnce(new ApiError(500, null))
    await router.push('/u/olena/followers')

    const wrapper = mount(PeopleListView, { props: { username: 'olena', kind: 'followers' }, global: { plugins } })
    await flushPromises()
    vi.mocked(socialApi.followers).mockResolvedValue({ data: [{ username: 'ivan', name: 'Іван' }], links: { next: null } })
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('@ivan')
  })

  it('reloads for another list of the same page', async () => {
    const { wrapper } = await mountView('followers', [])
    vi.mocked(socialApi.following).mockResolvedValue({ data: [{ username: 'ivan', name: 'Іван' }], links: { next: null } })

    await wrapper.setProps({ kind: 'following' })
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Підписки')
    expect(wrapper.text()).toContain('@ivan')
  })
})
