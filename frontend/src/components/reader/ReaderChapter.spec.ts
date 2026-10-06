import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'
import { makeHighlight } from '@/test/readerFixtures'
import ReaderChapter from './ReaderChapter.vue'

const HTML = '<p>Hello brave new world.</p><p>Second paragraph 😀 here.</p>'

function mountChapter(props: { html?: string; highlights?: ReturnType<typeof makeHighlight>[] } = {}) {
  return mount(ReaderChapter, { props: { html: HTML, highlights: [], ...props }, attachTo: document.body, global: { plugins: [i18n] } })
}

describe('ReaderChapter', () => {
  afterEach(() => {
    window.getSelection()?.removeAllRanges()
    document.body.innerHTML = ''
  })

  it('shows the html of the chapter as it is', () => {
    const wrapper = mountChapter()

    expect(wrapper.html()).toContain('<p>Hello brave new world.</p>')
    expect(wrapper.element.textContent).toBe('Hello brave new world.Second paragraph 😀 here.')
  })

  it('draws the highlights over the text', () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 }), makeHighlight({ id: 6, start: 39, end: 41 })] })

    const marks = [...wrapper.element.querySelectorAll('mark')]

    expect(marks.map((mark) => [mark.dataset.quoteId, mark.textContent])).toEqual([
      ['5', 'brave'],
      ['6', '😀'],
    ])
  })

  it('draws a highlight that is added, and takes one away that is removed', async () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 })] })

    await wrapper.setProps({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 }), makeHighlight({ id: 7, start: 0, end: 5 })] })
    expect([...wrapper.element.querySelectorAll('mark')].map((mark) => mark.textContent)).toEqual(['Hello', 'brave'])

    await wrapper.setProps({ highlights: [makeHighlight({ id: 7, start: 0, end: 5 })] })
    expect([...wrapper.element.querySelectorAll('mark')].map((mark) => mark.textContent)).toEqual(['Hello'])
    expect(wrapper.element.textContent).toBe('Hello brave new world.Second paragraph 😀 here.')
  })

  it('draws the highlights again over a new chapter', async () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 })] })

    await wrapper.setProps({ html: '<p>Другий розділ книги.</p>', highlights: [makeHighlight({ id: 8, start: 7, end: 13 })] })

    expect(wrapper.element.textContent).toBe('Другий розділ книги.')
    expect([...wrapper.element.querySelectorAll('mark')].map((mark) => [mark.dataset.quoteId, mark.textContent])).toEqual([['8', 'розділ']])
  })

  it('does not run what a hostile html would run, because it never gets such html: tags it does not know are only text', () => {
    const wrapper = mountChapter({ html: '<p>&lt;img src=x onerror=alert(1)&gt;</p>' })

    expect(wrapper.element.querySelector('img')).toBeNull()
    expect(wrapper.element.textContent).toBe('<img src=x onerror=alert(1)>')
  })

  it('says which quotes were tapped, innermost first', async () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 0, end: 15 }), makeHighlight({ id: 6, start: 6, end: 11 })] })

    await wrapper.get('mark[data-quote-id="6"]').trigger('click')

    expect(wrapper.emitted('highlight-tap')).toEqual([[[6, 5]]])
  })

  it('says nothing for a tap that is not on a highlight', async () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 })] })

    await wrapper.get('p').trigger('click')

    expect(wrapper.emitted('highlight-tap')).toBeUndefined()
  })

  it('says nothing when the click ends a selection', async () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 })] })
    const range = document.createRange()
    range.selectNodeContents(wrapper.get('p').element)
    window.getSelection()!.addRange(range)

    await wrapper.get('mark').trigger('click')

    expect(wrapper.emitted('highlight-tap')).toBeUndefined()
  })

  it('can be opened with the keyboard, with Enter and with Space', async () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 })] })

    expect(wrapper.get('mark').attributes('tabindex')).toBe('0')
    await wrapper.get('mark').trigger('keydown', { key: 'Enter' })
    await wrapper.get('mark').trigger('keydown', { key: ' ' })
    await wrapper.get('mark').trigger('keydown', { key: 'a' })

    expect(wrapper.emitted('highlight-tap')).toEqual([[[5]], [[5]]])
  })

  it('does not scroll the page when Space opens a highlight', async () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 6, end: 11 })] })
    const event = new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true })

    wrapper.get('mark').element.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
  })

  it('names a highlight for a screen reader by the quote, shortened', () => {
    const long = 'Слово '.repeat(40)
    const wrapper = mountChapter({
      highlights: [makeHighlight({ id: 5, start: 6, end: 11, content: 'brave\n  new' }), makeHighlight({ id: 6, start: 22, end: 30, content: long })],
    })

    const [short, shortened] = wrapper.findAll('mark[role="button"]')

    expect(short!.attributes('aria-label')).toBe('Збережена цитата: brave new')
    expect(shortened!.attributes('aria-label')?.length).toBeLessThan(90)
    expect(shortened!.attributes('aria-label')).toMatch(/^Збережена цитата: Слово Слово.*…$/)
  })

  it('makes one button of a quote that is drawn in pieces, and leaves the other pieces to the eye', () => {
    const wrapper = mountChapter({ highlights: [makeHighlight({ id: 5, start: 16, end: 28 })] })

    const pieces = wrapper.findAll('mark[data-quote-id="5"]')

    expect(pieces).toHaveLength(2)
    expect(pieces.map((piece) => piece.attributes('role'))).toEqual(['button', undefined])
    expect(pieces.map((piece) => piece.attributes('tabindex'))).toEqual(['0', undefined])
  })
})
