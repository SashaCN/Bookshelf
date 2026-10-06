import { nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  DEFAULT_SETTINGS,
  FONT_SIZE,
  LINE_HEIGHT,
  loadSettings,
  normalizeSettings,
  SETTINGS_STORAGE_KEY,
  themeVariables,
  useReaderSettings,
} from './useReaderSettings'

describe('normalizeSettings', () => {
  it('keeps good settings as they are', () => {
    expect(normalizeSettings({ fontSize: 22, lineHeight: 1.8, font: 'sans', theme: 'sepia' })).toEqual({
      fontSize: 22,
      lineHeight: 1.8,
      font: 'sans',
      theme: 'sepia',
    })
  })

  it('replaces what is missing or makes no sense by the defaults', () => {
    expect(normalizeSettings(null)).toEqual(DEFAULT_SETTINGS)
    expect(normalizeSettings('text')).toEqual(DEFAULT_SETTINGS)
    expect(normalizeSettings([])).toEqual(DEFAULT_SETTINGS)
    expect(normalizeSettings({ fontSize: '20', lineHeight: Number.NaN, font: 'comic', theme: 'neon' })).toEqual(DEFAULT_SETTINGS)
  })

  it('brings numbers into the allowed range', () => {
    expect(normalizeSettings({ fontSize: 3, lineHeight: 0.2 })).toMatchObject({ fontSize: FONT_SIZE.min, lineHeight: LINE_HEIGHT.min })
    expect(normalizeSettings({ fontSize: 300, lineHeight: 9 })).toMatchObject({ fontSize: FONT_SIZE.max, lineHeight: LINE_HEIGHT.max })
    expect(normalizeSettings({ fontSize: 18.4, lineHeight: 1.5999999 })).toMatchObject({ fontSize: 18, lineHeight: 1.6 })
  })
})

describe('the settings of the reader', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('start with the defaults', () => {
    expect(useReaderSettings().settings).toEqual({ fontSize: 18, lineHeight: 1.6, font: 'serif', theme: 'auto' })
  })

  it('are read from the device', () => {
    localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify({ fontSize: 24, lineHeight: 2, font: 'sans', theme: 'dark' }))

    expect(useReaderSettings().settings).toEqual({ fontSize: 24, lineHeight: 2, font: 'sans', theme: 'dark' })
  })

  it('are the defaults when what is stored is not JSON', () => {
    localStorage.setItem(SETTINGS_STORAGE_KEY, '{not json')

    expect(loadSettings()).toEqual(DEFAULT_SETTINGS)
  })

  it('are kept on the device when they change', async () => {
    const { settings } = useReaderSettings()

    settings.theme = 'sepia'
    settings.fontSize = 21
    await nextTick()

    expect(JSON.parse(localStorage.getItem(SETTINGS_STORAGE_KEY)!)).toEqual({ fontSize: 21, lineHeight: 1.6, font: 'serif', theme: 'sepia' })
    expect(useReaderSettings().settings.theme).toBe('sepia')
  })

  it('work when the storage cannot be read or written', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('full', 'QuotaExceededError')
    })

    const { settings } = useReaderSettings()
    settings.fontSize = 20
    await nextTick()

    expect(settings.fontSize).toBe(20)
    expect(settings.theme).toBe('auto')
  })

  it('change a step at a time and stay in range', () => {
    const { settings, stepFontSize, stepLineHeight } = useReaderSettings()

    stepFontSize(1)
    stepLineHeight(1)
    expect(settings.fontSize).toBe(19)
    expect(settings.lineHeight).toBe(1.7)

    for (let i = 0; i < 40; i++) {
      stepFontSize(1)
      stepLineHeight(1)
    }
    expect(settings.fontSize).toBe(FONT_SIZE.max)
    expect(settings.lineHeight).toBe(LINE_HEIGHT.max)

    for (let i = 0; i < 40; i++) {
      stepFontSize(-1)
      stepLineHeight(-1)
    }
    expect(settings.fontSize).toBe(FONT_SIZE.min)
    expect(settings.lineHeight).toBe(LINE_HEIGHT.min)
  })

  it('go back to the defaults', () => {
    const { settings, reset } = useReaderSettings()
    settings.theme = 'dark'
    settings.font = 'sans'
    settings.fontSize = 28

    reset()

    expect(settings).toEqual(DEFAULT_SETTINGS)
  })

  it('become the CSS variables of the reader', () => {
    const { settings, style } = useReaderSettings()
    settings.fontSize = 20
    settings.lineHeight = 1.8
    settings.font = 'sans'
    settings.theme = 'sepia'

    expect(style.value).toMatchObject({
      '--reader-size': '20px',
      '--reader-leading': '1.8',
      '--reader-bg': '#f4ecd8',
      colorScheme: 'light',
    })
    expect(style.value['--reader-font']).toContain('system-ui')
  })

  it('give the theme "auto" the colors of the app and the others colors of their own', () => {
    expect(themeVariables('auto')['--reader-bg']).toBe('var(--paper)')
    expect(themeVariables('light')['--reader-bg']).not.toBe(themeVariables('dark')['--reader-bg'])

    for (const theme of ['auto', 'light', 'sepia', 'dark'] as const) {
      expect(Object.keys(themeVariables(theme)).sort()).toEqual(Object.keys(themeVariables('auto')).sort())
    }
  })
})
