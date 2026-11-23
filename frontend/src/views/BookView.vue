<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import BookCover from '@/components/BookCover.vue'
import BookQuotes from '@/components/BookQuotes.vue'
import BookReviews from '@/components/BookReviews.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import PageJumpForm from '@/components/PageJumpForm.vue'
import ProgressBar from '@/components/ProgressBar.vue'
import RatingStars from '@/components/RatingStars.vue'
import BookTextBlock from '@/components/reader/BookTextBlock.vue'
import ReadingJournal from '@/components/ReadingJournal.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useAuthStore } from '@/stores/auth'
import { useLibraryStore } from '@/stores/library'
import { formatMoment } from '@/utils/format'
import type { BookStatus, ReadPass, UserBook } from '@/types/api'

const props = defineProps<{ id: number }>()

const { t } = useI18n()
const router = useRouter()
const library = useLibraryStore()
const auth = useAuthStore()
const errorMessage = useApiErrorMessage()

const userBook = ref<UserBook | null>(null)
const notFound = ref(false)
const error = ref('')
const busy = ref(false)
const pagesInput = ref('')
const confirmingDelete = ref(false)
const confirmingReread = ref(false)
/** Bumped whenever the reading journal may have a new entry. */
const journalVersion = ref(0)

/** Earlier finished passes; the current one, when finished, is not among them. */
const archivedCount = computed(() => (userBook.value?.times_read ?? 0) - (userBook.value?.status === 'finished' ? 1 : 0))
/**
 * The list of the library only counts the passes, the single entry has them in full; so they are fetched here once
 * the count says there are any, and kept apart from the entry, which later updates replace without them.
 */
const reads = ref<ReadPass[]>([])

watch(
  () => [userBook.value?.id, archivedCount.value] as const,
  async ([id, count]) => {
    if (id === undefined || count <= 0) {
      reads.value = []
      return
    }
    if (userBook.value?.reads) {
      reads.value = userBook.value.reads
      return
    }
    try {
      const full = await libraryApi.get(id)
      if (userBook.value?.id === id) reads.value = full.reads ?? []
    } catch {
      reads.value = []
    }
  },
  { immediate: true },
)

const book = computed(() => userBook.value?.book ?? null)
const needsPages = computed(() => userBook.value !== null && !userBook.value.total_pages)

async function load() {
  notFound.value = false
  error.value = ''

  try {
    await library.load()
    userBook.value = library.find(props.id) ?? (await libraryApi.get(props.id))
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true
    } else {
      error.value = errorMessage(e)
    }
  }
}

onMounted(load)
watch(() => props.id, () => {
  confirmingReread.value = false
  return load()
})

// Keep the page in sync with the store, which receives every saved change.
watch(
  () => library.find(props.id),
  (fresh) => {
    if (fresh) userBook.value = fresh
  },
)

watch(
  userBook,
  (value) => {
    pagesInput.value = value?.total_pages ? String(value.total_pages) : ''
  },
  { immediate: true },
)

