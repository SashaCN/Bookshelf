<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { readerApi } from '@/api/reader'
import ErrorNotice from '@/components/ErrorNotice.vue'
import HighlightSheet from '@/components/reader/HighlightSheet.vue'
import ReaderChapter from '@/components/reader/ReaderChapter.vue'
import ReaderContents from '@/components/reader/ReaderContents.vue'
import ReaderSelectionBar from '@/components/reader/ReaderSelectionBar.vue'
import ReaderSettingsSheet from '@/components/reader/ReaderSettings.vue'
import ReaderTopBar from '@/components/reader/ReaderTopBar.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { usePositionSaver } from '@/composables/usePositionSaver'
import { useReaderSettings } from '@/composables/useReaderSettings'
import { useTextSelection } from '@/composables/useTextSelection'
import { useLibraryStore } from '@/stores/library'
import { useQuotesStore } from '@/stores/quotes'
import type { UserBook } from '@/types/api'
import type { BookText, Chapter, Highlight, PositionPayload } from '@/types/reader'
import '@/components/reader/reader.css'

/** The longest quote the server keeps. */
const MAX_QUOTE_LENGTH = 5000
const TOAST_MS = 4000
const FLASH_MS = 2500

/** The id of the library entry whose text is read. */
const props = defineProps<{ id: number }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const library = useLibraryStore()
const quotes = useQuotesStore()
const errorMessage = useApiErrorMessage()
const { settings, style, stepFontSize, stepLineHeight, reset: resetSettings } = useReaderSettings()

type Phase = 'loading' | 'ready' | 'failed' | 'notFound' | 'noText'

const phase = ref<Phase>('loading')
const loadError = ref('')
const userBook = ref<UserBook | null>(null)
const text = ref<BookText | null>(null)
const chapter = ref<Chapter | null>(null)
const chapterError = ref('')
/** The chapter the reader asked for and did not get, to ask for again. */
const failedPosition = ref<number | null>(null)
const loadingChapter = ref(false)
/** How far into the chapter the reader has scrolled, 0 to 1. */
const fraction = ref(0)

const scroller = useTemplateRef<HTMLElement>('scroller')
const content = useTemplateRef<InstanceType<typeof ReaderChapter>>('content')
const contentRoot = computed(() => content.value?.root ?? null)

type Sheet = 'contents' | 'settings' | null
const sheet = ref<Sheet>(null)

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value))
}

// ---------------------------------------------------------------- chapters

/** Chapters that are loaded or on their way, by position: the next one is fetched while the reader reads this one. */
const chapters = new Map<number, Promise<Chapter>>()
/** Bumped by every request to show a chapter, so that an answer for one the reader has left is dropped. */
let showing = 0

function fetchChapter(position: number): Promise<Chapter> {
  let pending = chapters.get(position)

  if (!pending) {
    pending = readerApi.chapter(text.value!.id, position)
    chapters.set(position, pending)
    pending.catch(() => chapters.delete(position))
  }

  return pending
}

/** Only the chapters next to the one that is read are kept: a chapter can be 2 MB, and a book has hundreds. */
function forgetFarChapters(near: number) {
  for (const position of chapters.keys()) {
    if (Math.abs(position - near) > 1) {
      chapters.delete(position)
    }
  }
}

function readFraction(): number {
  const element = scroller.value

  if (!element) return 0

  const range = element.scrollHeight - element.clientHeight

  return range > 0 ? clamp(element.scrollTop / range, 0, 1) : 0
}

function onScroll() {
  fraction.value = readFraction()
}

function scrollToFraction(value: number) {
  const element = scroller.value

  if (!element) return

  element.scrollTop = value * Math.max(0, element.scrollHeight - element.clientHeight)
  fraction.value = readFraction()
}

/** Scrolls to the highlight of a quote and makes it flash for a moment. Returns whether it was there. */
function showHighlight(id: number): boolean {
  const marks = contentRoot.value?.querySelectorAll<HTMLElement>(`mark[data-quote-id="${id}"]`)

  if (!marks?.length) return false

  marks[0]!.scrollIntoView({ block: 'center' })
  marks.forEach((mark) => mark.classList.add('reader-flash'))
  window.setTimeout(() => marks.forEach((mark) => mark.classList.remove('reader-flash')), FLASH_MS)
  fraction.value = readFraction()

  return true
}

