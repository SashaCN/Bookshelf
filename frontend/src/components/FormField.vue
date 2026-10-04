<script setup lang="ts">
withDefaults(
  defineProps<{
    id: string
    label: string
    type?: string
    autocomplete?: string
    hint?: string
    error?: string
    minlength?: number
  }>(),
  { type: 'text', autocomplete: undefined, hint: undefined, error: undefined, minlength: undefined },
)

const model = defineModel<string>({ required: true })
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="id" class="text-sm font-medium">{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      :type="type"
      :autocomplete="autocomplete"
      :minlength="minlength"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
      required
      class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
    >
    <p v-if="error" :id="`${id}-error`" class="text-sm text-danger">{{ error }}</p>
    <p v-else-if="hint" :id="`${id}-hint`" class="text-sm text-ink-muted">{{ hint }}</p>
  </div>
</template>
