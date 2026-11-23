<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import QuoteCard from '@/components/QuoteCard.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { quotesApi } from '@/api/quotes'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import { useQuotesStore } from '@/stores/quotes'
import type { QuoteFilters } from '@/types/api'

const route = useRoute()
const router = useRouter()
const quotes = useQuotesStore()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const moreError = ref('')
const exporting = ref(false)
const exportError = ref('')

const REVOKE_DELAY_MS = 10_000
const SEARCH_DELAY_MS = 300
const MIN_QUERY_LENGTH = 2
const MAX_QUERY_LENGTH = 100
const MAX_TAG_LENGTH = 40

// The filters live in the URL, so going back from a quote returns to the same list.
const favoritesOnly = computed(() => route.query.favorite === '1')
const bookFilter = computed(() => {
  const id = Number(route.query.book)
  return Number.isInteger(id) && id > 0 ? id : undefined
})
// A search shorter than the server accepts is not a search yet.
const searchFilter = computed(() => {
  const text = typeof route.query.q === 'string' ? route.query.q.trim() : ''
  return text.length >= MIN_QUERY_LENGTH ? text.slice(0, MAX_QUERY_LENGTH) : undefined
})
const tagFilter = computed(() => {
  const name = typeof route.query.tag === 'string' ? route.query.tag.replace(/\s+/g, ' ').trim().toLowerCase() : ''
  return name ? name.slice(0, MAX_TAG_LENGTH) : undefined
})
const filters = computed(() => {
  const result: QuoteFilters = {}
  if (favoritesOnly.value) result.favorite = true
  if (bookFilter.value !== undefined) result.book = bookFilter.value
  if (searchFilter.value !== undefined) result.q = searchFilter.value
  if (tagFilter.value !== undefined) result.tag = tagFilter.value
  return result
})
const hasFilters = computed(
  () => favoritesOnly.value || bookFilter.value !== undefined || searchFilter.value !== undefined || tagFilter.value !== undefined,
)

function select(next: { favorite?: boolean; book?: number; q?: string; tag?: string }) {
  const query: Record<string, string> = {}
  if (next.favorite) query.favorite = '1'
  if (next.book !== undefined) query.book = String(next.book)
  if (next.q) query.q = next.q
  if (next.tag) query.tag = next.tag
  router.replace({ name: 'quotes', query })
}

// --- search -----------------------------------------------------------------------------------------------------

// What is typed; it reaches the URL (and so the list) 300 ms after the last keystroke. Only the newest request counts:
// the store drops the answer of any list the reader has already moved on from.
const search = ref(searchFilter.value ?? (typeof route.query.q === 'string' ? route.query.q : ''))
let searchTimer: ReturnType<typeof setTimeout> | undefined

function applySearch() {
  const text = search.value.trim()
  select({ favorite: favoritesOnly.value, book: bookFilter.value, q: text.length >= MIN_QUERY_LENGTH ? text : undefined, tag: tagFilter.value })
}

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applySearch, SEARCH_DELAY_MS)
})

function clearSearch() {
  clearTimeout(searchTimer)
  search.value = ''
  select({ favorite: favoritesOnly.value, book: bookFilter.value, tag: tagFilter.value })
}

// Back and forward change the URL under the field.
watch(searchFilter, (value) => {
  const typed = search.value.trim()
  if ((value ?? '') !== (typed.length >= MIN_QUERY_LENGTH ? typed : '')) {
    clearTimeout(searchTimer)
    search.value = value ?? ''
    // Setting the field above queued another update of the URL; it would only repeat this one.
    clearTimeout(searchTimer)
  }
})

onBeforeUnmount(() => clearTimeout(searchTimer))

const book = computed({
  get: () => bookFilter.value ?? '',
  set: (value: number | '') =>
    select({ favorite: favoritesOnly.value, book: value === '' ? undefined : value, q: searchFilter.value, tag: tagFilter.value }),
})

