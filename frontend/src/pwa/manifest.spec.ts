import { describe, expect, it } from 'vitest'
import { router } from '@/router'
import indexHtml from '../../index.html?raw'
import manifestSource from '../../public/manifest.webmanifest?raw'

interface Icon {
  src: string
  sizes: string
  type: string
  purpose?: string
}

interface Manifest {
  name: string
  short_name: string
  lang: string
  start_url: string
  scope: string
  display: string
  icons: Icon[]
  shortcuts: { name: string; url: string }[]
  share_target: { action: string; method: string; params: Record<string, string> }
}

const manifest = JSON.parse(manifestSource) as Manifest

/** The files of public/, as data: URLs, which can be taken apart without Node. */
const publicFiles = import.meta.glob<string>('../../public/**/*.png', { query: '?inline', import: 'default', eager: true })

function pngSize(path: string): string {
  const url = publicFiles[`../../public${path}`]
  expect(url, `${path} is in public/`).toBeDefined()

  const bytes = Uint8Array.from(atob(url!.split(',')[1]!), (char) => char.charCodeAt(0))
  const view = new DataView(bytes.buffer)
  // A PNG starts with an 8-byte signature and the IHDR chunk, which holds the width and the height.
  expect(view.getUint32(0)).toBe(0x89504e47)

  return `${view.getUint32(16)}x${view.getUint32(20)}`
}

describe('the web app manifest', () => {
  it('describes an app that opens in its own window, in Ukrainian', () => {
    expect(manifest.name).toBeTruthy()
    expect(manifest.short_name.length).toBeLessThanOrEqual(12)
    expect(manifest.display).toBe('standalone')
    expect(manifest.lang).toBe('uk')
  })

  it('starts inside its own scope', () => {
    expect(new URL(manifest.start_url, 'https://example.test').pathname.startsWith(manifest.scope)).toBe(true)
  })

  it('has the icons that browsers ask for before they offer to install, and they are as big as they say', () => {
    const sizes = manifest.icons.map((icon) => icon.sizes)
    expect(sizes).toContain('192x192')
    expect(sizes).toContain('512x512')
    expect(manifest.icons.some((icon) => icon.purpose === 'maskable' && icon.sizes === '512x512')).toBe(true)

    for (const icon of manifest.icons) {
      expect(icon.type).toBe('image/png')
      expect(pngSize(icon.src), icon.src).toBe(icon.sizes)
    }
  })

  it('leads its shortcuts to screens that exist', () => {
    expect(manifest.shortcuts.length).toBeGreaterThan(0)

    for (const shortcut of manifest.shortcuts) {
      expect(router.resolve(shortcut.url).name, shortcut.url).not.toBe('not-found')
    }
  })

  it('receives shared text on the screen for a new quote, which reads it from ?text=', () => {
    const { action, method, params } = manifest.share_target

    expect(method).toBe('GET')
    expect(router.resolve(action).name).toBe('quote-new')
    expect(params.text).toBe('text')
  })
})

describe('index.html', () => {
  it('links the manifest and the icons that iPhones and browsers use', () => {
    expect(indexHtml).toContain('<link rel="manifest" href="/manifest.webmanifest"')
    expect(indexHtml).toContain('rel="apple-touch-icon" href="/apple-touch-icon.png"')
    expect(pngSize('/apple-touch-icon.png')).toBe('180x180')
    expect(indexHtml).toContain('rel="icon" href="/favicon.svg"')
  })

  it('colours the browser bar for both themes', () => {
    expect(indexHtml).toContain('media="(prefers-color-scheme: light)"')
    expect(indexHtml).toContain('media="(prefers-color-scheme: dark)"')
  })

  it('covers the whole screen of a phone with a notch', () => {
    expect(indexHtml).toContain('viewport-fit=cover')
  })
})
