import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { useLibraryStore } from '@/stores/library'
import { usePostsStore } from '@/stores/posts'
import { makeBook, makeUserBook } from '@/test/fixtures'
import { makePost, makeReview } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Post } from '@/types/social'
import PostCard from './PostCard.vue'

vi.mock('@/api/social', () => ({
  socialApi: { like: vi.fn(), unlike: vi.fn(), deletePost: vi.fn(), report: vi.fn() },
}))

// The card gets its post from the store, the way the lists pass it in (and goes away with it).
const Host = defineComponent({
  components: { PostCard },
  props: { id: { type: Number, required: true }, detail: Boolean, showBook: { type: Boolean, default: true } },
  setup: () => ({ posts: usePostsStore() }),
  template: '<PostCard v-if="posts.get(id)" :post="posts.get(id)" :detail="detail" :show-book="showBook" />',
})

async function mountCard(post: Post, props: { detail?: boolean; showBook?: boolean } = {}) {
  const { plugins, router } = createTestEnvironment()
  const posts = usePostsStore()
  posts.put(post)
  await router.push('/community')

  const wrapper = mount(Host, { props: { id: post.id, ...props }, global: { plugins }, attachTo: document.body })

  return { wrapper, posts, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

function likeButton(wrapper: ReturnType<typeof mount>) {
  return wrapper.get('[data-testid="like"]')
}

describe('PostCard', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    document.body.innerHTML = ''
  })

  describe('a quote', () => {
    it('shows the text, the comment and where the quote comes from', async () => {
      const { wrapper } = await mountCard(makePost({ comment: 'My favourite line.', page: 42 }))

      expect(wrapper.get('blockquote').text()).toBe('We are what we repeatedly do.')
      expect(wrapper.text()).toContain('My favourite line.')
      expect(wrapper.text()).toContain('Atomic Habits · James Clear · с. 42')
    })

    it('leaves out the parts that are not known', async () => {
      const { wrapper } = await mountCard(
        makePost({ comment: null, page: null, book: { id: 1, title: 'Atomic Habits', authors: [], cover_url: null } }),
      )

      expect(wrapper.text()).not.toContain('с.')
      expect(wrapper.text()).not.toContain('·  ')
      expect(wrapper.findAll('p').map((paragraph) => paragraph.text())).not.toContain('')
    })

    it('does not claim to be a review', async () => {
      const { wrapper } = await mountCard(makePost())

      expect(wrapper.text()).not.toContain('Відгук')
    })
  })

  describe('a review', () => {
    it('shows the rating and the text', async () => {
      const { wrapper } = await mountCard(makeReview({ rating: 4, content: 'Worth reading twice.' }))

      expect(wrapper.text()).toContain('Відгук')
      expect(wrapper.find('[aria-label="Оцінка 4 з 5"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Worth reading twice.')
      expect(wrapper.find('blockquote').exists()).toBe(false)
    })

    it('shows a rating alone', async () => {
      const { wrapper } = await mountCard(makeReview({ rating: 5, content: null }))

      expect(wrapper.find('[aria-label="Оцінка 5 з 5"]').exists()).toBe(true)
    })

    it('can leave the book out where the screen is about it', async () => {
      const { wrapper } = await mountCard(makeReview(), { showBook: false })

      expect(wrapper.text()).not.toContain('Atomic Habits')
    })
  })

  describe('the author and the book', () => {
    it('names the author with a link to their profile', async () => {
      const { wrapper } = await mountCard(makePost({ author: { handle: 'taras_b', name: 'Taras Bondar' } }))

      const link = wrapper.findAll('a').find((candidate) => candidate.text() === 'Taras Bondar')

      expect(link?.attributes('href')).toBe('/u/taras_b')
      expect(wrapper.text()).toContain('@taras_b')
    })

    it('links the title to the book when it is in my library', async () => {
      const { wrapper } = await mountCard(makePost({ book: { id: 9, title: 'Atomic Habits', authors: [], cover_url: null } }))
      useLibraryStore().items = [makeUserBook({ id: 7, book: makeBook({ id: 9 }) })]
      await flushPromises()

      const link = wrapper.findAll('a').find((candidate) => candidate.text() === 'Atomic Habits')

      expect(link?.attributes('href')).toBe('/library/7')
    })

    it('does not link a book that is not in my library', async () => {
      const { wrapper } = await mountCard(makePost({ book: { id: 9, title: 'Atomic Habits', authors: [], cover_url: null } }))

      expect(wrapper.findAll('a').find((candidate) => candidate.text() === 'Atomic Habits')).toBeUndefined()
      expect(wrapper.text()).toContain('Atomic Habits')
    })

    it('never shows an e-mail address or a time zone', async () => {
      const post = { ...makePost(), email: 'secret@example.com', timezone: 'Asia/Tokyo' } as Post
      const { wrapper } = await mountCard(post)

      expect(wrapper.text()).not.toContain('secret@example.com')
      expect(wrapper.text()).not.toContain('Tokyo')
    })
  })

  describe('moderation', () => {
    it('tells the author that moderation hid the post', async () => {
      const { wrapper } = await mountCard(makePost({ is_mine: true, is_hidden: true }))

      expect(wrapper.get('[role="status"]').text()).toContain('Приховано модерацією')
    })

    it('says nothing of the sort for a post that is visible', async () => {
      const { wrapper } = await mountCard(makePost({ is_mine: true }))

      expect(wrapper.find('[role="status"]').exists()).toBe(false)
      expect(wrapper.text()).not.toContain('Приховано')
    })
  })

  describe('the like', () => {
    it('is named by its words and its count together, so the count is not hidden from a screen reader', async () => {
      const { wrapper } = await mountCard(makePost({ likes_count: 3 }))

      expect(likeButton(wrapper).attributes('aria-label')).toBeUndefined()
      expect(likeButton(wrapper).text()).toBe('Подобається3')
      expect(likeButton(wrapper).get('.sr-only').text()).toBe('Подобається')
    })

    it('has the new count in its name as soon as it changes', async () => {
      const { wrapper } = await mountCard(makePost({ likes_count: 3 }))
      vi.mocked(socialApi.like).mockReturnValue(new Promise(() => {}))

      await likeButton(wrapper).trigger('click')

      expect(likeButton(wrapper).text()).toBe('Подобається4')
    })

    it('is a toggle that says whether I have liked the post, with the number of likes', async () => {
      const { wrapper } = await mountCard(makePost({ liked_by_me: true, likes_count: 3 }))

      expect(likeButton(wrapper).attributes('aria-pressed')).toBe('true')
      expect(wrapper.get('[data-testid="likes"]').text()).toBe('3')
    })

    it('is not pressed for a post I have not liked', async () => {
      const { wrapper } = await mountCard(makePost())

      expect(likeButton(wrapper).attributes('aria-pressed')).toBe('false')
    })

    it('turns on at once and tells the server', async () => {
      const { wrapper } = await mountCard(makePost({ id: 3, likes_count: 2 }))
      vi.mocked(socialApi.like).mockReturnValue(new Promise(() => {}))

      await likeButton(wrapper).trigger('click')

      expect(likeButton(wrapper).attributes('aria-pressed')).toBe('true')
      expect(wrapper.get('[data-testid="likes"]').text()).toBe('3')
      expect(socialApi.like).toHaveBeenCalledWith(3)
    })

    it('shows the exact number the server answers with', async () => {
      const { wrapper } = await mountCard(makePost({ id: 3, likes_count: 2 }))
      vi.mocked(socialApi.like).mockResolvedValue(makePost({ id: 3, liked_by_me: true, likes_count: 9 }))

      await likeButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.get('[data-testid="likes"]').text()).toBe('9')
    })

    it('turns off again with another tap', async () => {
      const { wrapper } = await mountCard(makePost({ id: 3, liked_by_me: true, likes_count: 1 }))
      vi.mocked(socialApi.unlike).mockResolvedValue(makePost({ id: 3, liked_by_me: false, likes_count: 0 }))

      await likeButton(wrapper).trigger('click')
      await flushPromises()

      expect(socialApi.unlike).toHaveBeenCalledWith(3)
      expect(likeButton(wrapper).attributes('aria-pressed')).toBe('false')
      expect(wrapper.get('[data-testid="likes"]').text()).toBe('0')
    })

    it('goes back and says why when the server turns it down', async () => {
      const { wrapper } = await mountCard(makePost({ likes_count: 2 }))
      vi.mocked(socialApi.like).mockRejectedValue(new ApiError(500, null))

      await likeButton(wrapper).trigger('click')
      await flushPromises()

      expect(likeButton(wrapper).attributes('aria-pressed')).toBe('false')
      expect(wrapper.get('[data-testid="likes"]').text()).toBe('2')
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
    })

    it('tells the reader to slow down when the server says so', async () => {
      const { wrapper } = await mountCard(makePost())
      vi.mocked(socialApi.like).mockRejectedValue(new ApiError(429, null))

      await likeButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Забагато запитів')
    })

    it('ignores a second tap while the first is still on its way', async () => {
      const { wrapper } = await mountCard(makePost())
      vi.mocked(socialApi.like).mockReturnValue(new Promise(() => {}))

      await likeButton(wrapper).trigger('click')
      await likeButton(wrapper).trigger('click')

      expect(socialApi.like).toHaveBeenCalledTimes(1)
      expect(socialApi.unlike).not.toHaveBeenCalled()
    })
  })

  describe('the comments', () => {
    it.each([
      [0, '0 коментарів'],
      [1, '1 коментар'],
      [2, '2 коментарі'],
      [5, '5 коментарів'],
      [21, '21 коментар'],
    ])('link to the post page, saying how many there are (%i)', async (count, label) => {
      const { wrapper } = await mountCard(makePost({ id: 4, comments_count: count }))

      const link = wrapper.get('a[href="/community/posts/4"]')

      // The name of the link is the words with the number in them; the number on show is left out of it.
      expect(link.get('.sr-only').text()).toBe(label)
      expect(link.get('[data-testid="comments"]').attributes('aria-hidden')).toBe('true')
      expect(wrapper.get('[data-testid="comments"]').text()).toBe(String(count))
    })

    it('are not a link on the page of the post itself', async () => {
      const { wrapper } = await mountCard(makePost({ comments_count: 2 }), { detail: true })

      expect(wrapper.find('a[href="/community/posts/5"]').exists()).toBe(false)
      expect(wrapper.text()).toContain('2 коментарі')
    })

    it('are not named by an aria-label on a plain span, which a screen reader would not read', async () => {
      const { wrapper } = await mountCard(makePost({ comments_count: 2 }), { detail: true })

      expect(wrapper.findAll('span[aria-label]')).toHaveLength(0)
    })
  })

  describe('the long text', () => {
    it('is cut short in a list and whole on the page of the post', async () => {
      const list = await mountCard(makePost({ comment: 'x' }))
      expect(list.wrapper.get('blockquote').classes()).toContain('line-clamp-6')

      const detail = await mountCard(makePost({ comment: 'x' }), { detail: true })
      expect(detail.wrapper.get('blockquote').classes()).not.toContain('line-clamp-6')
    })
  })

  describe('the menu of somebody else\'s post', () => {
    it('is closed at first and offers to report the post', async () => {
      const { wrapper } = await mountCard(makePost())
      const menu = wrapper.get('[aria-label="Дії з публікацією"]')
      expect(menu.attributes('aria-expanded')).toBe('false')
      expect(wrapper.text()).not.toContain('Поскаржитись')

      await menu.trigger('click')

      expect(menu.attributes('aria-expanded')).toBe('true')
      expect(buttonWithText(wrapper, 'Поскаржитись').exists()).toBe(true)
      expect(wrapper.text()).not.toContain('Видалити')
      expect(wrapper.text()).not.toContain('Редагувати')
    })

    it('has a button that names the menu it opens, and tells whether it is open', async () => {
      const { wrapper } = await mountCard(makePost())
      const menu = wrapper.get('[aria-label="Дії з публікацією"]')

      await menu.trigger('click')

      const panel = wrapper.get(`#${menu.attributes('aria-controls')}`)
      expect(menu.attributes('aria-expanded')).toBe('true')
      expect(panel.text()).toContain('Поскаржитись')
    })

    it('closes on Escape, and the focus goes back to the button that opened it', async () => {
      const { wrapper } = await mountCard(makePost())
      const menu = wrapper.get('[aria-label="Дії з публікацією"]')
      await menu.trigger('click')

      await wrapper.get('article').trigger('keydown', { key: 'Escape' })

      expect(menu.attributes('aria-expanded')).toBe('false')
      expect(wrapper.text()).not.toContain('Поскаржитись')
      expect(document.activeElement).toBe(menu.element)
    })

    it('closes on a tap anywhere outside of it', async () => {
      const { wrapper } = await mountCard(makePost())
      const menu = wrapper.get('[aria-label="Дії з публікацією"]')
      await menu.trigger('click')
      expect(menu.attributes('aria-expanded')).toBe('true')

      document.body.click()
      await flushPromises()

      expect(menu.attributes('aria-expanded')).toBe('false')
      expect(wrapper.text()).not.toContain('Поскаржитись')
    })

    it('stays open on a tap inside it, and on a tap on its own button it closes once, not twice', async () => {
      const { wrapper } = await mountCard(makePost({ is_mine: true }))
      const menu = wrapper.get('[aria-label="Дії з публікацією"]')
      await menu.trigger('click')

      await wrapper.get('a[href$="edit=1"]').trigger('click')
      expect(menu.attributes('aria-expanded')).toBe('true')

      await menu.trigger('click')
      expect(menu.attributes('aria-expanded')).toBe('false')
    })

    it('does not listen to the page once it is closed or the card is gone', async () => {
      const add = vi.spyOn(document, 'addEventListener')
      const remove = vi.spyOn(document, 'removeEventListener')
      const { wrapper } = await mountCard(makePost())
      const menu = wrapper.get('[aria-label="Дії з публікацією"]')

      await menu.trigger('click')
      expect(add).toHaveBeenCalledWith('click', expect.any(Function))

      await menu.trigger('click')
      expect(remove).toHaveBeenCalledWith('click', expect.any(Function))

      remove.mockClear()
      wrapper.unmount()
      expect(remove).toHaveBeenCalledWith('click', expect.any(Function))
      add.mockRestore()
      remove.mockRestore()
    })

    it('opens the report dialog for this post', async () => {
      const { wrapper } = await mountCard(makePost({ id: 6 }))

      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Поскаржитись').trigger('click')

      expect(wrapper.get('[role="dialog"]').text()).toContain('Поскаржитись')

      await wrapper.findAll('input[type="radio"]')[0]!.setValue(true)
      vi.mocked(socialApi.report).mockResolvedValue({})
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.report).toHaveBeenCalledWith({ kind: 'post', id: 6 }, { reason: 'spam', note: null })
    })

    it('closes the report dialog on cancel, and the focus goes back to the menu button', async () => {
      const { wrapper } = await mountCard(makePost())
      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Поскаржитись').trigger('click')

      await buttonWithText(wrapper, 'Скасувати').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(document.activeElement).toBe(wrapper.get('[aria-label="Дії з публікацією"]').element)
    })
  })

  describe('the menu of my own post', () => {
    it('offers editing, as a link to the page of the post, and deleting', async () => {
      const { wrapper } = await mountCard(makePost({ id: 5, is_mine: true }))

      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')

      const edit = wrapper.findAll('a').find((candidate) => candidate.text() === 'Редагувати')
      expect(edit?.attributes('href')).toBe('/community/posts/5?edit=1')
      expect(buttonWithText(wrapper, 'Видалити').exists()).toBe(true)
      expect(wrapper.text()).not.toContain('Поскаржитись')
    })

    it('asks first, and does nothing until the reader confirms', async () => {
      const { wrapper } = await mountCard(makePost({ is_mine: true }))
      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')

      await buttonWithText(wrapper, 'Видалити').trigger('click')

      expect(wrapper.text()).toContain('Видалити цю публікацію разом із коментарями?')
      expect(socialApi.deletePost).not.toHaveBeenCalled()
    })

    it('deletes the post once confirmed, which then disappears', async () => {
      const { wrapper, posts } = await mountCard(makePost({ id: 4, is_mine: true }))
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)
      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Видалити').trigger('click')

      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(socialApi.deletePost).toHaveBeenCalledWith(4)
      expect(posts.get(4)).toBeUndefined()
      expect(wrapper.find('article').exists()).toBe(false)
    })

    it('lets the reader back out', async () => {
      const { wrapper } = await mountCard(makePost({ is_mine: true }))
      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Видалити').trigger('click')

      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(socialApi.deletePost).not.toHaveBeenCalled()
      expect(wrapper.text()).not.toContain('Видалити цю публікацію')
    })

    it('keeps the post and says why when deleting fails', async () => {
      const { wrapper, posts } = await mountCard(makePost({ id: 4, is_mine: true }))
      vi.mocked(socialApi.deletePost).mockRejectedValue(new ApiError(500, null))
      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Видалити').trigger('click')

      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(posts.get(4)).toBeDefined()
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(wrapper.text()).not.toContain('Видалити цю публікацію')
    })
  })
})
