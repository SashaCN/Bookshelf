import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { socialApi } from '@/api/social'
import { makePost, makeProfile } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Post, PostsPage, Profile } from '@/types/social'
import ReaderProfileView from './ReaderProfileView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))
vi.mock('@/api/social', () => ({
  socialApi: {
    profile: vi.fn(),
    profilePosts: vi.fn(),
    follow: vi.fn(),
    unfollow: vi.fn(),
    block: vi.fn(),
    unblock: vi.fn(),
    feed: vi.fn(),
    blocks: vi.fn(),
    like: vi.fn(),
    unlike: vi.fn(),
    deletePost: vi.fn(),
    report: vi.fn(),
  },
}))

function postsPage(posts: Post[], next: string | null = null): PostsPage {
  return { data: posts, links: { next } }
}

async function mountView(profile: Profile = makeProfile(), posts: PostsPage = postsPage([makePost({ id: 1 })])) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue([])
  vi.mocked(socialApi.profile).mockResolvedValue(profile)
  vi.mocked(socialApi.profilePosts).mockResolvedValue(posts)
  await router.push({ name: 'reader', params: { handle: profile.handle } })

  const wrapper = mount(ReaderProfileView, { props: { handle: profile.handle }, global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

const FOLLOW = 'button[aria-label^="Підписатися на"]'

function followButton(wrapper: ReturnType<typeof mount>) {
  return wrapper.get(FOLLOW)
}

describe('ReaderProfileView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('the profile', () => {
    it('shows the name, the handle, the bio and the numbers', async () => {
      const { wrapper } = await mountView(
        makeProfile({ name: 'Olena Kovalenko', handle: 'olena_k', bio: 'I read on trains.', posts_count: 3, followers_count: 12, following_count: 7 }),
      )

      expect(socialApi.profile).toHaveBeenCalledWith('olena_k')
      expect(wrapper.get('h2').text()).toBe('Olena Kovalenko')
      expect(wrapper.text()).toContain('@olena_k')
      expect(wrapper.text()).toContain('I read on trains.')
      const tiles = wrapper.findAll('.tabular-nums').map((tile) => tile.text())
      expect(tiles).toEqual(expect.arrayContaining(['3', '12', '7']))
      expect(wrapper.text()).toContain('Підписники')
      expect(wrapper.text()).toContain('Підписки')
    })

    it('shows a loading note until the profile arrives', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.profile).mockReturnValue(new Promise(() => {}))
      await router.push({ name: 'reader', params: { handle: 'olena_k' } })

      const wrapper = mount(ReaderProfileView, { props: { handle: 'olena_k' }, global: { plugins } })
      await flushPromises()

      expect(wrapper.get('[role="status"]').text()).toContain('Завантаження…')
    })

    it('says so when there is no such reader, or they have blocked this one', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.profile).mockRejectedValue(new ApiError(404, null))
      await router.push({ name: 'reader', params: { handle: 'nobody' } })

      const wrapper = mount(ReaderProfileView, { props: { handle: 'nobody' }, global: { plugins } })
      await flushPromises()

      expect(wrapper.text()).toContain('Такого читача немає, або його профіль вам недоступний.')
      expect(socialApi.profilePosts).not.toHaveBeenCalled()
    })

    it('says why and offers a retry when the profile cannot be loaded', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.profile).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(makeProfile())
      vi.mocked(socialApi.profilePosts).mockResolvedValue(postsPage([]))
      await router.push({ name: 'reader', params: { handle: 'olena_k' } })
      const wrapper = mount(ReaderProfileView, { props: { handle: 'olena_k' }, global: { plugins } })
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.get('h2').text()).toBe('Olena Kovalenko')
    })

    it('never shows an e-mail address or a time zone, whatever the server sends', async () => {
      const profile = { ...makeProfile(), email: 'secret@example.com', timezone: 'Asia/Tokyo' } as Profile
      const { wrapper } = await mountView(profile)

      expect(wrapper.text()).not.toContain('secret@example.com')
      expect(wrapper.text()).not.toContain('Tokyo')
    })

    it('shows the bio as plain text, never as markup', async () => {
      const { wrapper } = await mountView(makeProfile({ bio: '<b>bold</b><img src=x onerror=alert(1)>' }))

      expect(wrapper.find('img').exists()).toBe(false)
      expect(wrapper.text()).toContain('<b>bold</b>')
    })

    it('has no bio line for a reader without one', async () => {
      const { wrapper } = await mountView(makeProfile({ bio: null }))

      expect(wrapper.findAll('p').map((paragraph) => paragraph.text())).not.toContain('')
    })
  })

  describe('their posts', () => {
    it('shows them, newest first as the server sends them', async () => {
      const { wrapper } = await mountView(makeProfile(), postsPage([makePost({ id: 2, content: 'Second' }), makePost({ id: 1, content: 'First' })]))

      expect(socialApi.profilePosts).toHaveBeenCalledWith('olena_k', 1)
      expect(wrapper.findAll('blockquote').map((quote) => quote.text())).toEqual(['Second', 'First'])
    })

    it('says there are none yet', async () => {
      const { wrapper } = await mountView(makeProfile({ posts_count: 0 }), postsPage([]))

      expect(wrapper.text()).toContain('Ще немає публікацій')
    })

    it('invites the reader themselves to write something when they have no posts', async () => {
      const { wrapper } = await mountView(makeProfile({ is_me: true, posts_count: 0 }), postsPage([]))

      expect(wrapper.text()).toContain('Ви ще нічого не опублікували')
    })

    it('adds the next page below on "show more"', async () => {
      const { wrapper } = await mountView(makeProfile(), postsPage([makePost({ id: 2 })], '/next'))
      vi.mocked(socialApi.profilePosts).mockResolvedValue(postsPage([makePost({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(socialApi.profilePosts).toHaveBeenLastCalledWith('olena_k', 2)
      expect(wrapper.findAll('article')).toHaveLength(2)
    })

    it('keeps the posts and offers a retry when the next page fails', async () => {
      const { wrapper } = await mountView(makeProfile(), postsPage([makePost({ id: 2 })], '/next'))
      vi.mocked(socialApi.profilePosts).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(postsPage([makePost({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()
      expect(wrapper.findAll('article')).toHaveLength(1)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(2)
    })

    it('says why and offers a retry when the posts cannot be loaded', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
      vi.mocked(socialApi.profilePosts).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(postsPage([makePost()]))
      await router.push({ name: 'reader', params: { handle: 'olena_k' } })
      const wrapper = mount(ReaderProfileView, { props: { handle: 'olena_k' }, global: { plugins } })
      await flushPromises()

      expect(wrapper.get('h2').text()).toBe('Olena Kovalenko')
      expect(wrapper.find('[role="alert"]').exists()).toBe(true)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(1)
    })
  })

  describe('following', () => {
    it('is a toggle that says whether the reader is followed', async () => {
      const { wrapper } = await mountView(makeProfile({ is_following: false }))

      expect(followButton(wrapper).attributes('aria-pressed')).toBe('false')
      expect(followButton(wrapper).text()).toBe('Підписатися')
      expect(followButton(wrapper).attributes('aria-label')).toBe('Підписатися на Olena Kovalenko')
    })

    it('shows the button and the number of followers at once, and tells the server', async () => {
      const { wrapper } = await mountView(makeProfile({ followers_count: 5 }))
      vi.mocked(socialApi.follow).mockReturnValue(new Promise(() => {}))

      await followButton(wrapper).trigger('click')

      expect(followButton(wrapper).attributes('aria-pressed')).toBe('true')
      expect(followButton(wrapper).text()).toBe('Ви підписані')
      expect(wrapper.text()).toContain('6')
      expect(socialApi.follow).toHaveBeenCalledWith('olena_k')
    })

    it('unfollows with another tap', async () => {
      const { wrapper } = await mountView(makeProfile({ is_following: true, followers_count: 5 }))
      vi.mocked(socialApi.unfollow).mockResolvedValue(makeProfile({ is_following: false, followers_count: 4 }))

      await followButton(wrapper).trigger('click')
      await flushPromises()

      expect(socialApi.unfollow).toHaveBeenCalledWith('olena_k')
      expect(followButton(wrapper).attributes('aria-pressed')).toBe('false')
    })

    it('goes back and says why when the server turns it down', async () => {
      const { wrapper } = await mountView(makeProfile({ followers_count: 5 }))
      vi.mocked(socialApi.follow).mockRejectedValue(new ApiError(429, null))

      await followButton(wrapper).trigger('click')
      await flushPromises()

      expect(followButton(wrapper).attributes('aria-pressed')).toBe('false')
      expect(wrapper.get('[role="alert"]').text()).toContain('Забагато запитів')
    })

    it('says a reader may not follow somebody they have blocked', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.follow).mockRejectedValue(new ApiError(422, { errors: { handle: ['social.user_blocked'] } }))

      await followButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Ви заблокували цього читача')
    })

    it('ignores a second tap while the first is on its way', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.follow).mockReturnValue(new Promise(() => {}))

      await followButton(wrapper).trigger('click')
      await followButton(wrapper).trigger('click')

      expect(socialApi.follow).toHaveBeenCalledTimes(1)
    })
  })

  describe('my own profile', () => {
    it('offers to edit it, and neither to follow nor to block myself', async () => {
      const { wrapper } = await mountView(makeProfile({ is_me: true }))

      const edit = wrapper.findAll('a').find((link) => link.text() === 'Редагувати профіль')

      expect(edit?.attributes('href')).toBe('/profile')
      expect(wrapper.find(FOLLOW).exists()).toBe(false)
      expect(wrapper.findAll('button').some((button) => button.text() === 'Заблокувати')).toBe(false)
    })
  })

  describe('blocking', () => {
    it('asks first, and does nothing until the reader confirms', async () => {
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Заблокувати').trigger('click')

      expect(wrapper.text()).toContain('Ви не бачитимете публікацій цього читача')
      expect(socialApi.block).not.toHaveBeenCalled()
    })

    it('blocks once confirmed: the posts are gone and the reader can unblock', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.block).mockResolvedValue(makeProfile({ is_blocked: true, is_following: false, posts_count: 0 }))

      await buttonWithText(wrapper, 'Заблокувати').trigger('click')
      await buttonWithText(wrapper, 'Так, заблокувати').trigger('click')
      await flushPromises()

      expect(socialApi.block).toHaveBeenCalledWith('olena_k')
      expect(wrapper.text()).toContain('Ви заблокували цього читача')
      expect(wrapper.findAll('article')).toHaveLength(0)
      expect(wrapper.find(FOLLOW).exists()).toBe(false)
      expect(buttonWithText(wrapper, 'Розблокувати').exists()).toBe(true)
    })

    it('lets the reader back out', async () => {
      const { wrapper } = await mountView()
      await buttonWithText(wrapper, 'Заблокувати').trigger('click')

      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(socialApi.block).not.toHaveBeenCalled()
      expect(wrapper.text()).not.toContain('Ви не бачитимете публікацій цього читача')
    })

    it('says why and stays as it was when blocking fails', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.block).mockRejectedValue(new ApiError(500, null))

      await buttonWithText(wrapper, 'Заблокувати').trigger('click')
      await buttonWithText(wrapper, 'Так, заблокувати').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(wrapper.findAll('article')).toHaveLength(1)
      expect(wrapper.text()).not.toContain('Ви не бачитимете публікацій цього читача')
    })

    it('shows a reader that is blocked without their posts, and unblocks them', async () => {
      const { wrapper } = await mountView(makeProfile({ is_blocked: true, posts_count: 0 }), postsPage([]))
      expect(socialApi.profilePosts).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Ви заблокували цього читача')
      vi.mocked(socialApi.unblock).mockResolvedValue(null)
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ is_blocked: false }))
      vi.mocked(socialApi.profilePosts).mockResolvedValue(postsPage([makePost({ id: 8, content: 'Back again' })]))

      await buttonWithText(wrapper, 'Розблокувати').trigger('click')
      await flushPromises()

      expect(socialApi.unblock).toHaveBeenCalledWith('olena_k')
      expect(wrapper.text()).not.toContain('Ви заблокували цього читача')
      expect(wrapper.get('blockquote').text()).toBe('Back again')
      expect(buttonWithText(wrapper, 'Заблокувати').exists()).toBe(true)
    })

    it('says why when unblocking fails', async () => {
      const { wrapper } = await mountView(makeProfile({ is_blocked: true }))
      vi.mocked(socialApi.unblock).mockRejectedValue(new ApiError(500, null))

      await buttonWithText(wrapper, 'Розблокувати').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(buttonWithText(wrapper, 'Розблокувати').exists()).toBe(true)
    })
  })

  describe('the posts in it', () => {
    it('can be liked, and the like shows at once', async () => {
      const { wrapper } = await mountView(makeProfile(), postsPage([makePost({ id: 3, likes_count: 1 })]))
      vi.mocked(socialApi.like).mockReturnValue(new Promise(() => {}))

      await wrapper.get('[data-testid="like"]').trigger('click')

      expect(wrapper.get('[data-testid="likes"]').text()).toBe('2')
    })
  })

  it('loads the other reader when the handle changes', async () => {
    const { wrapper } = await mountView()
    vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ handle: 'taras_b', name: 'Taras Bondar' }))
    vi.mocked(socialApi.profilePosts).mockResolvedValue(postsPage([]))

    await wrapper.setProps({ handle: 'taras_b' })
    await flushPromises()

    expect(socialApi.profile).toHaveBeenLastCalledWith('taras_b')
    expect(wrapper.get('h2').text()).toBe('Taras Bondar')
  })
})
