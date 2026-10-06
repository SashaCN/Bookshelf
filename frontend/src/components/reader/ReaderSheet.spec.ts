import { mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { h } from 'vue'
import { createTestEnvironment } from '@/test/utils'
import ReaderSheet from './ReaderSheet.vue'

function press(key: string, { shift = false }: { shift?: boolean } = {}): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key, shiftKey: shift, bubbles: true, cancelable: true })
  document.dispatchEvent(event)
  return event
}

describe('ReaderSheet', () => {
  let opener: HTMLButtonElement
  let wrapper: VueWrapper | undefined

  function open(slot: () => ReturnType<typeof h>[] = () => [h('button', { id: 'first' }, 'First'), h('a', { id: 'link', href: '#' }, 'Link'), h('button', { id: 'last' }, 'Last')]) {
    wrapper = mount(ReaderSheet, {
      props: { title: 'Зміст', closeLabel: 'Закрити' },
      slots: { default: slot },
      attachTo: document.body,
      global: { plugins: createTestEnvironment().plugins },
    })

    return wrapper
  }

  const active = () => document.activeElement as HTMLElement | null
  /** The first thing the keyboard reaches in a sheet is its button to close it, which is in its header. */
  const closeButton = () => document.querySelector('button[aria-label="Закрити"]') as HTMLElement

  beforeEach(() => {
    opener = document.createElement('button')
    opener.textContent = 'Open'
    document.body.appendChild(opener)
    opener.focus()
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    document.body.innerHTML = ''
  })

  it('is a modal dialog with a name, and takes the focus when it opens', () => {
    const sheet = open()

    const dialog = sheet.get('[role="dialog"]')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.attributes('aria-labelledby')).toBe(sheet.get('h2').attributes('id'))
    expect(active()).toBe(dialog.element)
  })

  it('closes on Escape, and on the button and the backdrop', async () => {
    const sheet = open()

    press('Escape')
    await sheet.get('button[aria-label="Закрити"]').trigger('click')
    await sheet.get('.bg-black\\/40').trigger('click')

    expect(sheet.emitted('close')).toHaveLength(3)
  })

  it('goes from the last thing it holds to the first on Tab, and from the first to the last on Shift+Tab', () => {
    open()
    const last = document.getElementById('last')!

    last.focus()
    expect(press('Tab').defaultPrevented).toBe(true)
    expect(active()).toBe(closeButton())

    expect(press('Tab', { shift: true }).defaultPrevented).toBe(true)
    expect(active()).toBe(last)
  })

  it('goes to the last thing on Shift+Tab from the sheet itself, where the focus starts', () => {
    const sheet = open()
    expect(active()).toBe(sheet.get('[role="dialog"]').element)

    press('Tab', { shift: true })

    expect(active()).toBe(document.getElementById('last'))
  })

  it('leaves Tab alone in the middle, where the browser takes it to the next thing', () => {
    open()
    document.getElementById('first')!.focus()

    expect(press('Tab').defaultPrevented).toBe(false)
    expect(press('Tab', { shift: true }).defaultPrevented).toBe(false)
  })

  it('does not stop at what cannot be focused', () => {
    open(() => [h('button', { id: 'first' }, 'First'), h('button', { id: 'last' }, 'Last'), h('button', { id: 'off', disabled: true }, 'Off')])
    document.getElementById('last')!.focus()

    press('Tab')

    // Back to the start, and not to the button that is disabled at the end.
    expect(active()).toBe(closeButton())
  })

  it('keeps the focus on the one thing it holds when that is all there is', () => {
    open(() => [h('p', 'Only text')])
    const close = wrapper!.get('button[aria-label="Закрити"]').element as HTMLElement

    close.focus()
    press('Tab')

    expect(active()).toBe(close)
  })

  it('brings the focus back when it gets out by another way', () => {
    const sheet = open()

    opener.focus()

    expect(active()).toBe(sheet.get('[role="dialog"]').element)
  })

  it('gives the focus back to what opened it when it goes away', () => {
    const sheet = open()
    expect(active()).not.toBe(opener)

    sheet.unmount()
    wrapper = undefined

    expect(active()).toBe(opener)
  })

  it('stops listening to the keyboard when it goes away', () => {
    const sheet = open()
    sheet.unmount()
    wrapper = undefined

    expect(press('Tab').defaultPrevented).toBe(false)
  })
})
