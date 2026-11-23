import { effectScope, ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useTextSelection } from './useTextSelection'

function select(node: Node, start: number, end: number) {
  const range = document.createRange()
  range.setStart(node, start)
  range.setEnd(node, end)
  const selection = window.getSelection()!
  selection.removeAllRanges()
  selection.addRange(range)
  document.dispatchEvent(new Event('selectionchange'))
}

describe('useTextSelection', () => {
  let root: HTMLElement
  let text: Node

  beforeEach(() => {
    vi.useFakeTimers()
    root = document.createElement('div')
    root.innerHTML = '<p>Hello brave new world</p>'
    document.body.appendChild(root)
    text = root.querySelector('p')!.firstChild!
  })

  afterEach(() => {
    window.getSelection()?.removeAllRanges()
    document.body.innerHTML = ''
    vi.useRealTimers()
  })

  it('knows what is selected in the text, once the selection has settled', async () => {
    const { selection } = useTextSelection(ref(root))

    select(text, 6, 11)
    expect(selection.value).toBeNull()

    await vi.advanceTimersByTimeAsync(150)
    expect(selection.value).toEqual({ start: 6, end: 11, text: 'brave' })
  })

  it('follows the selection as it grows', async () => {
    const { selection } = useTextSelection(ref(root))

    select(text, 6, 11)
    await vi.advanceTimersByTimeAsync(150)
    select(text, 6, 15)
    await vi.advanceTimersByTimeAsync(150)

    expect(selection.value).toMatchObject({ start: 6, end: 15, text: 'brave new' })
  })

  it('ignores what is selected outside of the text', async () => {
    const outside = document.createElement('p')
    outside.textContent = 'Something else'
    document.body.appendChild(outside)
    const { selection } = useTextSelection(ref(root))

    select(outside.firstChild!, 0, 9)
    await vi.advanceTimersByTimeAsync(150)

    expect(selection.value).toBeNull()
  })

  it('keeps the selection for a moment after it is gone, so that a tap on a button still finds it', async () => {
    const { selection } = useTextSelection(ref(root))
    select(text, 6, 11)
    await vi.advanceTimersByTimeAsync(150)

    window.getSelection()!.removeAllRanges()
    document.dispatchEvent(new Event('selectionchange'))
    await vi.advanceTimersByTimeAsync(150)
    expect(selection.value).not.toBeNull()

    await vi.advanceTimersByTimeAsync(300)
    expect(selection.value).toBeNull()
  })

  it('keeps it for good when a new selection comes before the moment is over', async () => {
    const { selection } = useTextSelection(ref(root))
    select(text, 6, 11)
    await vi.advanceTimersByTimeAsync(150)

    window.getSelection()!.removeAllRanges()
    document.dispatchEvent(new Event('selectionchange'))
    await vi.advanceTimersByTimeAsync(150)
    select(text, 12, 15)
    await vi.advanceTimersByTimeAsync(600)

    expect(selection.value).toMatchObject({ start: 12, end: 15, text: 'new' })
  })

  it('forgets the selection when told to, in the text as well', async () => {
    const { selection, clear } = useTextSelection(ref(root))
    select(text, 6, 11)
    await vi.advanceTimersByTimeAsync(150)

    clear()

    expect(selection.value).toBeNull()
    expect(window.getSelection()!.rangeCount).toBe(0)
  })

  it('does nothing before the text is there', async () => {
    const { selection } = useTextSelection(ref(null))

    select(text, 6, 11)
    await vi.advanceTimersByTimeAsync(150)

    expect(selection.value).toBeNull()
  })

  it('stops listening when its scope ends', async () => {
    const scope = effectScope()
    const { selection } = scope.run(() => useTextSelection(ref(root)))!

    scope.stop()
    select(text, 6, 11)
    await vi.advanceTimersByTimeAsync(150)

    expect(selection.value).toBeNull()
  })
})