/**
 * Shows a chapter, fetching it unless it is at hand, and puts the reader in it: at the quote `highlight`, or
 * at `at` (0 to 1) of the way down.
 */
async function showChapter(position: number, { at = 0, highlight }: { at?: number; highlight?: number } = {}): Promise<void> {
  const current = text.value

  if (!current) return

  const target = clamp(position, 0, current.chapters_count - 1)
  const request = ++showing

  loadingChapter.value = true
  chapterError.value = ''
  failedPosition.value = null

  try {
    const loaded = await fetchChapter(target)

    if (request !== showing) return

    chapter.value = loaded
    phase.value = 'ready'
    forgetFarChapters(target)
    await nextTick()

    if (request !== showing) return

    if (highlight === undefined || !showHighlight(highlight)) {
      scrollToFraction(at)
    }

    // The next chapter is ready by the time the reader gets to the end of this one.
    if (target + 1 < current.chapters_count) {
      void fetchChapter(target + 1).catch(() => undefined)
    }
  } catch (e) {
    if (request === showing) {
      chapterError.value = errorMessage(e)
      failedPosition.value = target
    }
  } finally {
    if (request === showing) {
      loadingChapter.value = false
    }
  }
}

async function goTo(position: number) {
  sheet.value = null

  if (chapter.value && position !== chapter.value.position) {
    // Going on means the chapter has been read to the end; going back leaves it where it was.
    const left = chapter.value.position
    void saver.flush({ chapter: left, chapter_progress: position > left ? 1 : readFraction() })
  }

  await showChapter(position)
}

// ---------------------------------------------------------------- position

function currentPosition(): PositionPayload | null {
  return phase.value === 'ready' && chapter.value ? { chapter: chapter.value.position, chapter_progress: readFraction() } : null
}

async function savePosition(position: PositionPayload, options?: { keepalive: true }) {
  if (!text.value) return

  const result = await (options ? readerApi.savePosition(text.value.id, position, options) : readerApi.savePosition(text.value.id, position))
  // The page of the book moves on with the reader.
  library.upsert(result.meta.user_book)
}

const saver = usePositionSaver({ read: currentPosition, save: savePosition })

/** How far into the whole book the reader is: the chapters before this one, and the part of this one that is behind. */
const overall = computed(() => {
  const current = text.value
  const shown = chapter.value

  if (!current || !shown || current.characters_count === 0) return 0

  let before = 0

  for (const summary of current.chapters) {
    if (summary.position >= shown.position) break
    before += summary.characters
  }

  return clamp((before + fraction.value * shown.characters) / current.characters_count, 0, 1)
})

const subtitle = computed(() => {
  const current = text.value
  const shown = chapter.value

  if (!current || !shown) return ''

  const label = shown.title ?? t('reader.chapter.untitled', { n: shown.position + 1 })

  return `${t('reader.chapter.position', { current: shown.position + 1, total: current.chapters_count })} · ${label}`
})

const hasPrevious = computed(() => (chapter.value?.position ?? 0) > 0)
const hasNext = computed(() => chapter.value !== null && text.value !== null && chapter.value.position < text.value.chapters_count - 1)

// ---------------------------------------------------------------- loading

async function load() {
  phase.value = 'loading'
  loadError.value = ''
  chapters.clear()
  chapter.value = null
  text.value = null

  try {
    await library.load().catch(() => undefined)
    userBook.value = library.find(props.id) ?? (await libraryApi.get(props.id))
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      phase.value = 'notFound'
    } else {
      loadError.value = errorMessage(e)
      phase.value = 'failed'
    }
    return
  }

  try {
    text.value = await readerApi.text(props.id)
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      phase.value = 'noText'
    } else {
      loadError.value = errorMessage(e)
      phase.value = 'failed'
    }
    return
  }

  // Where to begin: at the quote someone came here to see, else where the reader left off.
  const requested = Number(route.query.chapter)
  const wanted = Number(route.query.highlight)
  const fromQuote = route.query.chapter !== undefined && Number.isInteger(requested) && requested >= 0

  await showChapter(fromQuote ? requested : text.value.position.chapter, {
    at: fromQuote ? 0 : text.value.position.chapter_progress,
    highlight: fromQuote && Number.isInteger(wanted) ? wanted : undefined,
  })

  // `showChapter` moves the phase on while this function waits for it.
  if ((phase.value as Phase) !== 'ready') {
    loadError.value = chapterError.value
    phase.value = 'failed'
    return
  }

  // The address was only the way in: from here on a reload goes back to where the reader is.
  if (route.query.chapter !== undefined || route.query.highlight !== undefined) {
    void router.replace({ name: 'read', params: { id: props.id } })
  }
}

