import { defineStore } from 'pinia'
import { ref } from 'vue'
import { socialApi } from '@/api/social'
import type { Reader } from '@/types/social'

/** The readers the current reader has blocked. */
export const useBlocksStore = defineStore('blocks', () => {
  const items = ref<Reader[]>([])
  const hasMore = ref(false)
  const loaded = ref(false)
  const loadingMore = ref(false)

  let page = 1
  let generation = 0

  async function load(): Promise<void> {
    loadingMore.value = false
    const current = ++generation

    try {
      const result = await socialApi.blocks(1)
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
    if (!hasMore.value || loadingMore.value) {
      return
    }

    const current = generation
    loadingMore.value = true

    try {
      const result = await socialApi.blocks(page + 1)
      if (current !== generation) return
      const known = new Set(items.value.map((item) => item.handle))
      items.value = [...items.value, ...result.data.filter((item) => !known.has(item.handle))]
      hasMore.value = result.links.next !== null
      page++
    } finally {
      if (current === generation) {
        loadingMore.value = false
      }
    }
  }

  async function unblock(handle: string): Promise<void> {
    await socialApi.unblock(handle)
    items.value = items.value.filter((item) => item.handle !== handle)
  }

  /** Forget everything: when somebody else signs in, or when the list is out of date (a reader was just blocked). */
  function reset(): void {
    generation++
    items.value = []
    hasMore.value = false
    loaded.value = false
    loadingMore.value = false
    page = 1
  }

  return { items, hasMore, loaded, loadingMore, load, loadMore, unblock, reset }
})
