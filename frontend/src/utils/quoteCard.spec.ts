import { describe, expect, it, vi } from 'vitest'
import { CARD_HEIGHT, CARD_WIDTH, drawQuoteCard, fitText, wrapText } from './quoteCard'
import type { CardTemplate, FitOptions, Measure, QuoteCardData } from './quoteCard'

/** Every character is half as wide as the font is tall, so 10px type fits 20 characters into 100px. */
const measure: Measure = (text, fontSize) => text.length * fontSize * 0.5

describe('wrapText', () => {
  const wrap = (text: string, maxWidth = 100) => wrapText(text, maxWidth, 10, measure)

  it('keeps a short text on one line', () => {
    expect(wrap('short text')).toEqual(['short text'])
  })

  it('wraps between words, as late as possible', () => {
    expect(wrap('alpha beta gamma delta epsilon')).toEqual(['alpha beta gamma', 'delta epsilon'])
  })

  it('never makes a line wider than the box', () => {
    const text = 'The quick brown fox jumps over the lazy dog and then keeps running through the whole wide field'

    for (const line of wrap(text)) {
      expect(measure(line, 10)).toBeLessThanOrEqual(100)
    }
    expect(wrap(text).join(' ')).toBe(text)
  })

  it('collapses runs of spaces and tabs', () => {
    expect(wrap('a   b\t\tc')).toEqual(['a b c'])
  })

  it('keeps the line breaks of the text', () => {
    expect(wrap('one\ntwo\r\nthree')).toEqual(['one', 'two', 'three'])
  })

  it('turns a run of blank lines into a single blank line', () => {
    expect(wrap('one\n\n\n  \n\ntwo')).toEqual(['one', '', 'two'])
  })

  it('wraps each paragraph on its own', () => {
    expect(wrap('alpha beta gamma delta epsilon\n\nzeta eta')).toEqual(['alpha beta gamma', 'delta epsilon', '', 'zeta eta'])
  })

  it('drops blank lines at the start and the end', () => {
    expect(wrap('\n\n  hello  \n\n')).toEqual(['hello'])
  })

  it('gives nothing for an empty text', () => {
    expect(wrap('')).toEqual([])
    expect(wrap(' \n\t ')).toEqual([])
  })

  it('breaks a word that is wider than the box instead of overflowing', () => {
    const lines = wrap('x'.repeat(45))

    expect(lines).toEqual(['x'.repeat(20), 'x'.repeat(20), 'x'.repeat(5)])
  })

  it('starts a too long word on a fresh line', () => {
    expect(wrap(`hi ${'y'.repeat(25)}`)).toEqual(['hi', 'y'.repeat(20), 'y'.repeat(5)])
  })

  it('goes on with the next words after the last piece of a broken word', () => {
    expect(wrap(`${'z'.repeat(25)} end`)).toEqual(['z'.repeat(20), 'zzzzz end'])
  })

  it('does not split an emoji in half', () => {
    const lines = wrap('😀'.repeat(15))

    expect(lines.map((line) => Array.from(line).length)).toEqual([10, 5])
    expect(lines.join('')).toBe('😀'.repeat(15))
  })

  it('still puts a single character that is wider than the box on a line of its own', () => {
    expect(wrapText('abc', 3, 10, measure)).toEqual(['a', 'b', 'c'])
  })
})

