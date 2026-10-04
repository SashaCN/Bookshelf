<script setup lang="ts">
const props = defineProps<{ modelValue: number | null; readonly?: boolean; label: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()

function select(star: number) {
  // Tapping the current rating again clears it.
  emit('update:modelValue', props.modelValue === star ? null : star)
}
</script>

<template>
  <div class="flex gap-1" role="group" :aria-label="label">
    <component
      :is="readonly ? 'span' : 'button'"
      v-for="star in 5"
      :key="star"
      :type="readonly ? undefined : 'button'"
      :aria-pressed="readonly ? undefined : modelValue === star"
      :aria-label="readonly ? undefined : `${star} / 5`"
      class="grid size-9 place-items-center rounded-lg"
      :class="[readonly ? 'size-5' : 'hover:bg-sunken', (modelValue ?? 0) >= star ? 'text-accent' : 'text-line']"
      @click="readonly ? undefined : select(star)"
    >
      <svg viewBox="0 0 24 24" class="size-full max-h-6 max-w-6" fill="currentColor" aria-hidden="true">
        <path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z" />
      </svg>
    </component>
  </div>
</template>
