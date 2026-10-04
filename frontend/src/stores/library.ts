import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { libraryApi } from '@/api/library'
import type { AddBookPayload, BookStatus, UpdateBookPayload, UserBook } from '@/types/api'

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

  /** Forget everything, e.g. when somebody else signs in on the same device. */
  function reset(): void {
    items.value = []
    loaded.value = false
    loading.value = false
  }

  return { items, loaded, loading, byStatus, find, upsert, load, add, update, remove, reset }
})
