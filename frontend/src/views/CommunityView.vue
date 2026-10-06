<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { socialApi } from '@/api/social'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import PostCard from '@/components/PostCard.vue'
import ReaderListItem from '@/components/ReaderListItem.vue'
import SegmentedControl from '@/components/SegmentedControl.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useFeedStore } from '@/stores/feed'
import { useLibraryStore } from '@/stores/library'
import type { FeedScope, Reader } from '@/types/social'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const feed = useFeedStore()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const moreError = ref('')

// The scope and the search live in the URL, so going back from a profile or a post returns to the same screen.
const scope = computed<FeedScope>(() => (route.query.scope === 'all' ? 'all' : 'following'))
/** At least this many characters (after the "@" of "@handle") make a search: one letter would list half of the readers. */
const MIN_SEARCH_LENGTH = 2

/** What a box or an address holds, as it is searched for: without the "@", and nothing at all when it is too short. */
function searchTerm(text: string): string {
  const term = text.trim().replace(/^@+/, '')
  return term.length >= MIN_SEARCH_LENGTH ? term : ''
}

const searchQuery = computed(() => (typeof route.query.q === 'string' ? searchTerm(route.query.q) : ''))
const searching = computed(() => searchQuery.value !== '')

const scopeOptions = computed(() => [
  { value: 'following' as const, label: t('social.scope.following') },
  { value: 'all' as const, label: t('social.scope.all') },
])

function goTo(next: { scope?: FeedScope; q?: string }) {
  const query: Record<string, string> = {}
  if (next.scope === 'all') query.scope = 'all'
  if (next.q) query.q = next.q
  router.replace({ name: 'community', query })
}

const scopeModel = computed({
  get: () => scope.value,
  set: (value: FeedScope) => goTo({ scope: value, q: searchQuery.value }),
})

