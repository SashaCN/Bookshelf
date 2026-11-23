import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeComment, makePost } from '@/test/socialFixtures'
import type { CommentsPage } from '@/types/social'
import { useCommentsStore } from './comments'
import { usePostsStore } from './posts'

vi.mock('@/api/social', () => ({
  socialApi: { comments: vi.fn(), addComment: vi.fn(), deleteComment: vi.fn() },
}))

function page(ids: number[], next: string | null = null): CommentsPage {
  return { data: ids.map((id) => makeComment({ id })), links: { next } }
}

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

describe('comments store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  describe('loading', () => {
    it('loads the first page of the comments of a post', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue(page([3, 2], '/next'))
      const comments = useCommentsStore()

      await comments.load(5)

      expect(socialApi.comments).toHaveBeenCalledWith(5, 1)
      expect(comments.items.map((comment) => comment.id)).toEqual([3, 2])
      expect(comments.hasMore).toBe(true)
      expect(comments.loaded).toBe(true)
      expect(comments.postId).toBe(5)
    })

    it('appends the next page, without a comment twice', async () => {
      vi.mocked(socialApi.comments).mockResolvedValueOnce(page([3, 2], '/next')).mockResolvedValueOnce(page([2, 1]))
      const comments = useCommentsStore()
      await comments.load(5)

      await comments.loadMore()

      expect(socialApi.comments).toHaveBeenLastCalledWith(5, 2)
      expect(comments.items.map((comment) => comment.id)).toEqual([3, 2, 1])
      expect(comments.hasMore).toBe(false)
    })

    it('keeps what is loaded when "load more" fails, so it can be tried again', async () => {
      vi.mocked(socialApi.comments)
        .mockResolvedValueOnce(page([2], '/next'))
        .mockRejectedValueOnce(new Error('offline'))
        .mockResolvedValueOnce(page([1]))
      const comments = useCommentsStore()
      await comments.load(5)

      await expect(comments.loadMore()).rejects.toThrow('offline')
      expect(comments.items).toHaveLength(1)
      expect(comments.loadingMore).toBe(false)

      await comments.loadMore()
      expect(comments.items.map((comment) => comment.id)).toEqual([2, 1])
    })

    it('empties the list at once when another post is opened, and keeps it while the same one refreshes', async () => {
      vi.mocked(socialApi.comments).mockResolvedValueOnce(page([1])).mockReturnValue(new Promise(() => {}))
      const comments = useCommentsStore()
      await comments.load(5)

      void comments.load(5)
      expect(comments.items).toHaveLength(1)

      void comments.load(6)
      expect(comments.items).toEqual([])
      expect(comments.loaded).toBe(false)
    })

    it('drops the answer for a post the reader has already left', async () => {
      const slow = deferred<CommentsPage>()
      vi.mocked(socialApi.comments).mockReturnValueOnce(slow.promise).mockResolvedValueOnce(page([7]))
      const comments = useCommentsStore()

      const stale = comments.load(5)
      await comments.load(6)
      slow.resolve(page([1, 2]))
      await stale

      expect(comments.items.map((comment) => comment.id)).toEqual([7])
    })

    it('throws when loading fails and stays unloaded, so it can be retried', async () => {
      vi.mocked(socialApi.comments).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(page([1]))
      const comments = useCommentsStore()

      await expect(comments.load(5)).rejects.toBeInstanceOf(ApiError)
      expect(comments.loaded).toBe(false)

      await comments.load(5)
      expect(comments.loaded).toBe(true)
    })
  })

  describe('writing and deleting', () => {
    it('puts a new comment first and adds one to the counter of the post', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue(page([1]))
      vi.mocked(socialApi.addComment).mockResolvedValue(makeComment({ id: 2, post_id: 5, is_mine: true, can_delete: true }))
      const posts = usePostsStore()
      posts.put(makePost({ id: 5, comments_count: 1 }))
      const comments = useCommentsStore()
      await comments.load(5)

      await comments.add(5, 'Nice!')

      expect(socialApi.addComment).toHaveBeenCalledWith(5, 'Nice!')
      expect(comments.items.map((comment) => comment.id)).toEqual([2, 1])
      expect(posts.get(5)?.comments_count).toBe(2)
    })

    it('does not touch the list of another post but still counts', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue(page([1]))
      vi.mocked(socialApi.addComment).mockResolvedValue(makeComment({ id: 2, post_id: 9 }))
      const posts = usePostsStore()
      posts.put(makePost({ id: 9, comments_count: 0 }))
      const comments = useCommentsStore()
      await comments.load(5)

      await comments.add(9, 'Elsewhere')

      expect(comments.items.map((comment) => comment.id)).toEqual([1])
      expect(posts.get(9)?.comments_count).toBe(1)
    })

    it('adds nothing when the server refuses the comment', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue(page([1]))
      vi.mocked(socialApi.addComment).mockRejectedValue(new ApiError(429, null))
      const posts = usePostsStore()
      posts.put(makePost({ id: 5, comments_count: 1 }))
      const comments = useCommentsStore()
      await comments.load(5)

      await expect(comments.add(5, 'Nice!')).rejects.toBeInstanceOf(ApiError)

      expect(comments.items).toHaveLength(1)
      expect(posts.get(5)?.comments_count).toBe(1)
    })

    it('removes a comment and takes one off the counter', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue({ data: [makeComment({ id: 2, post_id: 5 }), makeComment({ id: 1, post_id: 5 })], links: { next: null } })
      vi.mocked(socialApi.deleteComment).mockResolvedValue(null)
      const posts = usePostsStore()
      posts.put(makePost({ id: 5, comments_count: 2 }))
      const comments = useCommentsStore()
      await comments.load(5)

      await comments.remove(comments.items[0]!)

      expect(socialApi.deleteComment).toHaveBeenCalledWith(2)
      expect(comments.items.map((comment) => comment.id)).toEqual([1])
      expect(posts.get(5)?.comments_count).toBe(1)
    })

    it('takes the reader\'s own hidden comment off the counter too: the server counts it for them, as it shows it', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue({
        data: [makeComment({ id: 2, post_id: 5, is_hidden: true, is_mine: true, can_delete: true }), makeComment({ id: 1, post_id: 5 })],
        links: { next: null },
      })
      vi.mocked(socialApi.deleteComment).mockResolvedValue(null)
      const posts = usePostsStore()
      // The two comments on screen, both of them in the number the server gave this reader.
      posts.put(makePost({ id: 5, comments_count: 2 }))
      const comments = useCommentsStore()
      await comments.load(5)

      await comments.remove(comments.items[0]!)

      expect(posts.get(5)?.comments_count).toBe(1)
      expect(comments.items.map((comment) => comment.id)).toEqual([1])
    })

    it('ends at zero when the comments are deleted one after the other, hidden or not', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue({
        data: [makeComment({ id: 2, post_id: 5, is_hidden: true, is_mine: true }), makeComment({ id: 1, post_id: 5, is_mine: true })],
        links: { next: null },
      })
      vi.mocked(socialApi.deleteComment).mockResolvedValue(null)
      const posts = usePostsStore()
      posts.put(makePost({ id: 5, comments_count: 2 }))
      const comments = useCommentsStore()
      await comments.load(5)

      await comments.remove(comments.items[0]!)
      await comments.remove(comments.items[0]!)

      expect(posts.get(5)?.comments_count).toBe(0)
    })

    it('keeps the comment and the counter when the server refuses to delete it', async () => {
      vi.mocked(socialApi.comments).mockResolvedValue(page([1]))
      vi.mocked(socialApi.deleteComment).mockRejectedValue(new ApiError(500, null))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, comments_count: 1 }))
      const comments = useCommentsStore()
      await comments.load(1)

      await expect(comments.remove(comments.items[0]!)).rejects.toBeInstanceOf(ApiError)

      expect(comments.items).toHaveLength(1)
      expect(posts.get(1)?.comments_count).toBe(1)
    })
  })

  it('forgets everything on reset', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue(page([1], '/next'))
    const comments = useCommentsStore()
    await comments.load(5)

    comments.reset()

    expect(comments.items).toEqual([])
    expect(comments.postId).toBeNull()
    expect(comments.hasMore).toBe(false)
    expect(comments.loaded).toBe(false)
  })

  it('ignores an answer that arrives after a reset', async () => {
    const slow = deferred<CommentsPage>()
    vi.mocked(socialApi.comments).mockReturnValue(slow.promise)
    const comments = useCommentsStore()

    const pending = comments.load(5)
    comments.reset()
    slow.resolve(page([1]))
    await pending

    expect(comments.items).toEqual([])
    expect(comments.loaded).toBe(false)
  })
})
