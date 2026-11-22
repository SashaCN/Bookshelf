<script setup lang="ts">
import ErrorNotice from './ErrorNotice.vue'

defineProps<{ hasMore: boolean; loading: boolean; error: string }>()
defineEmits<{ more: [] }>()
</script>

<template>
  <ErrorNotice v-if="error" :message="error" retryable class="mt-4" @retry="$emit('more')" />

  <button
    v-else-if="hasMore"
    type="button"
    :disabled="loading"
    class="mt-4 min-h-11 w-full rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
    @click="$emit('more')"
  >
    {{ loading ? $t('common.loading') : $t('common.more') }}
  </button>
</template>
