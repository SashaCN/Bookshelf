<script setup lang="ts">
import { READER_FONTS, READER_THEMES } from '@/composables/useReaderSettings'
import type { ReaderFont, ReaderSettings, ReaderTheme } from '@/types/reader'
import ReaderSheet from './ReaderSheet.vue'

defineProps<{ settings: ReaderSettings }>()

const emit = defineEmits<{
  close: []
  'step-font-size': [direction: 1 | -1]
  'step-line-height': [direction: 1 | -1]
  'update:font': [font: ReaderFont]
  'update:theme': [theme: ReaderTheme]
  reset: []
}>()
</script>

<template>
  <ReaderSheet :title="$t('reader.settings.title')" :close-label="$t('reader.settings.close')" @close="emit('close')">
    <div class="flex flex-col gap-4">
      <div class="flex items-center gap-2" role="group" :aria-label="$t('reader.settings.fontSize')">
        <span class="min-w-0 flex-1 text-sm font-medium">{{ $t('reader.settings.fontSize') }}</span>
        <button type="button" :aria-label="$t('reader.settings.smaller')" class="reader-btn grid size-11 place-items-center rounded-lg text-sm font-bold" @click="emit('step-font-size', -1)">A</button>
        <output class="w-12 text-center text-sm tabular-nums" aria-live="polite">{{ settings.fontSize }}</output>
        <button type="button" :aria-label="$t('reader.settings.larger')" class="reader-btn grid size-11 place-items-center rounded-lg text-xl font-bold" @click="emit('step-font-size', 1)">A</button>
      </div>

      <div class="flex items-center gap-2" role="group" :aria-label="$t('reader.settings.lineHeight')">
        <span class="min-w-0 flex-1 text-sm font-medium">{{ $t('reader.settings.lineHeight') }}</span>
        <button type="button" :aria-label="$t('reader.settings.tighter')" class="reader-btn grid size-11 place-items-center rounded-lg text-lg font-bold" @click="emit('step-line-height', -1)">−</button>
        <output class="w-12 text-center text-sm tabular-nums" aria-live="polite">{{ settings.lineHeight.toFixed(1) }}</output>
        <button type="button" :aria-label="$t('reader.settings.looser')" class="reader-btn grid size-11 place-items-center rounded-lg text-lg font-bold" @click="emit('step-line-height', 1)">+</button>
      </div>

      <div class="flex flex-col gap-1.5" role="group" :aria-label="$t('reader.settings.font')">
        <span class="text-sm font-medium">{{ $t('reader.settings.font') }}</span>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="font in READER_FONTS"
            :key="font"
            type="button"
            :aria-pressed="settings.font === font"
            class="reader-btn min-h-11 rounded-lg px-3 text-sm font-medium"
            :class="font === 'serif' ? 'font-display' : 'font-sans'"
            @click="emit('update:font', font)"
          >
            {{ $t(`reader.settings.${font}`) }}
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-1.5" role="group" :aria-label="$t('reader.settings.theme')">
        <span class="text-sm font-medium">{{ $t('reader.settings.theme') }}</span>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="theme in READER_THEMES"
            :key="theme"
            type="button"
            :aria-pressed="settings.theme === theme"
            class="reader-btn min-h-11 rounded-lg px-3 text-sm font-medium"
            @click="emit('update:theme', theme)"
          >
            {{ $t(`reader.settings.themes.${theme}`) }}
          </button>
        </div>
      </div>

      <button type="button" class="reader-link min-h-11 self-start text-sm font-medium underline underline-offset-2" @click="emit('reset')">
        {{ $t('reader.settings.reset') }}
      </button>
    </div>
  </ReaderSheet>
</template>
