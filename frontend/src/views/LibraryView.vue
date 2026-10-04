<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BookCard from '@/components/BookCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useLibraryStore } from '@/stores/library'
import type { BookStatus } from '@/types/api'

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

function select(status: BookStatus) {
  router.replace({ name: 'library', query: { status } })
}

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
        class="inline-flex min-h-10 items-center rounded-lg bg-accent px-3 text-sm font-semibold text-on-accent"
      >
        {{ $t('library.add') }}
      </RouterLink>
    </template>
  </PageHeader>

  <div class="-mx-4 mb-4 overflow-x-auto px-4">
    <div role="tablist" :aria-label="$t('library.title')" class="flex gap-2">
      <button
        v-for="status in statuses"
        :key="status"
        type="button"
        role="tab"
        :aria-selected="current === status"
        class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full border px-4 text-sm font-medium"
        :class="current === status ? 'border-accent bg-accent text-on-accent' : 'border-line bg-surface text-ink-muted'"
        @click="select(status)"
      >
        {{ $t(`status.${status}`) }}
        <span class="tabular-nums opacity-80">{{ library.byStatus[status].length }}</span>
      </button>
    </div>
  </div>

  <ErrorNotice v-if="failed" :message="$t('apiErrors.generic')" retryable @retry="load(true)" />

  <p v-else-if="!library.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <ul v-else-if="library.byStatus[current].length" class="flex flex-col gap-3">
    <li v-for="userBook in library.byStatus[current]" :key="userBook.id">
      <BookCard :user-book="userBook" />
    </li>
  </ul>

  <EmptyState v-else :title="$t(`library.empty.${current}.title`)" :text="$t(`library.empty.${current}.text`)">
    <RouterLink
      v-if="current === 'want'"
      :to="{ name: 'library-add' }"
      class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
    >
      {{ $t('library.add') }}
    </RouterLink>
  </EmptyState>
</template>
