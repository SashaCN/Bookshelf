/**
 * The offset maths of the reader: from a selection in the text of a chapter to offsets and back, and the highlights
 * that are drawn over them.
 *
 * THE UNIT. An offset is a count of UTF-16 code units, the unit of a JavaScript string index: "a" is one, "я" is one,
 * an emoji such as "😀" is two. The text of a chapter is the data of all its text nodes joined in document order
 * (what `textContent` is), and an offset is an index into that string. The server counts the same way (see
 * backend/app/Services/Epub/Text.php), so a range saved here is the same range there, whatever the language.
 *
 * Highlights are `<mark data-quote-id="...">` elements wrapped around the text nodes of a range, one per text node
 * (a range crosses elements, and an element can not be cut). Wrapping splits text nodes but never changes the text
 * of the chapter, so every offset stays valid, and highlights may overlap.
 */

const MARK_SELECTOR = 'mark[data-quote-id]'

/** Elements that start a new line: a selection that crosses them has a line break there. */
const BLOCK_TAGS = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'LI', 'UL', 'OL', 'PRE', 'DIV', 'TABLE', 'TR', 'TD', 'TH'])

export interface TextRange {
  start: number
  end: number
}

export interface SelectedText extends TextRange {
  /** The selected text, with a line break where it crosses a paragraph. */
  text: string
}

export interface HighlightSpan extends TextRange {
  /** The id of the quote. */
  id: number
  /** What the highlight is called for a screen reader: it is a button that opens the quote. */
  label?: string
}

function documentOf(node: Node): Document {
  return node.ownerDocument ?? (node as Document)
}

/** The text nodes of an element in document order. */
export function textNodesOf(root: Node): Text[] {
  const walker = documentOf(root).createTreeWalker(root, NodeFilter.SHOW_TEXT)
  const nodes: Text[] = []

  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    nodes.push(node as Text)
  }

  return nodes
}

/** The text of an element: what `textContent` is, and what every offset counts in. */
export function textOf(root: Node): string {
  return textNodesOf(root)
    .map((node) => node.data)
    .join('')
}

/**
 * The offset of a point of the DOM (a node and an offset in it, as a selection has them) in the text of `root`, or
 * null when the point is not inside it.
 */
export function offsetOfPoint(root: Node, node: Node, offset: number): number | null {
  if (!root.contains(node)) {
    return null
  }

  const probe = documentOf(root).createRange()
  probe.selectNodeContents(root)
  probe.setEnd(node, offset)

  return probe.toString().length
}

/**
 * The offsets of a range of the DOM in the text of `root`. A range that sticks out of `root` is cut to it. Null when
 * nothing of the range is inside, or what is inside is empty.
 */
export function offsetsOfRange(root: Node, range: Range): TextRange | null {
  if (!range.intersectsNode(root)) {
    return null
  }

  const bounds = documentOf(root).createRange()
  bounds.selectNodeContents(root)

  const startsBefore = range.compareBoundaryPoints(Range.START_TO_START, bounds) < 0
  const endsAfter = range.compareBoundaryPoints(Range.END_TO_END, bounds) > 0

  const start = startsBefore ? 0 : offsetOfPoint(root, range.startContainer, range.startOffset)
  const end = endsAfter ? textOf(root).length : offsetOfPoint(root, range.endContainer, range.endOffset)

  return start === null || end === null || end <= start ? null : { start, end }
}

/**
 * The range of the DOM that covers the offsets `start` to `end` of the text of `root`, or null when the offsets
 * are not a range of that text.
 */
export function rangeOfOffsets(root: Node, start: number, end: number): Range | null {
  if (!Number.isInteger(start) || !Number.isInteger(end) || start < 0 || end <= start) {
    return null
  }

  let from: [Text, number] | null = null
  let to: [Text, number] | null = null
  let position = 0

  for (const node of textNodesOf(root)) {
    const next = position + node.data.length

    if (from === null && start < next) {
      from = [node, start - position]
    }

    if (from !== null && end <= next) {
      to = [node, end - position]
      break
    }

    position = next
  }

  if (from === null || to === null) {
    return null
  }

  const range = documentOf(root).createRange()
  range.setStart(from[0], from[1])
  range.setEnd(to[0], to[1])

  return range
}

function isHighSurrogate(code: number): boolean {
  return code >= 0xd800 && code <= 0xdbff
}

function isLowSurrogate(code: number): boolean {
  return code >= 0xdc00 && code <= 0xdfff
}

/**
 * Moves the ends of a range out of the middle of an emoji (a pair of code units that are one character), so it
 * never begins or ends with half of one.
 */
export function alignToCodePoints(text: string, start: number, end: number): TextRange {
  const alignedStart = start > 0 && isLowSurrogate(text.charCodeAt(start)) && isHighSurrogate(text.charCodeAt(start - 1)) ? start - 1 : start
  const alignedEnd = end < text.length && isLowSurrogate(text.charCodeAt(end)) && isHighSurrogate(text.charCodeAt(end - 1)) ? end + 1 : end

  return { start: alignedStart, end: alignedEnd }
}

/** Narrows a range so that it neither begins nor ends with white space; null when nothing else is left. */
export function trimOffsets(text: string, start: number, end: number): TextRange | null {
  let from = start
  let to = end

  while (from < to && /\s/.test(text.charAt(from))) from++
  while (to > from && /\s/.test(text.charAt(to - 1))) to--

  return to > from ? { start: from, end: to } : null
}

