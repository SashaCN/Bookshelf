<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ percent: number | null; label: string }>()

const value = computed(() => Math.min(100, Math.max(0, props.percent ?? 0)))
</script>

<template>
  <div
    class="h-2 overflow-hidden rounded-full bg-sunken"
    role="progressbar"
    :aria-label="label"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-valuenow="value"
  >
    <div class="h-full rounded-full bg-accent transition-[width]" :style="{ width: `${value}%` }" />
  </div>
</template>
