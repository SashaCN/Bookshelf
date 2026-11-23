import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makePost, makeProfile } from '@/test/socialFixtures'
import { usePostsStore } from './posts'
import { useProfilesStore } from './profiles'

vi.mock('@/api/social', () => ({
  socialApi: {
    profile: vi.fn(),
    profilePosts: vi.fn(),
    getPost: vi.fn(),
    createPost: vi.fn(),
    updatePost: vi.fn(),
    deletePost: vi.fn(),
    like: vi.fn(),
    unlike: vi.fn(),
  },
}))

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

describe('posts store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  describe('the cache', () => {
    it('keeps the latest copy of a post by its id', () => {
      const posts = usePostsStore()

      posts.putAll([makePost({ id: 1 }), makePost({ id: 2 })])
      posts.put(makePost({ id: 1, comment: 'Edited' }))

      expect(posts.get(1)?.comment).toBe('Edited')
      expect(posts.get(2)).toBeDefined()
      expect(posts.get(3)).toBeUndefined()
    })

    it('fetches a post and remembers it', async () => {
      vi.mocked(socialApi.getPost).mockResolvedValue(makePost({ id: 4 }))
      const posts = usePostsStore()

      const post = await posts.fetch(4)

      expect(socialApi.getPost).toHaveBeenCalledWith(4)
      expect(post.id).toBe(4)
      expect(posts.get(4)).toEqual(post)
    })

    it('throws what the server answers for a post that is not there', async () => {
      vi.mocked(socialApi.getPost).mockRejectedValue(new ApiError(404, null))
      const posts = usePostsStore()

      await expect(posts.fetch(4)).rejects.toBeInstanceOf(ApiError)
      expect(posts.get(4)).toBeUndefined()
    })

    it('forgets everything on reset', () => {
      const posts = usePostsStore()
      posts.put(makePost({ id: 1 }))

      posts.reset()

      expect(posts.get(1)).toBeUndefined()
    })
  })

  describe('publishing, editing and deleting', () => {
    it('creates a post and remembers it', async () => {
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 8, is_mine: true }))
      const posts = usePostsStore()

      const post = await posts.create({ type: 'quote', quote_id: 3 })

      expect(socialApi.createPost).toHaveBeenCalledWith({ type: 'quote', quote_id: 3 })
      expect(posts.get(post.id)?.is_mine).toBe(true)
    })

    it('does not remember a post the server refused', async () => {
      vi.mocked(socialApi.createPost).mockRejectedValue(new ApiError(422, { errors: { user_book_id: ['social.review_exists'] } }))
      const posts = usePostsStore()

      await expect(posts.create({ type: 'review', user_book_id: 1, rating: 4 })).rejects.toBeInstanceOf(ApiError)
    })

    it('replaces the post with the server\'s copy after an edit', async () => {
      vi.mocked(socialApi.updatePost).mockResolvedValue(makePost({ id: 2, comment: 'New' }))
      const posts = usePostsStore()
      posts.put(makePost({ id: 2, comment: 'Old' }))

      await posts.update(2, { comment: 'New' })

      expect(socialApi.updatePost).toHaveBeenCalledWith(2, { comment: 'New' })
      expect(posts.get(2)?.comment).toBe('New')
    })

    it('forgets a deleted post', async () => {
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)
      const posts = usePostsStore()
      posts.put(makePost({ id: 2 }))

      await posts.remove(2)

      expect(socialApi.deletePost).toHaveBeenCalledWith(2)
      expect(posts.get(2)).toBeUndefined()
    })

    it('keeps the post when the server refuses to delete it', async () => {
      vi.mocked(socialApi.deletePost).mockRejectedValue(new ApiError(500, null))
      const posts = usePostsStore()
      posts.put(makePost({ id: 2 }))

      await expect(posts.remove(2)).rejects.toBeInstanceOf(ApiError)

      expect(posts.get(2)).toBeDefined()
    })

    it('forgets the posts of an author the reader has blocked, and only those', () => {
      const posts = usePostsStore()
      posts.putAll([
        makePost({ id: 1, author: { handle: 'olena_k', name: 'Olena' } }),
        makePost({ id: 2, author: { handle: 'taras_b', name: 'Taras' } }),
        makePost({ id: 3, author: { handle: 'olena_k', name: 'Olena' } }),
      ])

      posts.removeByAuthor('olena_k')

      expect([1, 2, 3].map((id) => posts.get(id)?.id)).toEqual([undefined, 2, undefined])
    })
  })

  describe('toggleLike', () => {
    it('shows the heart and the counter at once, before the server answers', () => {
      vi.mocked(socialApi.like).mockReturnValue(new Promise(() => {}))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, likes_count: 2 }))

      void posts.toggleLike(1)

      expect(posts.get(1)).toMatchObject({ liked_by_me: true, likes_count: 3 })
      expect(socialApi.like).toHaveBeenCalledWith(1)
    })

    it('takes the server\'s post once it answers, so the counter is the exact one', async () => {
      vi.mocked(socialApi.like).mockResolvedValue(makePost({ id: 1, liked_by_me: true, likes_count: 7 }))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, likes_count: 2 }))

      await posts.toggleLike(1)

      expect(posts.get(1)).toMatchObject({ liked_by_me: true, likes_count: 7 })
    })

    it('takes a like back', async () => {
      vi.mocked(socialApi.unlike).mockResolvedValue(makePost({ id: 1, liked_by_me: false, likes_count: 1 }))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, liked_by_me: true, likes_count: 2 }))

      const pending = posts.toggleLike(1)
      expect(posts.get(1)).toMatchObject({ liked_by_me: false, likes_count: 1 })
      await pending

      expect(socialApi.unlike).toHaveBeenCalledWith(1)
      expect(socialApi.like).not.toHaveBeenCalled()
    })

    it('never shows a negative counter', () => {
      vi.mocked(socialApi.unlike).mockReturnValue(new Promise(() => {}))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, liked_by_me: true, likes_count: 0 }))

      void posts.toggleLike(1)

      expect(posts.get(1)?.likes_count).toBe(0)
    })

    it('puts the heart and the counter back and throws when the server turns the like down', async () => {
      vi.mocked(socialApi.like).mockRejectedValue(new ApiError(500, null))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, likes_count: 2 }))

      await expect(posts.toggleLike(1)).rejects.toBeInstanceOf(ApiError)

      expect(posts.get(1)).toMatchObject({ liked_by_me: false, likes_count: 2 })
    })

    it('puts a taken-back like back when the server turns it down', async () => {
      vi.mocked(socialApi.unlike).mockRejectedValue(new Error('offline'))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, liked_by_me: true, likes_count: 2 }))

      await expect(posts.toggleLike(1)).rejects.toThrow('offline')

      expect(posts.get(1)).toMatchObject({ liked_by_me: true, likes_count: 2 })
    })

    it('does not undo what changed meanwhile, such as a new comment', async () => {
      const answer = deferred<ReturnType<typeof makePost>>()
      vi.mocked(socialApi.like).mockReturnValue(answer.promise)
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, comments_count: 0 }))

      const pending = posts.toggleLike(1)
      posts.changeCommentsCount(1, 1)
      answer.reject(new Error('offline'))
      await expect(pending).rejects.toThrow('offline')

      expect(posts.get(1)).toMatchObject({ liked_by_me: false, comments_count: 1 })
    })

    it('ignores a second tap while the first is on its way', async () => {
      const answer = deferred<ReturnType<typeof makePost>>()
      vi.mocked(socialApi.like).mockReturnValue(answer.promise)
      const posts = usePostsStore()
      posts.put(makePost({ id: 1 }))

      const first = posts.toggleLike(1)
      await posts.toggleLike(1)
      answer.resolve(makePost({ id: 1, liked_by_me: true, likes_count: 1 }))
      await first

      expect(socialApi.like).toHaveBeenCalledTimes(1)
      expect(socialApi.unlike).not.toHaveBeenCalled()
      expect(posts.get(1)?.liked_by_me).toBe(true)
    })

    it('can be tapped again once the request has finished, even a failed one', async () => {
      vi.mocked(socialApi.like).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(makePost({ id: 1, liked_by_me: true, likes_count: 1 }))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1 }))

      await expect(posts.toggleLike(1)).rejects.toThrow('offline')
      await posts.toggleLike(1)

      expect(posts.get(1)?.liked_by_me).toBe(true)
    })

    it('does nothing for a post it does not know', async () => {
      const posts = usePostsStore()

      await posts.toggleLike(99)

      expect(socialApi.like).not.toHaveBeenCalled()
    })
  })

  describe('toggleLike without a post in the answer', () => {
    it('keeps the heart and the counter as the reader set them, when the post is not shown to them any more', async () => {
      vi.mocked(socialApi.unlike).mockResolvedValue(null)
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, liked_by_me: true, likes_count: 3 }))

      await posts.toggleLike(1)

      expect(posts.get(1)).toMatchObject({ liked_by_me: false, likes_count: 2 })
    })
  })

  describe('an answer that comes after the reader was signed out or replaced', () => {
    it('does not put a fetched post into the cache', async () => {
      const answer = deferred<ReturnType<typeof makePost>>()
      vi.mocked(socialApi.getPost).mockReturnValue(answer.promise)
      const posts = usePostsStore()

      const pending = posts.fetch(4)
      posts.reset()
      answer.resolve(makePost({ id: 4 }))
      await pending

      expect(posts.get(4)).toBeUndefined()
    })

    it('does not put a created or an edited post into the cache', async () => {
      const created = deferred<ReturnType<typeof makePost>>()
      const edited = deferred<ReturnType<typeof makePost>>()
      vi.mocked(socialApi.createPost).mockReturnValue(created.promise)
      vi.mocked(socialApi.updatePost).mockReturnValue(edited.promise)
      const posts = usePostsStore()

      const first = posts.create({ type: 'quote', quote_id: 1 })
      const second = posts.update(2, { comment: 'x' })
      posts.reset()
      created.resolve(makePost({ id: 3 }))
      edited.resolve(makePost({ id: 2 }))
      await Promise.all([first, second])

      expect(posts.get(3)).toBeUndefined()
      expect(posts.get(2)).toBeUndefined()
    })

    it('does not put the post of the answer to a like into the cache', async () => {
      const answer = deferred<ReturnType<typeof makePost>>()
      vi.mocked(socialApi.like).mockReturnValue(answer.promise)
      const posts = usePostsStore()
      posts.put(makePost({ id: 1 }))

      const pending = posts.toggleLike(1)
      posts.reset()
      answer.resolve(makePost({ id: 1, liked_by_me: true, likes_count: 1 }))
      await pending

      expect(posts.get(1)).toBeUndefined()
    })

    it('does not bring the post back when a like is turned down after the reset', async () => {
      const answer = deferred<ReturnType<typeof makePost>>()
      vi.mocked(socialApi.like).mockReturnValue(answer.promise)
      const posts = usePostsStore()
      posts.put(makePost({ id: 1 }))

      const pending = posts.toggleLike(1)
      posts.reset()
      answer.reject(new Error('offline'))

      await expect(pending).rejects.toThrow('offline')
      expect(posts.get(1)).toBeUndefined()
    })

    it('does not unlock a post of the new reader when the old request finishes', async () => {
      const answer = deferred<ReturnType<typeof makePost>>()
      vi.mocked(socialApi.like).mockReturnValueOnce(answer.promise).mockReturnValueOnce(new Promise(() => {}))
      const posts = usePostsStore()
      posts.put(makePost({ id: 1 }))

      const old = posts.toggleLike(1)
      posts.reset()
      posts.put(makePost({ id: 1 }))
      void posts.toggleLike(1)
      answer.resolve(makePost({ id: 1 }))
      await old
      await posts.toggleLike(1)

      // The second tap of the new reader is still held back by their own request in flight.
      expect(socialApi.like).toHaveBeenCalledTimes(2)
    })

    it('does not forget a deleted post for the new reader', async () => {
      const answer = deferred<null>()
      vi.mocked(socialApi.deletePost).mockReturnValue(answer.promise)
      const posts = usePostsStore()
      posts.put(makePost({ id: 1 }))

      const pending = posts.remove(1)
      posts.reset()
      posts.put(makePost({ id: 1 }))
      answer.resolve(null)
      await pending

      expect(posts.get(1)).toBeDefined()
    })
  })

  describe('the number of posts on the profile of the reader', () => {
    async function ownProfile(count = 3) {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ handle: 'me_reader', is_me: true, posts_count: count }))
      vi.mocked(socialApi.profilePosts).mockResolvedValue({ data: [makePost({ id: 1, is_mine: true })], links: { next: null } })
      const profiles = useProfilesStore()
      await profiles.load('me_reader')
      await profiles.loadPosts('me_reader')
      return profiles
    }

    it('goes up and the post comes first in the list when the reader publishes one', async () => {
      const profiles = await ownProfile(3)
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 9, is_mine: true }))

      await usePostsStore().create({ type: 'quote', quote_id: 1 })

      expect(profiles.profile?.posts_count).toBe(4)
      expect(profiles.items.map((post) => post.id)).toEqual([9, 1])
    })

    it('goes down when the reader deletes a post of their own', async () => {
      const profiles = await ownProfile(3)
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)

      await usePostsStore().remove(1)

      expect(profiles.profile?.posts_count).toBe(2)
      expect(profiles.items).toEqual([])
    })

    it('does not go below zero', async () => {
      const profiles = await ownProfile(0)
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)

      await usePostsStore().remove(1)

      expect(profiles.profile?.posts_count).toBe(0)
    })

    it('stays when the server refuses to delete the post', async () => {
      const profiles = await ownProfile(3)
      vi.mocked(socialApi.deletePost).mockRejectedValue(new ApiError(500, null))

      await expect(usePostsStore().remove(1)).rejects.toBeInstanceOf(ApiError)

      expect(profiles.profile?.posts_count).toBe(3)
    })

    it('is not touched on the profile of somebody else', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ handle: 'olena_k', is_me: false, posts_count: 3 }))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 9, is_mine: true }))
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)
      const posts = usePostsStore()

      await posts.create({ type: 'quote', quote_id: 1 })
      await posts.remove(9)

      expect(profiles.profile?.posts_count).toBe(3)
    })

    it('is not touched by somebody else\'s post being removed from the cache', async () => {
      const profiles = await ownProfile(3)
      const posts = usePostsStore()
      posts.put(makePost({ id: 5, is_mine: false }))
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)

      await posts.remove(5)

      expect(profiles.profile?.posts_count).toBe(3)
    })
  })

  describe('changeCommentsCount', () => {
    it('adds and removes, never going below zero', () => {
      const posts = usePostsStore()
      posts.put(makePost({ id: 1, comments_count: 1 }))

      posts.changeCommentsCount(1, 1)
      expect(posts.get(1)?.comments_count).toBe(2)

      posts.changeCommentsCount(1, -5)
      expect(posts.get(1)?.comments_count).toBe(0)
    })

    it('ignores a post it does not know', () => {
      const posts = usePostsStore()

      expect(() => posts.changeCommentsCount(9, 1)).not.toThrow()
    })
  })
})
