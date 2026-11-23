<script setup lang="ts">
withDefaults(defineProps<{ count?: number; tall?: boolean }>(), { count: 3, tall: false })
</script>

<template>
  <!-- Placeholders in the shape of the cards that are coming, so the screen does not jump when the data arrives. -->
  <div role="status" aria-busy="true" class="flex flex-col gap-3">
    <span class="sr-only">{{ $t('common.loading') }}</span>
    <div
      v-for="n in count"
      :key="n"
      aria-hidden="true"
      class="animate-pulse rounded-2xl border border-line bg-surface motion-reduce:animate-none"
      :class="tall ? 'h-44' : 'h-28'"
    />
  </div>
</template>
