import { describe, expect, it } from 'vitest'
import indexHtml from '../index.html?raw'
import manifestJson from '../public/manifest.webmanifest?raw'

describe('PWA metadata', () => {
  const manifest = JSON.parse(manifestJson) as {
    name: string
    start_url: string
    display: string
    lang: string
    icons: { src: string; sizes: string; type: string }[]
  }

  it('describes an installable standalone app in Ukrainian', () => {
    expect(manifest).toMatchObject({ name: 'Bookshelf', start_url: '/', display: 'standalone', lang: 'uk' })
    expect(manifest.icons.length).toBeGreaterThan(0)
  })

  it('is linked from the page together with its icon and both theme colours', () => {
    expect(indexHtml).toContain('rel="manifest" href="/manifest.webmanifest"')
    expect(indexHtml).toContain(`rel="icon" href="${manifest.icons[0]!.src}"`)
    expect(indexHtml.match(/name="theme-color"/g)).toHaveLength(2)
  })
})
