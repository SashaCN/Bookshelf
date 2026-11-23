<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { apiErrorCode } from '@/api/errors'
import { ApiError, NetworkError } from '@/api/http'
import { catalogApi } from '@/api/library'
import BaseDialog from '@/components/BaseDialog.vue'
import BookCover from '@/components/BookCover.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { isIsbnScanningSupported, useIsbnScanner } from '@/composables/useIsbnScanner'
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
const searchState = ref<'idle' | 'loading' | 'done' | 'unavailable' | 'failed'>('idle')
const searchError = ref('')
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
  searchState.value = 'loading'

  try {
    const found = await catalogApi.search(text)
    // A newer keystroke has started another search: this answer is already stale.
    if (id !== searchId) return
    results.value = found
    searchState.value = 'done'
  } catch (e) {
    if (id !== searchId) return
    results.value = []
    // No answer within the time limit counts as an unavailable catalog: the manual-entry link is right below.
    if (apiErrorCode(e) === 'catalog.unavailable' || e instanceof NetworkError) {
      searchState.value = 'unavailable'
    } else {
      searchState.value = 'failed'
      searchError.value = errorMessage(e)
    }
  }
}

function retrySearch() {
  const text = query.value.trim()
  if (text.length >= MIN_QUERY_LENGTH) runSearch(text)
}

async function addFromCatalog(entry: CatalogEntry) {
  if (submitting.value) return

  addingKey.value = entry.work_key
  await add({ openlibrary_work_key: entry.work_key })
  addingKey.value = null
}

// --- ISBN scanner -----------------------------------------------------------------------------------------------

const scanSupported = isIsbnScanningSupported()
const scanOpen = ref(false)
const scanVideo = ref<HTMLVideoElement | null>(null)
/** Read out by screen readers; lives outside the overlay, which closes the moment an ISBN is found. */
const announcement = ref('')

const scanner = useIsbnScanner((isbn) => {
  announcement.value = t('add.scan.found', { isbn })
  scanOpen.value = false
  // The search watcher debounces and runs the existing catalog search with this ISBN.
  query.value = isbn
})

async function openScanner() {
  announcement.value = ''
  scanner.stop() // clears the error of an earlier attempt, so the video is rendered again
  scanOpen.value = true
  await nextTick()
  if (scanVideo.value) await scanner.start(scanVideo.value)
}

function closeScanner() {
  scanner.stop()
  scanOpen.value = false
}

const scanProblem = computed(() => {
  switch (scanner.status.value) {
    case 'denied':
      return 'denied'
    case 'no-camera':
      return 'noCamera'
    case 'failed':
      return 'failed'
    default:
      return null
  }
})

// --- manual entry -----------------------------------------------------------------------------------------------

const form = reactive({ title: '', authors: '', pages: '', year: '', cover: '' })

function toNumber(value: string): number | null {
  const parsed = Number.parseInt(value, 10)
  return Number.isFinite(parsed) ? parsed : null
}

async function addManually() {
  if (submitting.value) return

  const authors = form.authors
    .split(',')
    .map((name) => name.trim())
    .filter(Boolean)

  await add({
    title: form.title.trim(),
    authors,
    total_pages: toNumber(form.pages),
    published_year: toNumber(form.year),
    ...(form.cover.trim() ? { cover_url: form.cover.trim() } : {}),
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

  <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>

  <BaseDialog v-if="scanOpen" :title="$t('add.scan.title')" initial-focus="button" @cancel="closeScanner">
    <template #default="{ descriptionId }">
      <div v-if="scanProblem" :id="descriptionId" role="alert" class="flex flex-col gap-1">
        <p class="font-semibold">{{ $t(`add.scan.${scanProblem}.title`) }}</p>
        <p class="text-sm text-ink-muted">{{ $t(`add.scan.${scanProblem}.hint`) }}</p>
      </div>
      <template v-else>
        <p :id="descriptionId" class="text-sm text-ink-muted">
          {{ scanner.status.value === 'starting' ? $t('add.scan.starting') : $t('add.scan.hint') }}
        </p>
        <video
          ref="scanVideo"
          muted
          playsinline
          :aria-label="$t('add.scan.video')"
          class="aspect-[4/3] w-full rounded-lg bg-black object-cover"
        />
      </template>
      <button type="button" class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent" @click="closeScanner">
        {{ $t('add.scan.close') }}
      </button>
    </template>
  </BaseDialog>

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
      <button
        v-if="scanSupported"
        type="button"
        class="min-h-11 self-start rounded-lg border border-line bg-surface px-3 text-sm font-medium"
        @click="openScanner"
      >
        {{ $t('add.scan.button') }}
      </button>
    </div>

    <p v-if="searchState === 'loading'" class="text-ink-muted" role="status">{{ $t('add.searching') }}</p>

    <ErrorNotice
      v-else-if="searchState === 'unavailable'"
      :message="$t('apiErrors.catalog.unavailable')"
      retryable
      @retry="retrySearch"
    />

    <ErrorNotice v-else-if="searchState === 'failed'" :message="searchError" retryable @retry="retrySearch" />

    <p v-else-if="searchState === 'done' && !results.length" class="text-ink-muted" role="status">{{ $t('add.noResults') }}</p>

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
          class="min-h-11 rounded-lg bg-accent px-3 text-sm font-semibold text-on-accent disabled:opacity-60"
          @click="addFromCatalog(entry)"
        >
          {{ addingKey === entry.work_key ? $t('add.adding') : $t('add.add') }}
        </button>
      </li>
    </ul>

    <p class="text-center text-sm text-ink-muted">
      {{ $t('add.notFound') }}
      <button type="button" class="min-h-11 font-medium text-accent underline underline-offset-2" @click="showManual">
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

    <div class="flex flex-col gap-1.5">
      <label for="manual-cover" class="text-sm font-medium">{{ $t('add.fields.cover') }}</label>
      <input
        id="manual-cover"
        v-model="form.cover"
        type="url"
        inputmode="url"
        pattern="https://.*"
        maxlength="2048"
        placeholder="https://"
        aria-describedby="manual-cover-hint"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
      <p id="manual-cover-hint" class="text-sm text-ink-muted">{{ $t('add.fields.coverHint') }}</p>
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
