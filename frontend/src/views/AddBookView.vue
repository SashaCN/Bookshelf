<script setup lang="ts">
import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { apiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'
import { catalogApi } from '@/api/library'
import BookCover from '@/components/BookCover.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import type { AddBookPayload, CatalogEntry } from '@/types/api'

const SEARCH_DELAY_MS = 350
const MIN_QUERY_LENGTH = 2

const { t } = useI18n()
const router = useRouter()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const mode = ref<'search' | 'manual'>('search')
const error = ref('')

// --- search -----------------------------------------------------------------------------------------------------

const query = ref('')
const results = ref<CatalogEntry[]>([])
const searchState = ref<'idle' | 'loading' | 'done' | 'unavailable'>('idle')
const addingKey = ref<string | null>(null)

let timer: ReturnType<typeof setTimeout> | undefined
let searchId = 0

watch(query, (value) => {
  clearTimeout(timer)
  searchId++

  if (value.trim().length < MIN_QUERY_LENGTH) {
    results.value = []
    searchState.value = 'idle'
    return
  }

  searchState.value = 'loading'
  timer = setTimeout(() => runSearch(value.trim()), SEARCH_DELAY_MS)
})

onBeforeUnmount(() => clearTimeout(timer))

async function runSearch(text: string) {
  const id = ++searchId
  error.value = ''

  try {
    const found = await catalogApi.search(text)
    // A newer keystroke has started another search: this answer is already stale.
    if (id !== searchId) return
    results.value = found
    searchState.value = 'done'
  } catch (e) {
    if (id !== searchId) return
    results.value = []
    if (apiErrorCode(e) === 'catalog.unavailable') {
      searchState.value = 'unavailable'
    } else {
      searchState.value = 'idle'
      error.value = errorMessage(e)
    }
  }
}

async function addFromCatalog(entry: CatalogEntry) {
  addingKey.value = entry.work_key
  await add({ openlibrary_work_key: entry.work_key })
  addingKey.value = null
}

// --- manual entry -----------------------------------------------------------------------------------------------

const form = reactive({ title: '', authors: '', pages: '', year: '' })

function toNumber(value: string): number | null {
  const parsed = Number.parseInt(value, 10)
  return Number.isFinite(parsed) ? parsed : null
}

async function addManually() {
  const authors = form.authors
    .split(',')
    .map((name) => name.trim())
    .filter(Boolean)

  await add({
    title: form.title.trim(),
    authors,
    total_pages: toNumber(form.pages),
    published_year: toNumber(form.year),
  })
}

// --- shared -----------------------------------------------------------------------------------------------------

const submitting = ref(false)

async function add(payload: AddBookPayload) {
  submitting.value = true
  error.value = ''

  try {
    const userBook = await library.add(payload)
    await router.push({ name: 'book', params: { id: userBook.id } })
  } catch (e) {
    const existing = e instanceof ApiError && e.status === 409 ? (e.body as { user_book_id?: number }).user_book_id : undefined

    if (existing) {
      // Already in the library: take the reader to the existing entry instead of showing an error.
      await router.push({ name: 'book', params: { id: existing } })
    } else {
      error.value = errorMessage(e)
    }
  } finally {
    submitting.value = false
  }
}

function showManual() {
  error.value = ''
  mode.value = 'manual'
  // Whatever was typed into the search box is most likely the title.
  if (!form.title) form.title = query.value.trim()
}
</script>

<template>
  <PageHeader :title="$t('add.title')" :back-to="{ name: 'library' }" />

  <ErrorNotice v-if="error" :message="error" class="mb-4" />

  <!-- Search the Open Library catalog -->
  <section v-if="mode === 'search'" class="flex flex-col gap-4">
    <div class="flex flex-col gap-1.5">
      <label for="catalog-search" class="text-sm font-medium">{{ $t('add.searchLabel') }}</label>
      <input
        id="catalog-search"
        v-model="query"
        type="search"
        enterkeyhint="search"
        autocomplete="off"
        :placeholder="$t('add.searchPlaceholder')"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
    </div>

    <p v-if="searchState === 'loading'" class="text-ink-muted" role="status">{{ $t('add.searching') }}</p>

    <div
      v-else-if="searchState === 'unavailable'"
      role="alert"
      class="rounded-lg bg-danger-soft px-3 py-3 text-sm text-danger"
    >
      {{ $t('apiErrors.catalog.unavailable') }}
    </div>

    <p v-else-if="searchState === 'done' && !results.length" class="text-ink-muted">{{ $t('add.noResults') }}</p>

    <ul v-if="results.length && searchState === 'done'" class="flex flex-col gap-2">
      <li
        v-for="entry in results"
        :key="entry.work_key"
        class="grid grid-cols-[3rem_1fr_auto] items-center gap-3 rounded-2xl border border-line bg-surface p-3"
      >
        <BookCover :title="entry.title" :cover-url="entry.cover_url" />

        <div class="min-w-0">
          <p class="line-clamp-2 font-semibold leading-snug">{{ entry.title }}</p>
          <p v-if="entry.authors.length" class="truncate text-sm text-ink-muted">{{ entry.authors.join(', ') }}</p>
          <p class="text-xs text-ink-muted">
            {{ [entry.published_year, entry.page_count ? t('add.pagesShort', { count: entry.page_count }) : null].filter(Boolean).join(' · ') }}
          </p>
        </div>

        <span v-if="entry.in_library" class="text-sm font-medium text-ink-muted">{{ $t('add.inLibrary') }}</span>
        <button
          v-else
          type="button"
          :disabled="submitting"
          class="min-h-10 rounded-lg bg-accent px-3 text-sm font-semibold text-on-accent disabled:opacity-60"
          @click="addFromCatalog(entry)"
        >
          {{ addingKey === entry.work_key ? $t('add.adding') : $t('add.add') }}
        </button>
      </li>
    </ul>

    <p class="text-center text-sm text-ink-muted">
      {{ $t('add.notFound') }}
      <button type="button" class="font-medium text-accent underline underline-offset-2" @click="showManual">
        {{ $t('add.manualLink') }}
      </button>
    </p>
  </section>

  <!-- Enter a book by hand -->
  <form v-else class="flex flex-col gap-4" @submit.prevent="addManually">
    <div class="flex flex-col gap-1.5">
      <label for="manual-title" class="text-sm font-medium">{{ $t('add.fields.title') }}</label>
      <input
        id="manual-title"
        v-model="form.title"
        type="text"
        required
        maxlength="255"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
    </div>

    <div class="flex flex-col gap-1.5">
      <label for="manual-authors" class="text-sm font-medium">{{ $t('add.fields.authors') }}</label>
      <input
        id="manual-authors"
        v-model="form.authors"
        type="text"
        aria-describedby="manual-authors-hint"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
      <p id="manual-authors-hint" class="text-sm text-ink-muted">{{ $t('add.fields.authorsHint') }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div class="flex flex-col gap-1.5">
        <label for="manual-pages" class="text-sm font-medium">{{ $t('add.fields.pages') }}</label>
        <input
          id="manual-pages"
          v-model="form.pages"
          type="number"
          inputmode="numeric"
          min="1"
          max="20000"
          class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
        >
      </div>
      <div class="flex flex-col gap-1.5">
        <label for="manual-year" class="text-sm font-medium">{{ $t('add.fields.year') }}</label>
        <input
          id="manual-year"
          v-model="form.year"
          type="number"
          inputmode="numeric"
          min="1"
          :max="new Date().getFullYear() + 1"
          class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
        >
      </div>
    </div>

    <button
      type="submit"
      :disabled="submitting"
      class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
    >
      {{ $t('add.submit') }}
    </button>

    <button
      type="button"
      class="text-sm font-medium text-accent underline underline-offset-2"
      @click="mode = 'search'"
    >
      {{ $t('add.backToSearch') }}
    </button>
  </form>
</template>
