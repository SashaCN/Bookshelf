<script setup lang="ts">
defineProps<{ title: string; text: string }>()
</script>

<template>
  <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-line px-6 py-10 text-center">
    <p class="font-display text-xl font-semibold">{{ title }}</p>
    <p class="text-ink-muted">{{ text }}</p>
    <div class="mt-3">
      <slot />
    </div>
  </div>
</template>
