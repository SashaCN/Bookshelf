import { describe, expect, it } from 'vitest'
import { chapterCorpus } from '@/test/chapterCorpus'
import fuzzCorpus from '@/test/fuzz-corpus.json'
import { applyHighlight, offsetsOfRange, rangeOfOffsets, textOf } from './readerDom'

/**
 * The server counts the offsets of quotes in the text of its sanitized html; the browser counts them in the text it
 * makes of the same html. If a browser were to move or drop text (it does so for loose text in a table and for the
 * newline after <pre>), a quote saved from there would be saved at the wrong place. Every chapter here is written the
 * way the server writes it, so the two must agree.
 */
function chapter(html: string): HTMLElement {
  const root = document.createElement('div')
  root.innerHTML = html
  document.body.appendChild(root)
  return root
}

describe('what the server counts and what the browser makes of it', () => {
  it.each(chapterCorpus.map((entry) => [entry.input, entry] as const))('agree on the text of %j', (_input, entry) => {
    const root = chapter(entry.html)

    expect(textOf(root)).toBe(entry.text)
    // The length that the server says is the length of a JavaScript string, in UTF-16 code units.
    expect(textOf(root).length).toBe(entry.characters)
  })

  it.each(chapterCorpus.filter((entry) => entry.characters >= 2).map((entry) => [entry.input, entry] as const))(
    'agree on where a range is in %j',
    (_input, entry) => {
      const root = chapter(entry.html)
      const start = Math.floor(entry.characters / 3)
      const end = Math.max(start + 1, Math.ceil((entry.characters * 2) / 3))

      const range = rangeOfOffsets(root, start, end)!

      expect(range.toString()).toBe(entry.text.slice(start, end))
      expect(offsetsOfRange(root, range)).toEqual({ start, end })

      // Highlighting it changes none of the text.
      applyHighlight(root, { id: 1, start, end })
      expect(textOf(root)).toBe(entry.text)
    },
  )
})

/**
 * Random chapters, always the same ones, made by a seeded generator on the server (backend/tests/Support/ChapterFuzz.php) and
 * written by its real sanitizer into backend/tests/fixtures/fuzz-corpus.json, of which src/test/fuzz-corpus.json is a copy.
 * Nested cells, loose text in tables, carriage returns, emoji: a browser must read every one of them as the text
 * the server counted.
 */
describe('random chapters', () => {
  it('are a few hundred', () => {
    expect(fuzzCorpus.length).toBeGreaterThan(300)
  })

  it('are read by a browser as the text the server counted, in as many UTF-16 code units', () => {
    const disagreements: { html: string; server: string; browser: string }[] = []

    for (const entry of fuzzCorpus) {
      const root = chapter(entry.html)
      const browser = textOf(root)

      if (browser !== entry.text || browser.length !== entry.characters) {
        disagreements.push({ html: entry.html, server: entry.text, browser })
      }

      root.remove()
    }

    expect(disagreements).toEqual([])
  })
})
