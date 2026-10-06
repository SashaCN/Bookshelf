import { afterEach, describe, expect, it } from 'vitest'
import {
  alignToCodePoints,
  applyHighlight,
  applyHighlights,
  clearHighlights,
  highlightIdsAt,
  offsetOfPoint,
  offsetsOfRange,
  rangeOfOffsets,
  removeHighlight,
  selectedText,
  selectionOf,
  textNodesOf,
  textOf,
  trimOffsets,
} from './readerDom'

/** A piece of a chapter in the document, the way the reader has it. */
function chapter(html: string): HTMLElement {
  const root = document.createElement('div')
  root.innerHTML = html
  document.body.appendChild(root)
  return root
}

function select(range: Range) {
  const selection = window.getSelection()!
  selection.removeAllRanges()
  selection.addRange(range)
  return selection
}

function rangeOf(start: Node, startOffset: number, end: Node, endOffset: number): Range {
  const range = document.createRange()
  range.setStart(start, startOffset)
  range.setEnd(end, endOffset)
  return range
}

afterEach(() => {
  window.getSelection()?.removeAllRanges()
  document.body.innerHTML = ''
})

describe('the text of a chapter', () => {
  it('is the data of its text nodes joined, with nothing between the paragraphs', () => {
    const root = chapter('<h2>Заголовок</h2><p>Перший <em>абзац</em>.</p><p>Другий.</p>')

    expect(textOf(root)).toBe('ЗаголовокПерший абзац.Другий.')
    expect(textNodesOf(root)).toHaveLength(5)
  })

  it('counts UTF-16 code units: an emoji is two', () => {
    const root = chapter('<p>a😀b</p>')

    expect(textOf(root).length).toBe(4)
  })
})

describe('offsetOfPoint', () => {
  it('counts from the start of the chapter to a point in a text node', () => {
    const root = chapter('<p>Hello <em>brave</em> world</p>')
    const brave = root.querySelector('em')!.firstChild!
    const world = root.querySelector('p')!.lastChild!

    expect(offsetOfPoint(root, brave, 0)).toBe(6)
    expect(offsetOfPoint(root, brave, 3)).toBe(9)
    expect(offsetOfPoint(root, world, 6)).toBe(17)
  })

  it('counts a point between elements (a boundary of a selection that is not in a text node)', () => {
    const root = chapter('<p>One</p><p>Two</p><p>Three</p>')

    expect(offsetOfPoint(root, root, 0)).toBe(0)
    expect(offsetOfPoint(root, root, 1)).toBe(3)
    expect(offsetOfPoint(root, root, 3)).toBe(11)
    expect(offsetOfPoint(root, root.children[1]!, 1)).toBe(6)
  })

  it('counts an emoji as two and Cyrillic as one', () => {
    const root = chapter('<p>Їжак 😀 тут</p>')
    const text = root.querySelector('p')!.firstChild!

    expect(offsetOfPoint(root, text, 5)).toBe(5)
    expect(offsetOfPoint(root, text, 6)).toBe(6)
    expect(offsetOfPoint(root, text, 7)).toBe(7)
    expect(offsetOfPoint(root, text, 9)).toBe(9)
  })

  it('says nothing for a point outside of the chapter', () => {
    const root = chapter('<p>Inside</p>')
    const outside = document.createElement('p')
    outside.textContent = 'Outside'
    document.body.appendChild(outside)

    expect(offsetOfPoint(root, outside.firstChild!, 2)).toBeNull()
  })
})

describe('offsetsOfRange', () => {
  it('turns a selection inside one text node into offsets', () => {
    const root = chapter('<p>Hello brave world</p>')
    const text = root.querySelector('p')!.firstChild!

    expect(offsetsOfRange(root, rangeOf(text, 6, text, 11))).toEqual({ start: 6, end: 11 })
  })

  it('turns a selection across elements and paragraphs into offsets', () => {
    const root = chapter('<p>Hello <em>brave</em> new</p><p>world here</p>')
    const brave = root.querySelector('em')!.firstChild!
    const world = root.children[1]!.firstChild!

    // "brave new" + "world"
    expect(offsetsOfRange(root, rangeOf(brave, 2, world, 5))).toEqual({ start: 8, end: 20 })
  })

  it('cuts a selection that sticks out of the chapter to it', () => {
    const root = chapter('<p>Hello world</p>')
    const before = document.createElement('p')
    before.textContent = 'Before'
    document.body.insertBefore(before, root)
    const after = document.createElement('p')
    after.textContent = 'After'
    document.body.appendChild(after)
    const text = root.querySelector('p')!.firstChild!

    expect(offsetsOfRange(root, rangeOf(before.firstChild!, 2, text, 5))).toEqual({ start: 0, end: 5 })
    expect(offsetsOfRange(root, rangeOf(text, 6, after.firstChild!, 3))).toEqual({ start: 6, end: 11 })
    expect(offsetsOfRange(root, rangeOf(before.firstChild!, 0, after.firstChild!, 5))).toEqual({ start: 0, end: 11 })
  })

  it('says nothing for a selection that is empty or outside of the chapter', () => {
    const root = chapter('<p>Hello world</p>')
    const outside = document.createElement('p')
    outside.textContent = 'Outside text'
    document.body.appendChild(outside)
    const text = root.querySelector('p')!.firstChild!

    expect(offsetsOfRange(root, rangeOf(text, 3, text, 3))).toBeNull()
    expect(offsetsOfRange(root, rangeOf(outside.firstChild!, 1, outside.firstChild!, 5))).toBeNull()
  })
})

