<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import QuoteCard from '@/components/QuoteCard.vue'
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

// The filters live in the URL, so going back from a quote returns to the same list.
const favoritesOnly = computed(() => route.query.favorite === '1')
const bookFilter = computed(() => {
  const id = Number(route.query.book)
  return Number.isInteger(id) && id > 0 ? id : undefined
})
const filters = computed(() => {
  const result: QuoteFilters = {}
  if (favoritesOnly.value) result.favorite = true
  if (bookFilter.value !== undefined) result.book = bookFilter.value
  return result
})
const hasFilters = computed(() => favoritesOnly.value || bookFilter.value !== undefined)

function select(next: { favorite?: boolean; book?: number }) {
  const query: Record<string, string> = {}
  if (next.favorite) query.favorite = '1'
  if (next.book !== undefined) query.book = String(next.book)
  router.replace({ name: 'quotes', query })
}

const book = computed({
  get: () => bookFilter.value ?? '',
  set: (value: number | '') => select({ favorite: favoritesOnly.value, book: value === '' ? undefined : value }),
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
  () => `${favoritesOnly.value}:${bookFilter.value}`,
  () => {
    // While the reader leaves for another screen the route already holds that screen's query.
    if (route.name === 'quotes') load()
  },
  { immediate: true },
)

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
    <div role="group" :aria-label="$t('quotes.filters.label')" class="flex gap-2">
      <button
        type="button"
        :aria-pressed="!favoritesOnly"
        class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-medium"
        :class="!favoritesOnly ? 'border-accent bg-accent text-on-accent' : 'border-line bg-surface text-ink-muted'"
        @click="select({ book: bookFilter })"
      >
        {{ $t('quotes.filters.all') }}
      </button>
      <button
        type="button"
        :aria-pressed="favoritesOnly"
        class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-medium"
        :class="favoritesOnly ? 'border-accent bg-accent text-on-accent' : 'border-line bg-surface text-ink-muted'"
        @click="select({ favorite: true, book: bookFilter })"
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

  <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

  <p v-else-if="!quotes.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <template v-if="quotes.loaded">
    <ul v-if="quotes.items.length" class="mt-4 flex flex-col gap-3">
      <li v-for="quote in quotes.items" :key="quote.id">
        <QuoteCard :quote="quote" />
      </li>
    </ul>

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