async function load() {
  error.value = ''
  moreError.value = ''

  try {
    await quotes.load(filters.value)
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function loadMore() {
  moreError.value = ''

  try {
    await quotes.loadMore()
  } catch (e) {
    moreError.value = errorMessage(e)
  }
}

watch(
  () => `${favoritesOnly.value}:${bookFilter.value}:${searchFilter.value ?? ''}:${tagFilter.value ?? ''}`,
  () => {
    // While the reader leaves for another screen the route already holds that screen's query.
    if (route.name === 'quotes') load()
  },
  { immediate: true },
)

async function exportMarkdown() {
  exporting.value = true
  exportError.value = ''

  try {
    const file = await quotesApi.exportMarkdown(filters.value)
    const url = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = url
    link.download = `bookshelf-quotes-${new Date().toLocaleDateString('sv-SE')}.md`
    document.body.append(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), REVOKE_DELAY_MS)
  } catch (e) {
    exportError.value = errorMessage(e)
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  // Only feeds the book filter: without the library it simply offers "all books".
  library.load().catch(() => undefined)
})
</script>

<template>
  <PageHeader :title="$t('quotes.title')">
    <template #action>
      <RouterLink
        :to="{ name: 'quote-new', query: bookFilter === undefined ? {} : { book: bookFilter } }"
        class="inline-flex min-h-10 items-center rounded-lg bg-accent px-3 text-sm font-semibold text-on-accent"
      >
        {{ $t('quotes.add') }}
      </RouterLink>
    </template>
  </PageHeader>

  <div class="mb-4 flex flex-col gap-3">
    <div v-if="!quotes.loaded || quotes.items.length || hasFilters" class="relative">
      <label for="quotes-search" class="sr-only">{{ $t('quotes.search.label') }}</label>
      <input
        id="quotes-search"
        v-model="search"
        type="search"
        :maxlength="MAX_QUERY_LENGTH"
        :placeholder="$t('quotes.search.label')"
        autocomplete="off"
        class="min-h-11 w-full rounded-lg border border-line bg-surface px-3 pr-12 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 [&::-webkit-search-cancel-button]:hidden"
      >
      <button
        v-if="search"
        type="button"
        :aria-label="$t('quotes.search.clear')"
        class="absolute right-0 top-0 inline-flex size-11 items-center justify-center text-ink-muted"
        @click="clearSearch"
      >
        <span aria-hidden="true">✕</span>
      </button>
    </div>

    <div v-if="tagFilter !== undefined" class="flex">
      <span class="inline-flex items-center rounded-full bg-sunken pl-3 text-sm" data-test="tag-filter">
        <span class="break-all">#{{ tagFilter }}</span>
        <button
          type="button"
          :aria-label="$t('quotes.tags.removeFilter', { tag: tagFilter })"
          class="inline-flex size-11 items-center justify-center text-ink-muted"
          @click="select({ favorite: favoritesOnly, book: bookFilter, q: searchFilter })"
        >
          <span aria-hidden="true">✕</span>
        </button>
      </span>
    </div>

    <div role="group" :aria-label="$t('quotes.filters.label')" class="flex gap-2">
      <button
        type="button"
        :aria-pressed="!favoritesOnly"
        class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-medium"
        :class="!favoritesOnly ? 'border-accent bg-accent text-on-accent' : 'border-line bg-surface text-ink-muted'"
        @click="select({ book: bookFilter, q: searchFilter, tag: tagFilter })"
      >
        {{ $t('quotes.filters.all') }}
      </button>
      <button
        type="button"
        :aria-pressed="favoritesOnly"
        class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-medium"
        :class="favoritesOnly ? 'border-accent bg-accent text-on-accent' : 'border-line bg-surface text-ink-muted'"
        @click="select({ favorite: true, book: bookFilter, q: searchFilter, tag: tagFilter })"
      >
        {{ $t('quotes.filters.favorites') }}
      </button>
    </div>

    <div>
      <label for="quotes-book" class="sr-only">{{ $t('quotes.filters.book') }}</label>
      <select
        id="quotes-book"
        v-model="book"
        class="min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
        <option value="">{{ $t('quotes.filters.allBooks') }}</option>
        <option v-for="userBook in library.byTitle" :key="userBook.id" :value="userBook.id">
          {{ userBook.book.title }}
        </option>
      </select>
    </div>
  </div>

  <div v-if="!quotes.loaded || quotes.items.length || hasFilters" class="mb-4">
    <button
      type="button"
      :disabled="exporting"
      class="inline-flex min-h-11 items-center rounded-lg border border-line bg-surface px-4 text-sm font-medium disabled:opacity-60"
      @click="exportMarkdown"
    >
      {{ exporting ? $t('quotes.exporting') : $t('quotes.export') }}
    </button>
  </div>

  <ErrorNotice v-if="exportError" class="mb-4" :message="exportError" />

  <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

  <SkeletonList v-else-if="!quotes.loaded" />

  <template v-if="quotes.loaded">
    <ul v-if="quotes.items.length" class="mt-4 flex flex-col gap-3">
      <li v-for="quote in quotes.items" :key="quote.id">
        <QuoteCard :quote="quote" />
      </li>
    </ul>

    <EmptyState
      v-else-if="searchFilter !== undefined"
      class="mt-4"
      :title="$t('quotes.search.emptyTitle')"
      :text="$t('quotes.search.emptyText', { q: searchFilter })"
    >
      <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium" @click="clearSearch">
        {{ $t('quotes.search.clear') }}
      </button>
    </EmptyState>

    <EmptyState
      v-else-if="hasFilters"
      class="mt-4"
      :title="$t('quotes.emptyFiltered.title')"
      :text="$t('quotes.emptyFiltered.text')"
    >
      <button
        type="button"
        class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium"
        @click="select({})"
      >
        {{ $t('quotes.filters.reset') }}
      </button>
    </EmptyState>

    <EmptyState v-else class="mt-4" :title="$t('quotes.empty.title')" :text="$t('quotes.empty.text')">
      <RouterLink
        :to="{ name: 'quote-new' }"
        class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
      >
        {{ $t('quotes.addQuote') }}
      </RouterLink>
    </EmptyState>

    <ErrorNotice v-if="moreError" :message="moreError" retryable class="mt-4" @retry="loadMore" />

    <button
      v-if="quotes.hasMore && !moreError"
      type="button"
      :disabled="quotes.loadingMore"
      class="mt-4 min-h-11 w-full rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
      @click="loadMore"
    >
      {{ $t('quotes.more') }}
    </button>
  </template>
</template>
