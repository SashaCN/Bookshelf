<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BookCard from '@/components/BookCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { useLibraryStore } from '@/stores/library'
import type { BookStatus } from '@/types/api'
import { isLibrarySort, librarySorts, matches, sortBooks, type LibrarySort } from '@/utils/librarySearch'

const statuses: BookStatus[] = ['want', 'reading', 'finished', 'abandoned']

const route = useRoute()
const router = useRouter()
const library = useLibraryStore()
const failed = ref(false)

// The selected tab lives in the URL, so going back from a book returns to the same list.
const current = computed<BookStatus>(() => {
  const requested = route.query.status
  return statuses.find((status) => status === requested) ?? 'want'
})

const SORT_KEY = 'bookshelf.librarySort'

function storedSort(): LibrarySort {
  try {
    const value = localStorage.getItem(SORT_KEY)
    return isLibrarySort(value) ? value : 'recent'
  } catch {
    return 'recent'
  }
}

const sort = computed<LibrarySort>(() => (isLibrarySort(route.query.sort) ? route.query.sort : storedSort()))

/** What the field shows right now; the route (and so the list) follows after a short pause. */
const draft = ref(typeof route.query.q === 'string' ? route.query.q : '')
const applied = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))
let timer: ReturnType<typeof setTimeout> | undefined

function go(changes: { status?: BookStatus; q?: string; sort?: LibrarySort }) {
  const query: Record<string, string> = {}
  const next = { status: current.value, q: applied.value, sort: isLibrarySort(route.query.sort) ? route.query.sort : undefined, ...changes }
  query.status = next.status
  if (next.q) query.q = next.q
  if (next.sort) query.sort = next.sort
  router.replace({ name: 'library', query })
}

function select(status: BookStatus) {
  go({ status })
}

function onSort(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  if (!isLibrarySort(value)) return
  try {
    localStorage.setItem(SORT_KEY, value)
  } catch {
    // Private mode or blocked storage: the route query still carries the choice.
  }
  go({ sort: value })
}

watch(draft, (value) => {
  clearTimeout(timer)
  if (value === applied.value) return
  timer = setTimeout(() => go({ q: value.trim() === '' ? '' : value }), 150)
})

// Back/forward navigation changes the query; keep the field in step.
watch(applied, (value) => {
  if (value !== draft.value && value !== draft.value.trim()) draft.value = value
})

function clearSearch() {
  clearTimeout(timer)
  draft.value = ''
  go({ q: '' })
}

onBeforeUnmount(() => clearTimeout(timer))

const visible = computed(() => {
  const inTab = library.byStatus[current.value]
  const keys = library.searchKeys
  const filtered = applied.value.trim() === '' ? inTab : inTab.filter((item) => matches(keys.get(item.id) ?? '', applied.value))
  return sortBooks(filtered, sort.value)
})

async function load(force = false) {
  failed.value = false
  try {
    await library.load(force)
  } catch {
    failed.value = true
  }
}

onMounted(() => load())
</script>

<template>
  <PageHeader :title="$t('library.title')">
    <template #action>
      <RouterLink
        :to="{ name: 'library-add' }"
        class="inline-flex min-h-11 items-center rounded-lg bg-accent px-3 text-sm font-semibold text-on-accent"
      >
        {{ $t('library.add') }}
      </RouterLink>
    </template>
  </PageHeader>

  <div class="-mx-4 mb-4 overflow-x-auto px-4">
    <div role="group" :aria-label="$t('library.statusFilter')" class="flex gap-2">
      <button
        v-for="status in statuses"
        :key="status"
        type="button"
        :aria-pressed="current === status"
        class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full border px-4 text-sm font-medium"
        :class="current === status ? 'border-accent bg-accent text-on-accent' : 'border-line bg-surface text-ink-muted'"
        @click="select(status)"
      >
        {{ $t(`status.${status}`) }}
        <span class="tabular-nums opacity-80">{{ library.byStatus[status].length }}</span>
      </button>
    </div>
  </div>

  <div v-if="library.loaded && library.items.length" class="mb-4 flex flex-col gap-3 sm:flex-row">
    <div class="flex flex-1 flex-col gap-1.5">
      <label for="library-search" class="text-sm font-medium">{{ $t('library.search.label') }}</label>
      <div class="relative">
        <input
          id="library-search"
          v-model="draft"
          type="search"
          enterkeyhint="search"
          autocomplete="off"
          maxlength="100"
          :placeholder="$t('library.search.placeholder')"
          class="min-h-11 w-full rounded-lg border border-line bg-surface py-0 pl-3 pr-11 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 [&::-webkit-search-cancel-button]:appearance-none"
        >
        <button
          v-if="draft"
          type="button"
          :aria-label="$t('library.search.clear')"
          class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-ink-muted"
          @click="clearSearch"
        >
          <span aria-hidden="true">✕</span>
        </button>
      </div>
    </div>
    <div class="flex flex-col gap-1.5 sm:w-56">
      <label for="library-sort" class="text-sm font-medium">{{ $t('library.sort.label') }}</label>
      <select
        id="library-sort"
        :value="sort"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
        @change="onSort"
      >
        <option v-for="option in librarySorts" :key="option" :value="option">{{ $t(`library.sort.${option}`) }}</option>
      </select>
    </div>
  </div>

  <ErrorNotice v-if="failed" :message="$t('apiErrors.generic')" retryable @retry="load(true)" />

  <SkeletonList v-else-if="!library.loaded" />

  <ul v-else-if="visible.length" class="flex flex-col gap-3">
    <li v-for="userBook in visible" :key="userBook.id">
      <BookCard :user-book="userBook" />
    </li>
  </ul>

  <EmptyState
    v-else-if="library.byStatus[current].length"
    :title="$t('library.search.noResults', { query: applied })"
    :text="$t('library.search.noResultsText')"
  >
    <button
      type="button"
      class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
      @click="clearSearch"
    >
      {{ $t('library.search.clear') }}
    </button>
  </EmptyState>

  <EmptyState v-else :title="$t(`library.empty.${current}.title`)" :text="$t(`library.empty.${current}.text`)">
    <RouterLink
      v-if="current === 'want'"
      :to="{ name: 'library-add' }"
      class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
    >
      {{ $t('library.add') }}
    </RouterLink>
  </EmptyState>

  <RouterLink :to="{ name: 'library-import' }" class="mt-6 flex min-h-11 items-center justify-center text-sm font-medium text-accent underline">
    {{ $t('library.import') }}
  </RouterLink>
</template>
