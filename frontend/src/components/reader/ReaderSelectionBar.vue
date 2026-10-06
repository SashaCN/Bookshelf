<script setup lang="ts">
import { useId } from 'vue'
import ErrorNotice from '@/components/ErrorNotice.vue'

defineProps<{
  busy: boolean
  /** Why the quote cannot be saved, when it cannot (it is too long). */
  problem?: string
  /** What went wrong when saving was tried. */
  error?: string
}>()

const emit = defineEmits<{ save: []; cancel: [] }>()

const note = defineModel<string>('note', { required: true })
const noteOpen = defineModel<boolean>('noteOpen', { required: true })
const noteId = useId()
</script>

<template>
  <section
    :aria-label="$t('reader.selection.bar')"
    class="reader-bar fixed inset-x-0 bottom-0 z-30 border-t px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
  >
    <div class="mx-auto flex max-w-md flex-col gap-2">
      <ErrorNotice v-if="error" :message="error" />
      <p v-if="problem" role="alert" class="reader-danger text-sm">{{ problem }}</p>

      <div v-if="noteOpen" class="flex flex-col gap-1">
        <label :for="noteId" class="sr-only">{{ $t('reader.selection.note') }}</label>
        <textarea
          :id="noteId"
          v-model="note"
          rows="2"
          maxlength="2000"
          :placeholder="$t('reader.selection.note')"
          class="reader-field rounded-lg px-3 py-2 text-base"
        />
      </div>

      <div class="flex gap-2">
        <!-- A press that does not take the focus (mousedown) does not take the selection away either. -->
        <button type="button" :disabled="busy || !!problem" class="reader-btn-primary min-h-11 min-w-0 flex-1 rounded-lg px-4 font-semibold" @mousedown.prevent @click="emit('save')">
          {{ busy ? $t('reader.selection.saving') : $t('reader.selection.save') }}
        </button>
        <button type="button" :aria-expanded="noteOpen" class="reader-btn min-h-11 rounded-lg px-3 text-sm font-medium" @mousedown.prevent @click="noteOpen = !noteOpen">
          {{ $t('reader.selection.addNote') }}
        </button>
        <button type="button" class="reader-btn min-h-11 rounded-lg px-3 text-sm font-medium" @mousedown.prevent @click="emit('cancel')">
          {{ $t('reader.selection.cancel') }}
        </button>
      </div>
    </div>
  </section>
</template>
