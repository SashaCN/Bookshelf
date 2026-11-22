import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeProfile, makeQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { PublicProfile } from '@/types/api'
import PublicProfileView from './PublicProfileView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn() },
}))

async function mountView(profile: PublicProfile | Error, next: string | null = null) {
  const { plugins, router } = createTestEnvironment()
  if (profile instanceof Error) {
    vi.mocked(socialApi.profile).mockRejectedValue(profile)
  } else {
    vi.mocked(socialApi.profile).mockResolvedValue(profile)
  }
  vi.mocked(socialApi.quotes).mockResolvedValue({ data: [makeQuote({ id: 1, content: 'Public words' })], links: { next } })
  await router.push('/u/olena')

  const wrapper = mount(PublicProfileView, { props: { username: 'olena' }, global: { plugins } })
  await flushPromises()
  return { wrapper }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  return wrapper.findAll('button').find((candidate) => candidate.text() === text)
}

describe('PublicProfileView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows the name, the username, the counters and the public quotes read-only', async () => {
    const { wrapper } = await mountView(makeProfile())

    expect(wrapper.get('h1').text()).toBe('Олена')
    expect(wrapper.text()).toContain('@olena')
    expect(wrapper.get('dl').text()).toContain('5')
    expect(wrapper.find('a[href="/u/olena/followers"]').text()).toContain('3')
    expect(wrapper.find('a[href="/u/olena/following"]').text()).toContain('2')
    expect(wrapper.text()).toContain('Public words')
    expect(wrapper.find('[aria-label="В улюблених"]').exists()).toBe(false)
    expect(buttonWithText(wrapper, 'Редагувати')).toBeUndefined()
  })

  it('follows and then unfollows', async () => {
    vi.mocked(socialApi.follow).mockResolvedValue(null)
    vi.mocked(socialApi.unfollow).mockResolvedValue(null)
    const { wrapper } = await mountView(makeProfile())

    await buttonWithText(wrapper, 'Підписатися')!.trigger('click')
    await flushPromises()

    expect(socialApi.follow).toHaveBeenCalledWith('olena')
    expect(wrapper.find('a[href="/u/olena/followers"]').text()).toContain('4')

    await buttonWithText(wrapper, 'Відписатися')!.trigger('click')
    await flushPromises()

    expect(socialApi.unfollow).toHaveBeenCalledWith('olena')
    expect(buttonWithText(wrapper, 'Підписатися')).toBeDefined()
  })

  it('shows an error and keeps the state when following fails', async () => {
    vi.mocked(socialApi.follow).mockRejectedValue(new ApiError(500, null))
    const { wrapper } = await mountView(makeProfile())

    await buttonWithText(wrapper, 'Підписатися')!.trigger('click')
    await flushPromises()

    expect(buttonWithText(wrapper, 'Підписатися')).toBeDefined()
    expect(wrapper.text()).toContain('Не вдалося')
  })

  it('has no follow button on the own profile', async () => {
    const { wrapper } = await mountView(makeProfile({ is_me: true }))

    expect(buttonWithText(wrapper, 'Підписатися')).toBeUndefined()
    expect(buttonWithText(wrapper, 'Відписатися')).toBeUndefined()
  })

  it('loads more quotes', async () => {
    const { wrapper } = await mountView(makeProfile(), '/n')
    vi.mocked(socialApi.quotes).mockResolvedValue({ data: [makeQuote({ id: 2, content: 'More words' })], links: { next: null } })

    await buttonWithText(wrapper, 'Показати ще')!.trigger('click')
    await flushPromises()

    expect(socialApi.quotes).toHaveBeenLastCalledWith('olena', 2)
    expect(wrapper.text()).toContain('More words')
    expect(buttonWithText(wrapper, 'Показати ще')).toBeUndefined()
  })

  it('says so when the profile is missing or private', async () => {
    const { wrapper } = await mountView(new ApiError(404, null))

    expect(wrapper.text()).toContain('Такого профілю немає')
    expect(wrapper.find('dl').exists()).toBe(false)
  })
})
