<script setup lang="ts" generic="T extends string">
import { useId } from 'vue'

defineProps<{
  label: string
  options: { value: T; label: string }[]
  /** Hide the label visually (it stays for screen readers) when the context already explains the control. */
  hideLabel?: boolean
}>()

const model = defineModel<T>({ required: true })
const labelId = useId()
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <span :id="labelId" class="text-sm font-medium" :class="{ 'sr-only': hideLabel }">{{ label }}</span>
    <div role="group" :aria-labelledby="labelId" class="grid auto-cols-fr grid-flow-col gap-1 rounded-xl bg-sunken p-1">
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        :aria-pressed="model === option.value"
        class="min-h-11 rounded-lg px-3 text-sm font-medium"
        :class="model === option.value ? 'bg-surface text-ink shadow-sm' : 'text-ink-muted'"
        @click="model = option.value"
      >
        {{ option.label }}
      </button>
    </div>
  </div>
</template>
