import { defineStore } from 'pinia'
import { ref } from 'vue'
import { socialApi } from '@/api/social'
import type { Comment } from '@/types/social'
import { usePostsStore } from './posts'

/** The comments of the post the reader is looking at. */
export const useCommentsStore = defineStore('comments', () => {
  const posts = usePostsStore()

  const postId = ref<number | null>(null)
  /** Newest first (the server's order). */
  const items = ref<Comment[]>([])
  const hasMore = ref(false)
  const loaded = ref(false)
  const loadingMore = ref(false)

  let page = 1
  let generation = 0

  async function load(id: number): Promise<void> {
    if (postId.value !== id) {
      items.value = []
      hasMore.value = false
      loaded.value = false
    }

    postId.value = id
    loadingMore.value = false
    const current = ++generation

    try {
      const result = await socialApi.comments(id, 1)
      if (current !== generation) return
      items.value = result.data
      hasMore.value = result.links.next !== null
      page = 1
      loaded.value = true
    } catch (error) {
      if (current === generation) throw error
    }
  }

  async function loadMore(): Promise<void> {
    if (postId.value === null || !hasMore.value || loadingMore.value) {
      return
    }

    const current = generation
    loadingMore.value = true

    try {
      const result = await socialApi.comments(postId.value, page + 1)
      if (current !== generation) return
      // A comment written meanwhile pushes the last one of the previous page onto this one.
      const known = new Set(items.value.map((item) => item.id))
      items.value = [...items.value, ...result.data.filter((item) => !known.has(item.id))]
      hasMore.value = result.links.next !== null
      page++
    } finally {
      if (current === generation) {
        loadingMore.value = false
      }
    }
  }

  /** Write a comment; it goes to the top of the list and onto the counter of the post. */
  async function add(id: number, content: string): Promise<Comment> {
    const comment = await socialApi.addComment(id, content)

    if (postId.value === id) {
      items.value = [comment, ...items.value.filter((item) => item.id !== comment.id)]
    }
    posts.changeCommentsCount(id, 1)

    return comment
  }

  async function remove(comment: Comment): Promise<void> {
    await socialApi.deleteComment(comment.id)

    items.value = items.value.filter((item) => item.id !== comment.id)
    // Every comment on screen is in the counter of the reader: the server counts what the reader can see, and the one
    // hidden comment they can see is their own.
    posts.changeCommentsCount(comment.post_id, -1)
  }

  /** Forget everything, e.g. when somebody else signs in on the same device. */
  function reset(): void {
    generation++
    postId.value = null
    items.value = []
    hasMore.value = false
    loaded.value = false
    loadingMore.value = false
    page = 1
  }

  return { postId, items, hasMore, loaded, loadingMore, load, loadMore, add, remove, reset }
})
