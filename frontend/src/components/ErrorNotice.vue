<script setup lang="ts">
import { nextTick, onMounted, useTemplateRef, watch } from 'vue'

const props = defineProps<{ message: string; retryable?: boolean }>()
defineEmits<{ retry: [] }>()

const root = useTemplateRef<HTMLElement>('root')

/** An error that appears above the fold (a long form, a failed save at the bottom) must not go unnoticed. */
function reveal() {
  root.value?.scrollIntoView?.({ block: 'nearest' })
}

onMounted(reveal)
watch(
  () => props.message,
  () => nextTick(reveal),
)
</script>

<template>
  <div ref="root" role="alert" class="flex items-center justify-between gap-3 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">
    <span>{{ message }}</span>
    <button
      v-if="retryable"
      type="button"
      class="min-h-11 shrink-0 rounded-md px-2 py-1 font-semibold underline underline-offset-2"
      @click="$emit('retry')"
    >
      {{ $t('common.retry') }}
    </button>
  </div>
</template>
