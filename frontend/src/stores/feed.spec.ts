import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { socialApi } from '@/api/social'
import { makePost } from '@/test/socialFixtures'
import type { PostsPage } from '@/types/social'
import { useFeedStore } from './feed'
import { usePostsStore } from './posts'

vi.mock('@/api/social', () => ({
  socialApi: { feed: vi.fn() },
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

describe('feed store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('starts on the posts of the readers one follows, with nothing loaded', () => {
    const feed = useFeedStore()

    expect(feed.scope).toBe('following')
    expect(feed.items).toEqual([])
    expect(feed.loaded).toBe(false)
  })

  it('loads the first page of a scope and puts the posts into the cache', async () => {
    vi.mocked(socialApi.feed).mockResolvedValue(page([3, 2], '/api/feed?page=2'))
    const feed = useFeedStore()

    await feed.load('all')

    expect(socialApi.feed).toHaveBeenCalledWith('all', 1)
    expect(feed.items.map((post) => post.id)).toEqual([3, 2])
    expect(feed.scope).toBe('all')
    expect(feed.hasMore).toBe(true)
    expect(feed.loaded).toBe(true)
    expect(usePostsStore().get(3)).toBeDefined()
  })

  it('appends the next page on "load more" and stops after the last one', async () => {
    vi.mocked(socialApi.feed).mockResolvedValueOnce(page([3, 2], '/api/feed?page=2')).mockResolvedValueOnce(page([1]))
    const feed = useFeedStore()
    await feed.load('following')

    await feed.loadMore()

    expect(socialApi.feed).toHaveBeenLastCalledWith('following', 2)
    expect(feed.items.map((post) => post.id)).toEqual([3, 2, 1])
    expect(feed.hasMore).toBe(false)

    await feed.loadMore()
    expect(socialApi.feed).toHaveBeenCalledTimes(2)
  })

  it('does not show a post twice when a new one has pushed it onto the next page', async () => {
    vi.mocked(socialApi.feed).mockResolvedValueOnce(page([3, 2], '/api/feed?page=2')).mockResolvedValueOnce(page([2, 1]))
    const feed = useFeedStore()
    await feed.load('all')

    await feed.loadMore()

    expect(feed.items.map((post) => post.id)).toEqual([3, 2, 1])
  })

  it('asks for one more page at a time', async () => {
    const second = deferred<PostsPage>()
    vi.mocked(socialApi.feed).mockResolvedValueOnce(page([2], '/api/feed?page=2')).mockReturnValueOnce(second.promise)
    const feed = useFeedStore()
    await feed.load('all')

    const first = feed.loadMore()
    void feed.loadMore()

    expect(feed.loadingMore).toBe(true)
    expect(socialApi.feed).toHaveBeenCalledTimes(2)

    second.resolve(page([1]))
    await first
    expect(feed.loadingMore).toBe(false)
  })

  it('keeps what is loaded when "load more" fails, so it can be tried again', async () => {
    vi.mocked(socialApi.feed)
      .mockResolvedValueOnce(page([2], '/api/feed?page=2'))
      .mockRejectedValueOnce(new Error('offline'))
      .mockResolvedValueOnce(page([1]))
    const feed = useFeedStore()
    await feed.load('all')

    await expect(feed.loadMore()).rejects.toThrow('offline')
    expect(feed.items.map((post) => post.id)).toEqual([2])
    expect(feed.hasMore).toBe(true)
    expect(feed.loadingMore).toBe(false)

    await feed.loadMore()
    expect(feed.items.map((post) => post.id)).toEqual([2, 1])
  })

  it('empties the list at once when the scope changes', async () => {
    vi.mocked(socialApi.feed).mockResolvedValueOnce(page([1])).mockReturnValueOnce(new Promise(() => {}))
    const feed = useFeedStore()
    await feed.load('following')

    void feed.load('all')

    expect(feed.items).toEqual([])
    expect(feed.loaded).toBe(false)
  })

  it('keeps showing the list while the same scope refreshes', async () => {
    vi.mocked(socialApi.feed).mockResolvedValueOnce(page([1])).mockReturnValueOnce(new Promise(() => {}))
    const feed = useFeedStore()
    await feed.load('all')

    void feed.load('all')

    expect(feed.items.map((post) => post.id)).toEqual([1])
    expect(feed.loaded).toBe(true)
  })

  it('drops the answer for a scope the reader has already left', async () => {
    const slow = deferred<PostsPage>()
    vi.mocked(socialApi.feed).mockReturnValueOnce(slow.promise).mockResolvedValueOnce(page([7]))
    const feed = useFeedStore()

    const stale = feed.load('following')
    await feed.load('all')
    slow.resolve(page([1, 2, 3]))
    await stale

    expect(feed.items.map((post) => post.id)).toEqual([7])
    expect(feed.scope).toBe('all')
  })

  it('does not report a failure of a request that is already out of date', async () => {
    const slow = deferred<PostsPage>()
    vi.mocked(socialApi.feed).mockReturnValueOnce(slow.promise).mockResolvedValueOnce(page([7]))
    const feed = useFeedStore()

    const stale = feed.load('following')
    await feed.load('all')
    slow.reject(new Error('late'))

    await expect(stale).resolves.toBeUndefined()
  })

  it('stays unloaded and throws when loading fails, so it can be retried', async () => {
    vi.mocked(socialApi.feed).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(page([1]))
    const feed = useFeedStore()

    await expect(feed.load('all')).rejects.toThrow('offline')
    expect(feed.loaded).toBe(false)

    await feed.load('all')
    expect(feed.loaded).toBe(true)
  })

  it('drops a post that was deleted or blocked away from the cache', async () => {
    vi.mocked(socialApi.feed).mockResolvedValue(page([2, 1]))
    const feed = useFeedStore()
    await feed.load('all')

    usePostsStore().removeByAuthor('olena_k')

    expect(feed.items).toEqual([])
  })

  it('shows a like made anywhere, because the posts come from the cache', async () => {
    vi.mocked(socialApi.feed).mockResolvedValue(page([1]))
    const feed = useFeedStore()
    await feed.load('all')

    usePostsStore().put(makePost({ id: 1, liked_by_me: true, likes_count: 4 }))

    expect(feed.items[0]).toMatchObject({ liked_by_me: true, likes_count: 4 })
  })

  describe('prepend', () => {
    it('puts a published post first in a feed that is loaded', async () => {
      vi.mocked(socialApi.feed).mockResolvedValue(page([2, 1]))
      const feed = useFeedStore()
      await feed.load('following')
      usePostsStore().put(makePost({ id: 5 }))

      feed.prepend(makePost({ id: 5 }))

      expect(feed.items.map((post) => post.id)).toEqual([5, 2, 1])
    })

    it('does not start a feed by itself when none has been loaded', () => {
      const feed = useFeedStore()

      feed.prepend(makePost({ id: 5 }))

      expect(feed.items).toEqual([])
    })
  })

  it('forgets everything on reset', async () => {
    vi.mocked(socialApi.feed).mockResolvedValue(page([1], '/api/feed?page=2'))
    const feed = useFeedStore()
    await feed.load('all')

    feed.reset()

    expect(feed.items).toEqual([])
    expect(feed.scope).toBe('following')
    expect(feed.hasMore).toBe(false)
    expect(feed.loaded).toBe(false)
  })

  it('ignores an answer that arrives after a reset', async () => {
    const slow = deferred<PostsPage>()
    vi.mocked(socialApi.feed).mockReturnValue(slow.promise)
    const feed = useFeedStore()

    const pending = feed.load('all')
    feed.reset()
    slow.resolve(page([1]))
    await pending

    expect(feed.items).toEqual([])
    expect(feed.loaded).toBe(false)
  })
})
