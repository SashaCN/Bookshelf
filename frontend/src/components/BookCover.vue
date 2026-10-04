<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = defineProps<{ title: string; coverUrl: string | null }>()

const failed = ref(false)
watch(() => props.coverUrl, () => (failed.value = false))

const showImage = computed(() => props.coverUrl !== null && !failed.value)
const initial = computed(() => props.title.trim().charAt(0).toUpperCase() || '?')

// A stable colour per title, so books without a cover are still easy to tell apart.
const background = computed(() => {
  let hash = 0
  for (const char of props.title) {
    hash = (hash * 31 + char.charCodeAt(0)) % 360
  }
  return `hsl(${hash} 32% 36%)`
})
</script>

<template>
  <div class="aspect-[2/3] overflow-hidden rounded-sm rounded-r-md bg-sunken shadow-sm">
    <img
      v-if="showImage"
      :src="coverUrl ?? undefined"
      :alt="''"
      loading="lazy"
      class="size-full object-cover"
      @error="failed = true"
    >
    <div
      v-else
      class="grid size-full place-items-center font-display text-2xl font-bold text-white"
      :style="{ background }"
      aria-hidden="true"
    >
      {{ initial }}
    </div>
  </div>
</template>
