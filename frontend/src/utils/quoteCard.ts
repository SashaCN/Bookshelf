export const CARD_WIDTH = 1080
export const CARD_HEIGHT = 1350

export type CardTemplate = 'light' | 'dark' | 'accent'

/** Width in pixels of `text` set at `fontSizePx`. Injected so the layout can be tested without a canvas. */
export type Measure = (text: string, fontSizePx: number) => number

const ELLIPSIS = '…'

/** Whether `line` still fits; a line is never cut mid-word unless the word alone is wider than the card. */
function fits(line: string, maxWidth: number, fontSize: number, measure: Measure): boolean {
  return measure(line, fontSize) <= maxWidth
}

/** Cut a word that is wider than a line into pieces that each fit (at least one character each). */
function breakWord(word: string, maxWidth: number, fontSize: number, measure: Measure): string[] {
  const pieces: string[] = []
  let piece = ''

  // Iterating a string yields whole characters, so an emoji is never split in half.
  for (const char of word) {
    if (piece && !fits(piece + char, maxWidth, fontSize, measure)) {
      pieces.push(piece)
      piece = char
    } else {
      piece += char
    }
  }

  pieces.push(piece)
  return pieces
}

/**
 * Break `text` into lines no wider than `maxWidth`. Line breaks of the text are kept (a run of blank lines becomes
 * one), spaces are collapsed, and a word wider than a line is broken instead of overflowing.
 */
export function wrapText(text: string, maxWidth: number, fontSize: number, measure: Measure): string[] {
  const lines: string[] = []

  for (const paragraph of text.replace(/\r\n?/g, '\n').trim().split('\n')) {
    const words = paragraph.split(/\s+/).filter(Boolean)

    if (!words.length) {
      if (lines.length && lines[lines.length - 1] !== '') lines.push('')
      continue
    }

    let line = ''

    for (const word of words) {
      const candidate = line ? `${line} ${word}` : word

      if (fits(candidate, maxWidth, fontSize, measure)) {
        line = candidate
        continue
      }

      if (line) lines.push(line)

      const pieces = fits(word, maxWidth, fontSize, measure) ? [word] : breakWord(word, maxWidth, fontSize, measure)
      line = pieces.pop() ?? ''
      lines.push(...pieces)
    }

    lines.push(line)
  }

  return lines
}

export interface FitOptions {
  maxWidth: number
  maxHeight: number
  maxFontSize: number
  minFontSize: number
  /** Line height as a multiple of the font size. */
  lineHeight: number
  /** How much smaller each attempt is, in pixels. */
  step?: number
}

export interface FittedText {
  fontSize: number
  /** In pixels. */
  lineHeight: number
  lines: string[]
  /** The text did not fit even at the smallest size, so the last line ends with an ellipsis. */
  truncated: boolean
}

/** Shorten `line` until it and an ellipsis fit. */
function ellipsize(line: string, maxWidth: number, fontSize: number, measure: Measure): string {
  let kept = Array.from(line.trimEnd())

  while (kept.length && !fits(kept.join('') + ELLIPSIS, maxWidth, fontSize, measure)) {
    kept = kept.slice(0, -1)
  }

  return kept.join('').trimEnd() + ELLIPSIS
}

/**
 * Wrap `text` at the largest font size that makes it fit the box. When even the smallest size is too big, the text is
 * cut after the last line that fits and marked with an ellipsis.
 */
export function fitText(text: string, options: FitOptions, measure: Measure): FittedText {
  const { maxWidth, maxHeight, maxFontSize, minFontSize, lineHeight, step = 2 } = options

  let fontSize = maxFontSize

  for (;;) {
    const lines = wrapText(text, maxWidth, fontSize, measure)
    const lineHeightPx = fontSize * lineHeight

    if (lines.length * lineHeightPx <= maxHeight) {
      return { fontSize, lineHeight: lineHeightPx, lines, truncated: false }
    }

    if (fontSize <= minFontSize) {
      const room = Math.max(1, Math.floor(maxHeight / lineHeightPx))
      const kept = lines.slice(0, room)
      while (kept.length > 1 && kept[kept.length - 1] === '') kept.pop()
      kept[kept.length - 1] = ellipsize(kept[kept.length - 1] ?? '', maxWidth, fontSize, measure)

      return { fontSize, lineHeight: lineHeightPx, lines: kept, truncated: true }
    }

    fontSize = Math.max(minFontSize, fontSize - Math.max(1, step))
  }
}

// --- drawing -----------------------------------------------------------------------------------------------------

export interface QuoteCardData {
  text: string
  title: string
  author: string
  /** Already worded for the reader, e.g. "с. 42". */
  pageLabel: string | null
  /** The small mark at the bottom. */
  brand: string
}

interface Palette {
  background: string
  text: string
  muted: string
  rule: string
  mark: string
}

