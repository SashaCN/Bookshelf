<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { libraryApi } from '@/api/library'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import type { ReadingLog } from '@/types/api'
import { formatDay, signed } from '@/utils/format'
import ErrorNotice from './ErrorNotice.vue'

const props = defineProps<{
  bookId: number
  /** Changes whenever the journal may have a new entry, so it loads again. */
  version: number
}>()

const errorMessage = useApiErrorMessage()

const entries = ref<ReadingLog[]>([])
const hasMore = ref(false)
const page = ref(1)
const loaded = ref(false)
const loading = ref(false)
const error = ref('')

async function load(pageToLoad = 1) {
  loading.value = true
  error.value = ''

  try {
    const result = await libraryApi.logs(props.bookId, pageToLoad)
    entries.value = pageToLoad === 1 ? result.data : [...entries.value, ...result.data]
    hasMore.value = result.links.next !== null
    page.value = pageToLoad
    loaded.value = true
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
watch([() => props.bookId, () => props.version], () => load())
</script>

<template>
  <section class="flex flex-col gap-2">
    <h2 class="text-sm font-medium">{{ $t('book.journal.title') }}</h2>

    <ErrorNotice v-if="error" :message="error" retryable @retry="load(page)" />

    <p v-else-if="loaded && !entries.length" class="text-sm text-ink-muted">{{ $t('book.journal.empty') }}</p>

    <ul v-else-if="entries.length" class="flex flex-col divide-y divide-line rounded-xl border border-line bg-surface">
      <li v-for="entry in entries" :key="entry.id" class="flex items-center justify-between gap-3 px-3 py-2.5">
        <div class="min-w-0">
          <p class="font-medium">{{ formatDay(entry.logged_on) }}</p>
          <p class="text-sm tabular-nums text-ink-muted">
            {{ $t('book.journal.range', { from: entry.from_page, to: entry.to_page }) }}
          </p>
        </div>
        <p class="shrink-0 font-semibold tabular-nums" :class="entry.pages < 0 ? 'text-danger' : 'text-accent'">
          {{ $t('book.journal.pages', { count: signed(entry.pages) }) }}
        </p>
      </li>
    </ul>

    <button
      v-if="hasMore"
      type="button"
      :disabled="loading"
      class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
      @click="load(page + 1)"
    >
      {{ $t('book.journal.more') }}
    </button>
  </section>
</template>
