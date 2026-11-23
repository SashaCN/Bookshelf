import { defineStore } from 'pinia'
import { reactive, ref, type Ref } from 'vue'
import { socialApi } from '@/api/social'
import type { FeedQuote, PersonSummary, PublicProfile, Quote } from '@/types/api'

interface Pager<T> {
  items: Ref<T[]>
  hasMore: Ref<boolean>
  loaded: Ref<boolean>
  loadingMore: Ref<boolean>
  load: (fetch: (page: number) => Promise<{ data: T[]; links: { next: string | null } }>) => Promise<void>
  loadMore: () => Promise<void>
  reset: () => void
}

/** A list read page by page; an answer for a list the reader has already left is dropped. */
function createPager<T>(key: (item: T) => string | number): Pager<T> {
  const items = ref([]) as Ref<T[]>
  const hasMore = ref(false)
  const loaded = ref(false)
  const loadingMore = ref(false)
  let fetchPage: (page: number) => Promise<{ data: T[]; links: { next: string | null } }> = async () => ({
    data: [],
    links: { next: null },
  })
  let page = 1
  let generation = 0

  function reset(): void {
    generation++
    items.value = []
    hasMore.value = false
    loaded.value = false
    loadingMore.value = false
    page = 1
  }

  async function load(fetch: (page: number) => Promise<{ data: T[]; links: { next: string | null } }>): Promise<void> {
    reset()
    fetchPage = fetch
    const current = generation

    const result = await fetch(1)
    if (current !== generation) return
    items.value = result.data
    hasMore.value = result.links.next !== null
    loaded.value = true
  }

  async function loadMore(): Promise<void> {
    if (!hasMore.value || loadingMore.value) return

    const current = generation
    loadingMore.value = true

    try {
      const result = await fetchPage(page + 1)
      if (current !== generation) return
      const known = new Set(items.value.map(key))
      items.value = [...items.value, ...result.data.filter((item) => !known.has(key(item)))]
      hasMore.value = result.links.next !== null
      page++
    } finally {
      if (current === generation) loadingMore.value = false
    }
  }

  return { items, hasMore, loaded, loadingMore, load, loadMore, reset }
}

export const useSocialStore = defineStore('social', () => {
  const profile = ref<PublicProfile | null>(null)
  const profileQuotes = reactive(createPager<Quote>((quote) => quote.id))
  const feed = reactive(createPager<FeedQuote>((quote) => quote.id))
  const people = reactive(createPager<PersonSummary>((person) => person.username))

  let profileGeneration = 0

  /** The profile and the first page of its public quotes. A missing or private profile rejects with a 404. */
  async function loadProfile(username: string): Promise<void> {
    const current = ++profileGeneration
    profile.value = null
    profileQuotes.reset()

    const loaded = await socialApi.profile(username)
    if (current !== profileGeneration) return
    profile.value = loaded
    await profileQuotes.load((page) => socialApi.quotes(username, page))
  }

  /** The button changes at once and the request follows; if the server turns it down everything goes back. */
  async function setFollowing(following: boolean): Promise<void> {
    const current = profile.value
    if (!current || current.is_me || current.is_following === following) return

    const delta = following ? 1 : -1
    profile.value = { ...current, is_following: following, followers_count: current.followers_count + delta }

    try {
      await (following ? socialApi.follow(current.username) : socialApi.unfollow(current.username))
    } catch (error) {
      if (profile.value?.username === current.username) profile.value = current
      throw error
    }
  }

  /** The heart changes at once and the request follows; if the server turns it down the heart and the count go back. */
  async function toggleLike(quote: Quote): Promise<void> {
    const liked = quote.liked_by_me === true
    const count = quote.likes_count ?? 0
    quote.liked_by_me = !liked
    quote.likes_count = Math.max(0, count + (liked ? -1 : 1))

    try {
      await (liked ? socialApi.unlike(quote.id) : socialApi.like(quote.id))
    } catch (error) {
      quote.liked_by_me = liked
      quote.likes_count = count
      throw error
    }
  }

  function loadFeed(): Promise<void> {
    return feed.load((page) => socialApi.feed(page))
  }

  function loadPeople(username: string, kind: 'followers' | 'following'): Promise<void> {
    return people.load((page) => socialApi[kind](username, page))
  }

  function reset(): void {
    profileGeneration++
    profile.value = null
    profileQuotes.reset()
    feed.reset()
    people.reset()
  }

  return { profile, profileQuotes, feed, people, loadProfile, setFollowing, toggleLike, loadFeed, loadPeople, reset }
})