describe('rangeOfOffsets', () => {
  it('finds the range for offsets, across nested elements', () => {
    const root = chapter('<p>Hello <em>brave</em> new</p><p>world here</p>')

    const range = rangeOfOffsets(root, 8, 20)!

    expect(range.toString()).toBe('ave newworld')
    expect(offsetsOfRange(root, range)).toEqual({ start: 8, end: 20 })
  })

  it('begins in the next node when the start is where a node ends, and ends in the previous one', () => {
    const root = chapter('<p>Hello <em>brave</em> new</p>')
    const em = root.querySelector('em')!.firstChild!

    const range = rangeOfOffsets(root, 6, 11)!

    expect(range.startContainer).toBe(em)
    expect(range.startOffset).toBe(0)
    expect(range.endContainer).toBe(em)
    expect(range.endOffset).toBe(5)
  })

  it('is the inverse of counting, for text with emoji and Cyrillic', () => {
    const root = chapter('<p>Їжак <strong>😀 їсть</strong> яблуко 😀😀 щодня</p>')
    const text = textOf(root)

    for (const word of ['Їжак', '😀 їсть', 'яблуко', '😀😀', 'щодня']) {
      const start = text.indexOf(word)
      const end = start + word.length
      const range = rangeOfOffsets(root, start, end)!

      expect(range.toString()).toBe(word)
      expect(offsetsOfRange(root, range)).toEqual({ start, end })
    }
  })

  it('says nothing for offsets that are not a range of the text', () => {
    const root = chapter('<p>Hello</p>')

    expect(rangeOfOffsets(root, 3, 3)).toBeNull()
    expect(rangeOfOffsets(root, 4, 2)).toBeNull()
    expect(rangeOfOffsets(root, -1, 2)).toBeNull()
    expect(rangeOfOffsets(root, 2, 6)).toBeNull()
    expect(rangeOfOffsets(root, 7, 9)).toBeNull()
    expect(rangeOfOffsets(root, 1.5, 3)).toBeNull()
    expect(rangeOfOffsets(root, 0, Number.NaN)).toBeNull()
  })
})

describe('code points and white space', () => {
  it('moves the ends of a range out of the middle of an emoji', () => {
    const text = 'a😀b' // a, high surrogate, low surrogate, b

    expect(alignToCodePoints(text, 2, 4)).toEqual({ start: 1, end: 4 })
    expect(alignToCodePoints(text, 0, 2)).toEqual({ start: 0, end: 3 })
    expect(alignToCodePoints(text, 1, 3)).toEqual({ start: 1, end: 3 })
    expect(alignToCodePoints(text, 0, 1)).toEqual({ start: 0, end: 1 })
  })

  it('narrows a range to where the text begins and ends', () => {
    expect(trimOffsets('  hello world  ', 0, 15)).toEqual({ start: 2, end: 13 })
    expect(trimOffsets('a b\n', 0, 4)).toEqual({ start: 0, end: 3 })
    expect(trimOffsets('   ', 0, 3)).toBeNull()
    expect(trimOffsets('abc', 1, 1)).toBeNull()
  })
})

describe('selectedText', () => {
  it('is the text of the range', () => {
    const root = chapter('<p>Hello <em>brave</em> new world</p>')

    expect(selectedText(root, 3, 15)).toBe('lo brave new')
  })

  it('has a line break where the range crosses a paragraph or a heading', () => {
    const root = chapter('<h2>Title</h2><p>First paragraph.</p><p>Second one.</p>')

    expect(selectedText(root, 2, 27)).toBe('tle\nFirst paragraph.\nSecond')
  })

  it('has a line break where the range crosses a <br>, and none at an inline element', () => {
    const root = chapter('<p>one<br>two <em>three</em> four</p>')

    expect(selectedText(root, 0, 17)).toBe('one\ntwo three four')
  })

  it('has a line break between table cells and list items', () => {
    const root = chapter('<ul><li>a</li><li>b</li></ul><table><tr><td>c</td><td>d</td></tr></table>')

    expect(selectedText(root, 0, 4)).toBe('a\nb\nc\nd')
  })
})

