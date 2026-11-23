<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { ApiError } from '@/api/http'
import ErrorNotice from '@/components/ErrorNotice.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useI18n } from 'vue-i18n'
import { useReaderStore } from '@/stores/reader'
import type { ReaderBookmark, ReaderChapter, ReaderDocument } from '@/types/api'

const props = defineProps<{ id: number }>()

const SAVE_DELAY_MS = 800
const FONT_KEY = 'reader.fontSize'
const FONT_MIN = 14
const FONT_MAX = 28
const FONT_DEFAULT = 18

const reader = useReaderStore()
const errorMessage = useApiErrorMessage()
const { t } = useI18n()

const doc = ref<ReaderDocument | null>(null)
const chapter = ref<ReaderChapter | null>(null)
const index = ref(0)
const error = ref('')
const notFound = ref(false)
const loadingChapter = ref(false)
/** The chapter being opened; a failed attempt is retried for this one, not for the saved position. */
const target = ref(0)
const prevButton = ref<HTMLButtonElement | null>(null)
const nextButton = ref<HTMLButtonElement | null>(null)
const tocButton = ref<HTMLButtonElement | null>(null)
const tocPanel = ref<HTMLElement | null>(null)
const tocOpen = ref(false)
const marksButton = ref<HTMLButtonElement | null>(null)
const marksPanel = ref<HTMLElement | null>(null)
const marksOpen = ref(false)
const fontButton = ref<HTMLButtonElement | null>(null)
const fontPanel = ref<HTMLElement | null>(null)
const fontOpen = ref(false)
/** Bookmarks are loaded once per book; null until then (or when loading failed). */
const bookmarks = ref<ReaderBookmark[] | null>(null)
const bookmarksError = ref('')
const bookmarkBusy = ref(false)
const noteDrafts = ref<Record<number, string>>({})
const noteSaving = ref<number | null>(null)
let nextTempId = -1

const currentBookmark = computed(() => bookmarks.value?.find((b) => b.chapter === index.value) ?? null)

const toc = computed(() => doc.value?.toc ?? [])
/** The last table-of-contents entry at or before the current chapter: chapters without a title belong to the one before. */
const currentTocIndex = computed(() => toc.value.reduce((found, entry) => (entry.index <= index.value ? entry.index : found), -1))

function readFontSize(): number {
  try {
    const value = Number(window.localStorage.getItem(FONT_KEY))
    return Number.isInteger(value) && value >= FONT_MIN && value <= FONT_MAX ? value : FONT_DEFAULT
  } catch {
    return FONT_DEFAULT
  }
}

const fontSize = ref(readFontSize())

function changeFont(step: number) {
  fontSize.value = Math.min(FONT_MAX, Math.max(FONT_MIN, fontSize.value + step))
  try {
    window.localStorage.setItem(FONT_KEY, String(fontSize.value))
  } catch {
    // Storage may be blocked: the size then simply lasts for this visit.
  }
  keepFontFocus()
}

/** A button that has just reached its limit is disabled and loses the focus; hand it to the other one. */
async function keepFontFocus() {
  await nextTick()
  const active = document.activeElement
  if (!fontPanel.value || (active && fontPanel.value.contains(active) && !(active as HTMLButtonElement).disabled)) return
  fontPanel.value.querySelector<HTMLElement>('button:not(:disabled)')?.focus()
}

async function openFont() {
  tocOpen.value = false
  closeMarks(false)
  fontOpen.value = true
  await nextTick()
  ;(fontPanel.value?.querySelector<HTMLElement>('button:not(:disabled)') ?? fontPanel.value)?.focus()
}

function closeFont(returnFocus = true) {
  if (!fontOpen.value) return
  fontOpen.value = false
  if (returnFocus) nextTick(() => fontButton.value?.focus())
}

const count = computed(() => chapter.value?.count ?? doc.value?.chapters_count ?? 0)

/**
 * The chapter markup is untrusted: it is shown only inside a sandboxed iframe (no allow-same-origin, no scripts)
 * with a CSP that forbids loading anything. Colors follow the system theme, like the rest of the app.
 */
