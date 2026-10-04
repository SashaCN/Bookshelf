<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { UserBook } from '@/types/api'
import BookCover from './BookCover.vue'
import ProgressBar from './ProgressBar.vue'
import RatingStars from './RatingStars.vue'

const props = defineProps<{ userBook: UserBook }>()
const { t } = useI18n()

const authors = computed(() => props.userBook.book.authors.join(', '))
const showsProgress = computed(
  () => props.userBook.status === 'reading' || props.userBook.status === 'abandoned',
)
</script>

<template>
  <RouterLink
    :to="{ name: 'book', params: { id: userBook.id } }"
    class="grid grid-cols-[3.5rem_1fr] gap-3 rounded-2xl border border-line bg-surface p-3 active:bg-sunken"
  >
    <BookCover :title="userBook.book.title" :cover-url="userBook.book.cover_url" />

    <div class="flex min-w-0 flex-col justify-center gap-1">
      <p class="truncate font-semibold leading-snug">{{ userBook.book.title }}</p>
      <p v-if="authors" class="truncate text-sm text-ink-muted">{{ authors }}</p>

      <template v-if="showsProgress && userBook.total_pages">
        <ProgressBar :percent="userBook.progress_percent" :label="t('book.progress')" class="mt-1" />
        <p class="text-xs tabular-nums text-ink-muted">
          {{ t('book.pagesOf', { current: userBook.current_page, total: userBook.total_pages }) }}
        </p>
      </template>

      <RatingStars
        v-else-if="userBook.status === 'finished' && userBook.rating"
        :model-value="userBook.rating"
        :label="t('book.rating')"
        readonly
        class="mt-1"
      />
    </div>
  </RouterLink>
</template>
