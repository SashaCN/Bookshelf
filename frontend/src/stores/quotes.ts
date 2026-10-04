import { defineStore } from 'pinia'
import { ref } from 'vue'
import { quotesApi } from '@/api/quotes'
import type { Quote, QuoteFilters, QuotePayload } from '@/types/api'

function sameFilters(a: QuoteFilters, b: QuoteFilters): boolean {
  return Boolean(a.favorite) === Boolean(b.favorite) && a.book === b.book && a.type === b.type
}

export const useQuotesStore = defineStore('quotes', () => {
  /** The quotes loaded so far for `filters`, newest first (the server's order). */
  const items = ref<Quote[]>([])
  const filters = ref<QuoteFilters>({})
  const hasMore = ref(false)
  const loaded = ref(false)
  const loadingMore = ref(false)
  /** The quote of the day for the home screen; null before it is loaded and while the reader has no quotes. */
  const daily = ref<Quote | null>(null)

  let page = 1
  /** Bumped by every fresh load and by reset, so an answer for a list the reader has already left is dropped. */
  let generation = 0

  function find(id: number): Quote | undefined {
    return items.value.find((item) => item.id === id) ?? (daily.value?.id === id ? daily.value : undefined)
  }

  /** Whether a quote belongs in the list that is on screen. */
  function matches(quote: Quote): boolean {
    const { favorite, book, type } = filters.value
    return (!favorite || quote.is_favorite) && (book === undefined || quote.user_book_id === book) && (type === undefined || quote.type === type)
  }

  /** Swap in the server's copy of a quote wherever it is shown, without moving it. */
  function replace(quote: Quote): void {
    items.value = items.value.map((item) => (item.id === quote.id ? quote : item))
    if (daily.value?.id === quote.id) {
      daily.value = quote
    }
  }

  function setFavorite(id: number, isFavorite: boolean): void {
    const current = find(id)
    if (current) {
      replace({ ...current, is_favorite: isFavorite })
    }
  }

  /** Load the first page of the list for `next`. The same filters as before keep what is shown while it refreshes. */
  async function load(next: QuoteFilters = {}): Promise<void> {
    if (!loaded.value || !sameFilters(filters.value, next)) {
      items.value = []
      hasMore.value = false
      loaded.value = false
    }

    filters.value = next
    loadingMore.value = false
    const current = ++generation

    try {
      const result = await quotesApi.list(next, 1)
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
      const result = await quotesApi.list(filters.value, page + 1)
      if (current !== generation) return
      // A quote added meanwhile pushes the last one of the previous page onto this one.
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

  async function loadDaily(): Promise<void> {
    daily.value = await quotesApi.daily()
  }

  /** A quote from the list or the quote of the day if it is there, otherwise from the server. */
  async function get(id: number): Promise<Quote> {
    return find(id) ?? (await quotesApi.get(id))
  }

  async function create(userBookId: number, payload: QuotePayload): Promise<Quote> {
    const quote = await quotesApi.create(userBookId, payload)
    if (loaded.value && matches(quote)) {
      items.value = [quote, ...items.value.filter((item) => item.id !== quote.id)]
    }
    return quote
  }

  async function update(id: number, payload: Partial<QuotePayload>): Promise<Quote> {
    const quote = await quotesApi.update(id, payload)
    replace(quote)
    return quote
  }

  async function remove(id: number): Promise<void> {
    await quotesApi.remove(id)
    items.value = items.value.filter((item) => item.id !== id)
    if (daily.value?.id === id) {
      daily.value = null
    }
  }

  /** The star changes at once and the request follows; if the server turns it down the star goes back and the error is thrown. */
  async function toggleFavorite(quote: Quote): Promise<void> {
    setFavorite(quote.id, !quote.is_favorite)

    try {
      replace(await quotesApi.update(quote.id, { is_favorite: !quote.is_favorite }))
    } catch (error) {
      setFavorite(quote.id, quote.is_favorite)
      throw error
    }
  }

  /** Forget everything, e.g. when somebody else signs in on the same device. */
  function reset(): void {
    generation++
    items.value = []
    filters.value = {}
    hasMore.value = false
    loaded.value = false
    loadingMore.value = false
    daily.value = null
    page = 1
  }

  return { items, filters, hasMore, loaded, loadingMore, daily, find, load, loadMore, loadDaily, get, create, update, remove, toggleFavorite, reset }
})
