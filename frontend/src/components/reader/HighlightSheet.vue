<script setup lang="ts">
import { ref } from 'vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import type { Highlight } from '@/types/reader'
import ReaderSheet from './ReaderSheet.vue'

defineProps<{
  /** The saved quotes at the place that was tapped; more than one when highlights overlap. */
  items: Highlight[]
  /** The quote that is being removed. */
  busyId: number | null
  error?: string
}>()

const emit = defineEmits<{ close: []; remove: [id: number] }>()

const confirming = ref<number | null>(null)
</script>

<template>
  <ReaderSheet :title="$t('reader.highlight.title')" :close-label="$t('reader.highlight.close')" @close="emit('close')">
    <div class="flex flex-col gap-4">
      <ErrorNotice v-if="error" :message="error" />

      <article v-for="item in items" :key="item.id" class="reader-line flex flex-col gap-2 border-b pb-4 last:border-b-0 last:pb-0">
        <blockquote class="max-h-48 overflow-y-auto whitespace-pre-line break-words font-display text-lg leading-relaxed">{{ item.content }}</blockquote>
        <p v-if="item.note" class="reader-muted whitespace-pre-line break-words text-sm">
          <span class="sr-only">{{ $t('reader.highlight.note') }}: </span>{{ item.note }}
        </p>

        <div v-if="confirming !== item.id" class="flex flex-wrap gap-2">
          <RouterLink
            :to="{ name: 'quote-edit', params: { id: item.id } }"
            class="reader-btn inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium"
          >
            {{ $t('reader.highlight.open') }}
          </RouterLink>
          <button type="button" class="reader-danger min-h-11 px-2 text-sm font-medium" @click="confirming = item.id">
            {{ $t('reader.highlight.remove') }}
          </button>
        </div>

        <div v-else class="flex flex-col gap-2">
          <p class="text-sm">{{ $t('reader.highlight.removeConfirm') }}</p>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="busyId === item.id"
              class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
              @click="emit('remove', item.id)"
            >
              {{ busyId === item.id ? $t('reader.highlight.removing') : $t('reader.highlight.removeYes') }}
            </button>
            <button type="button" class="reader-btn min-h-11 flex-1 rounded-lg px-4 font-medium" @click="confirming = null">
              {{ $t('common.cancel') }}
            </button>
          </div>
        </div>
      </article>
    </div>
  </ReaderSheet>
</template>
