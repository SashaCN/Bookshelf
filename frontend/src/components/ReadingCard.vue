<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import type { UserBook } from '@/types/api'
import { signed } from '@/utils/format'
import BookCover from './BookCover.vue'
import ErrorNotice from './ErrorNotice.vue'
import PageJumpForm from './PageJumpForm.vue'
import ProgressBar from './ProgressBar.vue'

/** How long the "+18 стор." note stays after an update. */
const FEEDBACK_MS = 3500
const QUICK_STEPS = [10, 25]

const props = defineProps<{ userBook: UserBook }>()

const { t } = useI18n()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const editing = ref(false)
const busy = ref(false)
const error = ref('')
const feedback = ref('')
let gained = 0
let feedbackTimer: ReturnType<typeof setTimeout> | undefined

const total = computed(() => props.userBook.total_pages ?? 0)
const atEnd = computed(() => total.value > 0 && props.userBook.current_page >= total.value)
const authors = computed(() => props.userBook.book.authors.join(', '))

/** A streak of one day is not worth mentioning; from two days on it is encouragement. */
const MIN_STREAK_TO_SHOW = 2

/** Taps in a row add up to one note ("+35 стор. · 🔥 5"), instead of flashing one note per tap. */
function showFeedback(pages: number, streak: number) {
  if (pages === 0) return

  gained += pages
  feedback.value = [
    t('progress.gained', { count: signed(gained) }),
    streak >= MIN_STREAK_TO_SHOW ? t('progress.streak', { days: streak }) : null,
  ]
    .filter(Boolean)
    .join(' · ')
  clearTimeout(feedbackTimer)
  feedbackTimer = setTimeout(() => {
    gained = 0
    feedback.value = ''
  }, FEEDBACK_MS)
}

async function moveTo(page: number) {
  error.value = ''

  try {
    const { meta } = await library.setProgress(props.userBook.id, Math.min(Math.max(page, 0), total.value))
    editing.value = false
    showFeedback(meta.pages, meta.streak)
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function finish() {
  busy.value = true
  error.value = ''

  try {
    await library.update(props.userBook.id, { status: 'finished' })
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

onBeforeUnmount(() => clearTimeout(feedbackTimer))
</script>

<template>
  <article class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-3">
    <RouterLink
      :to="{ name: 'book', params: { id: userBook.id } }"
      class="grid grid-cols-[3.5rem_1fr] gap-3"
    >
      <BookCover :title="userBook.book.title" :cover-url="userBook.book.cover_url" />

      <div class="flex min-w-0 flex-col justify-center gap-1">
        <p class="truncate font-semibold leading-snug">{{ userBook.book.title }}</p>
        <p v-if="authors" class="truncate text-sm text-ink-muted">{{ authors }}</p>
        <ProgressBar :percent="userBook.progress_percent" :label="t('book.progress')" class="mt-1" />
        <p class="flex items-center justify-between gap-2 text-xs tabular-nums text-ink-muted">
          <span>{{ t('book.pagesOf', { current: userBook.current_page, total }) }}</span>
          <span aria-live="polite" class="font-semibold text-accent">{{ feedback }}</span>
          <span class="ml-auto">{{ userBook.progress_percent !== null ? `${userBook.progress_percent}%` : '' }}</span>
        </p>
      </div>
    </RouterLink>

    <ErrorNotice v-if="error" :message="error" />

    <div v-if="atEnd" class="flex flex-col gap-2">
      <p class="text-sm">{{ t('progress.reachedEnd') }}</p>
      <button
        type="button"
        :disabled="busy"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
        @click="finish"
      >
        {{ t('progress.markFinished') }}
      </button>
    </div>

    <PageJumpForm
      v-else-if="editing"
      :id="`page-${userBook.id}`"
      :label="t('progress.pageLabel')"
      :current="userBook.current_page"
      :total="total"
      hide-label
      dismissable
      @submit="moveTo"
      @cancel="editing = false"
    />

    <div v-else class="flex gap-2">
      <button
        v-for="step in QUICK_STEPS"
        :key="step"
        type="button"
        class="min-h-11 min-w-14 rounded-lg border border-line bg-surface px-3 font-semibold tabular-nums active:bg-sunken"
        @click="moveTo(userBook.current_page + step)"
      >
        +{{ step }}
      </button>
      <button
        type="button"
        class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-3 font-medium active:bg-sunken"
        @click="editing = true"
      >
        {{ t('progress.jump') }}
      </button>
    </div>
  </article>
</template>
