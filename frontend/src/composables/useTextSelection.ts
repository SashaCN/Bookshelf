import { getCurrentScope, onScopeDispose, ref, type Ref } from 'vue'
import { selectionOf, type SelectedText } from '@/utils/readerDom'

/** Selecting by dragging handles fires `selectionchange` over and over; wait for it to settle. */
const SETTLE_MS = 120

/**
 * Tapping a button outside of the text collapses the selection before the tap becomes a click. The selection is
 * given up only after this long, so that the click still finds what it was about.
 */
const GIVE_UP_MS = 300

/**
 * What is selected in the text of a chapter, as offsets and as text. Watches `selectionchange`; `selection` is null
 * while nothing (or only something outside of `root`) is selected.
 */
export function useTextSelection(root: Ref<HTMLElement | null>) {
  const selection = ref<SelectedText | null>(null)
  let settleTimer: ReturnType<typeof setTimeout> | undefined
  let giveUpTimer: ReturnType<typeof setTimeout> | undefined

  function sameSelection(a: SelectedText | null, b: SelectedText | null): boolean {
    return a === b || (a !== null && b !== null && a.start === b.start && a.end === b.end)
  }

  function update(): void {
    const found = root.value ? selectionOf(root.value, window.getSelection()) : null

    if (found) {
      clearTimeout(giveUpTimer)
      if (!sameSelection(selection.value, found)) selection.value = found
    } else if (selection.value) {
      clearTimeout(giveUpTimer)
      giveUpTimer = setTimeout(() => (selection.value = null), GIVE_UP_MS)
    }
  }

  function onSelectionChange(): void {
    clearTimeout(settleTimer)
    settleTimer = setTimeout(update, SETTLE_MS)
  }

  /** Forgets the selection, in the text as well. */
  function clear(): void {
    clearTimeout(settleTimer)
    clearTimeout(giveUpTimer)
    window.getSelection()?.removeAllRanges()
    selection.value = null
  }

  function stop(): void {
    document.removeEventListener('selectionchange', onSelectionChange)
    clearTimeout(settleTimer)
    clearTimeout(giveUpTimer)
  }

  document.addEventListener('selectionchange', onSelectionChange)

  if (getCurrentScope()) {
    onScopeDispose(stop)
  }

  return { selection, clear, stop }
}
