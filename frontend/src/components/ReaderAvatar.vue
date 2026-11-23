<script setup lang="ts">
import { computed } from 'vue'
import { initialOf } from '@/utils/social'

const props = withDefaults(defineProps<{ name: string; size?: 'md' | 'lg' }>(), { size: 'md' })

const initial = computed(() => initialOf(props.name))
</script>

<template>
  <span
    aria-hidden="true"
    class="grid shrink-0 place-items-center rounded-full bg-sunken font-semibold"
    :class="size === 'lg' ? 'size-16 text-2xl' : 'size-10 text-sm'"
  >
    {{ initial }}
  </span>
</template>
