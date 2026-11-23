<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

defineProps<{
  username: string
  pending: boolean
  error: string
}>()

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()

const dialog = ref<HTMLElement | null>(null)
/** Whatever had the focus when the dialog opened gets it back when the dialog goes away. */
let opener: HTMLElement | null = null

onMounted(async () => {
  opener = document.activeElement instanceof HTMLElement ? document.activeElement : null
  await nextTick()
  dialog.value?.querySelector<HTMLElement>('button')?.focus()
})

onBeforeUnmount(() => {
  opener?.focus()
})

/** Keeps Tab inside the dialog, since the page behind it is not meant to be reached. */
function trapTab(event: KeyboardEvent) {
  const focusable = [...(dialog.value?.querySelectorAll<HTMLElement>('button:not(:disabled)') ?? [])]
  if (!focusable.length) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
    <div
      ref="dialog"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="block-user-title"
      aria-describedby="block-user-text"
      class="flex max-h-full w-full max-w-md flex-col gap-4 overflow-y-auto rounded-2xl border border-line bg-surface p-5 shadow-lg"
      @keydown.esc.stop="emit('cancel')"
      @keydown.tab="trapTab"
    >
      <h2 id="block-user-title" class="text-lg font-semibold">{{ $t('social.profile.blockTitle', { handle: '@' + username }) }}</h2>

      <div id="block-user-text" class="flex flex-col gap-2 text-sm">
        <p>{{ $t('social.profile.blockIntro') }}</p>
        <ul class="list-disc pl-5">
          <li v-for="item in ['hidden', 'follows', 'likes', 'silent']" :key="item">
            {{ $t(`social.profile.blockEffects.${item}`) }}
          </li>
        </ul>
        <p class="font-medium">{{ $t('social.profile.blockUndo') }}</p>
      </div>

      <p v-if="error" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium" @click="emit('cancel')">
          {{ $t('social.profile.blockCancel') }}
        </button>
        <button
          type="button"
          data-test="confirm-block"
          :disabled="pending"
          class="min-h-11 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
          @click="emit('confirm')"
        >
          {{ $t('social.profile.blockConfirm') }}
        </button>
      </div>
    </div>
  </div>
</template>
