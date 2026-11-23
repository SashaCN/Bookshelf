import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeProfile, makePublicReview, makeQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import { useAuthStore } from '@/stores/auth'
import type { PublicProfile } from '@/types/api'
import PublicProfileView from './PublicProfileView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), reviews: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn() },
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

  describe('sharing the own profile', () => {
    const url = `${location.origin}/u/olena`

    function stubNavigator(share?: unknown, writeText?: unknown) {
      Object.defineProperty(navigator, 'share', { value: share, configurable: true })
      Object.defineProperty(navigator, 'clipboard', { value: writeText ? { writeText } : undefined, configurable: true })
    }

    afterEach(() => {
      vi.useRealTimers()
      stubNavigator()
    })

    it('uses the share sheet when the browser has one', async () => {
      const share = vi.fn().mockResolvedValue(undefined)
      stubNavigator(share)
      const { wrapper } = await mountView(makeProfile({ is_me: true }))

      await buttonWithText(wrapper, 'Поділитися профілем')!.trigger('click')
      await flushPromises()

      expect(share).toHaveBeenCalledWith({ title: 'Олена', url })
      expect(wrapper.find('input[readonly]').exists()).toBe(false)
    })

    it('ignores a closed share sheet', async () => {
      const writeText = vi.fn()
      stubNavigator(vi.fn().mockRejectedValue(new DOMException('closed', 'AbortError')), writeText)
      const { wrapper } = await mountView(makeProfile({ is_me: true }))

      await buttonWithText(wrapper, 'Поділитися профілем')!.trigger('click')
      await flushPromises()

      expect(writeText).not.toHaveBeenCalled()
      expect(wrapper.find('input[readonly]').exists()).toBe(false)
      expect(wrapper.get('[role="status"]').text()).toBe('')
    })

    it('copies the link and shows a status that goes away', async () => {
      const writeText = vi.fn().mockResolvedValue(undefined)
      stubNavigator(undefined, writeText)
      const { wrapper } = await mountView(makeProfile({ is_me: true }))
      vi.useFakeTimers()

      await buttonWithText(wrapper, 'Поділитися профілем')!.trigger('click')
      await flushPromises()

      expect(writeText).toHaveBeenCalledWith(url)
      expect(wrapper.get('[role="status"]').text()).toBe('Посилання скопійовано')

      vi.advanceTimersByTime(2100)
      await flushPromises()
      expect(wrapper.get('[role="status"]').text()).toBe('')
    })

    it('shows the link in a read-only field when nothing else works', async () => {
      stubNavigator(undefined, vi.fn().mockRejectedValue(new Error('denied')))
      const { wrapper } = await mountView(makeProfile({ is_me: true }))

      await buttonWithText(wrapper, 'Поділитися профілем')!.trigger('click')
      await flushPromises()

      const input = wrapper.get('input[readonly]')
      expect((input.element as HTMLInputElement).value).toBe(url)
    })

    it('is not shown on another reader profile', async () => {
      const { wrapper } = await mountView(makeProfile({ is_me: false }))

      expect(buttonWithText(wrapper, 'Поділитися профілем')).toBeUndefined()
    })
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

  describe('reviews tab', () => {
    const review = makePublicReview({ id: 1, review: 'Перший рядок\nДругий <i>рядок</i>', rating: 4 })

    it('lists the reviews of the book only when the tab is opened', async () => {
      vi.mocked(socialApi.reviews).mockResolvedValue({ data: [review], links: { next: null } })
      const { wrapper } = await mountView(makeProfile({ reviews_count: 1 }))

      expect(socialApi.reviews).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('1 відгук')

      await buttonWithText(wrapper, 'Відгуки')!.trigger('click')
      await flushPromises()

      expect(socialApi.reviews).toHaveBeenCalledWith('olena', 1)
      expect(buttonWithText(wrapper, 'Відгуки')!.attributes('aria-pressed')).toBe('true')
      expect(wrapper.text()).toContain('Маленький принц')
      expect(wrapper.text()).toContain('5 жовтня')
      expect(wrapper.find('[aria-label="Оцінка: 4 з 5"]').exists()).toBe(true)
      expect(wrapper.text()).not.toContain('Public words')
      expect(wrapper.get('p.whitespace-pre-line').text()).toBe('Перший рядок\nДругий <i>рядок</i>')
      expect(wrapper.find('i').exists()).toBe(false)

      await buttonWithText(wrapper, 'Цитати')!.trigger('click')
      expect(wrapper.text()).toContain('Public words')
    })

    it('declines the number of reviews', async () => {
      expect((await mountView(makeProfile({ reviews_count: 3 }))).wrapper.text()).toContain('3 відгуки')
      expect((await mountView(makeProfile({ reviews_count: 5 }))).wrapper.text()).toContain('5 відгуків')
    })

    it('says so when there are no public reviews', async () => {
      vi.mocked(socialApi.reviews).mockResolvedValue({ data: [], links: { next: null } })
      const { wrapper } = await mountView(makeProfile())

      await buttonWithText(wrapper, 'Відгуки')!.trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Публічних відгуків поки немає.')
    })

    it('loads more reviews', async () => {
      vi.mocked(socialApi.reviews)
        .mockResolvedValueOnce({ data: [review], links: { next: '/n' } })
        .mockResolvedValueOnce({ data: [makePublicReview({ id: 2, book: { title: 'Друга книга', authors: [], cover_url: null } })], links: { next: null } })
      const { wrapper } = await mountView(makeProfile({ reviews_count: 2 }))

      await buttonWithText(wrapper, 'Відгуки')!.trigger('click')
      await flushPromises()
      await buttonWithText(wrapper, 'Показати ще')!.trigger('click')
      await flushPromises()

      expect(socialApi.reviews).toHaveBeenLastCalledWith('olena', 2)
      expect(wrapper.text()).toContain('Друга книга')
    })

    it('offers a retry when the reviews cannot be loaded', async () => {
      vi.mocked(socialApi.reviews).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce({ data: [review], links: { next: null } })
      const { wrapper } = await mountView(makeProfile())

      await buttonWithText(wrapper, 'Відгуки')!.trigger('click')
      await flushPromises()
      await wrapper.get('[role="alert"] button').trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Маленький принц')
    })

    it('works for a guest', async () => {
      vi.mocked(socialApi.reviews).mockResolvedValue({ data: [review], links: { next: null } })
      const { wrapper } = await mountView(makeProfile({ reviews_count: 1 }), null, true)

      await buttonWithText(wrapper, 'Відгуки')!.trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Маленький принц')
    })
  })
})
