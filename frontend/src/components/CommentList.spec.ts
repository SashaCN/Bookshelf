import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { useCommentsStore } from '@/stores/comments'
import { usePostsStore } from '@/stores/posts'
import { makeComment, makePost } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Comment } from '@/types/social'
import CommentList from './CommentList.vue'

vi.mock('@/api/social', () => ({
  socialApi: { comments: vi.fn(), deleteComment: vi.fn(), report: vi.fn() },
}))

async function mountList(comments: Comment[]) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(socialApi.comments).mockResolvedValue({ data: comments, links: { next: null } })
  const store = useCommentsStore()
  await store.load(1)
  usePostsStore().put(makePost({ id: 1, comments_count: comments.length }))
  await router.push('/community/posts/1')

  const wrapper = mount(
    { components: { CommentList }, template: '<CommentList :comments="store.items" />', setup: () => ({ store }) },
    { global: { plugins } },
  )

  return { wrapper, store }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('CommentList', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows the comments in the order it is given, each with its author and text', async () => {
    const { wrapper } = await mountList([
      makeComment({ id: 2, content: 'Second', author: { handle: 'taras_b', name: 'Taras Bondar' } }),
      makeComment({ id: 1, content: 'First', author: { handle: 'olena_k', name: 'Olena Kovalenko' } }),
    ])

    const items = wrapper.findAll('li')

    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('Taras Bondar')
    expect(items[0]!.text()).toContain('Second')
    expect(items[1]!.text()).toContain('First')
  })

  it('links the author to their profile', async () => {
    const { wrapper } = await mountList([makeComment({ author: { handle: 'taras_b', name: 'Taras Bondar' } })])

    expect(wrapper.get('a').attributes('href')).toBe('/u/taras_b')
  })

  it('keeps the line breaks of a comment', async () => {
    const { wrapper } = await mountList([makeComment({ content: 'One\nTwo' })])

    expect(wrapper.find('p.whitespace-pre-line').text()).toBe('One\nTwo')
  })

  it('shows a comment as plain text, never as markup', async () => {
    const { wrapper } = await mountList([makeComment({ content: '<img src=x onerror=alert(1)>' })])

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>')
  })

  it('flags a comment that moderation hid, for its author', async () => {
    const { wrapper } = await mountList([makeComment({ is_mine: true, can_delete: true, is_hidden: true })])

    expect(wrapper.get('[role="status"]').text()).toBe('Приховано модерацією')
  })

  describe('what a reader may do', () => {
    it('report somebody else\'s comment, but not delete it', async () => {
      const { wrapper } = await mountList([makeComment({ is_mine: false, can_delete: false })])

      expect(buttonWithText(wrapper, 'Поскаржитись').exists()).toBe(true)
      expect(wrapper.text()).not.toContain('Видалити')
    })

    it('delete their own comment, but not report it', async () => {
      const { wrapper } = await mountList([makeComment({ is_mine: true, can_delete: true })])

      expect(buttonWithText(wrapper, 'Видалити').exists()).toBe(true)
      expect(wrapper.text()).not.toContain('Поскаржитись')
    })

    it('delete a comment on their own post, and report it too', async () => {
      const { wrapper } = await mountList([makeComment({ is_mine: false, can_delete: true })])

      expect(buttonWithText(wrapper, 'Видалити').exists()).toBe(true)
      expect(buttonWithText(wrapper, 'Поскаржитись').exists()).toBe(true)
    })
  })

  describe('deleting', () => {
    it('asks first, and does nothing until the reader confirms', async () => {
      const { wrapper } = await mountList([makeComment({ can_delete: true })])

      await buttonWithText(wrapper, 'Видалити').trigger('click')

      expect(wrapper.text()).toContain('Видалити цей коментар?')
      expect(socialApi.deleteComment).not.toHaveBeenCalled()
    })

    it('deletes the comment once confirmed and uncounts it', async () => {
      const { wrapper, store } = await mountList([makeComment({ id: 4, post_id: 1, can_delete: true })])
      vi.mocked(socialApi.deleteComment).mockResolvedValue(null)
      await buttonWithText(wrapper, 'Видалити').trigger('click')

      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(socialApi.deleteComment).toHaveBeenCalledWith(4)
      expect(store.items).toEqual([])
      expect(wrapper.findAll('li')).toHaveLength(0)
      expect(usePostsStore().get(1)?.comments_count).toBe(0)
    })

    it('lets the reader back out', async () => {
      const { wrapper } = await mountList([makeComment({ can_delete: true })])
      await buttonWithText(wrapper, 'Видалити').trigger('click')

      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(socialApi.deleteComment).not.toHaveBeenCalled()
      expect(wrapper.text()).not.toContain('Видалити цей коментар?')
    })

    it('keeps the comment and says why when deleting fails', async () => {
      const { wrapper, store } = await mountList([makeComment({ can_delete: true })])
      vi.mocked(socialApi.deleteComment).mockRejectedValue(new ApiError(500, null))
      await buttonWithText(wrapper, 'Видалити').trigger('click')

      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(store.items).toHaveLength(1)
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(wrapper.text()).not.toContain('Видалити цей коментар?')
    })
  })

  describe('reporting', () => {
    it('opens the report dialog for this comment', async () => {
      const { wrapper } = await mountList([makeComment({ id: 7 })])
      vi.mocked(socialApi.report).mockResolvedValue({})

      await buttonWithText(wrapper, 'Поскаржитись').trigger('click')
      await wrapper.findAll('input[type="radio"]')[1]!.setValue(true)
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.report).toHaveBeenCalledWith({ kind: 'comment', id: 7 }, { reason: 'abuse', note: null })
    })

    it('closes the dialog on cancel', async () => {
      const { wrapper } = await mountList([makeComment()])
      await buttonWithText(wrapper, 'Поскаржитись').trigger('click')

      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })
  })
})