const srcdoc = computed(() => {
  if (!chapter.value) return ''
  return `<!doctype html>
<html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data:">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root { color-scheme: light; --bg: #ffffff; --fg: #141b2b; }
@media (prefers-color-scheme: dark) { :root { color-scheme: dark; --bg: #161c27; --fg: #e6e9f0; } }
html { background: var(--bg); color: var(--fg); }
body { font-family: 'Iowan Old Style', 'Palatino Linotype', Georgia, serif; font-size: ${fontSize.value}px; line-height: 1.65; max-width: 34em; margin: 0 auto; padding: 1.25rem 1.25rem 3rem; overflow-wrap: anywhere; }
p { margin: 0 0 1em; }
h1, h2, h3, h4 { line-height: 1.25; }
img, svg, video { max-width: 100%; height: auto; }
a { color: inherit; }
</style></head>
<body>${chapter.value.html}</body></html>`
})

/** Opening a book counts as reading it: the first chapter that loads is saved as the position. */
let openSaved = false
let saveTimer: ReturnType<typeof setTimeout> | null = null

function scheduleSave() {
  if (saveTimer) clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    saveTimer = null
    // Scroll inside the sandboxed iframe cannot be observed, so the position is the chapter only.
    reader.savePosition(props.id, { chapter: index.value, fraction: 0 }).catch(() => undefined)
  }, SAVE_DELAY_MS)
}

async function openChapter(next: number) {
  error.value = ''
  target.value = next
  loadingChapter.value = true

  try {
    chapter.value = await reader.chapter(props.id, next)
    index.value = next
    if (!openSaved) {
      openSaved = true
      scheduleSave()
    }
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loadingChapter.value = false
  }
}

/** Disabling the buttons while a chapter loads drops the focus; give it back, to the other button at either end. */
async function keepFocus(step: number) {
  await nextTick()
  const preferred = step > 0 ? nextButton.value : prevButton.value
  const other = step > 0 ? prevButton.value : nextButton.value
  const active = document.activeElement
  if (active === document.body || (active instanceof HTMLButtonElement && active.disabled)) (preferred?.disabled ? other : preferred)?.focus()
}

async function go(step: number) {
  const next = index.value + step
  if (loadingChapter.value || next < 0 || next >= count.value) return
  await openChapter(next)
  if (index.value === next) {
    scheduleSave()
    await keepFocus(step)
  }
}

async function openToc() {
  closeFont(false)
  closeMarks(false)
  tocOpen.value = true
  await nextTick()
  const current = tocPanel.value?.querySelector<HTMLElement>('[aria-current="true"]')
  ;(current ?? tocPanel.value?.querySelector<HTMLElement>('button'))?.focus()
}

function closeToc() {
  tocOpen.value = false
  nextTick(() => tocButton.value?.focus())
}

async function jumpTo(chapterIndex: number) {
  if (loadingChapter.value) return
  closeToc()
  closeMarks(false)
  if (chapterIndex === index.value || chapterIndex < 0 || chapterIndex >= count.value) return
  await openChapter(chapterIndex)
  if (index.value === chapterIndex) scheduleSave()
}

function bookmarkTitle(bookmark: ReaderBookmark): string {
  return bookmark.chapter_title ?? t('reader.bookmarks.chapterN', { n: bookmark.chapter + 1 })
}

function setBookmarks(list: ReaderBookmark[]) {
  bookmarks.value = [...list].sort((a, b) => a.chapter - b.chapter)
}

async function loadBookmarks() {
  bookmarksError.value = ''
  try {
    const list = await reader.bookmarks(props.id)
    setBookmarks(list)
    noteDrafts.value = Object.fromEntries(list.map((b) => [b.id, b.note ?? '']))
  } catch (e) {
    bookmarksError.value = errorMessage(e)
  }
}