describe('selectionOf', () => {
  it('gives the offsets and the text of what is selected', () => {
    const root = chapter('<p>Hello <em>brave</em> new world</p>')
    const brave = root.querySelector('em')!.firstChild!
    const tail = root.querySelector('p')!.lastChild!

    const found = selectionOf(root, select(rangeOf(brave, 0, tail, 4)))

    expect(found).toEqual({ start: 6, end: 15, text: 'brave new' })
  })

  it('trims white space off both ends', () => {
    const root = chapter('<p>Hello  brave  world</p>')
    const text = root.querySelector('p')!.firstChild!

    expect(selectionOf(root, select(rangeOf(text, 5, text, 13)))).toEqual({ start: 7, end: 12, text: 'brave' })
  })

  it('does not begin or end in the middle of an emoji', () => {
    const root = chapter('<p>a😀b</p>')
    const text = root.querySelector('p')!.firstChild!

    expect(selectionOf(root, select(rangeOf(text, 2, text, 4)))).toEqual({ start: 1, end: 4, text: '😀b' })
  })

  it('says nothing for no selection, a collapsed one, white space only, or one elsewhere', () => {
    const root = chapter('<p>Hello   world</p>')
    const text = root.querySelector('p')!.firstChild!
    const elsewhere = document.createElement('p')
    elsewhere.textContent = 'Elsewhere'
    document.body.appendChild(elsewhere)

    expect(selectionOf(root, null)).toBeNull()
    expect(selectionOf(root, window.getSelection())).toBeNull()
    expect(selectionOf(root, select(rangeOf(text, 2, text, 2)))).toBeNull()
    expect(selectionOf(root, select(rangeOf(text, 5, text, 8)))).toBeNull()
    expect(selectionOf(root, select(rangeOf(elsewhere.firstChild!, 0, elsewhere.firstChild!, 4)))).toBeNull()
  })
})

