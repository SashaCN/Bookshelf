<script setup lang="ts">
defineProps<{ label: string; value: string; unit?: string; hint?: string }>()
</script>

<template>
  <div class="flex flex-col gap-0.5 rounded-2xl border border-line bg-surface p-3">
    <p class="text-xs text-ink-muted">{{ label }}</p>
    <p class="flex items-baseline gap-1.5">
      <span class="font-display text-2xl font-bold leading-tight tabular-nums">{{ value }}</span>
      <span v-if="unit" class="text-sm text-ink-muted">{{ unit }}</span>
    </p>
    <p v-if="hint" class="text-xs text-ink-muted">{{ hint }}</p>
  </div>
</template>
