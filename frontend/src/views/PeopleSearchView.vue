<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { socialApi } from '@/api/social'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import type { PersonSearchResult } from '@/types/api'

const SEARCH_DELAY_MS = 300
const MIN_QUERY_LENGTH = 2

const route = useRoute()
const router = useRouter()
const errorMessage = useApiErrorMessage()

const initial = typeof route.query.q === 'string' ? route.query.q : ''
const query = ref(initial)
const results = ref<PersonSearchResult[]>([])
const state = ref<'idle' | 'loading' | 'done' | 'failed'>('idle')
const searchError = ref('')
const followError = ref('')
const pending = ref(new Set<string>())
const input = ref<HTMLInputElement | null>(null)

let timer: ReturnType<typeof setTimeout> | undefined
let searchId = 0

function schedule(value: string, delay: number) {
  clearTimeout(timer)
  searchId++
  followError.value = ''

  if (value.trim().length < MIN_QUERY_LENGTH) {
    results.value = []
    state.value = 'idle'
    return
  }

  state.value = 'loading'
  if (delay === 0) runSearch(value.trim())
  else timer = setTimeout(() => runSearch(value.trim()), delay)
}

watch(query, (value) => {
  schedule(value, SEARCH_DELAY_MS)
  // Keep the text in the URL so that coming back restores it; replace, so typing does not fill the history.
  const q = value.trim()
  router.replace({ query: q ? { q } : {} })
})

async function runSearch(text: string) {
  const id = ++searchId
  state.value = 'loading'

  try {
    const found = await socialApi.search(text)
    // A newer keystroke has started another search: this answer is already stale.
    if (id !== searchId) return
    results.value = found
    state.value = 'done'
  } catch (e) {
    if (id !== searchId) return
    results.value = []
    searchError.value = errorMessage(e)
    state.value = 'failed'
  }
}

function retry() {
  const text = query.value.trim()
  if (text.length >= MIN_QUERY_LENGTH) runSearch(text)
}

async function toggleFollow(person: PersonSearchResult) {
  if (pending.value.has(person.username)) return

  const was = person.is_following
  followError.value = ''
  pending.value = new Set(pending.value).add(person.username)
  person.is_following = !was

  try {
    await (was ? socialApi.unfollow(person.username) : socialApi.follow(person.username))
  } catch (e) {
    person.is_following = was
    followError.value = errorMessage(e)
  } finally {
    const next = new Set(pending.value)
    next.delete(person.username)
    pending.value = next
  }
}

onMounted(() => {
  input.value?.focus()
  // Restored from the URL: no need to wait for the debounce.
  if (initial.trim().length >= MIN_QUERY_LENGTH) schedule(initial, 0)
})

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <PageHeader :title="$t('social.search.title')" :back-to="{ name: 'feed' }" />

  <section class="flex flex-col gap-4">
    <div class="flex flex-col gap-1.5">
      <label for="people-search" class="text-sm font-medium">{{ $t('social.search.label') }}</label>
      <input
        id="people-search"
        ref="input"
        v-model="query"
        type="search"
        autofocus
        enterkeyhint="search"
        autocomplete="off"
        maxlength="50"
        :placeholder="$t('social.search.placeholder')"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
    </div>

    <ErrorNotice v-if="followError" :message="followError" />

    <p v-if="state === 'idle'" class="text-ink-muted">{{ $t('social.search.hint') }}</p>

    <p v-else-if="state === 'loading'" class="text-ink-muted" role="status">{{ $t('social.search.searching') }}</p>

    <ErrorNotice v-else-if="state === 'failed'" :message="searchError" retryable @retry="retry" />

    <p v-else-if="!results.length" class="text-ink-muted" role="status">{{ $t('social.search.empty') }}</p>

    <ul v-else class="flex flex-col gap-2">
      <li
        v-for="person in results"
        :key="person.username"
        class="grid grid-cols-[1fr_auto] items-center gap-3 rounded-2xl border border-line bg-surface p-3"
      >
        <RouterLink
          :to="{ name: 'public-profile', params: { username: person.username } }"
          class="flex min-h-11 min-w-0 flex-col justify-center"
        >
          <span class="truncate font-medium">{{ person.name }}</span>
          <span class="truncate text-sm text-ink-muted">@{{ person.username }}</span>
        </RouterLink>

        <button
          type="button"
          :aria-pressed="person.is_following"
          :disabled="pending.has(person.username)"
          :class="[
            'min-h-11 rounded-lg px-3 text-sm font-semibold disabled:opacity-60',
            person.is_following ? 'border border-line bg-surface' : 'bg-accent text-on-accent',
          ]"
          @click="toggleFollow(person)"
        >
          {{ person.is_following ? $t('social.search.unfollow') : $t('social.search.follow') }}
        </button>
      </li>
    </ul>
  </section>
</template>
