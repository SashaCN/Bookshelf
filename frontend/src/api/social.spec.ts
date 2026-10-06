import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeComment, makePost, makeProfile } from '@/test/socialFixtures'
import { http } from './http'
import { socialApi } from './social'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

describe('socialApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('the feed and the lists', () => {
    it('asks for a page of a scope of the feed', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })

      await socialApi.feed('all')
      await socialApi.feed('following', 3)

      expect(http.get).toHaveBeenNthCalledWith(1, '/api/feed?scope=all&page=1')
      expect(http.get).toHaveBeenNthCalledWith(2, '/api/feed?scope=following&page=3')
    })

    it('searches readers, escaping the query', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })

      await socialApi.searchReaders('Олена & co', 2)

      expect(http.get).toHaveBeenCalledWith(`/api/profiles?${new URLSearchParams({ q: 'Олена & co', page: '2' })}`)
    })

    it('reads the blocked readers by page', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })

      await socialApi.blocks()
      await socialApi.blocks(2)

      expect(http.get).toHaveBeenNthCalledWith(1, '/api/blocks?page=1')
      expect(http.get).toHaveBeenNthCalledWith(2, '/api/blocks?page=2')
    })

    it('reads the reviews of a book by page', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null }, meta: { my_review_id: null, reviews_count: 0, average_rating: null } })

      await socialApi.bookReviews(7, 2)

      expect(http.get).toHaveBeenCalledWith('/api/books/7/reviews?page=2')
    })
  })

  describe('profiles', () => {
    it('unwraps a profile and its posts', async () => {
      vi.mocked(http.get).mockResolvedValueOnce({ data: makeProfile({ handle: 'taras_b' }) })
      vi.mocked(http.get).mockResolvedValueOnce({ data: [makePost()], links: { next: null } })

      expect((await socialApi.profile('taras_b')).handle).toBe('taras_b')
      expect((await socialApi.profilePosts('taras_b', 2)).data).toHaveLength(1)

      expect(http.get).toHaveBeenNthCalledWith(1, '/api/profiles/taras_b')
      expect(http.get).toHaveBeenNthCalledWith(2, '/api/profiles/taras_b/posts?page=2')
    })

    it('follows and unfollows with PUT and DELETE, and unwraps the profile', async () => {
      vi.mocked(http.put).mockResolvedValue({ data: makeProfile({ is_following: true }) })
      vi.mocked(http.delete).mockResolvedValue({ data: makeProfile({ is_following: false }) })

      expect((await socialApi.follow('olena_k')).is_following).toBe(true)
      expect((await socialApi.unfollow('olena_k')).is_following).toBe(false)

      expect(http.put).toHaveBeenCalledWith('/api/profiles/olena_k/follow')
      expect(http.delete).toHaveBeenCalledWith('/api/profiles/olena_k/follow')
    })

    it('blocks and unblocks with PUT and DELETE', async () => {
      vi.mocked(http.put).mockResolvedValue({ data: makeProfile({ is_blocked: true }) })
      vi.mocked(http.delete).mockResolvedValue(null)

      expect((await socialApi.block('olena_k')).is_blocked).toBe(true)
      await socialApi.unblock('olena_k')

      expect(http.put).toHaveBeenCalledWith('/api/blocks/olena_k')
      expect(http.delete).toHaveBeenCalledWith('/api/blocks/olena_k')
    })
  })

  describe('posts', () => {
    it('creates a post and unwraps it', async () => {
      vi.mocked(http.post).mockResolvedValue({ data: makePost({ id: 9 }) })

      const post = await socialApi.createPost({ type: 'quote', quote_id: 4, comment: 'Mine' })

      expect(http.post).toHaveBeenCalledWith('/api/posts', { type: 'quote', quote_id: 4, comment: 'Mine' })
      expect(post.id).toBe(9)
    })

    it('reads, updates and deletes a post by its id', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: makePost({ id: 5 }) })
      vi.mocked(http.patch).mockResolvedValue({ data: makePost({ id: 5, comment: 'New' }) })
      vi.mocked(http.delete).mockResolvedValue(null)

      expect((await socialApi.getPost(5)).id).toBe(5)
      expect((await socialApi.updatePost(5, { comment: 'New' })).comment).toBe('New')
      await socialApi.deletePost(5)

      expect(http.get).toHaveBeenCalledWith('/api/posts/5')
      expect(http.patch).toHaveBeenCalledWith('/api/posts/5', { comment: 'New' })
      expect(http.delete).toHaveBeenCalledWith('/api/posts/5')
    })

    it('takes it that an unlike may answer without the post', async () => {
      vi.mocked(http.delete).mockResolvedValue({ data: null })

      expect(await socialApi.unlike(5)).toBeNull()
    })

    it('likes and unlikes with PUT and DELETE, and unwraps the post', async () => {
      vi.mocked(http.put).mockResolvedValue({ data: makePost({ liked_by_me: true, likes_count: 1 }) })
      vi.mocked(http.delete).mockResolvedValue({ data: makePost({ liked_by_me: false }) })

      expect((await socialApi.like(5)).likes_count).toBe(1)
      expect((await socialApi.unlike(5))?.liked_by_me).toBe(false)

      expect(http.put).toHaveBeenCalledWith('/api/posts/5/like')
      expect(http.delete).toHaveBeenCalledWith('/api/posts/5/like')
    })
  })

  describe('comments and reports', () => {
    it('reads the comments of a post by page and writes one', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })
      vi.mocked(http.post).mockResolvedValue({ data: makeComment({ id: 3 }) })

      await socialApi.comments(5, 2)
      const comment = await socialApi.addComment(5, 'Nice!')

      expect(http.get).toHaveBeenCalledWith('/api/posts/5/comments?page=2')
      expect(http.post).toHaveBeenCalledWith('/api/posts/5/comments', { content: 'Nice!' })
      expect(comment.id).toBe(3)
    })

    it('deletes a comment by its id', async () => {
      vi.mocked(http.delete).mockResolvedValue(null)

      await socialApi.deleteComment(8)

      expect(http.delete).toHaveBeenCalledWith('/api/comments/8')
    })

    it('reports a post and a comment on their own addresses', async () => {
      vi.mocked(http.post).mockResolvedValue({ data: {} })

      await socialApi.report({ kind: 'post', id: 5 }, { reason: 'spam' })
      await socialApi.report({ kind: 'comment', id: 8 }, { reason: 'abuse', note: 'Rude' })

      expect(http.post).toHaveBeenNthCalledWith(1, '/api/posts/5/report', { reason: 'spam' })
      expect(http.post).toHaveBeenNthCalledWith(2, '/api/comments/8/report', { reason: 'abuse', note: 'Rude' })
    })
  })
})