async function save(payload: Parameters<typeof library.update>[1]) {
  if (!userBook.value) return

  busy.value = true
  error.value = ''

  try {
    userBook.value = await library.update(userBook.value.id, payload)
    if (payload.status) journalVersion.value++
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

async function saveProgress(page: number) {
  if (!userBook.value) return

  busy.value = true
  error.value = ''

  try {
    await library.setProgress(userBook.value.id, page)
    journalVersion.value++
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

function changeStatus(status: BookStatus) {
  return save({ status })
}

function savePages() {
  const pages = Number.parseInt(pagesInput.value, 10)
  if (Number.isFinite(pages)) {
    return save({ total_pages: pages })
  }
}

async function reread() {
  if (!userBook.value) return

  busy.value = true
  error.value = ''

  try {
    // The answer is the full entry, so the previous passes are shown at once.
    userBook.value = await library.reread(userBook.value.id)
    journalVersion.value++
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    confirmingReread.value = false
    busy.value = false
  }
}

/** "5 жовтня" for a pass moment (an ISO timestamp): the calendar day in the reader's own time zone. */
function passDay(iso: string): string {
  return formatMoment(iso, auth.user?.timezone)
}

async function remove() {
  if (!userBook.value) return

  busy.value = true
  try {
    await library.remove(userBook.value.id)
    await router.replace({ name: 'library' })
  } catch (e) {
    error.value = errorMessage(e)
    confirmingDelete.value = false
  } finally {
    busy.value = false
  }
}

/** A short label for moving from the current status to `target`. */
function actionLabel(target: BookStatus): string {
  return t(`book.actions.${userBook.value?.status}_${target}`)
}

/** Starting to read needs a known page count. */
function isDisabled(target: BookStatus): boolean {
  return busy.value || (target === 'reading' && needsPages.value)
}
</script>

<template>
  <PageHeader :title="book?.title ?? $t('book.title')" :back-to="{ name: 'library' }" />

  <p v-if="notFound" class="py-10 text-center text-ink-muted">{{ $t('book.notFound') }}</p>

  <ErrorNotice v-else-if="error && !userBook" :message="error" retryable @retry="load" />

  <SkeletonList v-else-if="!userBook || !book" :count="2" tall />

  <div v-else class="flex flex-col gap-6">
    <ErrorNotice v-if="error" :message="error" />

    <section class="grid grid-cols-[6rem_1fr] gap-4">
      <BookCover :title="book.title" :cover-url="book.cover_url" />
      <div class="flex min-w-0 flex-col justify-center gap-1">
        <p v-if="book.subtitle" class="text-ink-muted">{{ book.subtitle }}</p>
        <p v-if="book.authors.length" class="font-medium">{{ book.authors.join(', ') }}</p>
        <p class="text-sm text-ink-muted">
          {{ [book.published_year, book.source === 'manual' ? t('book.addedByHand') : null].filter(Boolean).join(' · ') }}
        </p>
        <p class="mt-1">
          <span class="inline-flex rounded-full bg-sunken px-3 py-1 text-sm font-medium">
            {{ $t(`status.${userBook.status}`) }}
          </span>
        </p>
      </div>
    </section>

    <section v-if="userBook.total_pages && userBook.status !== 'want'" class="flex flex-col gap-2">
      <ProgressBar :percent="userBook.progress_percent" :label="$t('book.progress')" />
      <p class="text-sm tabular-nums text-ink-muted">
        {{ $t('book.pagesOf', { current: userBook.current_page, total: userBook.total_pages }) }}
      </p>
    </section>

    <section v-if="userBook.total_pages && userBook.status !== 'finished'" class="flex flex-col gap-1.5">
      <PageJumpForm
        id="current-page"
        :label="$t('progress.pageLabel')"
        :current="userBook.current_page"
        :total="userBook.total_pages"
        :busy="busy"
        @submit="saveProgress"
      />
      <p v-if="userBook.status !== 'reading'" class="text-sm text-ink-muted">{{ $t('progress.startHint') }}</p>
    </section>

    <BookTextBlock :user-book="userBook" />

    <section v-if="userBook.allowed_statuses.length" class="flex flex-col gap-2">
      <button
        v-for="(target, index) in userBook.allowed_statuses"
        :key="target"
        type="button"
        :disabled="isDisabled(target)"
        class="min-h-11 rounded-lg px-4 font-semibold disabled:opacity-50"
        :class="index === 0 ? 'bg-accent text-on-accent' : 'border border-line bg-surface'"
        @click="changeStatus(target)"
      >
        {{ actionLabel(target) }}
      </button>
      <p v-if="needsPages && userBook.allowed_statuses.includes('reading')" class="text-sm text-ink-muted">
        {{ $t('book.needsPages') }}
      </p>
    </section>

    <section v-if="userBook.status === 'finished'" class="flex flex-col gap-2">
      <button
        v-if="!confirmingReread"
        type="button"
        :disabled="busy"
        class="min-h-11 rounded-lg border border-line bg-surface px-4 font-semibold disabled:opacity-50"
        @click="confirmingReread = true"
      >
        {{ $t('book.reread.action') }}
      </button>
      <div v-else class="flex flex-col gap-2">
        <p class="text-sm">{{ $t('book.reread.confirm') }}</p>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="busy"
            class="min-h-11 flex-1 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
            @click="reread"
          >
            {{ $t('book.reread.yes') }}
          </button>
          <button
            type="button"
            :disabled="busy"
            class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium"
            @click="confirmingReread = false"
          >
            {{ $t('common.cancel') }}
          </button>
        </div>
      </div>
    </section>

    <section v-if="userBook.times_read > 0" class="flex flex-col gap-1">
      <p class="text-sm font-medium">{{ $t('book.reread.timesRead', { n: userBook.times_read }, userBook.times_read) }}</p>
      <template v-if="reads.length">
        <p class="text-sm text-ink-muted">{{ $t('book.reread.previous') }}</p>
        <ul class="flex flex-col gap-0.5 text-sm tabular-nums text-ink-muted">
          <li v-for="(pass, index) in reads" :key="index">
            {{
              pass.started_at
                ? $t('book.reread.pass', { from: passDay(pass.started_at), to: passDay(pass.finished_at) })
                : $t('book.reread.noStart', { to: passDay(pass.finished_at) })
            }}
          </li>
        </ul>
      </template>
    </section>

    <form class="flex flex-col gap-1.5" @submit.prevent="savePages">
      <label for="total-pages" class="text-sm font-medium">{{ $t('book.totalPages') }}</label>
      <div class="flex gap-2">
        <input
          id="total-pages"
          v-model="pagesInput"
          type="number"
          inputmode="numeric"
          :min="Math.max(1, userBook.status === 'finished' ? 1 : userBook.current_page)"
          max="20000"
          class="min-h-11 min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
        >
        <button
          type="submit"
          :disabled="busy || !pagesInput || Number(pagesInput) === userBook.total_pages"
          class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-50"
        >
          {{ $t('common.save') }}
        </button>
      </div>
    </form>

    <section class="flex flex-col gap-1">
      <p class="text-sm font-medium">{{ $t('book.rating') }}</p>
      <RatingStars
        :model-value="userBook.rating"
        :label="$t('book.rating')"
        @update:model-value="save({ rating: $event })"
      />
    </section>

    <BookQuotes :book-id="userBook.id" />

    <BookReviews :book-id="book.id" :user-book-id="userBook.id" />

    <ReadingJournal v-if="userBook.status !== 'want'" :book-id="userBook.id" :version="journalVersion" />

    <section class="border-t border-line pt-4">
      <button
        v-if="!confirmingDelete"
        type="button"
        class="min-h-11 text-sm font-medium text-danger"
        @click="confirmingDelete = true"
      >
        {{ $t('book.remove') }}
      </button>
      <div v-else class="flex flex-col gap-2">
        <p class="text-sm">{{ $t('book.removeConfirm') }}</p>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="busy"
            class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
            @click="remove"
          >
            {{ $t('book.removeYes') }}
          </button>
          <button
            type="button"
            class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium"
            @click="confirmingDelete = false"
          >
            {{ $t('common.cancel') }}
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
