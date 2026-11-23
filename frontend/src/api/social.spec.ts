import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeProfile } from '@/test/fixtures'
import { http } from './http'
import { socialApi } from './social'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

describe('socialApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('unwraps a public profile', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: makeProfile({ username: 'olena' }) })

    const profile = await socialApi.profile('olena')

    expect(http.get).toHaveBeenCalledWith('/api/users/olena')
    expect(profile.username).toBe('olena')
  })

  it('asks for pages of quotes, followers, following and the feed', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })

    await socialApi.quotes('olena', 2)
    await socialApi.reviews('olena', 5)
    await socialApi.followers('olena')
    await socialApi.following('olena', 3)
    await socialApi.feed(4)

    expect(http.get).toHaveBeenNthCalledWith(1, '/api/users/olena/quotes?page=2')
    expect(http.get).toHaveBeenNthCalledWith(2, '/api/users/olena/reviews?page=5')
    expect(http.get).toHaveBeenNthCalledWith(3, '/api/users/olena/followers?page=1')
    expect(http.get).toHaveBeenNthCalledWith(4, '/api/users/olena/following?page=3')
    expect(http.get).toHaveBeenNthCalledWith(5, '/api/feed?page=4')
  })

  it('reads, posts and deletes comments', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })
    vi.mocked(http.post).mockResolvedValue({ data: { id: 9, body: 'Так', can_delete: true } })
    vi.mocked(http.delete).mockResolvedValue(null)

    await socialApi.comments(5)
    await socialApi.comments(5, 2)
    const created = await socialApi.addComment(5, 'Так')
    await socialApi.deleteComment(5, 9)

    expect(http.get).toHaveBeenNthCalledWith(1, '/api/quotes/5/comments?page=1')
    expect(http.get).toHaveBeenNthCalledWith(2, '/api/quotes/5/comments?page=2')
    expect(http.post).toHaveBeenCalledWith('/api/quotes/5/comments', { body: 'Так' })
    expect(created.id).toBe(9)
    expect(http.delete).toHaveBeenCalledWith('/api/quotes/5/comments/9')
  })

  it('likes and unlikes a quote', async () => {
    vi.mocked(http.post).mockResolvedValue(null)
    vi.mocked(http.delete).mockResolvedValue(null)

    await socialApi.like(5)
    await socialApi.unlike(5)

    expect(http.post).toHaveBeenCalledWith('/api/quotes/5/like')
    expect(http.delete).toHaveBeenCalledWith('/api/quotes/5/like')
  })

  it('searches people by an encoded query and unwraps the list', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [{ username: 'olena', name: 'Олена', is_following: false }] })

    const found = await socialApi.search('олена & co')

    expect(http.get).toHaveBeenCalledWith(`/api/users?q=${encodeURIComponent('олена & co')}`)
    expect(found).toEqual([{ username: 'olena', name: 'Олена', is_following: false }])
  })

  it('blocks, unblocks and lists the blocked readers', async () => {
    vi.mocked(http.post).mockResolvedValue(null)
    vi.mocked(http.delete).mockResolvedValue(null)
    vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })

    await socialApi.block('olena')
    await socialApi.unblock('olena')
    await socialApi.blocked(2)

    expect(http.post).toHaveBeenCalledWith('/api/users/olena/block')
    expect(http.delete).toHaveBeenCalledWith('/api/users/olena/block')
    expect(http.get).toHaveBeenCalledWith('/api/me/blocks?page=2')
  })

  it('follows and unfollows', async () => {
    vi.mocked(http.post).mockResolvedValue(null)
    vi.mocked(http.delete).mockResolvedValue(null)

    await socialApi.follow('olena')
    await socialApi.unfollow('olena')

    expect(http.post).toHaveBeenCalledWith('/api/users/olena/follow')
    expect(http.delete).toHaveBeenCalledWith('/api/users/olena/follow')
  })
})
