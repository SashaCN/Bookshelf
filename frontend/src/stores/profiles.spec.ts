import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makePost, makeProfile } from '@/test/socialFixtures'
import type { PostsPage, Profile } from '@/types/social'
import { useBlocksStore } from './blocks'
import { useFeedStore } from './feed'
import { usePostsStore } from './posts'
import { useProfilesStore } from './profiles'

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
  },
}))

function page(ids: number[], next: string | null = null): PostsPage {
  return { data: ids.map((id) => makePost({ id })), links: { next } }
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

describe('profiles store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  describe('loading', () => {
    it('loads a profile', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ handle: 'taras_b' }))
      const profiles = useProfilesStore()

      await profiles.load('taras_b')

      expect(socialApi.profile).toHaveBeenCalledWith('taras_b')
      expect(profiles.profile?.handle).toBe('taras_b')
    })

    it('throws a 404 for a reader who is not there, leaving no profile', async () => {
      vi.mocked(socialApi.profile).mockRejectedValue(new ApiError(404, null))
      const profiles = useProfilesStore()

      await expect(profiles.load('nobody')).rejects.toBeInstanceOf(ApiError)

      expect(profiles.profile).toBeNull()
    })

    it('clears the previous reader at once when another profile is opened', async () => {
      vi.mocked(socialApi.profile).mockResolvedValueOnce(makeProfile({ handle: 'a_reader' })).mockReturnValueOnce(new Promise(() => {}))
      vi.mocked(socialApi.profilePosts).mockResolvedValue(page([1]))
      const profiles = useProfilesStore()
      await profiles.load('a_reader')
      await profiles.loadPosts('a_reader')

      void profiles.load('b_reader')

      expect(profiles.profile).toBeNull()
      expect(profiles.items).toEqual([])
      expect(profiles.postsLoaded).toBe(false)
    })

    it('keeps the profile and the posts while the same reader refreshes', async () => {
      vi.mocked(socialApi.profile).mockResolvedValueOnce(makeProfile()).mockReturnValueOnce(new Promise(() => {}))
      vi.mocked(socialApi.profilePosts).mockResolvedValue(page([1]))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')
      await profiles.loadPosts('olena_k')

      void profiles.load('olena_k')

      expect(profiles.profile).not.toBeNull()
      expect(profiles.items).toHaveLength(1)
    })

    it('drops the answer for a profile the reader has already left', async () => {
      const slow = deferred<Profile>()
      vi.mocked(socialApi.profile).mockReturnValueOnce(slow.promise).mockResolvedValueOnce(makeProfile({ handle: 'b_reader' }))
      const profiles = useProfilesStore()

      const stale = profiles.load('a_reader')
      await profiles.load('b_reader')
      slow.resolve(makeProfile({ handle: 'a_reader' }))
      await stale

      expect(profiles.profile?.handle).toBe('b_reader')
    })

    it('does not report the failure of a profile the reader has already left', async () => {
      const slow = deferred<Profile>()
      vi.mocked(socialApi.profile).mockReturnValueOnce(slow.promise).mockResolvedValueOnce(makeProfile({ handle: 'b_reader' }))
      const profiles = useProfilesStore()

      const stale = profiles.load('a_reader')
      await profiles.load('b_reader')
      slow.reject(new ApiError(404, null))

      await expect(stale).resolves.toBeUndefined()
    })
  })

  describe('the posts of the reader', () => {
    it('loads the first page and puts the posts into the cache', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
      vi.mocked(socialApi.profilePosts).mockResolvedValue(page([3, 2], '/next'))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      await profiles.loadPosts('olena_k')

      expect(socialApi.profilePosts).toHaveBeenCalledWith('olena_k', 1)
      expect(profiles.items.map((post) => post.id)).toEqual([3, 2])
      expect(profiles.hasMorePosts).toBe(true)
      expect(profiles.postsLoaded).toBe(true)
      expect(usePostsStore().get(3)).toBeDefined()
    })

    it('appends the next page, without a post twice', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
      vi.mocked(socialApi.profilePosts).mockResolvedValueOnce(page([3, 2], '/next')).mockResolvedValueOnce(page([2, 1]))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')
      await profiles.loadPosts('olena_k')

      await profiles.loadMorePosts()

      expect(socialApi.profilePosts).toHaveBeenLastCalledWith('olena_k', 2)
      expect(profiles.items.map((post) => post.id)).toEqual([3, 2, 1])
      expect(profiles.hasMorePosts).toBe(false)
    })

    it('keeps what is loaded when "load more" fails, so it can be tried again', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
      vi.mocked(socialApi.profilePosts)
        .mockResolvedValueOnce(page([2], '/next'))
        .mockRejectedValueOnce(new Error('offline'))
        .mockResolvedValueOnce(page([1]))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')
      await profiles.loadPosts('olena_k')

      await expect(profiles.loadMorePosts()).rejects.toThrow('offline')
      expect(profiles.items.map((post) => post.id)).toEqual([2])
      expect(profiles.loadingMorePosts).toBe(false)

      await profiles.loadMorePosts()
      expect(profiles.items.map((post) => post.id)).toEqual([2, 1])
    })

    it('does nothing to load more when there is no more', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
      vi.mocked(socialApi.profilePosts).mockResolvedValue(page([1]))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')
      await profiles.loadPosts('olena_k')

      await profiles.loadMorePosts()

      expect(socialApi.profilePosts).toHaveBeenCalledTimes(1)
    })
  })

  describe('toggleFollow', () => {
    it('shows the button and the counter at once, before the server answers', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ followers_count: 5 }))
      vi.mocked(socialApi.follow).mockReturnValue(new Promise(() => {}))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      void profiles.toggleFollow()

      expect(profiles.profile).toMatchObject({ is_following: true, followers_count: 6 })
      expect(socialApi.follow).toHaveBeenCalledWith('olena_k')
    })

    it('takes the server\'s profile once it answers', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ followers_count: 5 }))
      vi.mocked(socialApi.follow).mockResolvedValue(makeProfile({ is_following: true, followers_count: 9 }))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      await profiles.toggleFollow()

      expect(profiles.profile).toMatchObject({ is_following: true, followers_count: 9 })
    })

    it('unfollows', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ is_following: true, followers_count: 5 }))
      vi.mocked(socialApi.unfollow).mockResolvedValue(makeProfile({ is_following: false, followers_count: 4 }))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      const pending = profiles.toggleFollow()
      expect(profiles.profile).toMatchObject({ is_following: false, followers_count: 4 })
      await pending

      expect(socialApi.unfollow).toHaveBeenCalledWith('olena_k')
      expect(socialApi.follow).not.toHaveBeenCalled()
    })

    it('puts everything back and throws when the server turns the follow down', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ followers_count: 5 }))
      vi.mocked(socialApi.follow).mockRejectedValue(new ApiError(500, null))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      await expect(profiles.toggleFollow()).rejects.toBeInstanceOf(ApiError)

      expect(profiles.profile).toMatchObject({ is_following: false, followers_count: 5 })
    })

    it('puts an unfollow back when the server turns it down', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ is_following: true, followers_count: 5 }))
      vi.mocked(socialApi.unfollow).mockRejectedValue(new Error('offline'))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      await expect(profiles.toggleFollow()).rejects.toThrow('offline')

      expect(profiles.profile).toMatchObject({ is_following: true, followers_count: 5 })
    })

    it('never shows a negative number of followers', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ is_following: true, followers_count: 0 }))
      vi.mocked(socialApi.unfollow).mockReturnValue(new Promise(() => {}))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      void profiles.toggleFollow()

      expect(profiles.profile?.followers_count).toBe(0)
    })

    it('ignores a second tap while the first is on its way', async () => {
      const answer = deferred<Profile>()
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
      vi.mocked(socialApi.follow).mockReturnValue(answer.promise)
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      const first = profiles.toggleFollow()
      await profiles.toggleFollow()
      answer.resolve(makeProfile({ is_following: true, followers_count: 6 }))
      await first

      expect(socialApi.follow).toHaveBeenCalledTimes(1)
      expect(socialApi.unfollow).not.toHaveBeenCalled()
    })

    it('does not touch another profile when the answer comes after the reader moved on', async () => {
      const answer = deferred<Profile>()
      vi.mocked(socialApi.profile).mockResolvedValueOnce(makeProfile({ handle: 'a_reader' })).mockResolvedValueOnce(makeProfile({ handle: 'b_reader' }))
      vi.mocked(socialApi.follow).mockReturnValue(answer.promise)
      const profiles = useProfilesStore()
      await profiles.load('a_reader')

      const pending = profiles.toggleFollow()
      await profiles.load('b_reader')
      answer.resolve(makeProfile({ handle: 'a_reader', is_following: true }))
      await pending

      expect(profiles.profile?.handle).toBe('b_reader')
      expect(profiles.profile?.is_following).toBe(false)
    })

    it('does nothing without a profile', async () => {
      const profiles = useProfilesStore()

      await profiles.toggleFollow()

      expect(socialApi.follow).not.toHaveBeenCalled()
    })
  })

  describe('block and unblock', () => {
    it('blocks the reader: the profile is the server\'s, the posts and the stale lists are gone', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ is_following: true }))
      vi.mocked(socialApi.profilePosts).mockResolvedValue(page([1, 2]))
      vi.mocked(socialApi.block).mockResolvedValue(makeProfile({ is_blocked: true, is_following: false, posts_count: 0 }))
      vi.mocked(socialApi.feed).mockResolvedValue(page([1]))
      vi.mocked(socialApi.blocks).mockResolvedValue({ data: [], links: { next: null } })
      const profiles = useProfilesStore()
      await profiles.load('olena_k')
      await profiles.loadPosts('olena_k')
      const feed = useFeedStore()
      await feed.load('all')
      const blocks = useBlocksStore()
      await blocks.load()

      await profiles.block()

      expect(socialApi.block).toHaveBeenCalledWith('olena_k')
      expect(profiles.profile).toMatchObject({ is_blocked: true, is_following: false })
      expect(profiles.items).toEqual([])
      expect(usePostsStore().get(1)).toBeUndefined()
      expect(feed.items).toEqual([])
      expect(feed.loaded).toBe(false)
      expect(blocks.loaded).toBe(false)
    })

    it('leaves everything as it was and throws when the server refuses to block', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
      vi.mocked(socialApi.profilePosts).mockResolvedValue(page([1]))
      vi.mocked(socialApi.block).mockRejectedValue(new ApiError(500, null))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')
      await profiles.loadPosts('olena_k')

      await expect(profiles.block()).rejects.toBeInstanceOf(ApiError)

      expect(profiles.profile?.is_blocked).toBe(false)
      expect(profiles.items).toHaveLength(1)
    })

    it('unblocks the reader and loads the profile again', async () => {
      vi.mocked(socialApi.profile)
        .mockResolvedValueOnce(makeProfile({ is_blocked: true, posts_count: 0 }))
        .mockResolvedValueOnce(makeProfile({ is_blocked: false, posts_count: 3 }))
      vi.mocked(socialApi.unblock).mockResolvedValue(null)
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      await profiles.unblock()

      expect(socialApi.unblock).toHaveBeenCalledWith('olena_k')
      expect(profiles.profile).toMatchObject({ is_blocked: false, posts_count: 3 })
    })

    it('shows the reader as unblocked even when the profile cannot be loaded again because they blocked back', async () => {
      vi.mocked(socialApi.profile).mockResolvedValueOnce(makeProfile({ is_blocked: true })).mockRejectedValueOnce(new ApiError(404, null))
      vi.mocked(socialApi.unblock).mockResolvedValue(null)
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      await profiles.unblock()

      expect(profiles.profile?.is_blocked).toBe(false)
    })

    it('throws when the server refuses to unblock, leaving the profile blocked', async () => {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile({ is_blocked: true }))
      vi.mocked(socialApi.unblock).mockRejectedValue(new ApiError(500, null))
      const profiles = useProfilesStore()
      await profiles.load('olena_k')

      await expect(profiles.unblock()).rejects.toBeInstanceOf(ApiError)

      expect(profiles.profile?.is_blocked).toBe(true)
    })
  })

  it('forgets everything on reset', async () => {
    vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
    vi.mocked(socialApi.profilePosts).mockResolvedValue(page([1], '/next'))
    const profiles = useProfilesStore()
    await profiles.load('olena_k')
    await profiles.loadPosts('olena_k')

    profiles.reset()

    expect(profiles.profile).toBeNull()
    expect(profiles.items).toEqual([])
    expect(profiles.hasMorePosts).toBe(false)
    expect(profiles.postsLoaded).toBe(false)
  })

  it('ignores an answer that arrives after a reset', async () => {
    const slow = deferred<Profile>()
    vi.mocked(socialApi.profile).mockReturnValue(slow.promise)
    const profiles = useProfilesStore()

    const pending = profiles.load('olena_k')
    profiles.reset()
    slow.resolve(makeProfile())
    await pending

    expect(profiles.profile).toBeNull()
  })
})
