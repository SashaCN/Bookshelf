<script setup lang="ts">
import { onMounted, ref } from 'vue'
import DailyQuoteCard from '@/components/DailyQuoteCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import ReadingCard from '@/components/ReadingCard.vue'
import { useLibraryStore } from '@/stores/library'

const library = useLibraryStore()
const failed = ref(false)

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
  <PageHeader :title="$t('home.title')" />

  <ErrorNotice v-if="failed" :message="$t('apiErrors.generic')" retryable @retry="load(true)" />

  <p v-else-if="!library.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <ul v-else-if="library.byStatus.reading.length" class="flex flex-col gap-3">
    <li v-for="userBook in library.byStatus.reading" :key="userBook.id">
      <ReadingCard :user-book="userBook" />
    </li>
  </ul>

  <EmptyState v-else :title="$t('home.emptyTitle')" :text="$t('home.emptyText')">
    <RouterLink
      :to="{ name: 'library' }"
      class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
    >
      {{ $t('home.goToLibrary') }}
    </RouterLink>
  </EmptyState>

  <DailyQuoteCard />
</template>
