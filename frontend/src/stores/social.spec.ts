import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeFeedQuote, makeProfile, makePublicReview, makeQuote } from '@/test/fixtures'
import { useSocialStore } from './social'

vi.mock('@/api/social', () => ({
  socialApi: { profile: vi.fn(), quotes: vi.fn(), reviews: vi.fn(), follow: vi.fn(), unfollow: vi.fn(), followers: vi.fn(), following: vi.fn(), feed: vi.fn() },
}))

describe('social store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('loads a profile with its first page of quotes', async () => {
    vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
    vi.mocked(socialApi.quotes).mockResolvedValue({ data: [makeQuote({ id: 2 })], links: { next: '/n' } })
    const social = useSocialStore()

    await social.loadProfile('olena')

    expect(social.profile?.username).toBe('olena')
    expect(social.profileQuotes.items.map((q) => q.id)).toEqual([2])
    expect(social.profileQuotes.hasMore).toBe(true)
  })

  it('loads the reviews of the shown profile only when asked, and drops them with the profile', async () => {
    vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
    vi.mocked(socialApi.quotes).mockResolvedValue({ data: [], links: { next: null } })
    vi.mocked(socialApi.reviews).mockResolvedValue({ data: [makePublicReview({ id: 7 })], links: { next: null } })
    const social = useSocialStore()

    await social.loadProfile('olena')
    expect(socialApi.reviews).not.toHaveBeenCalled()

    await social.loadProfileReviews()
    expect(socialApi.reviews).toHaveBeenCalledWith('olena', 1)
    expect(social.profileReviews.items.map((r) => r.id)).toEqual([7])

    await social.loadProfile('olena')
    expect(social.profileReviews.loaded).toBe(false)
  })

  it('leaves no profile behind when it is not found', async () => {
    vi.mocked(socialApi.profile).mockRejectedValue(new ApiError(404, null))
    const social = useSocialStore()

    await expect(social.loadProfile('ghost')).rejects.toBeInstanceOf(ApiError)

    expect(social.profile).toBeNull()
  })

  it('appends the next page without repeating a quote', async () => {
    vi.mocked(socialApi.feed)
      .mockResolvedValueOnce({ data: [makeFeedQuote({ id: 3 }), makeFeedQuote({ id: 2 })], links: { next: '/n' } })
      .mockResolvedValueOnce({ data: [makeFeedQuote({ id: 2 }), makeFeedQuote({ id: 1 })], links: { next: null } })
    const social = useSocialStore()
    await social.loadFeed()

    await social.feed.loadMore()
    await social.feed.loadMore()

    expect(socialApi.feed).toHaveBeenLastCalledWith(2)
    expect(socialApi.feed).toHaveBeenCalledTimes(2)
    expect(social.feed.items.map((q) => q.id)).toEqual([3, 2, 1])
    expect(social.feed.hasMore).toBe(false)
  })

  it('loads followers or following of a reader', async () => {
    vi.mocked(socialApi.followers).mockResolvedValue({ data: [{ username: 'a', name: 'A' }], links: { next: null } })
    vi.mocked(socialApi.following).mockResolvedValue({ data: [{ username: 'b', name: 'B' }], links: { next: null } })
    const social = useSocialStore()

    await social.loadPeople('olena', 'followers')
    expect(social.people.items[0].username).toBe('a')

    await social.loadPeople('olena', 'following')
    expect(social.people.items[0].username).toBe('b')
  })

  describe('following', () => {
    async function loaded(overrides = {}) {
      vi.mocked(socialApi.profile).mockResolvedValue(makeProfile(overrides))
      vi.mocked(socialApi.quotes).mockResolvedValue({ data: [], links: { next: null } })
      const social = useSocialStore()
      await social.loadProfile('olena')
      return social
    }

    it('follows at once and counts the new follower', async () => {
      vi.mocked(socialApi.follow).mockResolvedValue(null)
      const social = await loaded({ followers_count: 3 })

      await social.setFollowing(true)

      expect(socialApi.follow).toHaveBeenCalledWith('olena')
      expect(social.profile).toMatchObject({ is_following: true, followers_count: 4 })
    })

    it('unfollows', async () => {
      vi.mocked(socialApi.unfollow).mockResolvedValue(null)
      const social = await loaded({ is_following: true, followers_count: 3 })

      await social.setFollowing(false)

      expect(socialApi.unfollow).toHaveBeenCalledWith('olena')
      expect(social.profile).toMatchObject({ is_following: false, followers_count: 2 })
    })

    it('goes back when the server refuses', async () => {
      vi.mocked(socialApi.follow).mockRejectedValue(new ApiError(500, null))
      const social = await loaded({ followers_count: 3 })

      await expect(social.setFollowing(true)).rejects.toBeInstanceOf(ApiError)

      expect(social.profile).toMatchObject({ is_following: false, followers_count: 3 })
    })

    it('does not follow oneself', async () => {
      const social = await loaded({ is_me: true })

      await social.setFollowing(true)

      expect(socialApi.follow).not.toHaveBeenCalled()
    })
  })

  it('forgets everything on reset', async () => {
    vi.mocked(socialApi.feed).mockResolvedValue({ data: [makeFeedQuote()], links: { next: null } })
    const social = useSocialStore()
    await social.loadFeed()

    social.reset()

    expect(social.feed.items).toEqual([])
    expect(social.feed.loaded).toBe(false)
  })
})
