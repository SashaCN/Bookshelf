<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
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
body { font-family: 'Iowan Old Style', 'Palatino Linotype', Georgia, serif; font-size: ${fontSize.value}px; line-height: 1.6; max-width: 40em; margin: 0 auto; padding: 1.25rem 1rem 3rem; overflow-wrap: anywhere; }
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
  try {
    chapter.value = await reader.chapter(props.id, next)
    index.value = next
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function go(step: number) {
  const next = index.value + step
  if (next < 0 || next >= count.value) return
  await openChapter(next)
  if (index.value === next) scheduleSave()
}

async function load() {
  error.value = ''
  try {
    doc.value = await reader.get(props.id)
    await openChapter(Math.min(doc.value.position.chapter, Math.max(0, doc.value.chapters_count - 1)))
  } catch (e) {
    error.value = errorMessage(e)
  }
}

onMounted(load)

onBeforeUnmount(() => {
  // Do not lose the last page turn when the reader leaves within the debounce window.
  if (saveTimer) {
    clearTimeout(saveTimer)
    reader.savePosition(props.id, { chapter: index.value, fraction: 0 }).catch(() => undefined)
  }
})
</script>

<template>
  <div class="flex h-[calc(100dvh-8rem)] flex-col gap-2 pt-2">
    <div class="flex flex-wrap items-center gap-2">
      <RouterLink :to="{ name: 'reader' }" class="inline-flex min-h-10 items-center rounded-lg border border-line bg-surface px-3 text-sm">
        {{ $t('common.back') }}
      </RouterLink>
      <h1 class="min-w-0 flex-1 truncate font-display text-lg font-semibold">{{ doc?.title }}</h1>
      <button type="button" class="min-h-10 min-w-10 rounded-lg border border-line bg-surface text-sm" :aria-label="$t('reader.smaller')" :disabled="fontSize <= FONT_MIN" @click="changeFont(-2)">A−</button>
      <button type="button" class="min-h-10 min-w-10 rounded-lg border border-line bg-surface text-base" :aria-label="$t('reader.larger')" :disabled="fontSize >= FONT_MAX" @click="changeFont(2)">A+</button>
    </div>

    <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

    <p v-else-if="!chapter" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

    <iframe
      v-else
      sandbox=""
      :srcdoc="srcdoc"
      :title="$t('reader.frameTitle')"
      class="min-h-0 w-full flex-1 rounded-lg border border-line bg-surface"
    />

    <div class="flex items-center justify-between gap-2">
      <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-50" :disabled="index <= 0" @click="go(-1)">
        {{ $t('reader.prev') }}
      </button>
      <span class="text-sm text-ink-muted">{{ $t('reader.chapter', { n: index + 1, total: count }) }}</span>
      <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-50" :disabled="index >= count - 1" @click="go(1)">
        {{ $t('reader.next') }}
      </button>
    </div>
  </div>
</template>
