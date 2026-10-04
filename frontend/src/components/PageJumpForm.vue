<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'

const props = defineProps<{
  id: string
  label: string
  current: number
  total: number
  busy?: boolean
  /** Hide the label visually (it stays for screen readers) when the context already explains the field. */
  hideLabel?: boolean
  /** Show a cancel button and focus the field right away, for a form that appears on demand. */
  dismissable?: boolean
}>()

const emit = defineEmits<{ submit: [page: number]; cancel: [] }>()

// Kept as text on purpose: v-model on a number input turns the value into a number, and an empty field into "".
const value = ref(String(props.current))
const input = useTemplateRef<HTMLInputElement>('input')

const page = computed(() => (value.value.trim() === '' ? Number.NaN : Number(value.value)))
const valid = computed(() => Number.isInteger(page.value) && page.value >= 0 && page.value <= props.total)
const unchanged = computed(() => page.value === props.current)

// A fresh page from the server (or from a quick button) replaces what was typed.
watch(
  () => props.current,
  (current) => {
    value.value = String(current)
  },
)

onMounted(() => {
  if (props.dismissable) {
    input.value?.select()
  }
})

function submit() {
  if (valid.value && !unchanged.value) {
    emit('submit', page.value)
  }
}
</script>

<template>
  <form class="flex flex-col gap-1.5" @submit.prevent="submit">
    <label :for="id" class="text-sm font-medium" :class="{ 'sr-only': hideLabel }">{{ label }}</label>
    <div class="flex gap-2">
      <input
        :id="id"
        ref="input"
        :value="value"
        type="number"
        inputmode="numeric"
        min="0"
        :max="total"
        :aria-invalid="value !== '' && !valid ? 'true' : undefined"
        :aria-describedby="value !== '' && !valid ? `${id}-error` : undefined"
        class="min-h-11 min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 text-base tabular-nums outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
        @input="value = ($event.target as HTMLInputElement).value"
      >
      <button
        type="submit"
        :disabled="busy || !valid || unchanged"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-50"
      >
        {{ $t('common.save') }}
      </button>
      <button
        v-if="dismissable"
        type="button"
        class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium"
        @click="emit('cancel')"
      >
        {{ $t('common.cancel') }}
      </button>
    </div>
    <p v-if="value !== '' && !valid" :id="`${id}-error`" class="text-sm text-danger">
      {{ $t('progress.pageInvalid', { total }) }}
    </p>
  </form>
</template>