function blockOf(node: Node, root: Node): Node {
  for (let current = node.parentNode; current && current !== root; current = current.parentNode) {
    if (current.nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has((current as Element).tagName)) {
      return current
    }
  }

  return root
}

/**
 * The text between two offsets, the way a reader would copy it: with a line break where it crosses a paragraph
 * or a `<br>`, which the text of the chapter itself does not have.
 */
export function selectedText(root: Node, start: number, end: number): string {
  const walker = documentOf(root).createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
    acceptNode: (node) =>
      node.nodeType === Node.TEXT_NODE || (node as Element).tagName === 'BR' ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP,
  })

  let text = ''
  let position = 0
  let previousBlock: Node | null = null
  let lineBreak = false

  for (let node = walker.nextNode(); node && position < end; node = walker.nextNode()) {
    if (node.nodeType !== Node.TEXT_NODE) {
      lineBreak = true
      continue
    }

    const data = (node as Text).data
    const next = position + data.length

    if (next > start) {
      const part = data.slice(Math.max(start - position, 0), Math.min(end - position, data.length))
      const block = blockOf(node, root)

      if (text !== '' && (lineBreak || block !== previousBlock)) {
        text += '\n'
      }

      text += part
      previousBlock = block
      lineBreak = false
    }

    position = next
  }

  return text
}

/**
 * What is selected in `root`, as offsets into its text and as text; null when nothing is, or only white space.
 * The ends are moved out of the middle of an emoji and trimmed of white space.
 */
export function selectionOf(root: HTMLElement, selection: Selection | null): SelectedText | null {
  if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
    return null
  }

  const offsets = offsetsOfRange(root, selection.getRangeAt(0))

  if (!offsets) {
    return null
  }

  const text = textOf(root)
  const aligned = alignToCodePoints(text, offsets.start, offsets.end)
  const trimmed = trimOffsets(text, aligned.start, aligned.end)

  return trimmed ? { ...trimmed, text: selectedText(root, trimmed.start, trimmed.end) } : null
}

/**
 * Draws a highlight over the text between the offsets: a `<mark>` around each text node (or part of one) in the
 * range. Returns the marks, none when the offsets are not a range of the text.
 *
 * A highlight is one thing to a reader, however many pieces it is made of: the first piece is the button (focusable,
 * with a role and a name); the others are only drawn.
 */
export function applyHighlight(root: HTMLElement, { id, start, end, label }: HighlightSpan): HTMLElement[] {
  if (!rangeOfOffsets(root, start, end)) {
    return []
  }

  // What to wrap is worked out first: wrapping splits text nodes, which would throw the walk off.
  const slices: { node: Text; from: number; to: number }[] = []
  let position = 0

  for (const node of textNodesOf(root)) {
    const next = position + node.data.length
    const from = Math.max(start, position) - position
    const to = Math.min(end, next) - position

    if (to > from) {
      slices.push({ node, from, to })
    }

    position = next

    if (position >= end) {
      break
    }
  }

  const document = documentOf(root)
  const marks: HTMLElement[] = []

  for (const { node, from, to } of slices) {
    let target = node

    if (to < node.data.length) {
      node.splitText(to)
    }

    if (from > 0) {
      target = node.splitText(from)
    }

    const mark = document.createElement('mark')
    mark.dataset.quoteId = String(id)

    if (marks.length === 0) {
      mark.tabIndex = 0
      mark.setAttribute('role', 'button')

      if (label) {
        mark.setAttribute('aria-label', label)
      }
    }

    target.parentNode?.insertBefore(mark, target)
    mark.appendChild(target)
    marks.push(mark)
  }

  return marks
}

/** Draws highlights in the order of the text; they may overlap. */
export function applyHighlights(root: HTMLElement, highlights: readonly HighlightSpan[]): void {
  for (const highlight of [...highlights].sort((a, b) => a.start - b.start || a.end - b.end || a.id - b.id)) {
    applyHighlight(root, highlight)
  }
}

function unwrap(marks: Iterable<Element>): void {
  const parents = new Set<Node>()

  for (const mark of marks) {
    const parent = mark.parentNode

    if (!parent) continue

    while (mark.firstChild) {
      parent.insertBefore(mark.firstChild, mark)
    }

    parent.removeChild(mark)
    parents.add(parent)
  }

  // The pieces a text node was split into become one again.
  for (const parent of parents) {
    if (parent.isConnected) parent.normalize()
  }
}

/** Takes away the highlight of a quote. */
export function removeHighlight(root: HTMLElement, id: number): void {
  unwrap(root.querySelectorAll(`${MARK_SELECTOR}[data-quote-id="${id}"]`))
}

/** Takes away every highlight. */
export function clearHighlights(root: HTMLElement): void {
  unwrap(root.querySelectorAll(MARK_SELECTOR))
}

/** The ids of the quotes whose highlights contain a node, the innermost first. */
export function highlightIdsAt(node: Node | null, root: Node): number[] {
  const ids: number[] = []

  for (let current: Node | null = node; current && current !== root; current = current.parentNode) {
    if (current.nodeType === Node.ELEMENT_NODE && (current as Element).matches(MARK_SELECTOR)) {
      ids.push(Number((current as HTMLElement).dataset.quoteId))
    }
  }

  return ids
}