onMounted(() => {
  void load()
  saver.start()
})

// Another book in the same screen: what was read in the first is saved before the second is opened.
watch(
  () => props.id,
  async () => {
    await saver.flush()
    void load()
  },
)

onBeforeUnmount(() => {
  clearTimeout(toastTimer)
})

// ---------------------------------------------------------------- selecting a quote

const { selection, clear: clearSelection } = useTextSelection(contentRoot)

/** What the reader is about to save. It outlives the selection while the note is being written, which loses focus of the text. */
const draft = ref<typeof selection.value>(null)
const note = ref('')
const noteOpen = ref(false)
const savingQuote = ref(false)
const quoteError = ref('')
const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined

watch(selection, (value) => {
  if (value) {
    draft.value = value
    quoteError.value = ''
  } else if (!noteOpen.value && !savingQuote.value) {
    draft.value = null
  }
})

const draftLength = computed(() => (draft.value ? Array.from(draft.value.text).length : 0))
const problem = computed(() => (draftLength.value > MAX_QUOTE_LENGTH ? t('reader.selection.tooLong', { max: MAX_QUOTE_LENGTH }) : ''))

function say(message: string) {
  toast.value = message
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => (toast.value = ''), TOAST_MS)
}

function resetDraft() {
  draft.value = null
  note.value = ''
  noteOpen.value = false
  quoteError.value = ''
  clearSelection()
}

function addHighlight(shown: Chapter, highlight: Highlight) {
  shown.highlights = [...shown.highlights, highlight]
}

async function saveQuote() {
  const selected = draft.value
  const shown = chapter.value

  if (!selected || !shown || savingQuote.value || problem.value) return

  savingQuote.value = true
  quoteError.value = ''

  const body = { content: selected.text, note: note.value.trim() || null }

  try {
    try {
      const quote = await quotes.create(props.id, { ...body, anchor: { chapter: shown.position, start: selected.start, end: selected.end } })
      addHighlight(shown, { id: quote.id, start: selected.start, end: selected.end, content: quote.content, note: quote.note })
      say(t('reader.selection.saved'))
    } catch (e) {
      // The place could not be proved: the quote is still worth keeping, just not highlighted.
      const code = apiErrorCode(e)

      if (code !== 'reader.anchor_mismatch' && code !== 'reader.anchor_out_of_range') throw e

      await quotes.create(props.id, body)
      say(t('reader.selection.savedPlain'))
    }

    resetDraft()
  } catch (e) {
    quoteError.value = errorMessage(e)
  } finally {
    savingQuote.value = false
  }
}

// ---------------------------------------------------------------- tapping a highlight

const tappedIds = ref<number[]>([])
const removingId = ref<number | null>(null)
const highlightError = ref('')

const tapped = computed(() =>
  tappedIds.value.map((id) => chapter.value?.highlights.find((highlight) => highlight.id === id)).filter((highlight): highlight is Highlight => !!highlight),
)

function openHighlights(ids: number[]) {
  highlightError.value = ''
  tappedIds.value = ids
}

function closeHighlights() {
  tappedIds.value = []
}

async function removeQuote(id: number) {
  const shown = chapter.value

  if (!shown) return

  removingId.value = id
  highlightError.value = ''

  try {
    await quotes.remove(id)
    shown.highlights = shown.highlights.filter((highlight) => highlight.id !== id)
    tappedIds.value = tappedIds.value.filter((tappedId) => tappedId !== id)
  } catch (e) {
    highlightError.value = errorMessage(e)
  } finally {
    removingId.value = null
  }
}

// ---------------------------------------------------------------- settings

// A different size changes how tall the chapter is: stay at the same place in it.
watch(
  () => [settings.fontSize, settings.lineHeight, settings.font],
  async () => {
    const kept = fraction.value
    await nextTick()
    scrollToFraction(kept)
  },
)
</script>

