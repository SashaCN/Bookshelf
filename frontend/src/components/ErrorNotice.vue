<script setup lang="ts">
defineProps<{ message: string; retryable?: boolean }>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <div role="alert" class="flex items-center justify-between gap-3 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">
    <span>{{ message }}</span>
    <button
      v-if="retryable"
      type="button"
      class="shrink-0 rounded-md px-2 py-1 font-semibold underline underline-offset-2"
      @click="$emit('retry')"
    >
      {{ $t('common.retry') }}
    </button>
  </div>
</template>
