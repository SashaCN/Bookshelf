<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { isApiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import type { ReportReason, ReportTarget } from '@/types/social'
import ErrorNotice from './ErrorNotice.vue'

const NOTE_MAX = 500
const reasons: ReportReason[] = ['spam', 'abuse', 'other']

const props = defineProps<{ target: ReportTarget }>()
const emit = defineEmits<{ close: []; reported: [] }>()

const { t, te } = useI18n()
const errorMessage = useApiErrorMessage()
const titleId = useId()
const dialog = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
/** Where the focus was before the dialog took it: it goes back there when the dialog goes. */
const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null

const form = reactive({ reason: '' as ReportReason | '', note: '' })
const sending = ref(false)
const done = ref(false)
const error = ref('')
const reasonError = ref('')
const noteError = ref('')

onMounted(async () => {
  await nextTick()
  dialog.value?.focus()
})

onBeforeUnmount(() => {
  if (opener?.isConnected) {
    opener.focus()
  }
})

/** Tab stays inside the dialog while it is open. */
function keepFocusInside(event: KeyboardEvent) {
  const focusable = [...(dialog.value?.querySelectorAll<HTMLElement>('button, input, textarea') ?? [])].filter(
    (element) => !(element as HTMLButtonElement).disabled,
  )
  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (!first || !last) return

  if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

async function submit() {
  error.value = ''
  noteError.value = ''
  reasonError.value = ''

  if (!form.reason) {
    reasonError.value = t('social.report.reasonRequired')
    return
  }

  sending.value = true

  try {
    await socialApi.report(props.target, { reason: form.reason, note: form.note.trim() || null })
    done.value = true
    emit('reported')
    // The form is gone and the focus with it: it goes to the only button left, inside the dialog.
    await nextTick()
    closeButton.value?.focus()
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && e.fieldErrors.note?.length) {
      noteError.value = t('social.report.errors.note')
    } else if (e instanceof ApiError && e.status === 422 && e.fieldErrors.reason?.length) {
      // A business rule, such as "you have already reported this".
      const code = e.fieldErrors.reason.find(isApiErrorCode)
      reasonError.value = code && te(`apiErrors.${code}`) ? t(`apiErrors.${code}`) : t('social.report.reasonRequired')
    } else {
      error.value = errorMessage(e)
    }
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-20 flex items-end justify-center bg-black/40 p-4 sm:items-center" @click.self="emit('close')">
    <div
      ref="dialog"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      tabindex="-1"
      class="flex max-h-full w-full max-w-md flex-col gap-4 overflow-y-auto rounded-2xl bg-surface p-4 shadow-lg outline-none"
      @keydown.esc="emit('close')"
      @keydown.tab="keepFocusInside"
    >
      <h2 :id="titleId" class="font-display text-xl font-semibold">{{ $t('social.report.title') }}</h2>

      <template v-if="done">
        <p role="status">{{ $t('social.report.thanks') }}</p>
        <button
          ref="closeButton"
          type="button"
          class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent"
          @click="emit('close')"
        >
          {{ $t('social.report.close') }}
        </button>
      </template>

      <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
        <ErrorNotice v-if="error" :message="error" />

        <fieldset class="flex flex-col gap-1">
          <legend class="mb-1 text-sm font-medium">{{ $t('social.report.reason') }}</legend>
          <label
            v-for="reason in reasons"
            :key="reason"
            class="flex min-h-11 items-center gap-3 rounded-lg border border-line px-3"
            :class="form.reason === reason ? 'border-accent bg-sunken' : ''"
          >
            <input v-model="form.reason" type="radio" name="report-reason" :value="reason" class="size-4 accent-accent">
            <span>{{ $t(`social.report.reasons.${reason}`) }}</span>
          </label>
          <p v-if="reasonError" role="alert" class="text-sm text-danger">{{ reasonError }}</p>
        </fieldset>

        <div class="flex flex-col gap-1.5">
          <label for="report-note" class="text-sm font-medium">
            {{ $t('social.report.note') }}
            <span class="font-normal text-ink-muted">({{ $t('social.report.noteHint') }})</span>
          </label>
          <textarea
            id="report-note"
            v-model="form.note"
            rows="3"
            :maxlength="NOTE_MAX"
            :aria-invalid="noteError ? 'true' : undefined"
            :aria-describedby="noteError ? 'report-note-error' : undefined"
            class="rounded-lg border border-line bg-surface px-3 py-2 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
          />
          <p v-if="noteError" id="report-note-error" class="text-sm text-danger">{{ noteError }}</p>
          <p class="text-right text-xs tabular-nums text-ink-muted">
            {{ $t('social.report.counter', { count: form.note.length, max: NOTE_MAX }) }}
          </p>
        </div>

        <div class="flex gap-2">
          <button
            type="submit"
            :disabled="sending"
            class="min-h-11 flex-1 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
          >
            {{ sending ? $t('social.report.sending') : $t('social.report.submit') }}
          </button>
          <button
            type="button"
            class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium"
            @click="emit('close')"
          >
            {{ $t('social.report.cancel') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