/** Optimistic: the list changes at once and is put back when the server refuses. */
async function toggleBookmark() {
  const before = bookmarks.value
  if (!before || bookmarkBusy.value) return
  const chapterIndex = index.value
  const existing = before.find((b) => b.chapter === chapterIndex)
  bookmarkBusy.value = true

  try {
    if (existing) {
      setBookmarks(before.filter((b) => b !== existing))
      await reader.removeBookmark(props.id, existing.id)
    } else {
      const temp: ReaderBookmark = {
        id: nextTempId--,
        chapter: chapterIndex,
        chapter_title: chapter.value?.title ?? null,
        note: null,
        created_at: new Date().toISOString(),
      }
      setBookmarks([...before, temp])
      const saved = await reader.saveBookmark(props.id, chapterIndex)
      setBookmarks((bookmarks.value ?? []).map((b) => (b === temp ? saved : b)))
      noteDrafts.value[saved.id] = saved.note ?? ''
    }
  } catch (e) {
    bookmarks.value = before
    error.value = errorMessage(e)
  } finally {
    bookmarkBusy.value = false
  }
}

async function removeBookmark(bookmark: ReaderBookmark) {
  const before = bookmarks.value
  if (!before || bookmark.id < 0) return
  setBookmarks(before.filter((b) => b !== bookmark))

  try {
    await reader.removeBookmark(props.id, bookmark.id)
  } catch (e) {
    bookmarks.value = before
    error.value = errorMessage(e)
  }
}

async function saveNote(bookmark: ReaderBookmark) {
  if (noteSaving.value !== null || bookmark.id < 0) return
  const note = (noteDrafts.value[bookmark.id] ?? '').trim()
  noteSaving.value = bookmark.id

  try {
    const saved = await reader.saveBookmark(props.id, bookmark.chapter, note === '' ? null : note)
    setBookmarks((bookmarks.value ?? []).map((b) => (b.id === bookmark.id ? saved : b)))
    noteDrafts.value[saved.id] = saved.note ?? ''
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    noteSaving.value = null
  }
}

async function openMarks() {
  closeFont(false)
  closeToc()
  marksOpen.value = true
  if (!bookmarks.value) loadBookmarks()
  await nextTick()
  ;(marksPanel.value?.querySelector<HTMLElement>('button, input') ?? marksPanel.value)?.focus()
}

function closeMarks(returnFocus = true) {
  if (!marksOpen.value) return
  marksOpen.value = false
  if (returnFocus) nextTick(() => marksButton.value?.focus())
}

async function load() {
  error.value = ''
  notFound.value = false

  try {
    doc.value = await reader.get(props.id)
    loadBookmarks()
    await openChapter(Math.min(doc.value.position.chapter, Math.max(0, doc.value.chapters_count - 1)))
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true
    } else {
      error.value = errorMessage(e)
    }
  }
}

function retry() {
  return doc.value ? openChapter(target.value) : load()
}

function onKeydown(event: KeyboardEvent) {
  if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return
  if (event.target instanceof HTMLElement && event.target.closest('input, textarea, select')) return

  if (event.key === 'ArrowLeft') go(-1)
  else if (event.key === 'ArrowRight') go(1)
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  load()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  // Do not lose the last page turn when the reader leaves within the debounce window.
  if (saveTimer) {
    clearTimeout(saveTimer)
    reader.savePosition(props.id, { chapter: index.value, fraction: 0 }).catch(() => undefined)
  }
})
</script>

