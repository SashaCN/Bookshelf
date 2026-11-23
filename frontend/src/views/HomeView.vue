<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DailyQuoteCard from '@/components/DailyQuoteCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import ProgressBar from '@/components/ProgressBar.vue'
import ReadingCard from '@/components/ReadingCard.vue'
import { useLibraryStore } from '@/stores/library'
import { useReaderStore } from '@/stores/reader'

const links = [
  { name: 'feed', title: 'home.feedLink', hint: 'home.feedHint' },
  { name: 'reader', title: 'home.readerLink', hint: 'home.readerHint' },
]

const library = useLibraryStore()
const reader = useReaderStore()
const failed = ref(false)

// The list comes most recently read first, so the first read book is the one to continue.
const continuing = computed(() => reader.items.find((doc) => doc.last_read_at !== null) ?? null)

async function load(force = false) {
  failed.value = false
  try {
    await library.load(force)
  } catch {
    failed.value = true
  }
}

// A convenience card: it never blocks the screen and a failure just means no card.
async function loadReader() {
  try {
    await reader.load()
  } catch {
    // Silent on purpose.
  }
}

onMounted(() => {
  void loadReader()
  return load()
})
</script>

<template>
  <PageHeader :title="$t('home.title')" />

  <ErrorNotice v-if="failed" :message="$t('apiErrors.generic')" retryable @retry="load(true)" />

  <p v-else-if="!library.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <ul v-else-if="library.byStatus.reading.length" class="flex flex-col gap-3">
    <li v-for="userBook in library.byStatus.reading" :key="userBook.id">
      <ReadingCard :user-book="userBook" />
    </li>
  </ul>

  <!-- An EPUB in progress already answers "what am I reading", so the library nudge would contradict it. -->
  <EmptyState v-else-if="!continuing" :title="$t('home.emptyTitle')" :text="$t('home.emptyText')">
    <RouterLink
      :to="{ name: 'library' }"
      class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
    >
      {{ $t('home.goToLibrary') }}
    </RouterLink>
  </EmptyState>

  <DailyQuoteCard />

  <RouterLink
    v-if="continuing"
    :to="{ name: 'reader-view', params: { id: continuing.id } }"
    class="mt-4 block rounded-2xl border border-line bg-surface p-4"
  >
    <p class="text-sm font-semibold text-ink-muted">{{ $t('home.continueReading') }}</p>
    <p class="mt-1 font-display text-lg font-semibold">{{ continuing.title }}</p>
    <p v-if="continuing.author" class="text-sm text-ink-muted">{{ continuing.author }}</p>
    <div class="mt-3 flex items-center gap-3">
      <div class="flex-1">
        <ProgressBar :percent="continuing.progress" :label="$t('reader.progress')" />
      </div>
      <span class="text-sm tabular-nums text-ink-muted">{{ Math.round(continuing.progress) }}%</span>
    </div>
    <p class="mt-1 text-sm text-ink-muted">
      {{ $t('reader.chapter', { n: Math.min(continuing.position.chapter + 1, continuing.chapters_count), total: continuing.chapters_count }) }}
    </p>
  </RouterLink>

  <div class="mt-4 grid grid-cols-2 gap-3">
    <RouterLink
      v-for="link in links"
      :key="link.name"
      :to="{ name: link.name }"
      class="flex min-h-11 flex-col gap-1 rounded-2xl border border-line bg-surface p-4"
    >
      <span class="font-semibold">{{ $t(link.title) }}</span>
      <span class="text-sm text-ink-muted">{{ $t(link.hint) }}</span>
    </RouterLink>
  </div>
</template>