describe('fitText', () => {
  const options: FitOptions = { maxWidth: 100, maxHeight: 100, maxFontSize: 20, minFontSize: 8, lineHeight: 1.5, step: 2 }

  it('uses the largest size for a short text', () => {
    const fitted = fitText('Hi', options, measure)

    expect(fitted).toEqual({ fontSize: 20, lineHeight: 30, lines: ['Hi'], truncated: false })
  })

  it('shrinks a longer text to the largest size that still fits', () => {
    const text = 'word '.repeat(12).trim()

    const fitted = fitText(text, options, measure)

    expect(fitted.fontSize).toBeLessThan(20)
    expect(fitted.truncated).toBe(false)
    expect(fitted.lines.length * fitted.lineHeight).toBeLessThanOrEqual(100)
    for (const line of fitted.lines) {
      expect(measure(line, fitted.fontSize)).toBeLessThanOrEqual(100)
    }

    // Two pixels more would no longer fit.
    const bigger = wrapText(text, 100, fitted.fontSize + 2, measure)
    expect(bigger.length * (fitted.fontSize + 2) * 1.5).toBeGreaterThan(100)
  })

  it('shrinks further for more text', () => {
    const small = fitText('word '.repeat(6).trim(), options, measure)
    const large = fitText('word '.repeat(14).trim(), options, measure)

    expect(large.fontSize).toBeLessThan(small.fontSize)
  })

  it('breaks a long word and shrinks until its pieces fit the height', () => {
    const fitted = fitText('w'.repeat(60), options, measure)

    expect(fitted.truncated).toBe(false)
    expect(fitted.lines.length).toBeGreaterThan(1)
    expect(fitted.lines.join('')).toBe('w'.repeat(60))
    expect(fitted.lines.length * fitted.lineHeight).toBeLessThanOrEqual(100)
  })

  it('cuts the text with an ellipsis when it does not fit even at the smallest size', () => {
    const fitted = fitText('word '.repeat(200).trim(), options, measure)

    expect(fitted.truncated).toBe(true)
    expect(fitted.fontSize).toBe(8)
    expect(fitted.lines).toHaveLength(Math.floor(100 / 12))
    expect(fitted.lines[fitted.lines.length - 1]).toMatch(/…$/)
    for (const line of fitted.lines) {
      expect(measure(line, 8)).toBeLessThanOrEqual(100)
    }
  })

  it('tries the smallest size even when the steps do not land on it', () => {
    const fitted = fitText('word '.repeat(200).trim(), { ...options, minFontSize: 9 }, measure)

    expect(fitted.fontSize).toBe(9)
  })

  it('does not hang on a step of zero', () => {
    const fitted = fitText('word '.repeat(200).trim(), { ...options, step: 0 }, measure)

    expect(fitted.truncated).toBe(true)
  })

  it('never leaves the ellipsis alone on a blank line', () => {
    const fitted = fitText('aaa\n\nbbb', { ...options, maxFontSize: 10, minFontSize: 10, lineHeight: 1, maxHeight: 20 }, measure)

    expect(fitted).toMatchObject({ lines: ['aaa…'], truncated: true })
  })

  it('counts the blank lines between paragraphs', () => {
    const fitted = fitText('one\n\ntwo\n\nthree', options, measure)

    expect(fitted.lines).toEqual(['one', '', 'two', '', 'three'])
    expect(fitted.fontSize).toBeLessThan(20)
  })

  it('has nothing to fit for an empty text', () => {
    expect(fitText('  ', options, measure)).toEqual({ fontSize: 20, lineHeight: 30, lines: [], truncated: false })
  })
})