<template>
  <div class="flex h-[calc(100dvh-8rem)] flex-col gap-2 pt-2">
    <div class="relative flex items-center gap-1.5">
      <RouterLink
        :to="{ name: 'reader' }"
        class="grid size-11 shrink-0 place-items-center rounded-full border border-line bg-surface text-ink-muted"
        :aria-label="$t('reader.toList')"
      >
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M15 5l-7 7 7 7" />
        </svg>
      </RouterLink>
      <div class="min-w-0 flex-1 px-1">
        <h1 class="truncate font-display text-lg font-semibold leading-tight">{{ doc?.title }}</h1>
        <p v-if="chapter?.title" class="truncate text-xs text-ink-muted">{{ chapter.title }}</p>
      </div>
      <button
        v-if="toc.length"
        ref="tocButton"
        type="button"
        class="grid size-11 shrink-0 place-items-center rounded-lg border border-line bg-surface text-ink-muted"
        :class="{ 'text-accent': tocOpen }"
        :aria-label="$t('reader.toc')"
        aria-haspopup="dialog"
        :aria-expanded="tocOpen"
        @click="tocOpen ? closeToc() : openToc()"
      >
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01" />
        </svg>
      </button>
      <button
        ref="marksButton"
        type="button"
        class="grid size-11 shrink-0 place-items-center rounded-lg border border-line bg-surface text-ink-muted"
        :class="{ 'text-accent': marksOpen }"
        :aria-label="$t('reader.bookmarks.title')"
        aria-haspopup="dialog"
        :aria-expanded="marksOpen"
        @click="marksOpen ? closeMarks() : openMarks()"
      >
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M4.5 4h9v16l-4.5-3-4.5 3zM17 7v13" />
        </svg>
      </button>
      <button
        ref="fontButton"
        type="button"
        class="grid size-11 shrink-0 place-items-center rounded-lg border border-line bg-surface font-display text-base font-semibold text-ink-muted"
        :class="{ 'text-accent': fontOpen }"
        :aria-label="$t('reader.fontSize')"
        aria-haspopup="dialog"
        :aria-expanded="fontOpen"
        @click="fontOpen ? closeFont() : openFont()"
      >
        <span aria-hidden="true">Аа</span>
      </button>

      <div
        v-if="fontOpen"
        ref="fontPanel"
        role="dialog"
        tabindex="-1"
        :aria-label="$t('reader.fontSize')"
        class="absolute right-0 top-full z-10 mt-1 flex gap-2 rounded-2xl border border-line bg-surface p-2 shadow-lg"
        @keydown.esc.stop="closeFont()"
      >
        <button type="button" class="min-h-11 min-w-11 rounded-lg border border-line bg-surface text-sm disabled:opacity-50" :aria-label="$t('reader.smaller')" :disabled="fontSize <= FONT_MIN" @click="changeFont(-2)">A−</button>
        <button type="button" class="min-h-11 min-w-11 rounded-lg border border-line bg-surface text-base disabled:opacity-50" :aria-label="$t('reader.larger')" :disabled="fontSize >= FONT_MAX" @click="changeFont(2)">A+</button>
      </div>
    </div>

    <div
      v-if="tocOpen"
      ref="tocPanel"
      role="dialog"
      :aria-label="$t('reader.toc')"
      class="max-h-[50dvh] overflow-y-auto rounded-2xl border border-line bg-surface p-2 shadow-lg"
      @keydown.esc.stop="closeToc"
    >
      <ul class="flex flex-col">
        <li v-for="entry in toc" :key="entry.index">
          <button
            type="button"
            class="min-h-11 w-full rounded-lg px-3 text-left text-sm"
            :class="entry.index === currentTocIndex ? 'bg-accent/10 font-semibold' : ''"
            :aria-current="entry.index === currentTocIndex ? 'true' : undefined"
            @click="jumpTo(entry.index)"
          >
            {{ entry.title }}
          </button>
        </li>
      </ul>
    </div>

    <div
      v-if="marksOpen"
      ref="marksPanel"
      role="dialog"
      tabindex="-1"
      :aria-label="$t('reader.bookmarks.title')"
      class="max-h-[50dvh] overflow-y-auto rounded-2xl border border-line bg-surface p-2 shadow-lg"
      @keydown.esc.stop="closeMarks()"
    >
      <ErrorNotice v-if="bookmarksError" :message="bookmarksError" retryable @retry="loadBookmarks" />
      <p v-else-if="!bookmarks" class="px-3 py-2 text-sm text-ink-muted">{{ $t('common.loading') }}</p>
      <p v-else-if="!bookmarks.length" class="px-3 py-2 text-sm text-ink-muted">{{ $t('reader.bookmarks.empty') }}</p>
      <ul v-else class="flex flex-col gap-2">
        <li v-for="bookmark in bookmarks" :key="bookmark.id" class="flex flex-col gap-1 rounded-lg border border-line p-2">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="min-h-11 min-w-0 flex-1 rounded-lg px-3 text-left text-sm"
              :class="bookmark.chapter === index ? 'bg-accent/10 font-semibold' : ''"
              :aria-current="bookmark.chapter === index ? 'true' : undefined"
              @click="jumpTo(bookmark.chapter)"
            >
              <span class="block truncate">{{ bookmarkTitle(bookmark) }}</span>
              <span v-if="bookmark.note" class="block truncate text-xs font-normal text-ink-muted">{{ bookmark.note }}</span>
            </button>
            <button
              type="button"
              class="min-h-11 min-w-11 rounded-lg border border-line text-sm"
              :aria-label="$t('reader.bookmarks.removeNamed', { title: bookmarkTitle(bookmark) })"
              @click="removeBookmark(bookmark)"
            >
              <span aria-hidden="true">✕</span>
            </button>
          </div>
          <form class="flex items-center gap-2" @submit.prevent="saveNote(bookmark)">
            <input
              v-model="noteDrafts[bookmark.id]"
              type="text"
              maxlength="200"
              class="min-h-11 min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 text-sm"
              :aria-label="$t('reader.bookmarks.note')"
              :placeholder="$t('reader.bookmarks.notePlaceholder')"
            >
            <button
              type="submit"
              class="min-h-11 rounded-lg border border-line px-3 text-sm disabled:opacity-50"
              :disabled="noteSaving !== null || (noteDrafts[bookmark.id] ?? '').trim() === (bookmark.note ?? '')"
            >
              {{ noteSaving === bookmark.id ? $t('reader.bookmarks.saving') : $t('reader.bookmarks.save') }}
            </button>
          </form>
        </li>
      </ul>
    </div>

    <div v-if="notFound" class="flex flex-col items-start gap-2 rounded-2xl border border-dashed border-line p-4">
      <p class="text-ink-muted">{{ $t('reader.notFound') }}</p>
      <RouterLink :to="{ name: 'reader' }" class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent">
        {{ $t('reader.toList') }}
      </RouterLink>
    </div>

    <ErrorNotice v-else-if="error" :message="error" retryable @retry="retry" />

    <p v-else-if="!chapter" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

    <iframe
      v-else
      :key="index"
      sandbox=""
      :srcdoc="srcdoc"
      :title="$t('reader.frameTitle')"
      :aria-busy="loadingChapter"
      class="min-h-0 w-full flex-1 rounded-lg border border-line bg-surface transition-opacity"
      :class="{ 'opacity-60': loadingChapter }"
    />

    <div class="flex items-center gap-2">
      <button
        ref="prevButton"
        type="button"
        class="grid size-11 shrink-0 place-items-center rounded-lg border border-line bg-surface text-lg font-medium disabled:opacity-50"
        :aria-label="$t('reader.prev')"
        :disabled="loadingChapter || index <= 0"
        @click="go(-1)"
      >
        <span aria-hidden="true">←</span>
      </button>
      <span role="status" class="min-w-0 flex-1 whitespace-nowrap text-center text-sm text-ink-muted">{{ $t('reader.chapter', { n: index + 1, total: count }) }}</span>
      <button
        type="button"
        class="grid size-11 shrink-0 place-items-center rounded-lg border border-line bg-surface disabled:opacity-50"
        :class="currentBookmark ? 'text-accent' : 'text-ink-muted'"
        :aria-label="currentBookmark ? $t('reader.bookmarks.remove') : $t('reader.bookmarks.add')"
        :aria-pressed="currentBookmark !== null"
        :disabled="!bookmarks || bookmarkBusy"
        @click="toggleBookmark"
      >
        <svg viewBox="0 0 24 24" class="size-6" :fill="currentBookmark ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
          <path d="M6 4h12v17l-6-4-6 4z" />
        </svg>
      </button>
      <button
        ref="nextButton"
        type="button"
        class="grid size-11 shrink-0 place-items-center rounded-lg border border-line bg-surface text-lg font-medium disabled:opacity-50"
        :aria-label="$t('reader.next')"
        :disabled="loadingChapter || index >= count - 1"
        @click="go(1)"
      >
        <span aria-hidden="true">→</span>
      </button>
    </div>
  </div>
</template>