async function loadFeed() {
  error.value = ''
  moreError.value = ''

  try {
    await feed.load(scope.value)
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function loadMore() {
  moreError.value = ''

  try {
    await feed.loadMore()
  } catch (e) {
    moreError.value = errorMessage(e)
  }
}

watch(
  scope,
  () => {
    // While the reader leaves for another screen the route already holds that screen's query.
    if (route.name === 'community') loadFeed()
  },
  { immediate: true },
)

// The search box is a draft until it is submitted.
const draft = ref(searchQuery.value)
const results = ref<Reader[]>([])
const resultsHasMore = ref(false)
const resultsPage = ref(1)
const resultsLoaded = ref(false)
const resultsLoading = ref(false)
const searchError = ref('')
const searchMoreError = ref('')
let searchGeneration = 0

async function search(page = 1) {
  const query = searchQuery.value
  const current = ++searchGeneration

  if (page === 1) {
    results.value = []
    resultsHasMore.value = false
    resultsLoaded.value = false
  }

  searchError.value = ''
  searchMoreError.value = ''
  resultsLoading.value = true

  try {
    const result = await socialApi.searchReaders(query, page)
    if (current !== searchGeneration) return
    const known = new Set(results.value.map((reader) => reader.handle))
    results.value = [...results.value, ...result.data.filter((reader) => !known.has(reader.handle))]
    resultsHasMore.value = result.links.next !== null
    resultsPage.value = page
    resultsLoaded.value = true
  } catch (e) {
    if (current === searchGeneration) {
      if (page === 1) searchError.value = errorMessage(e)
      else searchMoreError.value = errorMessage(e)
    }
  } finally {
    if (current === searchGeneration) resultsLoading.value = false
  }
}

const draftTerm = computed(() => searchTerm(draft.value))
/** The box has something, but too little to search for. */
const draftTooShort = computed(() => draft.value.trim() !== '' && draftTerm.value === '')

function submitSearch() {
  if (draftTerm.value === '') return

  goTo({ scope: scope.value, q: draftTerm.value })
}

function clearSearch() {
  draft.value = ''
  goTo({ scope: scope.value })
}

watch(
  searchQuery,
  (query) => {
    draft.value = query
    if (route.name !== 'community') return
    if (query) {
      search()
    } else {
      searchGeneration++
    }
  },
  { immediate: true },
)

onMounted(() => {
  // Only feeds the links from the books of a post to the library: without it they are simply not links.
  library.load().catch(() => undefined)
})
</script>

<template>
  <PageHeader :title="$t('social.title')">
    <template #action>
      <RouterLink
        :to="{ name: 'community-new' }"
        class="inline-flex min-h-10 items-center rounded-lg bg-accent px-3 text-sm font-semibold text-on-accent"
      >
        {{ $t('social.write') }}
      </RouterLink>
    </template>
  </PageHeader>

  <form role="search" class="mb-4 flex gap-2" @submit.prevent="submitSearch">
    <label for="reader-search" class="sr-only">{{ $t('social.search.label') }}</label>
    <input
      id="reader-search"
      v-model="draft"
      type="search"
      maxlength="50"
      autocomplete="off"
      :aria-describedby="draftTooShort ? 'reader-search-hint' : undefined"
      :placeholder="$t('social.search.placeholder')"
      class="min-h-11 min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
    >
    <button
      type="submit"
      :disabled="draftTerm === ''"
      class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
    >
      {{ $t('social.search.submit') }}
    </button>
  </form>
  <p v-if="draftTooShort" id="reader-search-hint" class="-mt-2 mb-4 text-sm text-ink-muted">{{ $t('social.search.tooShort') }}</p>

  <section v-if="searching" :aria-label="$t('social.search.results')" class="flex flex-col gap-3">
    <button type="button" class="self-start text-sm font-medium text-accent underline underline-offset-2" @click="clearSearch">
      {{ $t('social.search.clear') }}
    </button>

    <ErrorNotice v-if="searchError" :message="searchError" retryable @retry="search()" />

    <SkeletonList v-else-if="!resultsLoaded" :count="2" />

    <template v-else>
      <ul v-if="results.length" class="flex flex-col gap-3">
        <li v-for="reader in results" :key="reader.handle">
          <ReaderListItem :reader="reader" />
        </li>
      </ul>

      <EmptyState v-else :title="$t('social.search.empty')" :text="$t('social.search.emptyText')" />

      <ErrorNotice v-if="searchMoreError" :message="searchMoreError" retryable @retry="search(resultsPage + 1)" />

      <button
        v-if="resultsHasMore && !searchMoreError"
        type="button"
        :disabled="resultsLoading"
        class="min-h-11 w-full rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
        @click="search(resultsPage + 1)"
      >
        {{ $t('social.more') }}
      </button>
    </template>
  </section>

  <template v-else>
    <SegmentedControl v-model="scopeModel" :label="$t('social.scope.label')" :options="scopeOptions" hide-label class="mb-4" />

    <ErrorNotice v-if="error" :message="error" retryable @retry="loadFeed" />

    <SkeletonList v-else-if="!feed.loaded" />

    <template v-else>
      <ul v-if="feed.items.length" class="flex flex-col gap-3">
        <li v-for="post in feed.items" :key="post.id">
          <PostCard :post="post" />
        </li>
      </ul>

      <EmptyState
        v-else-if="scope === 'following'"
        :title="$t('social.feed.emptyFollowing.title')"
        :text="$t('social.feed.emptyFollowing.text')"
      >
        <button
          type="button"
          class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent"
          @click="goTo({ scope: 'all' })"
        >
          {{ $t('social.feed.emptyFollowing.action') }}
        </button>
      </EmptyState>

      <EmptyState v-else :title="$t('social.feed.emptyAll.title')" :text="$t('social.feed.emptyAll.text')">
        <RouterLink
          :to="{ name: 'community-new' }"
          class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
        >
          {{ $t('social.write') }}
        </RouterLink>
      </EmptyState>

      <ErrorNotice v-if="moreError" :message="moreError" retryable class="mt-4" @retry="loadMore" />

      <button
        v-if="feed.hasMore && !moreError"
        type="button"
        :disabled="feed.loadingMore"
        class="mt-4 min-h-11 w-full rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
        @click="loadMore"
      >
        {{ $t('social.more') }}
      </button>
    </template>
  </template>
</template>
