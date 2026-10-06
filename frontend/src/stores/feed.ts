import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { socialApi } from '@/api/social'
import type { FeedScope, Post } from '@/types/social'
import { usePostsStore } from './posts'

export const useFeedStore = defineStore('feed', () => {
  const posts = usePostsStore()

  const scope = ref<FeedScope>('following')
  /** The ids of the loaded posts, newest first (the server's order). */
  const ids = ref<number[]>([])
  const hasMore = ref(false)
  const loaded = ref(false)
  const loadingMore = ref(false)

  let page = 1
  /** Bumped by every fresh load and by reset, so an answer for a list the reader has already left is dropped. */
  let generation = 0

  /** The loaded posts. One that was deleted or blocked away since is no longer known and drops out. */
  const items = computed(() => ids.value.map((id) => posts.get(id)).filter((post): post is Post => post !== undefined))

  /** Load the first page of a scope. The same scope as before keeps what is shown while it refreshes. */
  async function load(next: FeedScope): Promise<void> {
    if (!loaded.value || scope.value !== next) {
      ids.value = []
      hasMore.value = false
      loaded.value = false
    }

    scope.value = next
    loadingMore.value = false
    const current = ++generation

    try {
      const result = await socialApi.feed(next, 1)
      if (current !== generation) return
      posts.putAll(result.data)
      ids.value = result.data.map((post) => post.id)
      hasMore.value = result.links.next !== null
      page = 1
      loaded.value = true
    } catch (error) {
      if (current === generation) throw error
    }
  }

  async function loadMore(): Promise<void> {
    if (!hasMore.value || loadingMore.value) {
      return
    }

    const current = generation
    loadingMore.value = true

    try {
      const result = await socialApi.feed(scope.value, page + 1)
      if (current !== generation) return
      posts.putAll(result.data)
      // A post published meanwhile pushes the last one of the previous page onto this one.
      const known = new Set(ids.value)
      ids.value = [...ids.value, ...result.data.map((post) => post.id).filter((id) => !known.has(id))]
      hasMore.value = result.links.next !== null
      page++
    } finally {
      if (current === generation) {
        loadingMore.value = false
      }
    }
  }

  /** A post the reader has just published goes to the top of a feed that is loaded (their own posts are in both). */
  function prepend(post: Post): void {
    if (loaded.value) {
      ids.value = [post.id, ...ids.value.filter((id) => id !== post.id)]
    }
  }

  /** Forget everything, e.g. when somebody else signs in on the same device. */
  function reset(): void {
    generation++
    scope.value = 'following'
    ids.value = []
    hasMore.value = false
    loaded.value = false
    loadingMore.value = false
    page = 1
  }

  return { scope, items, hasMore, loaded, loadingMore, load, loadMore, prepend, reset }
})
