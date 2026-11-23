<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    title: string
    /** `alertdialog` for destructive confirmations that need an answer. */
    role?: 'dialog' | 'alertdialog'
    /** What gets the focus first; a destructive dialog should start on its safe button. */
    initialFocus?: string
  }>(),
  { role: 'dialog', initialFocus: 'input, button' },
)

const emit = defineEmits<{ cancel: [] }>()

const FOCUSABLE = 'input:not(:disabled), select:not(:disabled), textarea:not(:disabled), button:not(:disabled), a[href]'

const id = useId()
const titleId = `${id}-title`
const descriptionId = `${id}-text`
const dialog = ref<HTMLElement | null>(null)
/** Whatever had the focus when the dialog opened gets it back when the dialog goes away. */
let opener: HTMLElement | null = null

onMounted(async () => {
  opener = document.activeElement instanceof HTMLElement ? document.activeElement : null
  await nextTick()
  dialog.value?.querySelector<HTMLElement>(props.initialFocus)?.focus()
})

onBeforeUnmount(() => {
  opener?.focus()
})

/** Keeps Tab inside the dialog, since the page behind it is not meant to be reached. */
function trapTab(event: KeyboardEvent) {
  const focusable = [...(dialog.value?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [])]
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
      :role="role"
      aria-modal="true"
      :aria-labelledby="titleId"
      :aria-describedby="descriptionId"
      class="flex max-h-full w-full max-w-md flex-col gap-4 overflow-y-auto rounded-2xl border border-line bg-surface p-5 shadow-lg"
      @keydown.esc.stop="emit('cancel')"
      @keydown.tab="trapTab"
    >
      <h2 :id="titleId" class="text-lg font-semibold">{{ title }}</h2>
      <slot :description-id="descriptionId" />
    </div>
  </div>
</template>
