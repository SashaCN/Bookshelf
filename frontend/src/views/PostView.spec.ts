import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { socialApi } from '@/api/social'
import { usePostsStore } from '@/stores/posts'
import { makeComment, makePost, makeReview } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Comment, CommentsPage, Post } from '@/types/social'
import PostView from './PostView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))
vi.mock('@/api/social', () => ({
  socialApi: {
    getPost: vi.fn(),
    updatePost: vi.fn(),
    deletePost: vi.fn(),
    like: vi.fn(),
    unlike: vi.fn(),
    comments: vi.fn(),
    addComment: vi.fn(),
    deleteComment: vi.fn(),
    report: vi.fn(),
  },
}))

function commentsPage(comments: Comment[], next: string | null = null): CommentsPage {
  return { data: comments, links: { next } }
}

async function mountView(post: Post = makePost({ id: 5 }), options: { query?: Record<string, string>; comments?: CommentsPage } = {}) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue([])
  vi.mocked(socialApi.getPost).mockResolvedValue(post)
  vi.mocked(socialApi.comments).mockResolvedValue(options.comments ?? commentsPage([]))
  await router.push({ name: 'community-post', params: { id: post.id }, query: options.query ?? {} })

  const wrapper = mount(PostView, { props: { id: post.id }, global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('PostView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('the post', () => {
    it('asks the server for the post, and shows it whole', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, content: 'The whole text', comment: 'My thought' }))

      expect(socialApi.getPost).toHaveBeenCalledWith(5)
      expect(wrapper.get('blockquote').text()).toBe('The whole text')
      expect(wrapper.get('blockquote').classes()).not.toContain('line-clamp-6')
      expect(wrapper.text()).toContain('My thought')
    })

    it('shows a loading note until the post arrives', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.getPost).mockReturnValue(new Promise(() => {}))
      await router.push({ name: 'community-post', params: { id: 5 } })

      const wrapper = mount(PostView, { props: { id: 5 }, global: { plugins } })
      await flushPromises()

      expect(wrapper.get('[role="status"]').text()).toContain('Завантаження…')
      expect(wrapper.find('article').exists()).toBe(false)
    })

    it('says so when the post does not exist or is not for this reader', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.getPost).mockRejectedValue(new ApiError(404, null))
      await router.push({ name: 'community-post', params: { id: 5 } })

      const wrapper = mount(PostView, { props: { id: 5 }, global: { plugins } })
      await flushPromises()

      expect(wrapper.text()).toContain('Цієї публікації немає, або вона вам недоступна.')
      expect(socialApi.comments).not.toHaveBeenCalled()
    })

    it('says why and offers a retry when the post cannot be loaded', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.getPost).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(makePost({ id: 5 }))
      vi.mocked(socialApi.comments).mockResolvedValue(commentsPage([]))
      await router.push({ name: 'community-post', params: { id: 5 } })
      const wrapper = mount(PostView, { props: { id: 5 }, global: { plugins } })
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.find('article').exists()).toBe(true)
    })

    it('can be liked here, and the like shows at once', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, likes_count: 1 }))
      vi.mocked(socialApi.like).mockReturnValue(new Promise(() => {}))

      await wrapper.get('[data-testid="like"]').trigger('click')

      expect(wrapper.get('[data-testid="like"]').attributes('aria-pressed')).toBe('true')
      expect(wrapper.get('[data-testid="likes"]').text()).toBe('2')
    })

    it('flags a post that moderation hid, for its author', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, is_mine: true, is_hidden: true }))

      expect(wrapper.text()).toContain('Приховано модерацією')
    })

    it('goes back to the community when the reader deletes their post here', async () => {
      const { wrapper, router } = await mountView(makePost({ id: 5, is_mine: true }))
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)

      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Видалити').trigger('click')
      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(socialApi.deletePost).toHaveBeenCalledWith(5)
      expect(router.currentRoute.value.name).toBe('community')
    })
  })

  describe('the comments', () => {
    it('loads and shows them, with the form above', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, comments_count: 2 }), {
        comments: commentsPage([makeComment({ id: 2, content: 'Second' }), makeComment({ id: 1, content: 'First' })]),
      })

      expect(socialApi.comments).toHaveBeenCalledWith(5, 1)
      const items = wrapper.findAll('section li')
      expect(items.map((item) => item.text()).join(' ')).toContain('Second')
      expect(items).toHaveLength(2)
      expect(wrapper.find('textarea#comment-content').exists()).toBe(true)
    })

    it('invites the first comment when there are none', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.text()).toContain('Коментарів поки немає. Напишіть перший.')
    })

    it('says why and offers a retry when the comments cannot be loaded', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(socialApi.getPost).mockResolvedValue(makePost({ id: 5 }))
      vi.mocked(socialApi.comments).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(commentsPage([makeComment()]))
      await router.push({ name: 'community-post', params: { id: 5 } })
      const wrapper = mount(PostView, { props: { id: 5 }, global: { plugins } })
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(wrapper.find('article').exists()).toBe(true)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.findAll('section li')).toHaveLength(1)
    })

    it('adds the next page below on "show more"', async () => {
      const { wrapper } = await mountView(makePost({ id: 5 }), { comments: commentsPage([makeComment({ id: 2 })], '/next') })
      vi.mocked(socialApi.comments).mockResolvedValue(commentsPage([makeComment({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(socialApi.comments).toHaveBeenLastCalledWith(5, 2)
      expect(wrapper.findAll('section li')).toHaveLength(2)
      expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
    })

    it('puts a new comment first and counts it', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, comments_count: 1 }), { comments: commentsPage([makeComment({ id: 1, content: 'Old one' })]) })
      vi.mocked(socialApi.addComment).mockResolvedValue(makeComment({ id: 2, post_id: 5, content: 'Brand new', is_mine: true, can_delete: true }))

      await wrapper.get('#comment-content').setValue('Brand new')
      await wrapper.get('#comment-content').element.closest('form')!.dispatchEvent(new Event('submit'))
      await flushPromises()

      const items = wrapper.findAll('section li')
      expect(items[0]!.text()).toContain('Brand new')
      expect(items).toHaveLength(2)
      expect(wrapper.get('[data-testid="comments"]').text()).toBe('2')
    })

    it('loads the comments of the other post when the post changes', async () => {
      const { wrapper } = await mountView(makePost({ id: 5 }))
      vi.mocked(socialApi.getPost).mockResolvedValue(makePost({ id: 6 }))
      vi.mocked(socialApi.comments).mockResolvedValue(commentsPage([makeComment({ id: 9, post_id: 6, content: 'On the other post' })]))

      await wrapper.setProps({ id: 6 })
      await flushPromises()

      expect(socialApi.getPost).toHaveBeenLastCalledWith(6)
      expect(socialApi.comments).toHaveBeenLastCalledWith(6, 1)
      expect(wrapper.text()).toContain('On the other post')
    })
  })

  describe('editing', () => {
    it('is closed at first', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, is_mine: true }))

      expect(wrapper.find('#edit-comment').exists()).toBe(false)
    })

    it('opens for the author with ?edit=1, starting from the comment as it is', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, is_mine: true, comment: 'Old thought' }), { query: { edit: '1' } })

      expect((wrapper.get('#edit-comment').element as HTMLTextAreaElement).value).toBe('Old thought')
      expect(wrapper.text()).toContain('Текст цитати змінити не можна')
    })

    it('does not open for somebody who is not the author', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, is_mine: false }), { query: { edit: '1' } })

      expect(wrapper.find('#edit-comment').exists()).toBe(false)
    })

    it('saves the comment of a quote post and closes', async () => {
      const { wrapper, router } = await mountView(makePost({ id: 5, is_mine: true, comment: 'Old' }), { query: { edit: '1' } })
      vi.mocked(socialApi.updatePost).mockResolvedValue(makePost({ id: 5, is_mine: true, comment: 'New thought' }))

      await wrapper.get('#edit-comment').setValue('  New thought  ')
      await wrapper.get('#edit-comment').element.closest('form')!.dispatchEvent(new Event('submit'))
      await flushPromises()

      expect(socialApi.updatePost).toHaveBeenCalledWith(5, { comment: 'New thought' })
      expect(usePostsStore().get(5)?.comment).toBe('New thought')
      expect(router.currentRoute.value.query).toEqual({})
      expect(wrapper.find('#edit-comment').exists()).toBe(false)
    })

    it('clears the comment when it is emptied', async () => {
      const { wrapper } = await mountView(makePost({ id: 5, is_mine: true, comment: 'Old' }), { query: { edit: '1' } })
      vi.mocked(socialApi.updatePost).mockResolvedValue(makePost({ id: 5, is_mine: true, comment: null }))

      await wrapper.get('#edit-comment').setValue('')
      await wrapper.get('#edit-comment').element.closest('form')!.dispatchEvent(new Event('submit'))
      await flushPromises()

      expect(socialApi.updatePost).toHaveBeenCalledWith(5, { comment: null })
    })

    it('saves the rating and the text of a review', async () => {
      const { wrapper } = await mountView(makeReview({ id: 5, is_mine: true, rating: 3, content: 'Meh' }), { query: { edit: '1' } })
      vi.mocked(socialApi.updatePost).mockResolvedValue(makeReview({ id: 5, is_mine: true, rating: 5, content: 'Great after all' }))

      await wrapper.get('[aria-label="5 / 5"]').trigger('click')
      await wrapper.get('#edit-content').setValue('Great after all')
      await wrapper.get('#edit-content').element.closest('form')!.dispatchEvent(new Event('submit'))
      await flushPromises()

      expect(socialApi.updatePost).toHaveBeenCalledWith(5, { rating: 5, content: 'Great after all' })
      expect(usePostsStore().get(5)?.rating).toBe(5)
    })

    it('does not save a review without a rating', async () => {
      const { wrapper } = await mountView(makeReview({ id: 5, is_mine: true, rating: 3 }), { query: { edit: '1' } })

      // Tapping the rating that is set takes it away.
      await wrapper.get('[aria-label="3 / 5"]').trigger('click')
      await wrapper.get('#edit-content').element.closest('form')!.dispatchEvent(new Event('submit'))
      await flushPromises()

      expect(socialApi.updatePost).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Поставте оцінку від 1 до 5.')
    })

    it('shows a refused text next to the field', async () => {
      const { wrapper } = await mountView(makeReview({ id: 5, is_mine: true }), { query: { edit: '1' } })
      vi.mocked(socialApi.updatePost).mockRejectedValue(new ApiError(422, { errors: { content: ['The content field must not be greater than 5000 characters.'] } }))

      await wrapper.get('#edit-content').element.closest('form')!.dispatchEvent(new Event('submit'))
      await flushPromises()

      expect(wrapper.get('#edit-content-error').text()).toBe('Відгук має бути не довшим за 5000 символів.')
    })

    it('says why and stays open when saving fails', async () => {
      const { wrapper, router } = await mountView(makePost({ id: 5, is_mine: true }), { query: { edit: '1' } })
      vi.mocked(socialApi.updatePost).mockRejectedValue(new ApiError(500, null))

      await wrapper.get('#edit-comment').element.closest('form')!.dispatchEvent(new Event('submit'))
      await flushPromises()

      expect(wrapper.find('#edit-comment').exists()).toBe(true)
      expect(wrapper.find('form [role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(router.currentRoute.value.query).toEqual({ edit: '1' })
    })

    it('closes on cancel without saving', async () => {
      const { wrapper, router } = await mountView(makePost({ id: 5, is_mine: true }), { query: { edit: '1' } })

      await buttonWithText(wrapper, 'Скасувати').trigger('click')
      await flushPromises()

      expect(socialApi.updatePost).not.toHaveBeenCalled()
      expect(router.currentRoute.value.query).toEqual({})
    })
  })
})