describe('highlights', () => {
  it('wraps the text of a range in a mark, and leaves the text of the chapter as it was', () => {
    const root = chapter('<p>Hello brave world</p>')
    const before = textOf(root)

    const marks = applyHighlight(root, { id: 7, start: 6, end: 11 })

    expect(marks).toHaveLength(1)
    expect(marks[0]!.outerHTML).toBe('<mark data-quote-id="7" tabindex="0" role="button">brave</mark>')
    expect(root.innerHTML).toBe('<p>Hello <mark data-quote-id="7" tabindex="0" role="button">brave</mark> world</p>')
    expect(textOf(root)).toBe(before)
  })

  it('makes one button of a highlight, however many pieces it has: only the first is focusable, with a role and a name', () => {
    const root = chapter('<p>Hello <em>brave</em> new</p><p>world here</p>')

    const marks = applyHighlight(root, { id: 1, start: 8, end: 20, label: 'Quote: ave new world' })

    expect(marks).toHaveLength(3)
    expect(marks.map((mark) => mark.getAttribute('tabindex'))).toEqual(['0', null, null])
    expect(marks.map((mark) => mark.getAttribute('role'))).toEqual(['button', null, null])
    expect(marks.map((mark) => mark.getAttribute('aria-label'))).toEqual(['Quote: ave new world', null, null])
    expect(root.querySelectorAll('[tabindex]')).toHaveLength(1)
  })

  it('gives a button without a name only the name its text gives it', () => {
    const root = chapter('<p>Hello brave world</p>')

    const [mark] = applyHighlight(root, { id: 1, start: 6, end: 11 })

    expect(mark!.getAttribute('role')).toBe('button')
    expect(mark!.hasAttribute('aria-label')).toBe(false)
  })

  it('puts one mark in every text node of a range that crosses elements', () => {
    const root = chapter('<p>Hello <em>brave</em> new</p><p>world here</p>')
    const before = textOf(root)

    const marks = applyHighlight(root, { id: 1, start: 8, end: 20 })

    expect(marks.map((mark) => mark.textContent)).toEqual(['ave', ' new', 'world'])
    expect(root.innerHTML).toBe(
      '<p>Hello <em>br<mark data-quote-id="1" tabindex="0" role="button">ave</mark></em><mark data-quote-id="1"> new</mark></p>'
        + '<p><mark data-quote-id="1">world</mark> here</p>',
    )
    expect(textOf(root)).toBe(before)
  })

  it('keeps every offset valid once something is highlighted, so highlights can follow one another', () => {
    const root = chapter('<p>Hello <em>brave</em> new world</p>')
    const text = textOf(root)

    applyHighlight(root, { id: 1, start: 6, end: 11 })
    applyHighlight(root, { id: 2, start: 11, end: 15 })
    applyHighlight(root, { id: 3, start: 0, end: 5 })

    const marked = (id: number) => [...root.querySelectorAll(`mark[data-quote-id="${id}"]`)].map((mark) => mark.textContent).join('')

    expect(marked(1)).toBe('brave')
    expect(marked(2)).toBe(' new')
    expect(marked(3)).toBe('Hello')
    expect(textOf(root)).toBe(text)
  })

  it('lets highlights overlap, one inside the other', () => {
    const root = chapter('<p>Hello brave new world</p>')

    applyHighlight(root, { id: 1, start: 0, end: 15 })
    applyHighlight(root, { id: 2, start: 6, end: 11 })
    applyHighlight(root, { id: 3, start: 9, end: 19 })

    const marked = (id: number) => [...root.querySelectorAll(`mark[data-quote-id="${id}"]`)].map((mark) => mark.textContent).join('')

    expect(marked(1)).toBe('Hello brave new')
    expect(marked(2)).toBe('brave')
    expect(marked(3)).toBe('ve new wor')
    expect(textOf(root)).toBe('Hello brave new world')
  })

  it('highlights emoji and Cyrillic by UTF-16 offsets', () => {
    const root = chapter('<p>Їжак 😀 їсть яблуко</p>')
    const text = textOf(root)
    const start = text.indexOf('😀 їсть')

    const marks = applyHighlight(root, { id: 1, start, end: start + '😀 їсть'.length })

    expect(marks.map((mark) => mark.textContent)).toEqual(['😀 їсть'])
    expect(textOf(root)).toBe(text)
  })

  it('draws nothing for offsets that are not a range of the text', () => {
    const root = chapter('<p>Hello</p>')

    expect(applyHighlight(root, { id: 1, start: 3, end: 3 })).toEqual([])
    expect(applyHighlight(root, { id: 1, start: 3, end: 30 })).toEqual([])
    expect(root.querySelector('mark')).toBeNull()
  })

  it('draws a list of highlights in any order', () => {
    const first = chapter('<p>Hello brave new world</p>')
    applyHighlights(first, [
      { id: 2, start: 12, end: 15 },
      { id: 1, start: 6, end: 11 },
    ])
    const second = chapter('<p>Hello brave new world</p>')
    applyHighlights(second, [
      { id: 1, start: 6, end: 11 },
      { id: 2, start: 12, end: 15 },
    ])

    expect(first.innerHTML).toBe(second.innerHTML)
    expect(first.querySelectorAll('mark')).toHaveLength(2)
  })

  it('takes one highlight away and puts the text back together', () => {
    const root = chapter('<p>Hello brave world</p>')
    applyHighlight(root, { id: 1, start: 6, end: 11 })
    applyHighlight(root, { id: 2, start: 0, end: 5 })

    removeHighlight(root, 1)

    expect(root.innerHTML).toBe('<p><mark data-quote-id="2" tabindex="0" role="button">Hello</mark> brave world</p>')
    expect(root.querySelector('p')!.childNodes).toHaveLength(2)
  })

  it('takes away only the highlight that is asked for when two overlap', () => {
    const root = chapter('<p>Hello brave new world</p>')
    applyHighlight(root, { id: 1, start: 0, end: 15 })
    applyHighlight(root, { id: 2, start: 6, end: 11 })

    removeHighlight(root, 2)

    expect([...root.querySelectorAll('mark')].map((mark) => mark.textContent)).toEqual(['Hello brave new'])
    expect(root.querySelector('mark')!.childNodes).toHaveLength(1)
    expect(textOf(root)).toBe('Hello brave new world')
  })

  it('takes every highlight away', () => {
    const root = chapter('<p>Hello <em>brave</em> new world</p>')
    const original = root.innerHTML
    applyHighlight(root, { id: 1, start: 3, end: 17 })
    applyHighlight(root, { id: 2, start: 0, end: 21 })

    clearHighlights(root)

    expect(root.innerHTML).toBe(original)
    expect(textNodesOf(root)).toHaveLength(3)
  })

  it('tells which highlights a node is in, the innermost first', () => {
    const root = chapter('<p>Hello brave new world</p>')
    applyHighlight(root, { id: 1, start: 0, end: 15 })
    applyHighlight(root, { id: 2, start: 6, end: 11 })
    const inner = root.querySelector('mark[data-quote-id="2"]')!

    expect(highlightIdsAt(inner.firstChild, root)).toEqual([2, 1])
    expect(highlightIdsAt(inner, root)).toEqual([2, 1])
    expect(highlightIdsAt(root.querySelector('p'), root)).toEqual([])
    expect(highlightIdsAt(null, root)).toEqual([])
  })
})
