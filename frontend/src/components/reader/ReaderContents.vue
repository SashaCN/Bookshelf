<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ChapterSummary } from '@/types/reader'
import ReaderSheet from './ReaderSheet.vue'

defineProps<{
  chapters: ChapterSummary[]
  /** The position of the chapter the reader is in. */
  current: number
}>()

const emit = defineEmits<{ select: [position: number]; close: [] }>()
const { t } = useI18n()

function label(chapter: ChapterSummary): string {
  return chapter.title ?? t('reader.chapter.untitled', { n: chapter.position + 1 })
}
</script>

<template>
  <ReaderSheet :title="$t('reader.contents.title')" :close-label="$t('reader.contents.close')" @close="emit('close')">
    <ol class="flex flex-col">
      <li v-for="chapter in chapters" :key="chapter.position" class="reader-line border-b last:border-b-0">
        <button
          type="button"
          :aria-current="chapter.position === current ? 'true' : undefined"
          class="flex min-h-11 w-full items-center gap-3 py-2 text-left"
          :class="chapter.position === current ? 'font-semibold' : ''"
          @click="emit('select', chapter.position)"
        >
          <span class="reader-muted w-8 shrink-0 text-right text-sm tabular-nums">{{ chapter.position + 1 }}</span>
          <span class="min-w-0 flex-1">{{ label(chapter) }}</span>
          <span v-if="chapter.position === current" class="reader-link shrink-0 text-xs font-medium">{{ $t('reader.contents.current') }}</span>
        </button>
      </li>
    </ol>
  </ReaderSheet>
</template>
