<script setup lang="ts">
import { computed, onMounted, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Highlight } from '@/types/reader'
import { applyHighlights, clearHighlights, highlightIdsAt } from '@/utils/readerDom'

const props = defineProps<{
  /** Sanitized by the server: only elements from a short list, without attributes. */
  html: string
  highlights: Highlight[]
}>()

const emit = defineEmits<{
  /** A highlight was tapped: the ids of the quotes whose highlights are at that place. */
  'highlight-tap': [ids: number[]]
}>()

const { t } = useI18n()
const root = useTemplateRef<HTMLElement>('root')

/** How many characters of a quote name its highlight for a screen reader. */
const LABEL_LENGTH = 60

function labelOf(highlight: Highlight): string {
  const text = highlight.content.replace(/\s+/g, ' ').trim()

  return t('reader.highlight.label', { text: text.length > LABEL_LENGTH ? `${text.slice(0, LABEL_LENGTH).trimEnd()}…` : text })
}

defineExpose({ root })

/** What the highlights look like, so that they are drawn again only when they have changed. */
const key = computed(() => props.highlights.map((highlight) => `${highlight.id}:${highlight.start}:${highlight.end}`).join('|'))

/** Draws the highlights over the text. Starting from the plain text each time keeps this simple and always right. */
function paint() {
  if (!root.value) return

  clearHighlights(root.value)
  applyHighlights(
    root.value,
    props.highlights.map((highlight) => ({ id: highlight.id, start: highlight.start, end: highlight.end, label: labelOf(highlight) })),
  )
}

onMounted(paint)
// After the DOM has been patched: a new chapter replaces the whole text, a new highlight is drawn over it.
watch(() => [props.html, key.value], paint, { flush: 'post' })

function tapped(target: EventTarget | null) {
  if (!root.value || !(target instanceof Node)) return

  const ids = highlightIdsAt(target, root.value)

  if (ids.length) {
    emit('highlight-tap', ids)
  }
}

function onClick(event: MouseEvent) {
  // Selecting text ends with a click too; that is not a tap on a highlight.
  if (window.getSelection()?.isCollapsed === false) return

  tapped(event.target)
}

function onKeydown(event: KeyboardEvent) {
  // A button is pressed with Enter and with Space (which would otherwise scroll the page).
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    tapped(event.target)
  }
}
</script>

<template>
  <!-- eslint-disable-next-line vue/no-v-html -- the server rebuilds every chapter from allowed elements without attributes -->
  <div ref="root" class="reader-content" @click="onClick" @keydown="onKeydown" v-html="html" />
</template>