// Fixed colours on purpose: the card is an image that leaves the app, whatever theme the reader uses.
const PALETTES: Record<CardTemplate, Palette> = {
  light: { background: '#f7f3ea', text: '#1d1a14', muted: '#6b6455', rule: '#2d47c8', mark: '#2d47c8' },
  dark: { background: '#12151d', text: '#eceef4', muted: '#9aa3b8', rule: '#93a5ff', mark: '#93a5ff' },
  accent: { background: '#2d47c8', text: '#ffffff', muted: '#d6dcff', rule: '#ffffff', mark: '#aab8ff' },
}

const SERIF = "'Iowan Old Style', 'Palatino Linotype', Georgia, serif"
const SANS = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"

const PADDING = 96
const CONTENT_WIDTH = CARD_WIDTH - PADDING * 2
const TEXT_TOP = 260
const TEXT_BOTTOM = 940
/** The opening mark hangs above the first line: its baseline is this far below the top of the text block. */
const MARK_DROP = 40
const RULE_TOP = 990
const BRAND_BASELINE = 1270

function measureIn(ctx: CanvasRenderingContext2D, font: (sizePx: number) => string): Measure {
  return (text, sizePx) => {
    ctx.font = font(sizePx)
    return ctx.measureText(text).width
  }
}

/** Draw lines top to bottom from `top`, each centred in its own line box. */
function drawLines(ctx: CanvasRenderingContext2D, fitted: FittedText, top: number): void {
  const inset = (fitted.lineHeight - fitted.fontSize) / 2

  fitted.lines.forEach((line, index) => {
    if (line) ctx.fillText(line, PADDING, top + index * fitted.lineHeight + inset)
  })
}

/** Paint the quote card (CARD_WIDTH x CARD_HEIGHT) in one of the templates. */
export function drawQuoteCard(ctx: CanvasRenderingContext2D, data: QuoteCardData, template: CardTemplate): void {
  const palette = PALETTES[template]
  const serif = (weight: string) => (sizePx: number) => `${weight} ${sizePx}px ${SERIF}`.trim()
  const sans = (weight: string) => (sizePx: number) => `${weight} ${sizePx}px ${SANS}`.trim()

  ctx.save()
  ctx.textAlign = 'left'
  ctx.textBaseline = 'top'

  ctx.fillStyle = palette.background
  ctx.fillRect(0, 0, CARD_WIDTH, CARD_HEIGHT)

  // The quote, as large as it can be and centred between the top and the divider.
  const quote = fitText(
    data.text,
    {
      maxWidth: CONTENT_WIDTH,
      maxHeight: TEXT_BOTTOM - TEXT_TOP,
      maxFontSize: 76,
      minFontSize: 26,
      lineHeight: 1.4,
    },
    measureIn(ctx, serif('')),
  )
  const blockTop = TEXT_TOP + (TEXT_BOTTOM - TEXT_TOP - quote.lines.length * quote.lineHeight) / 2

  // The opening mark, which follows the text block so that a short quote does not float away from it.
  ctx.fillStyle = palette.mark
  ctx.font = serif('bold')(260)
  ctx.textBaseline = 'alphabetic'
  ctx.fillText('\u201C', PADDING, blockTop + MARK_DROP)
  ctx.textBaseline = 'top'

  ctx.fillStyle = palette.text
  ctx.font = serif('')(quote.fontSize)
  drawLines(ctx, quote, blockTop)

  // Divider.
  ctx.fillStyle = palette.rule
  ctx.fillRect(PADDING, RULE_TOP, 120, 6)

  // Where it comes from.
  const title = fitText(
    data.title,
    { maxWidth: CONTENT_WIDTH, maxHeight: 116, maxFontSize: 48, minFontSize: 32, lineHeight: 1.25 },
    measureIn(ctx, sans('bold')),
  )
  ctx.fillStyle = palette.text
  ctx.font = sans('bold')(title.fontSize)
  const titleTop = RULE_TOP + 40
  drawLines(ctx, title, titleTop)

  const byline = [data.author, data.pageLabel].filter(Boolean).join(' · ')
  if (byline) {
    const detail = fitText(
      byline,
      { maxWidth: CONTENT_WIDTH, maxHeight: 50, maxFontSize: 36, minFontSize: 24, lineHeight: 1.3 },
      measureIn(ctx, sans('')),
    )
    ctx.fillStyle = palette.muted
    ctx.font = sans('')(detail.fontSize)
    drawLines(ctx, detail, titleTop + title.lines.length * title.lineHeight + 14)
  }

  // The brand.
  ctx.fillStyle = palette.muted
  ctx.font = sans('600')(30)
  ctx.textBaseline = 'alphabetic'
  ctx.fillText(data.brand, PADDING, BRAND_BASELINE)

  ctx.restore()
}
