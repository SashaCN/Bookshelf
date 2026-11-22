<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { ApiError } from '@/api/http'
import ErrorNotice from '@/components/ErrorNotice.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useReaderStore } from '@/stores/reader'
import type { ReaderChapter, ReaderDocument } from '@/types/api'

const props = defineProps<{ id: number }>()

const SAVE_DELAY_MS = 800
const FONT_KEY = 'reader.fontSize'
const FONT_MIN = 14
const FONT_MAX = 28
const FONT_DEFAULT = 18

const reader = useReaderStore()
const errorMessage = useApiErrorMessage()

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
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'">
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

async function load() {
  error.value = ''
  notFound.value = false

  try {
    doc.value = await reader.get(props.id)
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
    <div class="flex items-center gap-2">
      <RouterLink
        :to="{ name: 'reader' }"
        class="grid size-11 shrink-0 place-items-center rounded-full border border-line bg-surface text-ink-muted"
        :aria-label="$t('reader.toList')"
      >
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M15 5l-7 7 7 7" />
        </svg>
      </RouterLink>
      <div class="min-w-0 flex-1">
        <h1 class="truncate font-display text-lg font-semibold leading-tight">{{ doc?.title }}</h1>
        <p v-if="chapter?.title" class="truncate text-xs text-ink-muted">{{ chapter.title }}</p>
      </div>
      <button type="button" class="min-h-11 min-w-11 rounded-lg border border-line bg-surface text-sm disabled:opacity-50" :aria-label="$t('reader.smaller')" :disabled="fontSize <= FONT_MIN" @click="changeFont(-2)">A−</button>
      <button type="button" class="min-h-11 min-w-11 rounded-lg border border-line bg-surface text-base disabled:opacity-50" :aria-label="$t('reader.larger')" :disabled="fontSize >= FONT_MAX" @click="changeFont(2)">A+</button>
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

    <div class="flex items-center justify-between gap-2">
      <button
        ref="prevButton"
        type="button"
        class="min-h-11 min-w-11 rounded-lg border border-line bg-surface px-4 text-lg font-medium disabled:opacity-50"
        :aria-label="$t('reader.prev')"
        :disabled="loadingChapter || index <= 0"
        @click="go(-1)"
      >
        <span aria-hidden="true">←</span>
      </button>
      <span role="status" class="text-sm text-ink-muted">{{ $t('reader.chapter', { n: index + 1, total: count }) }}</span>
      <button
        ref="nextButton"
        type="button"
        class="min-h-11 min-w-11 rounded-lg border border-line bg-surface px-4 text-lg font-medium disabled:opacity-50"
        :aria-label="$t('reader.next')"
        :disabled="loadingChapter || index >= count - 1"
        @click="go(1)"
      >
        <span aria-hidden="true">→</span>
      </button>
    </div>
  </div>
</template>
