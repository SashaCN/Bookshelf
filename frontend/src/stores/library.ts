import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { libraryApi } from '@/api/library'
import type { AddBookPayload, BookStatus, ProgressResult, UpdateBookPayload, UserBook } from '@/types/api'

export const useLibraryStore = defineStore('library', () => {
  /** The whole library, most recently changed first (the server's order). */
  const items = ref<UserBook[]>([])
  const loaded = ref(false)
  const loading = ref(false)

  const byStatus = computed(() => {
    const groups: Record<BookStatus, UserBook[]> = { want: [], reading: [], finished: [], abandoned: [] }
    for (const item of items.value) {
      groups[item.status].push(item)
    }
    return groups
  })

  function find(id: number): UserBook | undefined {
    return items.value.find((item) => item.id === id)
  }

  function upsert(userBook: UserBook): void {
    items.value = [userBook, ...items.value.filter((item) => item.id !== userBook.id)]
  }

  /** Swap a book for a fresh copy without moving it, so a card does not jump away from under a tapping finger. */
  function replaceInPlace(userBook: UserBook): void {
    if (items.value.some((item) => item.id === userBook.id)) {
      items.value = items.value.map((item) => (item.id === userBook.id ? userBook : item))
    } else {
      upsert(userBook)
    }
  }

  async function load(force = false): Promise<void> {
    if ((loaded.value && !force) || loading.value) {
      return
    }

    loading.value = true
    try {
      items.value = await libraryApi.list()
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  async function add(payload: AddBookPayload): Promise<UserBook> {
    const userBook = await libraryApi.create(payload)
    upsert(userBook)
    return userBook
  }

  async function update(id: number, payload: UpdateBookPayload): Promise<UserBook> {
    const userBook = await libraryApi.update(id, payload)
    upsert(userBook)
    return userBook
  }

  async function remove(id: number): Promise<void> {
    await libraryApi.remove(id)
    items.value = items.value.filter((item) => item.id !== id)
  }

  /** Requests of one book go out one at a time, so a quick second tap builds on the first instead of racing it. */
  const progressQueues = new Map<number, Promise<unknown>>()
  const progressInFlight = new Map<number, number>()
  /** How a book looked when its current burst of taps began, in case the server turns the taps down. */
  const progressSnapshots = new Map<number, UserBook>()

  /**
   * Move the bookmark. A book that is being read shows the new page at once and the request follows in the background;
   * any other book (it will change status) waits for the server's answer.
   */
  async function setProgress(id: number, page: number): Promise<ProgressResult> {
    const before = find(id)
    const inFlight = progressInFlight.get(id) ?? 0

    if (before && inFlight === 0) {
      progressSnapshots.set(id, before)
    }

    if (before?.status === 'reading' && before.total_pages) {
      replaceInPlace({
        ...before,
        current_page: page,
        progress_percent: Math.floor((page / before.total_pages) * 100),
      })
    }

    progressInFlight.set(id, inFlight + 1)
    const request = (progressQueues.get(id) ?? Promise.resolve()).then(() => libraryApi.progress(id, page))
    progressQueues.set(id, request.catch(() => undefined))

    try {
      const result = await request
      // While later taps are queued their page is the one to show; the last answer settles it.
      if (progressInFlight.get(id) === 1) {
        replaceInPlace(result.data)
      }
      return result
    } catch (error) {
      if (progressInFlight.get(id) === 1) {
        await restore(id)
      }
      throw error
    } finally {
      const left = (progressInFlight.get(id) ?? 1) - 1
      if (left > 0) {
        progressInFlight.set(id, left)
      } else {
        progressInFlight.delete(id)
        progressQueues.delete(id)
        progressSnapshots.delete(id)
      }
    }
  }

  /** Show the server's truth after a rejected update; if the server cannot be reached, the state from before the taps. */
  async function restore(id: number): Promise<void> {
    try {
      replaceInPlace(await libraryApi.get(id))
    } catch {
      const snapshot = progressSnapshots.get(id)
      if (snapshot) {
        replaceInPlace(snapshot)
      }
    }
  }

  /** Forget everything, e.g. when somebody else signs in on the same device. */
  function reset(): void {
    items.value = []
    loaded.value = false
    loading.value = false
  }

  return { items, loaded, loading, byStatus, find, upsert, load, add, update, remove, setProgress, reset }
})