describe('drawQuoteCard', () => {
  interface Drawn {
    text: string
    x: number
    y: number
    fill: string
    font: string
    size: number
  }

  function fakeContext() {
    const rects: { x: number; y: number; width: number; height: number; fill: string }[] = []
    const texts: Drawn[] = []

    const sizeOf = (font: string) => Number(/(\d+(?:\.\d+)?)px/.exec(font)?.[1] ?? 0)
    const ctx = {
      font: '',
      fillStyle: '',
      textAlign: '',
      textBaseline: '',
      save: vi.fn(),
      restore: vi.fn(),
      fillRect: vi.fn((x: number, y: number, width: number, height: number) => {
        rects.push({ x, y, width, height, fill: ctx.fillStyle })
      }),
      fillText: vi.fn((text: string, x: number, y: number) => {
        texts.push({ text, x, y, fill: ctx.fillStyle, font: ctx.font, size: sizeOf(ctx.font) })
      }),
      measureText: (text: string) => ({ width: measure(text, sizeOf(ctx.font)) }),
    }

    return { ctx, rects, texts, context: ctx as unknown as CanvasRenderingContext2D }
  }

  const data: QuoteCardData = {
    text: 'Stay hungry.',
    title: 'Atomic Habits',
    author: 'James Clear',
    pageLabel: 'с. 42',
    brand: 'Bookshelf',
  }

  function draw(overrides: Partial<QuoteCardData> = {}, template: CardTemplate = 'light') {
    const fake = fakeContext()
    drawQuoteCard(fake.context, { ...data, ...overrides }, template)
    return fake
  }

  it('paints the whole card in the colour of the template', () => {
    const backgrounds = (['light', 'dark', 'accent'] as const).map((template) => draw({}, template).rects[0])

    for (const background of backgrounds) {
      expect(background).toMatchObject({ x: 0, y: 0, width: CARD_WIDTH, height: CARD_HEIGHT })
    }
    expect(new Set(backgrounds.map((background) => background?.fill)).size).toBe(3)
  })

  it('writes the quote, the title, the author with the page, and the brand', () => {
    const { texts } = draw()

    expect(texts.map((drawn) => drawn.text)).toEqual(
      expect.arrayContaining([data.text, 'Atomic Habits', 'James Clear · с. 42', 'Bookshelf']),
    )
  })

  it('leaves the page out when there is none', () => {
    const { texts } = draw({ pageLabel: null })

    expect(texts.map((drawn) => drawn.text)).toContain('James Clear')
  })

  it('writes no author line when neither author nor page is known', () => {
    const { texts } = draw({ author: '', pageLabel: null })

    expect(texts.map((drawn) => drawn.text)).toEqual(['“', data.text, 'Atomic Habits', 'Bookshelf'])
  })

  it('uses a different text colour on each template', () => {
    const colours = (['light', 'dark', 'accent'] as const).map(
      (template) => draw({}, template).texts.find((drawn) => drawn.text === data.text)?.fill,
    )

    expect(new Set(colours).size).toBe(3)
  })

  it('sets a short quote larger than a long one', () => {
    const short = draw({ text: 'Short.' }).texts.find((drawn) => drawn.text === 'Short.')
    const long = draw({ text: 'A long thought. '.repeat(30).trim() }).texts.find((drawn) => drawn.text.startsWith('A long'))

    expect(short?.size).toBeGreaterThan(long?.size ?? Infinity)
  })

  it('keeps the opening mark next to the first line, whatever the length of the quote', () => {
    const marks = ['Short.', 'A long thought. '.repeat(30).trim()].map((text) => {
      const { texts } = draw({ text })
      const mark = texts.find((drawn) => drawn.text === '“')
      const firstLine = texts.find((drawn) => drawn.text !== '“')
      expect(Math.abs((mark?.y ?? 0) - (firstLine?.y ?? 0))).toBeLessThan(60)
      return mark?.y
    })

    expect(marks[0]).not.toBe(marks[1])
  })

  it('draws each line of a multi-paragraph quote separately', () => {
    const { texts } = draw({ text: 'First paragraph.\n\nSecond paragraph.' })

    const written = texts.map((drawn) => drawn.text)
    expect(written).toContain('First paragraph.')
    expect(written).toContain('Second paragraph.')
    expect(written).not.toContain('')
  })

  it('keeps everything inside the card, even for an enormous quote and title', () => {
    const { texts } = draw({
      text: `${'Wisdom '.repeat(400)}${'x'.repeat(300)}`,
      title: 'An Extremely Long Title That Goes On And On And On And On And On And On And On And On And On',
      author: 'A. Very-Long-Hyphenated Author With Many Names And Initials And Co-Authors, And Another One, And More',
    })

    for (const drawn of texts) {
      expect(drawn.x).toBeGreaterThanOrEqual(0)
      expect(measure(drawn.text, drawn.size)).toBeLessThanOrEqual(CARD_WIDTH - 2 * drawn.x)
      expect(drawn.y).toBeGreaterThanOrEqual(0)
      expect(drawn.y + drawn.size).toBeLessThanOrEqual(CARD_HEIGHT)
    }
  })

  it('keeps the quote clear of the title below it', () => {
    const { texts } = draw({ text: 'Wisdom '.repeat(400) })

    const lastQuoteLine = texts.filter((drawn) => drawn.text.includes('Wisdom')).at(-1)
    const title = texts.find((drawn) => drawn.text === 'Atomic Habits')

    expect((lastQuoteLine?.y ?? 0) + (lastQuoteLine?.size ?? 0)).toBeLessThan(title?.y ?? 0)
  })

  it('leaves the context as it found it', () => {
    const { ctx } = draw()

    expect(ctx.save).toHaveBeenCalledTimes(1)
    expect(ctx.restore).toHaveBeenCalledTimes(1)
  })
})
