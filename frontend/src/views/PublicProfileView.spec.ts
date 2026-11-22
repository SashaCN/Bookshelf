import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeProfile, makeQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import { useAuthStore } from '@/stores/auth'
import type { PublicProfile } from '@/types/api'
import PublicProfileView from './PublicProfileView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn() },
}))

const reader = { id: 1, name: 'Іван', email: 'ivan@example.com', timezone: 'Europe/Kyiv', username: 'ivan', is_public: true }

async function mountView(profile: PublicProfile | Error, next: string | null = null, guest = false) {
  const { plugins, router } = createTestEnvironment()
  useAuthStore().user = guest ? null : reader
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

/** The number and its word are two lines of one link. */
function stat(wrapper: ReturnType<typeof mount>, selector: string) {
  return wrapper.findAll(`${selector} span`).map((span) => span.text()).join(' ')
}

describe('PublicProfileView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows the name, the username, the counters and the public quotes read-only', async () => {
    const { wrapper } = await mountView(makeProfile())

    expect(wrapper.get('h1').text()).toBe('Олена')
    expect(wrapper.text()).toContain('@olena')
    expect(wrapper.text()).toContain('Прочитано цього року')
    expect(wrapper.find('a[href="/u/olena/followers"]').text()).toContain('3')
    expect(wrapper.find('a[href="/u/olena/following"]').text()).toContain('2')
    expect(wrapper.text()).toContain('Public words')
    expect(wrapper.find('[aria-label="В улюблених"]').exists()).toBe(false)
    expect(buttonWithText(wrapper, 'Редагувати')).toBeUndefined()
  })

  it('shows a guest the profile and quotes without links to the people lists or a follow button', async () => {
    const { wrapper } = await mountView(makeProfile(), null, true)

    expect(wrapper.get('h1').text()).toBe('Олена')
    expect(wrapper.text()).toContain('Public words')
    expect(wrapper.text()).toContain('3підписники')
    expect(wrapper.find('a[href="/u/olena/followers"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/u/olena/following"]').exists()).toBe(false)
    expect(buttonWithText(wrapper, 'Підписатися')).toBeUndefined()
    expect(wrapper.find('a[href="/"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/profile"]').exists()).toBe(false)
  })

  it('offers a guest to sign in and come back to the profile', async () => {
    const { wrapper } = await mountView(makeProfile(), null, true)

    const cta = wrapper.findAll('a').find((link) => link.text() === 'Увійдіть, щоб підписатися')

    expect(cta?.attributes('href')).toBe('/login?redirect=/u/olena')
  })

  it('keeps the people links and the follow button for a signed-in reader', async () => {
    const { wrapper } = await mountView(makeProfile())

    expect(wrapper.find('a[href="/u/olena/followers"]').exists()).toBe(true)
    expect(buttonWithText(wrapper, 'Підписатися')).toBeDefined()
    expect(wrapper.text()).not.toContain('Увійдіть, щоб підписатися')
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
    expect(wrapper.find('a[href="/u/olena/followers"]').exists()).toBe(false)
  })

  it('declines the counter words in Ukrainian', async () => {
    const one = await mountView(makeProfile({ followers_count: 1, following_count: 1 }))
    expect(stat(one.wrapper, 'a[href="/u/olena/followers"]')).toBe('1 підписник')
    expect(stat(one.wrapper, 'a[href="/u/olena/following"]')).toBe('1 підписка')

    const few = await mountView(makeProfile({ followers_count: 2, following_count: 3 }))
    expect(stat(few.wrapper, 'a[href="/u/olena/followers"]')).toBe('2 підписники')
    expect(stat(few.wrapper, 'a[href="/u/olena/following"]')).toBe('3 підписки')

    const many = await mountView(makeProfile({ followers_count: 5, following_count: 11 }))
    expect(stat(many.wrapper, 'a[href="/u/olena/followers"]')).toBe('5 підписників')
    expect(stat(many.wrapper, 'a[href="/u/olena/following"]')).toBe('11 підписок')
  })

  it('disables the follow button while the request is on its way', async () => {
    let finish: () => void = () => undefined
    vi.mocked(socialApi.follow).mockReturnValue(new Promise((resolve) => { finish = () => resolve(null) }))
    const { wrapper } = await mountView(makeProfile())

    await buttonWithText(wrapper, 'Підписатися')!.trigger('click')

    const button = buttonWithText(wrapper, 'Відписатися')!
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-pressed')).toBe('true')
    await button.trigger('click')
    expect(socialApi.unfollow).not.toHaveBeenCalled()

    finish()
    await flushPromises()
    expect(buttonWithText(wrapper, 'Відписатися')!.attributes('disabled')).toBeUndefined()
  })

  it('retries a failed follow', async () => {
    vi.mocked(socialApi.follow).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValue(null)
    const { wrapper } = await mountView(makeProfile())

    await buttonWithText(wrapper, 'Підписатися')!.trigger('click')
    await flushPromises()
    await buttonWithText(wrapper, 'Повторити')!.trigger('click')
    await flushPromises()

    expect(socialApi.follow).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).not.toContain('Не вдалося')
    expect(buttonWithText(wrapper, 'Відписатися')).toBeDefined()
  })

  it('shows an error with a retry when the profile cannot be loaded', async () => {
    const { wrapper } = await mountView(new ApiError(500, null))
    vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())

    await buttonWithText(wrapper, 'Повторити')!.trigger('click')
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Олена')
  })

  it('keeps the quotes and offers a retry when loading more fails', async () => {
    const { wrapper } = await mountView(makeProfile(), '/n')
    vi.mocked(socialApi.quotes).mockRejectedValueOnce(new ApiError(500, null))

    await buttonWithText(wrapper, 'Показати ще')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Public words')
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')
    expect(buttonWithText(wrapper, 'Показати ще')).toBeUndefined()

    vi.mocked(socialApi.quotes).mockResolvedValue({ data: [makeQuote({ id: 2, content: 'More words' })], links: { next: null } })
    await buttonWithText(wrapper, 'Повторити')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('More words')
  })

  it('says when there are no public quotes yet', async () => {
    const { wrapper } = await mountView(makeProfile())
    vi.mocked(socialApi.quotes).mockResolvedValue({ data: [], links: { next: null } })
    await wrapper.setProps({ username: 'ivan' })
    await flushPromises()

    expect(wrapper.text()).toContain('Публічних цитат поки немає.')
  })
})