<template>
  <div class="reader fixed inset-0 z-40 flex flex-col" :style="style" :data-theme="settings.theme">
    <template v-if="phase === 'ready' && text && chapter">
      <ReaderTopBar
        :title="userBook?.book.title ?? ''"
        :subtitle="subtitle"
        :back-to="{ name: 'book', params: { id } }"
        @contents="sheet = 'contents'"
        @settings="sheet = 'settings'"
      />

      <div
        class="reader-track h-0.5 shrink-0"
        role="progressbar"
        :aria-label="$t('reader.progress')"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="Math.floor(overall * 100)"
      >
        <div class="reader-fill h-full" :style="{ width: `${overall * 100}%` }" />
      </div>

      <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto overscroll-contain" :aria-busy="loadingChapter" @scroll.passive="onScroll">
        <article class="mx-auto w-full max-w-2xl px-5 pb-40 pt-6">
          <ErrorNotice v-if="chapterError" :message="chapterError" retryable class="mb-4" @retry="showChapter(failedPosition ?? chapter.position)" />

          <ReaderChapter ref="content" :html="chapter.html" :highlights="chapter.highlights" @highlight-tap="openHighlights" />

          <nav class="mt-10 flex flex-col gap-2" :aria-label="$t('reader.chapter.position', { current: chapter.position + 1, total: text.chapters_count })">
            <button v-if="hasNext" type="button" :disabled="loadingChapter" class="reader-btn-primary min-h-11 rounded-lg px-4 font-semibold" @click="goTo(chapter.position + 1)">
              {{ $t('reader.chapter.next') }}
            </button>
            <p v-else class="reader-muted py-3 text-center text-sm">{{ $t('reader.chapter.end') }}</p>
            <button v-if="hasPrevious" type="button" :disabled="loadingChapter" class="reader-btn min-h-11 rounded-lg px-4 font-medium" @click="goTo(chapter.position - 1)">
              {{ $t('reader.chapter.previous') }}
            </button>
          </nav>
        </article>
      </div>

      <ReaderSelectionBar
        v-if="draft"
        v-model:note="note"
        v-model:note-open="noteOpen"
        :busy="savingQuote"
        :problem="problem"
        :error="quoteError"
        @save="saveQuote"
        @cancel="resetDraft"
      />

      <p v-if="toast && !draft" role="status" class="reader-toast fixed inset-x-4 bottom-6 z-30 mx-auto max-w-sm rounded-lg px-4 py-3 text-center text-sm shadow-lg">{{ toast }}</p>

      <ReaderContents v-if="sheet === 'contents'" :chapters="text.chapters" :current="chapter.position" @select="goTo" @close="sheet = null" />

      <ReaderSettingsSheet
        v-if="sheet === 'settings'"
        :settings="settings"
        @step-font-size="stepFontSize"
        @step-line-height="stepLineHeight"
        @update:font="settings.font = $event"
        @update:theme="settings.theme = $event"
        @reset="resetSettings"
        @close="sheet = null"
      />

      <HighlightSheet v-if="tapped.length" :items="tapped" :busy-id="removingId" :error="highlightError" @remove="removeQuote" @close="closeHighlights" />
    </template>

    <div v-else class="mx-auto flex w-full max-w-md flex-col gap-4 px-4 pt-[max(1rem,env(safe-area-inset-top))]">
      <RouterLink
        :to="phase === 'notFound' ? { name: 'library' } : { name: 'book', params: { id } }"
        :aria-label="$t('common.back')"
        class="reader-icon grid size-11 place-items-center rounded-full"
      >
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M15 5l-7 7 7 7" />
        </svg>
      </RouterLink>

      <SkeletonList v-if="phase === 'loading'" tall :count="2" />

      <ErrorNotice v-else-if="phase === 'failed'" :message="loadError" retryable @retry="load" />

      <p v-else-if="phase === 'notFound'" class="reader-muted py-10 text-center">{{ $t('reader.state.notFound') }}</p>

      <div v-else-if="phase === 'noText'" class="flex flex-col items-center gap-4 py-10 text-center">
        <p class="reader-muted">{{ $t('reader.state.noText') }}</p>
        <RouterLink :to="{ name: 'book', params: { id } }" class="reader-btn-primary inline-flex min-h-11 items-center rounded-lg px-4 font-semibold">
          {{ $t('reader.state.toBook') }}
        </RouterLink>
      </div>
    </div>
  </div>
</template>
