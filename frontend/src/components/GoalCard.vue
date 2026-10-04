<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GoalProgress } from '@/types/api'
import ErrorNotice from './ErrorNotice.vue'
import ProgressBar from './ProgressBar.vue'

const props = defineProps<{ goal: GoalProgress; busy?: boolean; error?: string }>()
const emit = defineEmits<{ save: [targetBooks: number]; remove: [] }>()

const { t } = useI18n()

const hasGoal = computed(() => props.goal.target_books !== null)
// Kept as text on purpose: v-model on a number input turns the value into a number, and an empty field into "".
const draft = ref(props.goal.target_books ? String(props.goal.target_books) : '')
const editing = ref(false)

const target = computed(() => props.goal.target_books ?? 0)
const percent = computed(() => (target.value ? Math.min(100, Math.round((props.goal.finished_books / target.value) * 100)) : 0))
const done = computed(() => hasGoal.value && props.goal.finished_books >= target.value)

const parsed = computed(() => (draft.value.trim() === '' ? Number.NaN : Number(draft.value)))
const valid = computed(() => Number.isInteger(parsed.value) && parsed.value >= 1 && parsed.value <= 1000)
const showForm = computed(() => !hasGoal.value || editing.value)

const status = computed(() => {
  if (done.value) return t('stats.goal.done')
  if (props.goal.on_track) return t('stats.goal.onTrack', { n: props.goal.remaining_books ?? 0 }, props.goal.remaining_books ?? 0)
  return t('stats.goal.behind', { n: props.goal.due_books ?? 0 }, props.goal.due_books ?? 0)
})

function startEditing() {
  draft.value = String(target.value)
  editing.value = true
}

// The form closes when the new goal arrives, not before: if saving fails, what was typed is still there.
watch(
  () => props.goal.target_books,
  () => {
    editing.value = false
  },
)

function submit() {
  if (!valid.value) return

  if (parsed.value === props.goal.target_books) {
    editing.value = false
  } else {
    emit('save', parsed.value)
  }
}
</script>

<template>
  <section class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-4" :aria-label="t('stats.goal.title', { year: goal.year })">
    <h2 class="font-display text-xl font-semibold">{{ t('stats.goal.title', { year: goal.year }) }}</h2>

    <ErrorNotice v-if="error" :message="error" />

    <template v-if="hasGoal && !editing">
      <p class="flex items-baseline gap-2">
        <span class="font-display text-4xl font-bold tabular-nums">{{ goal.finished_books }}</span>
        <span class="text-ink-muted">{{ t('stats.goal.of', { n: target }) }}</span>
      </p>
      <ProgressBar :percent="percent" :label="t('stats.goal.progress')" />
      <p class="text-sm font-medium" :class="done || goal.on_track ? 'text-accent' : 'text-danger'">{{ status }}</p>
      <div class="flex gap-2">
        <button
          type="button"
          class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
          :disabled="busy"
          @click="startEditing"
        >
          {{ t('stats.goal.change') }}
        </button>
        <button
          type="button"
          class="min-h-11 rounded-lg px-4 font-medium text-danger disabled:opacity-60"
          :disabled="busy"
          @click="emit('remove')"
        >
          {{ t('stats.goal.remove') }}
        </button>
      </div>
    </template>

    <form v-if="showForm" class="flex flex-col gap-2" @submit.prevent="submit">
      <p v-if="!hasGoal" class="text-sm text-ink-muted">{{ t('stats.goal.empty') }}</p>
      <label for="goal-target" class="text-sm font-medium">{{ t('stats.goal.label') }}</label>
      <div class="flex gap-2">
        <input
          id="goal-target"
          :value="draft"
          type="number"
          inputmode="numeric"
          min="1"
          max="1000"
          :aria-invalid="draft !== '' && !valid ? 'true' : undefined"
          class="min-h-11 min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 text-base tabular-nums outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
          @input="draft = ($event.target as HTMLInputElement).value"
        >
        <button
          type="submit"
          :disabled="busy || !valid"
          class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-50"
        >
          {{ t('common.save') }}
        </button>
        <button
          v-if="hasGoal"
          type="button"
          class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium"
          @click="editing = false"
        >
          {{ t('common.cancel') }}
        </button>
      </div>
      <p v-if="draft !== '' && !valid" class="text-sm text-danger">{{ t('stats.goal.invalid') }}</p>
    </form>
  </section>
</template>
