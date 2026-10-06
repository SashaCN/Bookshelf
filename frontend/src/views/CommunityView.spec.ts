import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { socialApi } from '@/api/social'
import { useFeedStore } from '@/stores/feed'
import { makePost, makeReader } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Post, PostsPage, Reader, ReadersPage } from '@/types/social'
import CommunityView from './CommunityView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))
vi.mock('@/api/social', () => ({
  socialApi: { feed: vi.fn(), searchReaders: vi.fn(), like: vi.fn(), unlike: vi.fn(), deletePost: vi.fn(), report: vi.fn() },
}))

function feedPage(posts: Post[], next: string | null = null): PostsPage {
  return { data: posts, links: { next } }
}

function readersPage(readers: Reader[], next: string | null = null): ReadersPage {
  return { data: readers, links: { next } }
}

async function mountView(query: Record<string, string> = {}, firstPage: PostsPage = feedPage([makePost({ id: 1 })])) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue([])
  vi.mocked(socialApi.feed).mockResolvedValue(firstPage)
  await router.push({ name: 'community', query })

  const wrapper = mount(CommunityView, { global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('CommunityView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('the feed', () => {
    it('shows the posts, newest first as the server sends them', async () => {
      const { wrapper } = await mountView(
        {},
        feedPage([makePost({ id: 2, content: 'Second' }), makePost({ id: 1, content: 'First' })]),
      )

      expect(socialApi.feed).toHaveBeenCalledWith('following', 1)
      expect(wrapper.findAll('blockquote').map((quote) => quote.text())).toEqual(['Second', 'First'])
    })

    it('shows a loading note until the first answer', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.feed).mockReturnValue(new Promise(() => {}))
      await router.push({ name: 'community' })

      const wrapper = mount(CommunityView, { global: { plugins } })
      await flushPromises()

      expect(wrapper.get('[role="status"]').text()).toContain('Завантаження…')
      expect(wrapper.find('article').exists()).toBe(false)
    })

    it('offers writing a post', async () => {
      const { wrapper } = await mountView()

      const write = wrapper.findAll('a').find((link) => link.text() === 'Написати')

      expect(write?.attributes('href')).toBe('/community/new')
    })

    it('says why and offers a retry when the feed cannot be loaded', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.feed).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(feedPage([makePost()]))
      await router.push({ name: 'community' })
      const wrapper = mount(CommunityView, { global: { plugins } })
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.findAll('article')).toHaveLength(1)
    })

    it('says there is no connection when the network is down', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.feed).mockRejectedValue(new TypeError('offline'))
      await router.push({ name: 'community' })
      const wrapper = mount(CommunityView, { global: { plugins } })
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    })
  })

  describe('the scope', () => {
    it('starts on the readers one follows', async () => {
      const { wrapper } = await mountView()

      expect(buttonWithText(wrapper, 'Підписки').attributes('aria-pressed')).toBe('true')
      expect(buttonWithText(wrapper, 'Усі').attributes('aria-pressed')).toBe('false')
    })

    it('switches to everybody and remembers it in the URL', async () => {
      const { wrapper, router } = await mountView()
      vi.mocked(socialApi.feed).mockResolvedValue(feedPage([makePost({ id: 5, content: 'From everybody' })]))

      await buttonWithText(wrapper, 'Усі').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({ scope: 'all' })
      expect(socialApi.feed).toHaveBeenLastCalledWith('all', 1)
      expect(buttonWithText(wrapper, 'Усі').attributes('aria-pressed')).toBe('true')
      expect(wrapper.get('blockquote').text()).toBe('From everybody')
    })

    it('goes back to the readers one follows', async () => {
      const { wrapper, router } = await mountView({ scope: 'all' })

      await buttonWithText(wrapper, 'Підписки').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({})
      expect(socialApi.feed).toHaveBeenLastCalledWith('following', 1)
    })

    it('is restored from the URL', async () => {
      const { wrapper } = await mountView({ scope: 'all' })

      expect(socialApi.feed).toHaveBeenCalledWith('all', 1)
      expect(buttonWithText(wrapper, 'Усі').attributes('aria-pressed')).toBe('true')
    })
  })

  describe('an empty feed', () => {
    it('suggests everybody\'s posts to a reader who follows nobody', async () => {
      const { wrapper, router } = await mountView({}, feedPage([]))

      expect(wrapper.text()).toContain('У стрічці поки порожньо')

      vi.mocked(socialApi.feed).mockResolvedValue(feedPage([makePost()]))
      await buttonWithText(wrapper, 'Показати всі').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({ scope: 'all' })
      expect(wrapper.findAll('article')).toHaveLength(1)
    })

    it('invites the first post when nobody has written anything', async () => {
      const { wrapper } = await mountView({ scope: 'all' }, feedPage([]))

      expect(wrapper.text()).toContain('Ще ніхто нічого не опублікував')
      const links = wrapper.findAll('a').filter((link) => link.text() === 'Написати')
      expect(links.map((link) => link.attributes('href'))).toEqual(['/community/new', '/community/new'])
    })
  })

  describe('paging', () => {
    it('shows "show more" only while there is a next page, and adds the next page below', async () => {
      const { wrapper } = await mountView({}, feedPage([makePost({ id: 2 })], '/api/feed?page=2'))
      vi.mocked(socialApi.feed).mockResolvedValue(feedPage([makePost({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(socialApi.feed).toHaveBeenLastCalledWith('following', 2)
      expect(wrapper.findAll('article')).toHaveLength(2)
      expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
    })

    it('has no "show more" when everything fits on one page', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
    })

    it('keeps the posts and offers a retry when the next page fails', async () => {
      const { wrapper } = await mountView({}, feedPage([makePost({ id: 2 })], '/next'))
      vi.mocked(socialApi.feed).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(feedPage([makePost({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(1)
      expect(wrapper.find('[role="alert"]').exists()).toBe(true)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(2)
    })
  })

  describe('the posts in it', () => {
    it('can be liked, and the like shows at once', async () => {
      const { wrapper } = await mountView({}, feedPage([makePost({ id: 3, likes_count: 1 })]))
      vi.mocked(socialApi.like).mockReturnValue(new Promise(() => {}))

      await wrapper.get('[data-testid="like"]').trigger('click')

      expect(wrapper.get('[data-testid="like"]').attributes('aria-pressed')).toBe('true')
      expect(wrapper.get('[data-testid="likes"]').text()).toBe('2')
    })

    it('disappear when the reader deletes one of their own', async () => {
      const { wrapper } = await mountView({}, feedPage([makePost({ id: 3, is_mine: true })]))
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)

      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Видалити').trigger('click')
      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(0)
      expect(useFeedStore().items).toEqual([])
    })
  })

  describe('searching for readers', () => {
    it('finds readers by what was typed and remembers the search in the URL', async () => {
      const { wrapper, router } = await mountView()
      vi.mocked(socialApi.searchReaders).mockResolvedValue(
        readersPage([makeReader({ handle: 'olena_k', name: 'Olena Kovalenko', bio: 'I read on trains.' })]),
      )

      await wrapper.get('#reader-search').setValue('  olen ')
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({ q: 'olen' })
      expect(socialApi.searchReaders).toHaveBeenCalledWith('olen', 1)
      const result = wrapper.get('section li')
      expect(result.text()).toContain('Olena Kovalenko')
      expect(result.text()).toContain('@olena_k')
      expect(result.text()).toContain('I read on trains.')
      expect(result.get('a').attributes('href')).toBe('/u/olena_k')
    })

    it('shows the results in place of the feed', async () => {
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([makeReader()]))
      const { wrapper } = await mountView({ q: 'olen' })

      expect(wrapper.find('article').exists()).toBe(false)
      expect(wrapper.find('[aria-pressed]').exists()).toBe(false)
    })

    it('is restored from the URL', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.feed).mockResolvedValue(feedPage([]))
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([makeReader({ handle: 'taras_b', name: 'Taras' })]))
      await router.push({ name: 'community', query: { q: 'taras' } })

      const wrapper = mount(CommunityView, { global: { plugins } })
      await flushPromises()

      expect(socialApi.searchReaders).toHaveBeenCalledWith('taras', 1)
      expect((wrapper.get('#reader-search').element as HTMLInputElement).value).toBe('taras')
      expect(wrapper.text()).toContain('@taras_b')
    })

    it('says nobody was found', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([]))

      await wrapper.get('#reader-search').setValue('zzz')
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()

      expect(wrapper.text()).toContain('Нікого не знайшли')
    })

    it('marks the reader themselves in the results', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([makeReader({ is_me: true })]))

      await wrapper.get('#reader-search').setValue('olena')
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()

      expect(wrapper.get('section li').text()).toContain('(це ви)')
    })

    it('cannot be sent while the box is empty', async () => {
      const { wrapper } = await mountView()

      expect(buttonWithText(wrapper, 'Знайти').attributes('disabled')).toBeDefined()

      await wrapper.get('#reader-search').setValue('ol')
      expect(buttonWithText(wrapper, 'Знайти').attributes('disabled')).toBeUndefined()

      await wrapper.get('#reader-search').setValue('   ')
      await wrapper.get('form[role="search"]').trigger('submit')
      expect(socialApi.searchReaders).not.toHaveBeenCalled()
    })

    it.each([['@'], ['@@'], ['a'], ['@a'], [' o ']])('cannot be sent for "%s", which is too little to search for, and says so', async (text) => {
      const { wrapper } = await mountView()

      await wrapper.get('#reader-search').setValue(text)
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()

      expect(buttonWithText(wrapper, 'Знайти').attributes('disabled')).toBeDefined()
      expect(socialApi.searchReaders).not.toHaveBeenCalled()
      expect(wrapper.get('#reader-search-hint').text()).toBe('Введіть щонайменше два символи.')
      expect(wrapper.get('#reader-search').attributes('aria-describedby')).toBe('reader-search-hint')
    })

    it('says nothing about the length while the box is empty', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.find('#reader-search-hint').exists()).toBe(false)
    })

    it('leaves out the @ of what was typed: "@ol" searches for "ol"', async () => {
      const { wrapper, router } = await mountView()
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([makeReader()]))

      await wrapper.get('#reader-search').setValue('@ol')
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()

      expect(socialApi.searchReaders).toHaveBeenCalledWith('ol', 1)
      expect(router.currentRoute.value.query).toEqual({ q: 'ol' })
    })

    it('does not search for what an address asks that is too short, and shows the feed', async () => {
      const { wrapper } = await mountView({ q: '@' })

      expect(socialApi.searchReaders).not.toHaveBeenCalled()
      expect(wrapper.findAll('article')).toHaveLength(1)
    })

    it('goes back to the feed when the search is cleared', async () => {
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([makeReader()]))
      const { wrapper, router } = await mountView({ q: 'olen' })

      await buttonWithText(wrapper, 'Скинути пошук').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({})
      expect((wrapper.get('#reader-search').element as HTMLInputElement).value).toBe('')
      expect(wrapper.findAll('article')).toHaveLength(1)
    })

    it('keeps the scope of the feed while searching', async () => {
      const { wrapper, router } = await mountView({ scope: 'all' })
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([]))

      await wrapper.get('#reader-search').setValue('olen')
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({ scope: 'all', q: 'olen' })
    })

    it('adds the next page of results below, without a reader twice', async () => {
      vi.mocked(socialApi.searchReaders)
        .mockResolvedValueOnce(readersPage([makeReader({ handle: 'a_reader' })], '/next'))
        .mockResolvedValueOnce(readersPage([makeReader({ handle: 'a_reader' }), makeReader({ handle: 'b_reader' })]))
      const { wrapper } = await mountView({ q: 'olen2' })

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(socialApi.searchReaders).toHaveBeenLastCalledWith('olen2', 2)
      expect(wrapper.findAll('section li')).toHaveLength(2)
    })

    it('says why and offers a retry when the search fails', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.searchReaders).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(readersPage([makeReader()]))

      await wrapper.get('#reader-search').setValue('olen')
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()
      expect(wrapper.find('[role="alert"]').exists()).toBe(true)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.findAll('section li')).toHaveLength(1)
    })

    it('never shows an e-mail address of a reader', async () => {
      const { wrapper } = await mountView()
      const reader = { ...makeReader(), email: 'secret@example.com' } as Reader
      vi.mocked(socialApi.searchReaders).mockResolvedValue(readersPage([reader]))

      await wrapper.get('#reader-search').setValue('olen')
      await wrapper.get('form[role="search"]').trigger('submit')
      await flushPromises()

      expect(wrapper.text()).not.toContain('secret@example.com')
    })
  })
})
