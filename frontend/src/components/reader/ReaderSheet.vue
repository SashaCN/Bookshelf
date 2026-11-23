<script setup lang="ts">
import { onBeforeUnmount, onMounted, useId, useTemplateRef } from 'vue'

defineProps<{ title: string; closeLabel: string }>()
const emit = defineEmits<{ close: [] }>()

const titleId = useId()
const panel = useTemplateRef<HTMLElement>('panel')
let opener: Element | null = null

const FOCUSABLE = 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'

/** What the keyboard can reach inside of the sheet, in order. */
function focusable(): HTMLElement[] {
  return [...(panel.value?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [])].filter(
    (element) => !element.hasAttribute('disabled') && element.getAttribute('aria-hidden') !== 'true',
  )
}

/**
 * A sheet is modal: Escape closes it, and Tab goes round inside of it instead of out into the text behind it.
 */
function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.stopPropagation()
    emit('close')
    return
  }

  if (event.key !== 'Tab' || !panel.value) return

  const items = focusable()
  const first = items[0]
  const last = items[items.length - 1]
  const active = document.activeElement
  const outside = !panel.value.contains(active)

  if (!first || !last) {
    event.preventDefault()
    panel.value.focus()
  } else if (event.shiftKey && (outside || active === first || active === panel.value)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && (outside || active === last)) {
    event.preventDefault()
    first.focus()
  }
}

/** Focus that gets out of the sheet some other way (a click on the text behind it) is brought back. */
function onFocusIn(event: FocusEvent) {
  if (panel.value && event.target instanceof Node && !panel.value.contains(event.target)) {
    panel.value.focus()
  }
}

onMounted(() => {
  opener = document.activeElement
  document.addEventListener('keydown', onKeydown)
  document.addEventListener('focusin', onFocusIn)
  panel.value?.focus()
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('focusin', onFocusIn)
  // Back to the button that opened the sheet, so a keyboard does not start over from the top.
  if (opener instanceof HTMLElement && opener.isConnected) {
    opener.focus()
  }
})
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-end justify-center">
    <div class="absolute inset-0 bg-black/40" aria-hidden="true" @click="emit('close')" />

    <section
      ref="panel"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      tabindex="-1"
      class="reader-sheet relative flex max-h-[85dvh] w-full max-w-md flex-col rounded-t-2xl outline-none"
    >
      <header class="flex items-center gap-2 pl-4 pr-2 pt-2">
        <h2 :id="titleId" class="min-w-0 flex-1 truncate font-display text-xl font-bold">{{ title }}</h2>
        <button type="button" :aria-label="closeLabel" class="reader-icon grid size-11 shrink-0 place-items-center rounded-full" @click="emit('close')">
          <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
      </header>

      <div class="overflow-y-auto px-4 pb-4 pt-2">
        <slot />
      </div>
    </section>
  </div>
</template>
