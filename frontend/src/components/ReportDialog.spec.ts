import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { createTestEnvironment } from '@/test/utils'
import type { ReportTarget } from '@/types/social'
import ReportDialog from './ReportDialog.vue'

vi.mock('@/api/social', () => ({
  socialApi: { report: vi.fn() },
}))

function mountDialog(target: ReportTarget = { kind: 'post', id: 5 }) {
  const { plugins } = createTestEnvironment()
  return mount(ReportDialog, { props: { target }, global: { plugins }, attachTo: document.body })
}

function radios(wrapper: ReturnType<typeof mount>) {
  return wrapper.findAll('input[type="radio"]')
}

async function send(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('ReportDialog', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    document.body.innerHTML = ''
  })

  it('is a modal dialog with a title', () => {
    const wrapper = mountDialog()

    const dialog = wrapper.get('[role="dialog"]')

    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(wrapper.get(`#${dialog.attributes('aria-labelledby')}`).text()).toBe('Поскаржитись')
    wrapper.unmount()
  })

  it('offers the three reasons, none of them chosen', () => {
    const wrapper = mountDialog()

    expect(wrapper.findAll('label').slice(0, 3).map((label) => label.text())).toEqual([
      'Спам',
      'Образи чи неприйнятний вміст',
      'Інше',
    ])
    expect(radios(wrapper).map((radio) => (radio.element as HTMLInputElement).checked)).toEqual([false, false, false])
    wrapper.unmount()
  })

  it('moves the focus into the dialog', async () => {
    const wrapper = mountDialog()
    await flushPromises()

    expect(document.activeElement).toBe(wrapper.get('[role="dialog"]').element)
    wrapper.unmount()
  })

  it('reports a post with the reason and the note', async () => {
    vi.mocked(socialApi.report).mockResolvedValue({})
    const wrapper = mountDialog({ kind: 'post', id: 5 })

    await radios(wrapper)[1]!.setValue(true)
    await wrapper.get('textarea').setValue('  Insults other readers.  ')
    await send(wrapper)

    expect(socialApi.report).toHaveBeenCalledWith({ kind: 'post', id: 5 }, { reason: 'abuse', note: 'Insults other readers.' })
    wrapper.unmount()
  })

  it('reports a comment, and sends no note when there is none', async () => {
    vi.mocked(socialApi.report).mockResolvedValue({})
    const wrapper = mountDialog({ kind: 'comment', id: 8 })

    await radios(wrapper)[0]!.setValue(true)
    await send(wrapper)

    expect(socialApi.report).toHaveBeenCalledWith({ kind: 'comment', id: 8 }, { reason: 'spam', note: null })
    wrapper.unmount()
  })

  it('refuses to send without a reason and says so', async () => {
    const wrapper = mountDialog()

    await send(wrapper)

    expect(socialApi.report).not.toHaveBeenCalled()
    expect(wrapper.get('[role="alert"]').text()).toBe('Оберіть причину скарги.')
    wrapper.unmount()
  })

  it('thanks the reader once the report is sent, and tells the parent', async () => {
    vi.mocked(socialApi.report).mockResolvedValue({})
    const wrapper = mountDialog()
    await radios(wrapper)[2]!.setValue(true)

    await send(wrapper)

    expect(wrapper.get('[role="status"]').text()).toBe('Дякуємо! Скаргу передано модераторам.')
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.emitted('reported')).toHaveLength(1)

    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('says that the reader has reported it before', async () => {
    vi.mocked(socialApi.report).mockRejectedValue(new ApiError(422, { errors: { reason: ['social.already_reported'] } }))
    const wrapper = mountDialog()
    await radios(wrapper)[0]!.setValue(true)

    await send(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Ви вже скаржилися на це.')
    expect(wrapper.emitted('reported')).toBeUndefined()
    wrapper.unmount()
  })

  it('says that a reader cannot report their own post', async () => {
    vi.mocked(socialApi.report).mockRejectedValue(new ApiError(422, { errors: { reason: ['social.cannot_report_own'] } }))
    const wrapper = mountDialog()
    await radios(wrapper)[0]!.setValue(true)

    await send(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toContain('Скаржитися на власну публікацію')
    wrapper.unmount()
  })

  it('shows a note that is too long next to the note', async () => {
    vi.mocked(socialApi.report).mockRejectedValue(new ApiError(422, { errors: { note: ['The note field must not be greater than 500 characters.'] } }))
    const wrapper = mountDialog()
    await radios(wrapper)[0]!.setValue(true)

    await send(wrapper)

    expect(wrapper.get('#report-note-error').text()).toBe('Пояснення має бути не довшим за 500 символів.')
    expect(wrapper.get('textarea').attributes('aria-invalid')).toBe('true')
    wrapper.unmount()
  })

  it('says why when the server turns the report down, and lets the reader try again', async () => {
    vi.mocked(socialApi.report).mockRejectedValueOnce(new ApiError(429, null)).mockResolvedValueOnce({})
    const wrapper = mountDialog()
    await radios(wrapper)[0]!.setValue(true)

    await send(wrapper)
    expect(wrapper.get('[role="alert"]').text()).toContain('Забагато запитів')
    expect(wrapper.find('form').exists()).toBe(true)

    await send(wrapper)
    expect(wrapper.emitted('reported')).toHaveLength(1)
    wrapper.unmount()
  })

  it('counts the characters of the note, up to 500', async () => {
    const wrapper = mountDialog()

    await wrapper.get('textarea').setValue('abc')

    expect(wrapper.text()).toContain('3 з 500')
    expect(wrapper.get('textarea').attributes('maxlength')).toBe('500')
    wrapper.unmount()
  })

  it('closes on cancel, on Escape and on a tap beside the dialog', async () => {
    const wrapper = mountDialog()

    await wrapper.findAll('button').find((button) => button.text() === 'Скасувати')!.trigger('click')
    await wrapper.get('[role="dialog"]').trigger('keydown.esc')
    await wrapper.get('[role="dialog"]').element.parentElement!.click()

    expect(wrapper.emitted('close')).toHaveLength(3)
    wrapper.unmount()
  })

  describe('after the report is sent', () => {
    async function sent() {
      vi.mocked(socialApi.report).mockResolvedValue({})
      const wrapper = mountDialog()
      await radios(wrapper)[0]!.setValue(true)
      await send(wrapper)
      return wrapper
    }

    it('puts the focus on the button that closes the dialog, not nowhere', async () => {
      const wrapper = await sent()

      const close = wrapper.findAll('button').find((button) => button.text() === 'Закрити')!

      expect(document.activeElement).toBe(close.element)
      wrapper.unmount()
    })

    it('still closes on Escape', async () => {
      const wrapper = await sent()

      await wrapper.get('[role="dialog"]').trigger('keydown.esc')

      expect(wrapper.emitted('close')).toHaveLength(1)
      wrapper.unmount()
    })

    it('keeps Tab, and Shift+Tab, on the only button left', async () => {
      const wrapper = await sent()
      const close = wrapper.findAll('button').find((button) => button.text() === 'Закрити')!

      const forward = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true })
      close.element.dispatchEvent(forward)
      const backward = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true })
      close.element.dispatchEvent(backward)

      expect(forward.defaultPrevented).toBe(true)
      expect(backward.defaultPrevented).toBe(true)
      expect(document.activeElement).toBe(close.element)
      wrapper.unmount()
    })

    it('closes on the button', async () => {
      const wrapper = await sent()

      await wrapper.findAll('button').find((button) => button.text() === 'Закрити')!.trigger('click')

      expect(wrapper.emitted('close')).toHaveLength(1)
      wrapper.unmount()
    })
  })

  it('gives the focus back to where it was when the dialog goes', async () => {
    const opener = document.createElement('button')
    document.body.appendChild(opener)
    opener.focus()
    const wrapper = mountDialog()
    await flushPromises()
    expect(document.activeElement).toBe(wrapper.get('[role="dialog"]').element)

    wrapper.unmount()

    expect(document.activeElement).toBe(opener)
  })

  it('does not fail when the place the focus came from is gone', async () => {
    const opener = document.createElement('button')
    document.body.appendChild(opener)
    opener.focus()
    const wrapper = mountDialog()
    opener.remove()

    expect(() => wrapper.unmount()).not.toThrow()
  })

  it('keeps Tab inside the dialog', async () => {
    const wrapper = mountDialog()
    await flushPromises()
    const cancel = wrapper.findAll('button').find((button) => button.text() === 'Скасувати')!
    ;(cancel.element as HTMLButtonElement).focus()

    await cancel.trigger('keydown', { key: 'Tab' })

    expect(document.activeElement).toBe(radios(wrapper)[0]!.element)
    wrapper.unmount()
  })
})
