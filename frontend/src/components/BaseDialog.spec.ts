import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import BaseDialog from './BaseDialog.vue'

const mounted: ReturnType<typeof mount>[] = []

function open(props: Record<string, unknown> = {}) {
  const opener = document.createElement('button')
  document.body.append(opener)
  opener.focus()

  const wrapper = mount(BaseDialog, {
    attachTo: document.body,
    props: { title: 'Заголовок', ...props },
    slots: {
      default: `<template #default="{ descriptionId }">
        <p :id="descriptionId">Опис</p>
        <input aria-label="field">
        <button type="button">Перша</button>
        <button type="button">Остання</button>
      </template>`,
    },
  })
  mounted.push(wrapper)

  return { wrapper, opener }
}

afterEach(() => {
  mounted.splice(0).forEach((wrapper) => wrapper.unmount())
  document.body.innerHTML = ''
})

describe('BaseDialog', () => {
  it('is a labelled modal dialog', async () => {
    const { wrapper } = open({ role: 'alertdialog' })
    const dialog = wrapper.get('[role="alertdialog"]')

    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(wrapper.get(`#${dialog.attributes('aria-labelledby')}`).text()).toBe('Заголовок')
    expect(wrapper.get(`#${dialog.attributes('aria-describedby')}`).text()).toBe('Опис')
  })

  it('focuses the requested element and gives the focus back when it closes', async () => {
    const { wrapper, opener } = open({ initialFocus: 'button' })
    await wrapper.vm.$nextTick()

    expect(document.activeElement?.textContent).toBe('Перша')

    wrapper.unmount()
    mounted.pop()

    expect(document.activeElement).toBe(opener)
  })

  it('asks to close on Escape', async () => {
    const { wrapper } = open()

    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('keeps Tab inside the dialog in both directions', async () => {
    const { wrapper } = open()
    await wrapper.vm.$nextTick()
    const dialog = wrapper.get('[role="dialog"]')
    const [input, , last] = [...document.querySelectorAll<HTMLElement>('[role="dialog"] input, [role="dialog"] button')]

    last.focus()
    await dialog.trigger('keydown', { key: 'Tab' })
    expect(document.activeElement).toBe(input)

    await dialog.trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(document.activeElement).toBe(last)
  })
})
